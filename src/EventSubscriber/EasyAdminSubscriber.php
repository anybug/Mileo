<?php

namespace App\EventSubscriber;

use App\Entity\Report;
use App\Entity\ReportLine;
use App\Service\ReportService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EasyAdminSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $em;
    private AdminUrlGenerator $adminUrlGenerator;
    private ReportService $reportService;

    public function __construct(
        EntityManagerInterface $em,
        AdminUrlGenerator $adminUrlGenerator,
        ReportService $reportService
    ) {
        $this->em = $em;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->reportService = $reportService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => ['afterPersistReport'],
            AfterEntityUpdatedEvent::class => ['afterUpdateReport'],
            AfterEntityDeletedEvent::class => ['afterDeleteReport'],
        ];
    }

    public function afterPersistReport(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof Report) {
            $this->recalculateReport($entity);

            return;
        }

        if ($entity instanceof ReportLine) {
            $this->handleReportLine($entity);
        }
    }

    public function afterUpdateReport(AfterEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof Report) {
            $this->recalculateReport($entity);

            return;
        }

        if ($entity instanceof ReportLine) {
            $this->handleReportLine($entity);
        }
    }

    public function afterDeleteReport(AfterEntityDeletedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if (!$entity instanceof ReportLine) {
            return;
        }

        $report = $entity->getReport();

        if (!$report) {
            return;
        }

        $remainingLines = $this->em
            ->getRepository(ReportLine::class)
            ->findBy([
                'report' => $report,
            ]);

        if (count($remainingLines) === 0) {
            $this->em->remove($report);
            $this->em->flush();

            return;
        }

        /*
         * La collection Doctrine peut encore contenir
         * la ligne qui vient d'être supprimée.
         */
        if ($report->getLines()->contains($entity)) {
            $report->removeLine($entity);
        }

        $this->recalculateReport($report);
    }

    private function handleReportLine(ReportLine $line): void
    {
        $report = $line->getReport();

        if (!$report) {
            return;
        }

        /*
         * Lors de la création, la collection inverse du rapport
         * peut ne pas encore contenir la nouvelle ligne.
         */
        if (!$report->getLines()->contains($line)) {
            $report->addLine($line);
        }

        $this->recalculateReport($report);
    }

    private function recalculateReport(Report $report): void
    {
        $this->reportService->refreshReport($report);
    }
}