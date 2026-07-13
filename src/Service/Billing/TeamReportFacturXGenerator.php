<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Entity\Invoice;
use App\Utils\TeamReportInvoicePdf;
use Doctrine\ORM\EntityManagerInterface;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Symfony\Component\Filesystem\Filesystem;
use App\Service\Billing\TeamReportInvoicePricing;
use App\Service\Billing\TeamReportInvoicePricingDetails;


final class TeamReportFacturXGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $projectDir,
        private readonly TeamReportInvoicePricing $pricing,
    ) {
    }

    public function generate(Invoice $invoice): string
    {
        if (!$invoice->isTeamReportInvoice()) {
            throw new \LogicException('Cette facture n’est pas une facture de rapports.');
        }

        if ($invoice->getNum() === null) {
            throw new \LogicException('Impossible de générer la Factur-X sans numéro de facture.');
        }

        $pricingDetails = $this->pricing->computeForInvoice($invoice);

        $invoice
            ->setTotalHt($pricingDetails->totalHt)
            ->setVatAmount($pricingDetails->vatAmount)
            ->setTotalTtc($pricingDetails->totalTtc);

        $document = $this->buildXmlDocument($invoice, $pricingDetails);

        $pdfContent = (new TeamReportInvoicePdf())->generatePdf(
            $invoice,
            $pricingDetails,
        );

        $pdfBuilder = ZugferdDocumentPdfBuilder::fromPdfString(
            $document,
            $pdfContent,
        );

        $pdfBuilder->setAdditionalCreatorTool('Mileo');
        $pdfBuilder->generateDocument();

        $directory = $this->projectDir.'/var/invoices/team-report';

        (new Filesystem())->mkdir($directory);

        $path = $directory.'/Mileo_Facture_'.$invoice->getNum().'_Factur-X.pdf';

        $pdfBuilder->saveDocument($path);

        $invoice->setFacturXPath($path);

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $path;
    }

    private function buildXmlDocument(
        Invoice $invoice,
        TeamReportInvoicePricingDetails $pricingDetails,
    ): ZugferdDocumentBuilder {
        $document = ZugferdDocumentBuilder::createNew(
            ZugferdProfiles::PROFILE_EN16931,
        );

        $invoiceDate = new \DateTime();
        $manager = $invoice->getTeamManager();

        $document->setDocumentInformation(
            (string) $invoice->getNum(),
            '380',
            $invoiceDate,
            'EUR',
        );

        $document->setDocumentSeller('Anybug / Mileo');
        $document->addDocumentSellerVATRegistrationNumber('FR14517653531');
        $document->setDocumentSellerAddress(
            '8 Rue Beaulieu',
            '',
            '',
            '17430',
            'Cabariot',
            'FR',
        );

        $buyerName = $manager?->getCompany()
            ?: trim(($manager?->getFirstName() ?? '').' '.($manager?->getLastName() ?? ''));

        $document->setDocumentBuyer($buyerName ?: 'Client Mileo');

        $document->setDocumentBuyerAddress(
            '',
            '',
            '',
            '',
            '',
            'FR',
        );

        $billingPeriod = sprintf(
            '%02d/%04d',
            $invoice->getBillingMonth(),
            $invoice->getBillingYear(),
        );

        $calculationExplanation = $this->buildPricingExplanation($pricingDetails);

        $label = sprintf(
            'Abonnement Team - utilisateurs actifs IK - facture %s',
            $billingPeriod,
        );

        $description = sprintf(
            'Facturation globale des utilisateurs actifs avec IK saisies. Détail du calcul : %s',
            $calculationExplanation,
        );

        $document->addNewPosition('1');

        $document->setDocumentPositionProductDetails(
            $label,
            $description,
            'TEAM-ACTIVE-USERS-GLOBAL-'.$invoice->getBillingYear().'-'.sprintf('%02d', $invoice->getBillingMonth()),
        );

        $document->setDocumentPositionNetPrice($pricingDetails->totalHt);
        $document->setDocumentPositionQuantity(1, 'C62');
        $document->addDocumentPositionTax('S', 'VAT', $pricingDetails->vatRate);
        $document->setDocumentPositionLineSummation($pricingDetails->totalHt);

        $document->addDocumentTax(
            'S',
            'VAT',
            $pricingDetails->totalHt,
            $pricingDetails->vatAmount,
            $pricingDetails->vatRate,
        );

        $document->setDocumentSummation(
            $pricingDetails->totalTtc,
            $pricingDetails->totalTtc,
            $pricingDetails->totalHt,
            0.0,
            0.0,
            $pricingDetails->totalHt,
            $pricingDetails->vatAmount,
        );

        return $document;
    }

    private function buildPricingExplanation(
        TeamReportInvoicePricingDetails $pricingDetails,
    ): string {
        $parts = [];

        foreach ($pricingDetails->lines as $line) {
            $parts[] = sprintf(
                '%s : %d utilisateur(s) actif(s) x %.2f EUR HT = %.2f EUR HT',
                $line->periodLabel,
                $line->activeUserCount,
                $line->unitPriceMonthlyHt,
                $line->totalHt,
            );
        }

        return implode(' ; ', $parts);
    }
}