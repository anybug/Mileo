<?php

// src/Service/PublicStatisticsService.php

namespace App\Service;

use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class PublicStatisticsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getData(): array
    {
        $reportLineRepository = $this->entityManager->getRepository(ReportLine::class);
        $reportRepository = $this->entityManager->getRepository(Report::class);
        $userRepository = $this->entityManager->getRepository(User::class);

        $tripsManaged = (int) $reportLineRepository->createQueryBuilder('reportLine')
            ->select('COUNT(reportLine.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $reportsGenerated = (int) $reportRepository->createQueryBuilder('report')
            ->select('COUNT(report.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $accountsCreated = (int) $userRepository->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $kilometersCalculated = (int) $reportLineRepository->createQueryBuilder('reportLine')
            ->select('COALESCE(SUM(reportLine.km_total), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'trips_managed' => $tripsManaged,
            'reports_generated' => $reportsGenerated,
            'accounts_created' => $accountsCreated,
            'kilometers_calculated' => $kilometersCalculated,
        ];
    }
}