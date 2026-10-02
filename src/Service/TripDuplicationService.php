<?php
// src/Service/TripDuplicationService.php
namespace App\Service;

use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\VehiculesReport;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TripDuplicationService
{
    private $entityManager;
    private $dispatcher;

    public function __construct(EntityManagerInterface $entityManager, EventDispatcherInterface $dispatcher)
    {
        $this->entityManager = $entityManager;
        $this->dispatcher = $dispatcher;
    }
    
    public function generatePreviewTrips(Report $report, string $action, string $source, string $destination, ?string $copyMode = 'week_for_week'): array
    {

        $previewTrips = [];

        if ($action === 'duplicate_week') {
            // Récupérer les trajets de la semaine source
            $sourceWeekLines = $report->getReportLinesForWeek($source);
            if (count($sourceWeekLines)<1) {
                return [];
            }

            // Récupérer la date du premier trajet de la semaine source pour calculer les décalages
            $firstLine = $sourceWeekLines[0];
            $sourceWeekDate = $firstLine->getTravelDate();
            $sourceWeekNumber = (int)$sourceWeekDate->format('W');
            $sourceYear = (int)$sourceWeekDate->format('Y');

            // Calculer les semaines cibles en fonction de la destination
            $targetWeeks = $this->getTargetWeeks($sourceWeekDate, $destination);

            // Pour chaque trajet de la semaine source
            foreach ($sourceWeekLines as $line) {
                $originalDate = $line->getTravelDate();
                $dayOfWeek = (int)$originalDate->format('N'); // 1 (lundi) à 7 (dimanche)

                // Pour chaque semaine cible
                foreach ($targetWeeks as $targetWeek)  
                {
                    // Calculer la date cible
                    if($targetWeek === $sourceWeekNumber) {
                        continue;
                    }
                    $targetDate = $this->calculateTargetDate($sourceWeekDate, $targetWeek, $dayOfWeek);

                    // Vérifier que la date cible est dans le mois du rapport
                    if ($this->isDateInReportMonth($targetDate, $report)) {
                        $previewTrips[] = $this->createPreviewTrip($line, $targetDate->format('Y-m-d'));
                    }
                }
            }
        }

        elseif ($action === 'duplicate_trip') {
            // Récupérer le trajet source
            $trip = $report->getReportLineById($source);
            if (!$trip) {
                return [];
            }

            $originalDate = $trip->getTravelDate();
            $dayOfWeek = (int)$originalDate->format('N'); // 1 (lundi) à 7 (dimanche)
            $sourceWeekNumber = (int)$originalDate->format('W');
            $sourceYear = (int)$originalDate->format('Y');

            switch ($destination) {
                case 'whole_week':
                    $previewTrips = $this->duplicateToWholeWeek($trip, $originalDate);
                    break;
                case 'all_weeks_same_day':
                    $previewTrips = $this->duplicateToAllWeeksSameDay($trip, $originalDate, $report);
                    break;
                case 'all_working_days':
                    $previewTrips = $this->duplicateToAllWorkingDays($trip, $originalDate, $report);
                    break;
            }
        }

        elseif ($action === 'duplicate_report') {
            // Récupérer tous les trajets du rapport source
            $sourceLines = $report->getLines();
            if ($sourceLines->isEmpty()) {
                return [];
            }

            // Extraire l'année et le mois de la période cible (format : YYYY-MM)
            [$targetYear, $targetMonth] = explode('-', $destination);

            // Pour chaque trajet du rapport source
            foreach ($sourceLines as $line) {
                $originalDate = $line->getTravelDate();
                if ($copyMode === 'day_for_day') {
                    // Copie jour pour jour (ex. : 4 mars → 4 avril)
                    $targetDate = new DateTime(sprintf('%04d-%02d-%02d', $targetYear, $targetMonth, (int)$originalDate->format('d')));
                    if ((int)$targetDate->format('m') === (int) $targetMonth) {
                        $previewTrips[] = $this->createPreviewTrip($line, $targetDate->format('Y-m-d'));
                    }
                } else {
                    $dayOfWeek = (int)$originalDate->format('N'); // Jour de la semaine (1-7)
                    $dayOfMonth = (int)$originalDate->format('j'); // Jour du mois (1-31)

                    // Calculer la date cible dans le mois/année cible
                    $targetDate = $this->calculateTargetDateForReportDuplication($originalDate, $targetYear, $targetMonth, $dayOfWeek, $dayOfMonth);

                    // Vérifier que la date cible est valide (dans le mois cible)
                    if ($targetDate) {
                        $previewTrips[] = $this->createPreviewTrip($line, $targetDate->format('Y-m-d'));
                    }
                }
            }
        }

        // Trier les trajets par date ascendante
        usort($previewTrips, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $previewTrips;
    }

    private function getTargetWeeks(DateTime $sourceWeekDate, string $destination): array
    {
        $targetWeeks = [];
        $sourceYear = (int)$sourceWeekDate->format('Y');
        $sourceMonth = (int)$sourceWeekDate->format('m');

        if ($destination === 'next_week') {
            $targetWeeks[] = (int)$sourceWeekDate->format('W') + 1;
        } elseif ($destination === 'full_month') {
            // Récupérer toutes les semaines du mois, en vérifiant qu'elles sont bien dans le mois
            $startOfMonth = (clone $sourceWeekDate)->modify('first day of this month');
            $endOfMonth = (clone $sourceWeekDate)->modify('last day of this month');

            // Parcourir chaque jour du mois et récupérer les numéros de semaine uniques
            $currentDate = clone $startOfMonth;
            while ($currentDate <= $endOfMonth) {
                $weekNumber = (int)$currentDate->format('W');
                if (!in_array($weekNumber, $targetWeeks)) {
                    $targetWeeks[] = $weekNumber;
                }
                $currentDate->modify('+1 day');
            }
        }

        return $targetWeeks;
    }


    private function calculateTargetDate(DateTime $sourceWeekDate, int $targetWeek, int $dayOfWeek): DateTime
    {
        $sourceYear = (int)$sourceWeekDate->format('Y');
        $targetDate = (clone $sourceWeekDate)->setISODate($sourceYear, $targetWeek, $dayOfWeek);

        // Si la date cible est en janvier de l'année suivante, reculer d'une année
        if ((int)$targetDate->format('m') === 1 && (int)$targetDate->format('Y') > $sourceYear) {
            $targetDate->setISODate($sourceYear, $targetWeek, $dayOfWeek);
        }

        return $targetDate;
    }

    private function isDateInReportMonth(DateTime $date, Report $report): bool
    {
        $reportStart = $report->getStartDate();
        $reportEnd = $report->getEndDate();

        return $date >= $reportStart && $date <= $reportEnd;
    }

    private function duplicateToWholeWeek($trip, DateTime $originalDate): array
    {
        $previewTrips = [];
        $startOfWeek = (clone $originalDate)->modify('this week monday');
        $originalDayOfWeek = (int)$originalDate->format('N'); // Jour de la semaine du trajet source (1-7)

        // Parcourir chaque jour de la semaine (lundi à samedi)
        for ($day = 0; $day < 6; $day++) { // 0=lundi, 5=samedi
            $targetDate = (clone $startOfWeek)->modify("+{$day} days");
            $currentDayOfWeek = (int)$targetDate->format('N');

            // Exclure le dimanche (7) et le jour du trajet source
            if ($currentDayOfWeek !== 7 && $currentDayOfWeek !== $originalDayOfWeek) {
                $previewTrips[] = $this->createPreviewTrip($trip, $targetDate->format('Y-m-d'));
            }
        }

        return $previewTrips;
    }

    private function duplicateToAllWeeksSameDay($trip, DateTime $originalDate, Report $report): array
    {
        $previewTrips = [];
        $dayOfWeek = (int)$originalDate->format('N');
        $originalDateString = $originalDate->format('Y-m-d');
        $startOfMonth = (clone $originalDate)->modify('first day of this month');
        $endOfMonth = (clone $originalDate)->modify('last day of this month');

        $period = new \DatePeriod($startOfMonth, new \DateInterval('P1D'), $endOfMonth->modify('+1 day'));
        foreach ($period as $currentDate) {
            if ((int)$currentDate->format('N') === $dayOfWeek) {
                $currentDateString = $currentDate->format('Y-m-d');
                if ($currentDateString !== $originalDateString) {
                    $previewTrips[] = $this->createPreviewTrip($trip, $currentDateString);
                }
            }
        }

        return $previewTrips;
    }

    // Dupliquer le trajet sur tous les jours ouvrables du mois
    private function duplicateToAllWorkingDays($trip, DateTime $originalDate, Report $report): array
    {
        $previewTrips = [];
        $originalDateString = $originalDate->format('Y-m-d');
        $startOfMonth = (clone $originalDate)->modify('first day of this month');
        $endOfMonth = (clone $originalDate)->modify('last day of this month');

        $period = new \DatePeriod($startOfMonth, new \DateInterval('P1D'), $endOfMonth->modify('+1 day'));
        foreach ($period as $currentDate) {
            $dayOfWeek = (int)$currentDate->format('N');
            $currentDateString = $currentDate->format('Y-m-d');
            if ($dayOfWeek >= 1 && $dayOfWeek <= 6 && $currentDateString !== $originalDateString) {
                $previewTrips[] = $this->createPreviewTrip($trip, $currentDateString);
            }
        }

        return $previewTrips;
    }

    public function calculateTargetDateForReportDuplication(DateTime $originalDate, int $targetYear, int $targetMonth, int $dayOfWeek, int $dayOfMonth): ?DateTime
    {
        // Trouver le premier jour du mois cible
        $firstDayOfTargetMonth = new DateTime("first day of $targetYear-$targetMonth");

        // Trouver le premier jour de la semaine cible qui correspond au jour de semaine du trajet source
        $firstDayOfWeekInTargetMonth = clone $firstDayOfTargetMonth;
        while ((int)$firstDayOfWeekInTargetMonth->format('N') !== $dayOfWeek) {
            $firstDayOfWeekInTargetMonth->modify('+1 day');
        }

        // Calculer la date cible en conservant le jour de semaine
        $targetDate = clone $firstDayOfWeekInTargetMonth;
        $weekOffset = (int)ceil($dayOfMonth / 7) - 1; // Décalage en semaines
        $targetDate->modify("+$weekOffset weeks");

        // Vérifier que la date cible est toujours dans le mois cible
        if ((int)$targetDate->format('m') !== $targetMonth) {
            // Si on dépasse la fin du mois, reculer au dernier jour du mois pour ce jour de semaine
            $lastDayOfTargetMonth = new DateTime("last day of $targetYear-$targetMonth");
            while ((int)$targetDate->format('N') !== $dayOfWeek && $targetDate > $firstDayOfTargetMonth) {
                $targetDate->modify('-1 week');
            }
        }

        // Vérifier que la date cible est toujours dans le mois cible
        if ((int)$targetDate->format('m') === $targetMonth) {
            return $targetDate;
        }

        return null;
    }

    private function createPreviewTrip(ReportLine $trip, $targetDate): array
    {
        return [
            'date' => $targetDate,
            'start' => $trip->getStartAdress(),
            'end' => $trip->getEndAdress(),
            'formattedStart' => $trip->formatAddressWithName($trip->getStartAdress()),
            'formattedEnd' => $trip->formatAddressWithName($trip->getEndAdress()),
            'km' => $trip->getKm(),
            'km_total' => $trip->getKmTotal(),
            'is_return' => $trip->getIsReturn(),
            'vehicule_id' => $trip->getVehicule()->getId(),
            'amount' => $trip->getAmount(),
            'comment' => $trip->getComment(),
        ];
    }

    private function findExistingReportForPeriod(Report $sourceReport, int $targetYear, int $targetMonth): ?Report
    {
        $startDate = new \DateTime(sprintf('%04d-%02d-01', $targetYear, $targetMonth));

        return $this->entityManager->getRepository(Report::class)->findOneBy([
            'user' => $sourceReport->getUser(),
            'start_date' => $startDate,
        ]);
    }

    public function duplicateReport(Report $sourceReport, string $targetPeriod, ?string $copyMode = null): Report
    {
        [$year, $month] = explode('-', $targetPeriod);
        $targetYear = (int) $year;
        $targetMonth = (int) $month;

        $existingReport = $this->findExistingReportForPeriod($sourceReport, $targetYear, $targetMonth);

        if ($existingReport !== null) {
            throw new \LogicException(sprintf(
                'Un rapport existe déjà pour %02d/%04d.',
                $targetMonth,
                $targetYear
            ));
        }

        $newReport = new Report();

        // Définir la nouvelle période via start_date et end_date
        $startDate = new \DateTime("first day of $targetYear-$targetMonth");
        $endDate = new \DateTime("last day of $targetYear-$targetMonth");
        $newReport->setStartDate($startDate);
        $newReport->setEndDate($endDate);
        $newReport->setUser($sourceReport->getUser());
        $newReport->calculateKm();
        $newReport->calculateTotal();
        $this->entityManager->persist($newReport);
        $this->entityManager->flush();

        /* Clone des lignes */
        foreach ($sourceReport->getLines() as $line) {

             $originalDate = $line->getTravelDate();

            // Calcul de la date cible selon le mode
            if ($copyMode === 'day_for_day') {
                $day = min((int) $originalDate->format('d'), (int) $endDate->format('d'));
                $targetDate = new \DateTime(sprintf('%04d-%02d-%02d', $targetYear, $targetMonth, $day));
            } else {
                // week_for_week : on conserve "semaine dans le mois" + jour de semaine
                $dayOfWeek  = (int) $originalDate->format('N');
                $dayOfMonth = (int) $originalDate->format('j');

                $targetDate = $this->calculateTargetDateForReportDuplication(
                    $originalDate,
                    $targetYear,
                    $targetMonth,
                    $dayOfWeek,
                    $dayOfMonth
                );

                // si pas de date valide dans le mois cible -> on skip la ligne
                if (!$targetDate) {
                    continue;
                }
            }

            $newLine = new ReportLine();
            // dd($original->getLines());
            $newLine->setKm($line->getKm());
            $newLine->setIsReturn($line->getIsReturn());
            $newLine->setKmTotal($line->getKmTotal());
            $newLine->setAmount($line->getAmount());
            $newLine->setStartAdress($line->getStartAdress());
            $newLine->setEndAdress($line->getEndAdress());
            $newLine->setComment($line->getComment());
            $newLine->setVehicule($line->getVehicule());
            $newLine->setScale($line->getScale());
            $newLine->setTravelDate($targetDate);
            $scale = $newLine->getVehicule()->getScale();
            $newLine->setScale($scale);
            $newLine->calculateAmount();
            $newLine->setReport($newReport);

            $this->entityManager->persist($newLine);
            $this->entityManager->flush();
                        
        }

        if ($this->entityManager->contains($newReport)) {
            $this->entityManager->refresh($newReport); 
        }

        $this->dispatcher->dispatch(new AfterEntityUpdatedEvent($newReport));

        return $newReport;
    }

}
