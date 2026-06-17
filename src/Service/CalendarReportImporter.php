<?php

namespace App\Service;

use App\Entity\Report;
use App\Entity\ReportLine;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CalendarReportImporter
{
    public const MODE_RETURN = 'return';
    public const MODE_TOUR = 'tour';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function importIntoReport(
        Report $report,
        string $tripMode,
        string $startAddress,
        ?string $calendarUrl = null,
        ?string $calendarUsername = null,
        ?string $calendarPassword = null
    ): int {
        $lines = $this->buildReportLinesFromCalendar(
            $report,
            $tripMode,
            $startAddress,
            $calendarUrl,
            $calendarUsername,
            $calendarPassword
        );

        foreach ($lines as $line) {
            $line->setReport($report);
            $report->addLine($line);

            $this->em->persist($line);
        }

        $report->calculateKm();
        $report->calculateTotal();

        $this->em->persist($report);
        $this->em->flush();

        return count($lines);
    }

    public function previewTrips(
        Report $report,
        string $tripMode,
        string $startAddress,
        ?string $calendarUrl = null,
        ?string $calendarUsername = null,
        ?string $calendarPassword = null
    ): array {
        $unrecognizedCount = 0;

        $lines = $this->buildReportLinesFromCalendar(
            $report,
            $tripMode,
            $startAddress,
            $calendarUrl,
            $calendarUsername,
            $calendarPassword,
            $unrecognizedCount
        );

        $previewTrips = [];

        foreach ($lines as $line) {
            $previewTrips[] = [
                'date' => $line->getTravelDate()->format('Y-m-d'),
                'start' => $line->getStartAdress(),
                'end' => $line->getEndAdress(),
                'km' => $line->getKm(),
                'km_total' => $line->getKmTotal(),
                'is_return' => $line->isIsReturn(),
                'vehicule_id' => $line->getVehicule()?->getId(),
                'amount' => $line->getAmount(),
                'comment' => $line->getComment(),
                'is_calendar' => true,
            ];
        }

        return [
            'trips' => $previewTrips,
            'unrecognized_count' => $unrecognizedCount,
        ];
    }

    private function buildReportLinesFromCalendar(
        Report $report,
        string $tripMode,
        string $startAddress,
        ?string $calendarUrl = null,
        ?string $calendarUsername = null,
        ?string $calendarPassword = null,
        ?int &$unrecognizedCount = null,
    ): array {
        $user = $report->getUser();

        $calendarUrl = trim($calendarUrl ?: (string) $user?->getCalendarUrl());

        if (!$user || $calendarUrl === '') {
            throw new \RuntimeException('Aucun calendrier n’est configuré pour votre compte.');
        }

        $vehicule = $user->getDefaultVehicule();

        if (!$vehicule) {
            throw new \RuntimeException('Aucun véhicule par défaut n’est configuré sur votre compte.');
        }

        if (trim($startAddress) === '') {
            throw new \RuntimeException('Aucune adresse de départ n’est renseignée.');
        }

        $calendarContent = $this->fetchCalendarContent(
            $calendarUrl,
            $calendarUsername,
            $calendarPassword
        );

        $calendar = Reader::read($calendarContent);

        $unrecognizedCount = 0;
        $events = [];

        $startDate = \DateTimeImmutable::createFromInterface($report->getStartDate())
            ->setTime(0, 0, 0);

        $endDate = \DateTimeImmutable::createFromInterface($report->getEndDate())
            ->setTime(23, 59, 59);

        foreach ($calendar->VEVENT as $event) {
            if (!$event instanceof VEvent) {
                continue;
            }

            $date = $event->DTSTART?->getDateTime();

            if (!$date) {
                continue;
            }

            if ($date < $startDate || $date > $endDate) {
                continue;
            }

            $location = trim((string) ($event->LOCATION ?? ''));

            if ($location === '') {
                $unrecognizedCount++;
                continue;
            }

            $events[] = [
                'date' => $date,
                'location' => $location,
                'summary' => trim((string) ($event->SUMMARY ?? '')),
            ];
        }

        usort(
            $events,
            static fn(array $a, array $b): int => $a['date'] <=> $b['date']
        );

        $lines = [];
        $previousEndAddress = null;
        $previousDateKey = null;

        foreach ($events as $event) {
            $currentDateKey = $event['date']->format('Y-m-d');

            if ($tripMode === self::MODE_TOUR) {
                if ($previousDateKey === $currentDateKey && $previousEndAddress !== null) {
                    $lineStartAddress = $previousEndAddress;
                } else {
                    $lineStartAddress = $startAddress;
                }
            } else {
                $lineStartAddress = $startAddress;
            }

            $isReturn = $tripMode === self::MODE_RETURN;

            $line = new ReportLine();

            $line->setTravelDate(\DateTime::createFromInterface($event['date']));
            $line->setStartAdress($lineStartAddress);
            $line->setEndAdress($event['location']);
            $line->setVehicule($vehicule);
            $line->setScale($vehicule->getScale());
            $line->setIsReturn($isReturn);

            $line->setKm(0);
            $line->setKmTotal(0);
            $line->setAmount(0);

            $line->setComment($event['summary']);

            $lines[] = $line;

            $previousEndAddress = $event['location'];
            $previousDateKey = $currentDateKey;
        }

        return $lines;
    }

    public function testCalendarUrl(
        string $calendarUrl,
        ?string $calendarUsername = null,
        ?string $calendarPassword = null
    ): array {
        return $this->checkCalendarAccess(
            $calendarUrl,
            $calendarUsername,
            $calendarPassword
        );
    }

    private function fetchCalendarContent(
        string $calendarUrl,
        ?string $username = null,
        ?string $password = null
    ): string {
        $calendarUrl = $this->getFetchableCalendarUrl($calendarUrl);

        $options = [
            'timeout' => 10,
        ];

        if ($username && $password) {
            $options['auth_basic'] = [$username, $password];
        }

        return $this->httpClient
            ->request('GET', $calendarUrl, $options)
            ->getContent();
    }

    private function getFetchableCalendarUrl(string $calendarUrl): string
    {
        $calendarUrl = $this->normalizeCalendarUrl($calendarUrl);

        if (
            str_contains($calendarUrl, '/remote.php/dav/calendars/')
            && !str_contains($calendarUrl, '?export')
        ) {
            return rtrim($calendarUrl, '/') . '/?export';
        }

        return $calendarUrl;
    }

    private function normalizeCalendarUrl(string $url): string
    {
        $url = trim($url);

        if (str_starts_with(strtolower($url), 'webcal://')) {
            return 'https://' . substr($url, strlen('webcal://'));
        }

        return $url;
    }

    public function checkCalendarAccess(
        string $calendarUrl,
        ?string $calendarUsername = null,
        ?string $calendarPassword = null
    ): array {
        $calendarUrl = $this->normalizeCalendarUrl($calendarUrl);

        if ($calendarUrl === '') {
            return [
                'valid' => false,
                'auth_required' => false,
                'field' => 'calendarUrl',
                'message' => 'Veuillez renseigner l’URL de votre calendrier.',
            ];
        }

        try {
            $calendarUrl = $this->getFetchableCalendarUrl($calendarUrl);

            $options = [
                'timeout' => 10,
            ];

            if ($calendarUsername && $calendarPassword) {
                $options['auth_basic'] = [$calendarUsername, $calendarPassword];
            }

            $response = $this->httpClient->request('GET', $calendarUrl, $options);
            $statusCode = $response->getStatusCode();

            if (in_array($statusCode, [401, 403], true)) {
                return [
                    'valid' => false,
                    'auth_required' => true,
                    'field' => 'plainCalendarPassword',
                    'message' => ($calendarUsername || $calendarPassword)
                        ? "Echec d'authentification: nom d'utilisateur ou mot de passe incorrect."
                        : 'Ce calendrier nécessite une authentification.',
                ];
            }

            if ($statusCode === 404) {
                return [
                    'valid' => false,
                    'auth_required' => false,
                    'field' => 'calendarUrl',
                    'message' => 'Calendrier introuvable. Merci de vérifier l’URL.',
                ];
            }

            if ($statusCode >= 400) {
                return [
                    'valid' => false,
                    'auth_required' => false,
                    'field' => 'calendarUrl',
                    'message' => sprintf('Le serveur calendrier a retourné une erreur HTTP %d.', $statusCode),
                ];
            }

            $content = $response->getContent(false);

            if (!str_contains($content, 'BEGIN:VCALENDAR')) {
                return [
                    'valid' => false,
                    'auth_required' => false,
                    'field' => 'calendarUrl',
                    'message' => "Connexion au calendrier réussie mais le format n'est pas reconnu. Formats supportés: ICS, calDav, iCal.",
                ];
            }

            $calendar = Reader::read($content);

            $eventsCount = 0;

            foreach ($calendar->VEVENT as $event) {
                if ($event instanceof VEvent) {
                    $eventsCount++;
                }
            }

            return [
                'valid' => true,
                'auth_required' => false,
                'message' => 'Calendrier valide .',
            ];
        } catch (TransportExceptionInterface) {
            return [
                'valid' => false,
                'auth_required' => false,
                'field' => 'calendarUrl',
                'message' => 'Impossible de contacter le serveur de calendrier. Merci de vérifier l’URL et de recommencer.',
            ];
        } catch (\Throwable) {
            return [
                'valid' => false,
                'auth_required' => false,
                'field' => 'calendarUrl',
                'message' => 'Impossible de se connecter au calendrier.',
            ];
        }
    }
}