<?php

namespace App\Service;

use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\VehiculesReport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ReportService
{
    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function getAvailablePeriodsForReportDuplication(Report $sourceReport): array
    {
        $user = $sourceReport->getUser();
        $availablePeriods = [];

        $existingReports = $this->entityManager->getRepository(Report::class)->findBy([
            'user' => $user,
        ]);

        $usedPeriods = [];

        foreach ($existingReports as $report) {
            if ($report->getStartDate() instanceof \DateTimeInterface) {
                $usedPeriods[$report->getStartDate()->format('Y-m')] = true;
            }
        }

        // On exclut aussi explicitement la période du rapport source
        if ($sourceReport->getStartDate() instanceof \DateTimeInterface) {
            $usedPeriods[$sourceReport->getStartDate()->format('Y-m')] = true;
        }

        $currentYear = (int) date('Y');

        for ($year = $currentYear - 3; $year <= $currentYear + 1; $year++) {
            $yearPeriods = [];

            for ($month = 1; $month <= 12; $month++) {
                $periodKey = sprintf('%04d-%02d', $year, $month);

                if (isset($usedPeriods[$periodKey])) {
                    continue;
                }

                $yearPeriods[$this->getMonthName($month) . ' ' . $year] = $periodKey;
            }

            if (!empty($yearPeriods)) {
                $availablePeriods[$year] = $yearPeriods;
            }
        }

        return $availablePeriods;
    }

    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        return $months[$month] ?? '';
    }

    public function refreshReport(Report $report, bool $flush = true): void
    {
        $existingVehiculesReports = [];

        foreach ($report->getVehiculesReports() as $vehiculesReport) {
            $vehicule = $vehiculesReport->getVehicule();

            if ($vehicule) {
                $existingVehiculesReports[$vehicule->getId()] = $vehiculesReport;
            }

            $vehiculesReport->setKm(0);
            $vehiculesReport->setTotal(0);
        }

        /** @var ReportLine $line */
        foreach ($report->getLines() as $line) {
            $vehicule = $line->getVehicule();

            if (!$vehicule) {
                continue;
            }

            $vehiculeId = $vehicule->getId();

            if (!isset($existingVehiculesReports[$vehiculeId])) {
                $vehiculesReport = new VehiculesReport();
                $vehiculesReport->setReport($report);
                $vehiculesReport->setVehicule($vehicule);
                $vehiculesReport->setScale($vehicule->getScale()); // seulement à la création
                $vehiculesReport->setKm(0);
                $vehiculesReport->setTotal(0);

                $report->addVehiculesReport($vehiculesReport);
                $this->entityManager->persist($vehiculesReport);

                $existingVehiculesReports[$vehiculeId] = $vehiculesReport;
            }

            $vehiculesReport = $existingVehiculesReports[$vehiculeId];

            // On garde toujours la scale du VehiculesReport comme source de vérité
            $scale = $vehiculesReport->getScale();

            if (!$scale) {
                $scale = $vehicule->getScale();
                $vehiculesReport->setScale($scale);
            }

            // On réaligne la ligne sur la scale du VehiculesReport
            if ($scale) {
                $line->setScale($scale);
                $line->calculateAmount();
            }

            $vehiculesReport->setKm(
                (int) $vehiculesReport->getKm() + (int) ($line->getKmTotal() ?? 0)
            );
        }

        foreach ($report->getVehiculesReports()->toArray() as $vehiculesReport) {
            $km = (int) ($vehiculesReport->getKm() ?? 0);

            if ($km === 0) {
                $report->removeVehiculesReport($vehiculesReport);
                $this->entityManager->remove($vehiculesReport);
                continue;
            }

            // Recalcule complet avec le vrai barème du VehiculesReport
            $vehiculesReport->calculateTotal();
        }

        $report->calculateKm();
        $report->calculateTotal();

        $this->entityManager->persist($report);

        if ($flush) {
            $this->entityManager->flush();
        }
    }
}