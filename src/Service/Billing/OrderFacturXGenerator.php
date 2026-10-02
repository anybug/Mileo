<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Entity\Order;
use App\Utils\InvoicePdf;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Symfony\Component\Filesystem\Filesystem;

final class OrderFacturXGenerator
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * Génère la facture Factur-X et retourne son chemin absolu.
     * A compléter ! la facture se génère bien mais elle n'est pas conforme à 100%
     */
    public function generate(Order $order): string
    {
        $invoice = $order->getInvoice();

        if ($invoice === null) {
            throw new \LogicException(
                'Impossible de générer une Factur-X sans facture associée.',
            );
        }

        if ($invoice->getNum() === null) {
            throw new \LogicException(
                'Impossible de générer une Factur-X sans numéro de facture.',
            );
        }

        $this->validateOrder($order);

        /*
         * 1. Création du XML structuré EN16931.
         */
        $document = $this->buildXmlDocument($order);

        /*
         * 2. Création du PDF visuel existant.
         */
        $pdfContent = (new InvoicePdf())->generatePdf($order);

        /*
         * 3. Intégration du XML dans le PDF et conversion Factur-X.
         */
        $pdfBuilder = ZugferdDocumentPdfBuilder::fromPdfString(
            $document,
            $pdfContent,
        );

        $pdfBuilder->setAdditionalCreatorTool('Mileo');
        $pdfBuilder->generateDocument();

        /*
         * 4. Enregistrement du fichier final.
         */
        $directory = $this->projectDir.'/var/invoices/orders';

        (new Filesystem())->mkdir($directory);

        $filename = sprintf(
            'Mileo_Facture_%s.pdf',
            $invoice->getNum(),
        );

        $path = $directory.'/'.$filename;

        $pdfBuilder->saveDocument($path);

        return $path;
    }

    private function buildXmlDocument(
        Order $order,
    ): ZugferdDocumentBuilder {
        $invoice = $order->getInvoice();

        if ($invoice === null || $invoice->getNum() === null) {
            throw new \LogicException(
                'La facture ou son numéro est manquant.',
            );
        }

        $invoiceDate = $this->resolveInvoiceDate($order);

        $totalHt = $this->roundAmount((float) $order->getTotalHt());
        $vatAmount = $this->roundAmount((float) $order->getVatAmount());
        $totalTtc = $this->roundAmount((float) $order->getTotalTTC());

        $vatRate = $this->resolveVatRate(
            $totalHt,
            $vatAmount,
        );

        $document = ZugferdDocumentBuilder::createNew(
            ZugferdProfiles::PROFILE_EN16931,
        );

        /*
         * Code 380 = facture commerciale.
         */
        $document->setDocumentInformation(
            (string) $invoice->getNum(),
            '380',
            $invoiceDate,
            'EUR',
        );

        $this->addSeller($document);
        $this->addBuyer($document, $order);
        $this->addInvoicePosition(
            $document,
            $order,
            $totalHt,
            $vatRate,
        );

        /*
         * Ventilation globale de TVA.
         */
        $document->addDocumentTax(
            'S',
            'VAT',
            $totalHt,
            $vatAmount,
            $vatRate,
        );

        /*
         * Totaux :
         * - montant dû ;
         * - total TTC ;
         * - total lignes HT ;
         * - charges ;
         * - remises ;
         * - base taxable HT ;
         * - montant de TVA.
         */
        $document->setDocumentSummation(
            $totalTtc,
            $totalTtc,
            $totalHt,
            0.0,
            0.0,
            $totalHt,
            $vatAmount,
        );

        return $document;
    }

    private function addSeller(
        ZugferdDocumentBuilder $document,
    ): void {
        $sellerName = $this->getEnvironmentValue(
            'BILLING_COMPANY_NAME',
            'Mileo édité par Anybug',
        );

        $sellerAddress = $this->getEnvironmentValue(
            'BILLING_COMPANY_ADDRESS',
            '8 Rue Beaulieu',
        );

        $sellerPostalCode = $this->getEnvironmentValue(
            'BILLING_COMPANY_POSTAL_CODE',
            '17430',
        );

        $sellerCity = $this->getEnvironmentValue(
            'BILLING_COMPANY_CITY',
            'Cabariot',
        );

        $sellerVatNumber = $this->getEnvironmentValue(
            'BILLING_COMPANY_VAT_NUMBER',
            'FR14517653531',
        );

        $document->setDocumentSeller($sellerName);

        $document->addDocumentSellerVATRegistrationNumber(
            $sellerVatNumber,
        );

        $document->setDocumentSellerAddress(
            $sellerAddress,
            '',
            '',
            $sellerPostalCode,
            $sellerCity,
            'FR',
        );
    }

    private function addBuyer(
        ZugferdDocumentBuilder $document,
        Order $order,
    ): void {
        $buyerName = trim((string) $order->getBillingName());

        if ($buyerName === '') {
            $buyerName = 'Client Mileo';
        }

        $buyerAddress = trim((string) $order->getBillingAddress());
        $buyerPostCode = trim((string) $order->getBillingPostCode());
        $buyerCity = trim((string) $order->getBillingCity());

        $document->setDocumentBuyer($buyerName);

        $document->setDocumentBuyerAddress(
            $buyerAddress,
            '',
            '',
            $buyerPostCode,
            $buyerCity,
            'FR',
        );
    }

    private function addInvoicePosition(
        ZugferdDocumentBuilder $document,
        Order $order,
        float $totalHt,
        float $vatRate,
    ): void {
        $planName = trim((string) $order->getPlan());

        if ($planName === '') {
            $planName = 'Abonnement Mileo';
        }

        $description = $this->buildDescription($order);

        $productCode = sprintf(
            'MILEO-SUBSCRIPTION-%s',
            $order->getId() ?? 'ORDER',
        );

        $document->addNewPosition('1');

        $document->setDocumentPositionProductDetails(
            $planName,
            $description,
            $productCode,
        );

        /*
         * Une ligne représentant un abonnement complet.
         */
        $document->setDocumentPositionNetPrice($totalHt);
        $document->setDocumentPositionQuantity(1, 'C62');

        $document->addDocumentPositionTax(
            'S',
            'VAT',
            $vatRate,
        );

        $document->setDocumentPositionLineSummation(
            $totalHt,
        );
    }

    private function buildDescription(
        Order $order,
    ): string {
        $description = trim(
            (string) $order->getProductDescription(),
        );

        if ($description === '') {
            $description = 'Abonnement Mileo';
        }

        $subscriptionEnd = $order->getSubscriptionEnd();

        if ($subscriptionEnd instanceof \DateTimeInterface) {
            $description .= sprintf(
                ' - Valide jusqu’au %s',
                $subscriptionEnd->format('d/m/Y'),
            );
        }

        return $description;
    }

    private function resolveInvoiceDate(
        Order $order,
    ): \DateTimeImmutable {
        $createdAt = $order->getCreatedAt();

        if ($createdAt instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface(
                $createdAt,
            );
        }

        return new \DateTimeImmutable();
    }

    private function resolveVatRate(
        float $totalHt,
        float $vatAmount,
    ): float {
        if ($totalHt <= 0.0) {
            return 20.0;
        }

        return round(
            ($vatAmount / $totalHt) * 100,
            2,
        );
    }

    private function roundAmount(
        float $amount,
    ): float {
        return round($amount, 2);
    }

    private function validateOrder(
        Order $order,
    ): void {
        $totalHt = (float) $order->getTotalHt();
        $vatAmount = (float) $order->getVatAmount();
        $totalTtc = (float) $order->getTotalTTC();

        if ($totalHt < 0 || $vatAmount < 0 || $totalTtc < 0) {
            throw new \LogicException(
                'Les montants de la facture ne peuvent pas être négatifs.',
            );
        }

        $expectedTotalTtc = round(
            $totalHt + $vatAmount,
            2,
        );

        if (abs($expectedTotalTtc - round($totalTtc, 2)) > 0.01) {
            throw new \LogicException(sprintf(
                'Les totaux de la facture sont incohérents : '
                .'%.2f € HT + %.2f € de TVA ne correspondent pas à %.2f € TTC.',
                $totalHt,
                $vatAmount,
                $totalTtc,
            ));
        }
    }

    private function getEnvironmentValue(
        string $name,
        string $default = '',
    ): string {
        $value = $_ENV[$name]
            ?? $_SERVER[$name]
            ?? getenv($name)
            ?: $default;

        return trim((string) $value);
    }
}