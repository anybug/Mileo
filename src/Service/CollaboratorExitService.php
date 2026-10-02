<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CollaboratorExitService
{
    public function __construct(
        private ReportRepository $reportRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getLastReportEndDate(
        User $collaborator
    ): ?\DateTimeImmutable {
        $report = $this->reportRepository
            ->findLastPeriodReportForUser($collaborator);

        $endDate = $report?->getEndDate();

        return null !== $endDate
            ? \DateTimeImmutable::createFromInterface($endDate)
            : null;
    }

    public function exit(
        User $collaborator,
        \DateTimeImmutable $exitDate
    ): void {
        if (null === $collaborator->getManagedBy()) {
            throw new \DomainException(
                'Cet utilisateur n’est pas un collaborateur.'
            );
        }

        if ($collaborator->hasLeftWorkforce()) {
            throw new \DomainException(
                'Ce collaborateur est déjà sorti de l’effectif.'
            );
        }

        $lastReportEndDate = $this->getLastReportEndDate(
            $collaborator
        );

        if (
            null !== $lastReportEndDate
            && $exitDate <= $lastReportEndDate
        ) {
            throw new \DomainException(sprintf(
                'La date de sortie doit être postérieure au %s.',
                $lastReportEndDate->format('d/m/Y')
            ));
        }

        $collaborator->leaveWorkforce($exitDate);

        $this->entityManager->flush();
    }
}