<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Entity\Order;
use App\Utils\InvoicePdf;
use Doctrine\ORM\EntityManagerInterface;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Symfony\Component\Filesystem\Filesystem;

final class ClassicInvoiceFacturXGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $projectDir,
    ) {
    }

    public function generate(Order $order): string
    {
        $invoice = $order->getInvoice();

        if ($invoice === null) {
            throw new \LogicException(
                'Impossible de générer la Factur-X : la commande ne possède pas de facture.'
            );
        }

        if ($invoice->getNum() === null) {
            throw new \LogicException(
                'Impossible de générer la Factur-X sans numéro de facture.'
            );
        }

        if ($order->getPlan() === null) {
            throw new \LogicException(
                'Impossible de générer la Factur-X : aucun plan associé à la commande.'
            );
        }

        /*
         * On enregistre les montants sur Invoice pour avoir une facture
         * cohérente, quel que soit son type.
         */
        $invoice
            ->setTotalHt($order->getTotalHt())
            ->setVatAmount($order->getVatAmount())
            ->setTotalTtc($order->getTotalTtc());

        $document = $this->buildXmlDocument($order);

        /*
         * Ton générateur InvoicePdf attend actuellement un Order.
         */
        $pdfContent = (new InvoicePdf())->generatePdf($order);

        $pdfBuilder = ZugferdDocumentPdfBuilder::fromPdfString(
            $document,
            $pdfContent,
        );

        $pdfBuilder->setAdditionalCreatorTool('Mileo');
        $pdfBuilder->generateDocument();

        $directory = $this->projectDir.'/var/invoices/classic';

        (new Filesystem())->mkdir($directory);

        $safeInvoiceNumber = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '_',
            (string) $invoice->getNum(),
        );

        $path = sprintf(
            '%s/Mileo_Facture_%s_Factur-X.pdf',
            $directory,
            $safeInvoiceNumber,
        );

        $pdfBuilder->saveDocument($path);

        $invoice->setFacturXPath($path);

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $path;
    }

    private function buildXmlDocument(Order $order): ZugferdDocumentBuilder
    {
        $invoice = $order->getInvoice();
        $plan = $order->getPlan();

        if ($invoice === null || $plan === null) {
            throw new \LogicException(
                'La commande doit posséder une facture et un plan.'
            );
        }

        $document = ZugferdDocumentBuilder::createNew(
            ZugferdProfiles::PROFILE_EN16931,
        );

        $invoiceDate = $order->getCreatedAt() ?? new \DateTime();

        /*
         * Code 380 = facture commerciale.
         */
        $document->setDocumentInformation(
            (string) $invoice->getNum(),
            '380',
            $invoiceDate,
            'EUR',
        );

        /*
         * Vendeur.
         */
        $document->setDocumentSeller('Anybug / Mileo');

        $document->addDocumentSellerVATRegistrationNumber(
            'FR14517653531',
        );

        $document->setDocumentSellerAddress(
            '8 Rue Beaulieu',
            '',
            '',
            '17430',
            'Cabariot',
            'FR',
        );

        /*
         * Acheteur.
         */
        $buyerName = trim((string) $order->getBillingName());

        $document->setDocumentBuyer(
            $buyerName !== '' ? $buyerName : 'Client Mileo',
        );

        $document->setDocumentBuyerAddress(
            (string) $order->getBillingAddress(),
            '',
            '',
            (string) $order->getBillingPostCode(),
            (string) $order->getBillingCity(),
            'FR',
        );

        /*
         * Ligne de facturation.
         */
        $document->addNewPosition('1');

        $label = sprintf(
            'Abonnement Mileo - %s',
            (string) $plan,
        );

        $description = sprintf(
            'Abonnement Mileo valable jusqu’au %s',
            $order->getSubscriptionEnd()?->format('d/m/Y') ?? 'date non définie',
        );

        $productReference = sprintf(
            'MILEO-PLAN-%s',
            method_exists($plan, 'getId')
                ? (string) $plan->getId()
                : 'CLASSIC',
        );

        $document->setDocumentPositionProductDetails(
            $label,
            $description,
            $productReference,
        );

        $document->setDocumentPositionNetPrice(
            (float) $order->getTotalHt(),
        );

        $document->setDocumentPositionQuantity(
            1,
            'C62',
        );

        $vatRate = $this->resolveVatRate($order);

        $document->addDocumentPositionTax(
            'S',
            'VAT',
            $vatRate,
        );

        $document->setDocumentPositionLineSummation(
            (float) $order->getTotalHt(),
        );

        /*
         * Totaux de TVA.
         */
        $document->addDocumentTax(
            'S',
            'VAT',
            (float) $order->getTotalHt(),
            (float) $order->getVatAmount(),
            $vatRate,
        );

        /*
         * Total général.
         *
         * Ordre des arguments :
         * - total TTC
         * - montant dû
         * - somme des lignes HT
         * - frais
         * - remises
         * - base taxable HT
         * - TVA
         */
        $document->setDocumentSummation(
            (float) $order->getTotalTtc(),
            (float) $order->getTotalTtc(),
            (float) $order->getTotalHt(),
            0.0,
            0.0,
            (float) $order->getTotalHt(),
            (float) $order->getVatAmount(),
        );

        return $document;
    }

    private function resolveVatRate(Order $order): float
    {
        $totalHt = (float) $order->getTotalHt();
        $vatAmount = (float) $order->getVatAmount();

        if ($totalHt <= 0.0) {
            return 20.0;
        }

        return round(($vatAmount / $totalHt) * 100, 2);
    }
}