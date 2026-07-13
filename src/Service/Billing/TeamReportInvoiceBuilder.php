<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Service\Billing\TeamReportInvoicePricing;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TeamReportInvoiceBuilder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly TeamReportInvoicePricing $pricing,
    ) {
    }

    public function createOrUpdateDraft(User $manager, int $year, int $month): Invoice
    {
        $invoice = $this->invoiceRepository->findTeamReportInvoice($manager, $year, $month);

        if ($invoice === null) {
            $invoice = new Invoice();
            $invoice
                ->setType(Invoice::TYPE_TEAM_REPORT)
                ->setStatus(InvoiceStatus::DRAFT)
                ->setTeamManager($manager)
                ->setBillingYear($year)
                ->setBillingMonth($month)
                ->setVatAmount(0.0)
                ->setTotalHt(0.0)
                ->setTotalTtc(0.0);

            $this->entityManager->persist($invoice);
            $this->entityManager->flush();

            $invoice->setNum($this->generateInvoiceNumber($invoice, $year, $month));

            $this->entityManager->flush();
        }

        if ($invoice->getNum() === null) {
            $invoice->setNum($this->generateInvoiceNumber($invoice, $year, $month));
        }

        if ($invoice->getStatus() !== InvoiceStatus::DRAFT) {
            $this->entityManager->flush();

            return $invoice;
        }

        foreach ($invoice->getReports()->toArray() as $report) {
            $invoice->removeReport($report);
        }

        $periodKey = sprintf('%04d-%02d', $year, $month);

        foreach ($manager->getMembers() as $member) {
            foreach ($member->getReports() as $report) {
                if ($report->getCreatedAt() === null) {
                    continue;
                }

                if ($report->getCreatedAt()->format('Y-m') !== $periodKey) {
                    continue;
                }

                if ($report->getInvoice() !== null && $report->getInvoice() !== $invoice) {
                    continue;
                }

                $invoice->addReport($report);
            }
        }

        $pricingDetails = $this->pricing->computeForInvoice($invoice);

        $invoice
            ->setTotalHt($pricingDetails->totalHt)
            ->setVatAmount($pricingDetails->vatAmount)
            ->setTotalTtc($pricingDetails->totalTtc);

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    private function generateInvoiceNumber(Invoice $invoice, int $year, int $month): int
    {
        return (int) sprintf(
            '%02d%02d%05d',
            $year % 100,
            $month,
            $invoice->getId(),
        );
    }
}