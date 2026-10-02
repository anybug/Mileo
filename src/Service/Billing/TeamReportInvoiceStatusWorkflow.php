<?php

declare(strict_types=1);

namespace App\Service\Billing;

use LogicException;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Billing\TeamReportInvoiceMailer;
use App\Service\Billing\TeamReportFacturXGenerator;
use Symfony\Component\Workflow\WorkflowInterface;

final class TeamReportInvoiceStatusWorkflow
{
    public function __construct(
        private readonly WorkflowInterface $teamReportInvoiceStatusStateMachine,
        private readonly EntityManagerInterface $entityManager,
        private readonly TeamReportFacturXGenerator $facturXGenerator,
        private readonly TeamReportInvoiceMailer $invoiceMailer,
    ) {
    }

    public function canMarkAsSent(Invoice $invoice): bool
    {
        return $this->teamReportInvoiceStatusStateMachine->can(
            $invoice,
            InvoiceStatus::SENT->value,
        );
    }

    public function sent(Invoice $invoice): void
    {
        if (!$this->canMarkAsSent($invoice)) {
            throw new LogicException(sprintf(
                "Impossible de passer la facture #%s au statut '%s'. Statut actuel : '%s'.",
                $invoice->getId() ?? 'nouvelle',
                InvoiceStatus::SENT->value,
                $invoice->getStatusAsString(),
            ));
        }

        $facturXPath = $this->facturXGenerator->generate($invoice);

        $this->invoiceMailer->sendFacturXToManager($invoice, $facturXPath);

        $this->teamReportInvoiceStatusStateMachine->apply(
            $invoice,
            InvoiceStatus::SENT->value,
        );

        $invoice->setSentAt(new \DateTimeImmutable());

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();
    }

    public function canMarkAsPaid(Invoice $invoice): bool
    {
        return $this->teamReportInvoiceStatusStateMachine->can(
            $invoice,
            InvoiceStatus::PAID->value,
        );
    }

    public function paid(Invoice $invoice): void
    {
        if (!$this->canMarkAsPaid($invoice)) {
            throw new LogicException(sprintf(
                "Impossible de passer la facture #%s au statut '%s'. Statut actuel : '%s'.",
                $invoice->getId() ?? 'nouvelle',
                InvoiceStatus::PAID->value,
                $invoice->getStatusAsString(),
            ));
        }

        $this->teamReportInvoiceStatusStateMachine->apply(
            $invoice,
            InvoiceStatus::PAID->value,
        );

        $invoice->setPaidAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function canMarkAsCancelled(Invoice $invoice): bool
    {
        return $invoice->isTeamReportInvoice()
            && $invoice->getStatus() === InvoiceStatus::DRAFT
            && $this->teamReportInvoiceStatusStateMachine->can($invoice, InvoiceStatus::CANCELLED->value);
    }

    public function cancelled(Invoice $invoice): void
    {
        if (!$this->canMarkAsCancelled($invoice)) {
            throw new LogicException(sprintf(
                "Impossible d’annuler la facture #%s. Statut actuel : '%s'.",
                $invoice->getId() ?? 'nouvelle',
                $invoice->getStatusAsString(),
            ));
        }

        $this->teamReportInvoiceStatusStateMachine->apply(
            $invoice,
            InvoiceStatus::CANCELLED->value,
        );

        $this->entityManager->flush();
    }
}