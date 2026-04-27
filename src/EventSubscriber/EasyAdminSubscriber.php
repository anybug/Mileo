<?php

namespace App\EventSubscriber;

use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\VehiculesReport;
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

    public function __construct(EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->em = $em;
        $this->adminUrlGenerator = $adminUrlGenerator;
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
            $this->em->flush();

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
            $this->em->flush();

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

        // Important : après suppression, on ne se base pas sur $report->getLines()
        // car la collection peut encore être stale.
        $remainingLines = $this->em
            ->getRepository(ReportLine::class)
            ->findBy(['report' => $report]);

        if (count($remainingLines) === 0) {
            $this->em->remove($report);
            $this->em->flush();

            return;
        }

        $this->recalculateReport($report);
        $this->em->flush();
    }

    private function handleReportLine(ReportLine $line): void
    {
        $report = $line->getReport();

        if (!$report) {
            return;
        }

        // Très important pour le cas création :
        // on synchronise la collection inverse en mémoire.
        if (!$report->getLines()->contains($line)) {
            $report->addLine($line);
        }

        $this->recalculateLine($line);
        $this->recalculateReport($report);

        $this->em->flush();
    }

    private function recalculateLine(ReportLine $line): void
    {
        $scale = $line->getVehicule()->getScale();

        $line->setScale($scale);
        $line->calculateAmount();
    }

    private function recalculateReport(Report $report): void
    {
        $scales = [];

        foreach ($report->getVehiculesReports() as $vehiculesReport) {
            $vehicule = $vehiculesReport->getVehicule();

            if ($vehicule) {
                $scales[$vehicule->getId()] = $vehiculesReport->getScale();
            }

            $report->removeVehiculesReport($vehiculesReport);
            $this->em->remove($vehiculesReport);
        }

        foreach ($report->getVehicules() as $vehicule) {
            $vehiculesReport = new VehiculesReport();

            $report->addVehiculesReport($vehiculesReport);

            $vehiculesReport->setVehicule($vehicule);

            if (isset($scales[$vehicule->getId()])) {
                $vehiculesReport->setScale($scales[$vehicule->getId()]);
            } else {
                $vehiculesReport->setScale($vehicule->getScale());
            }

            $vehiculesReport->calculateTotal();

            $this->em->persist($vehiculesReport);
        }

        $report->calculateKm();
        $report->calculateTotal();

        $this->em->persist($report);
    }
}
