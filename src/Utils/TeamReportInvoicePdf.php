<?php

declare(strict_types=1);

namespace App\Utils;

use App\Entity\Invoice;
use App\Service\Billing\TeamReportInvoicePricingDetails;
use TCPDF;

final class TeamReportInvoicePdf extends TCPDF
{
    private const PRIMARY_COLOR = '#6174D1';
    private const PRIMARY_LIGHT_COLOR = '#F1F3FF';
    private const BORDER_COLOR = '#DDE1F0';
    private const BACKGROUND_COLOR = '#FAFAFD';
    private const TEXT_COLOR = '#202331';
    private const MUTED_COLOR = '#646978';

    private ?Invoice $invoice = null;

    private function setInvoice(Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }

    private function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars(
            trim((string) $value),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', ' ').' €';
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

    private function getEnvironmentInteger(
        string $name,
        int $default,
    ): int {
        $value = $this->getEnvironmentValue($name);

        if ($value === '' || !is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    private function getOptionalString(
        ?object $object,
        string ...$methods,
    ): string {
        if ($object === null) {
            return '';
        }

        foreach ($methods as $method) {
            if (!method_exists($object, $method)) {
                continue;
            }

            $value = $object->{$method}();

            if (is_scalar($value)) {
                $stringValue = trim((string) $value);

                if ($stringValue !== '') {
                    return $stringValue;
                }
            }

            if ($value instanceof \Stringable) {
                $stringValue = trim((string) $value);

                if ($stringValue !== '') {
                    return $stringValue;
                }
            }
        }

        return '';
    }

    private function getCompanyObject(?object $manager): ?object
    {
        if (
            $manager === null
            || !method_exists($manager, 'getCompany')
        ) {
            return null;
        }

        $company = $manager->getCompany();

        return is_object($company) ? $company : null;
    }

    private function resolveInvoiceDate(
        Invoice $invoice,
    ): \DateTimeImmutable {
        foreach (
            [
                'getIssuedAt',
                'getInvoiceDate',
                'getCreatedAt',
            ] as $method
        ) {
            if (!method_exists($invoice, $method)) {
                continue;
            }

            $date = $invoice->{$method}();

            if ($date instanceof \DateTimeInterface) {
                return \DateTimeImmutable::createFromInterface($date);
            }
        }

        return new \DateTimeImmutable();
    }

    private function resolveDueDate(
        Invoice $invoice,
        \DateTimeImmutable $invoiceDate,
    ): \DateTimeImmutable {
        foreach (
            [
                'getDueAt',
                'getDueDate',
                'getPaymentDueAt',
            ] as $method
        ) {
            if (!method_exists($invoice, $method)) {
                continue;
            }

            $date = $invoice->{$method}();

            if ($date instanceof \DateTimeInterface) {
                return \DateTimeImmutable::createFromInterface($date);
            }
        }

        $paymentTermDays = $this->getEnvironmentInteger(
            'BILLING_PAYMENT_TERM_DAYS',
            30,
        );

        return $invoiceDate->modify(
            sprintf('+%d days', $paymentTermDays),
        );
    }

    private function getClientName(Invoice $invoice): string
    {
        $manager = $invoice->getTeamManager();
        $company = $this->getCompanyObject($manager);

        $companyName = $this->getOptionalString(
            $company,
            'getName',
            'getCompanyName',
            '__toString',
        );

        if ($companyName !== '') {
            return $companyName;
        }

        $managerCompanyName = $this->getOptionalString(
            $manager,
            'getCompany',
            'getCompanyName',
        );

        if ($managerCompanyName !== '') {
            return $managerCompanyName;
        }

        $managerName = trim(sprintf(
            '%s %s',
            $this->getOptionalString($manager, 'getFirstName'),
            $this->getOptionalString($manager, 'getLastName'),
        ));

        return $managerName !== ''
            ? $managerName
            : 'Client Mileo';
    }

    /**
     * @return list<string>
     */
    private function getClientDetails(Invoice $invoice): array
    {
        $manager = $invoice->getTeamManager();
        $company = $this->getCompanyObject($manager);

        if ($manager === null) {
            return [];
        }

        $details = [];

        $managerName = trim(sprintf(
            '%s %s',
            $this->getOptionalString($manager, 'getFirstName'),
            $this->getOptionalString($manager, 'getLastName'),
        ));

        $clientName = $this->getClientName($invoice);

        if (
            $managerName !== ''
            && $managerName !== $clientName
        ) {
            $details[] = 'À l’attention de '.$managerName;
        }

        $addressSource = $company ?? $manager;

        $address = $this->getOptionalString(
            $addressSource,
            'getBillingAddress',
            'getAddress',
            'getAddressLine1',
            'getStreet',
        );

        $addressLine2 = $this->getOptionalString(
            $addressSource,
            'getAddressLine2',
            'getAdditionalAddress',
        );

        $postalCode = $this->getOptionalString(
            $addressSource,
            'getPostalCode',
            'getZipCode',
        );

        $city = $this->getOptionalString(
            $addressSource,
            'getCity',
        );

        $country = $this->getOptionalString(
            $addressSource,
            'getCountry',
            'getCountryName',
        );

        if ($address !== '') {
            $details[] = $address;
        }

        if ($addressLine2 !== '') {
            $details[] = $addressLine2;
        }

        $postalCodeAndCity = trim($postalCode.' '.$city);

        if ($postalCodeAndCity !== '') {
            $details[] = $postalCodeAndCity;
        }

        if ($country !== '') {
            $details[] = $country;
        }

        $email = $this->getOptionalString(
            $manager,
            'getEmail',
        );

        if ($email !== '') {
            $details[] = 'Email : '.$email;
        }

        $registrationSource = $company ?? $manager;

        $siren = $this->getOptionalString(
            $registrationSource,
            'getSiren',
            'getSiret',
            'getCompanyRegistrationNumber',
        );

        if ($siren !== '') {
            $details[] = 'SIREN / SIRET : '.$siren;
        }

        $vatNumber = $this->getOptionalString(
            $registrationSource,
            'getVatNumber',
            'getVatIdentificationNumber',
            'getIntracommunityVatNumber',
        );

        if ($vatNumber !== '') {
            $details[] = 'TVA intracommunautaire : '.$vatNumber;
        }

        return $details;
    }

    /**
     * Le header ne contient que des éléments de hauteur fixe.
     */
    public function Header(): void
    {
        $logoPath = dirname(__DIR__, 2).'/assets/img/logo.png';

        if (is_file($logoPath)) {
            $this->Image(
                $logoPath,
                15,
                7,
                38,
                0,
                'PNG',
            );
        } else {
            $this->SetXY(15, 8);
            $this->SetFont('helvetica', 'B', 18);
            $this->SetTextColor(97, 116, 209);
            $this->Cell(45, 10, 'MILEO');
        }

        $this->SetXY(115, 7);
        $this->SetFont('helvetica', 'B', 21);
        $this->SetTextColor(32, 35, 49);
        $this->Cell(80, 12, 'FACTURE', 0, 0, 'R');

        $this->SetDrawColor(97, 116, 209);
        $this->SetLineWidth(0.4);
        $this->Line(15, 23, 195, 23);
    }

    public function Footer(): void
    {
        $contactEmail = $this->getEnvironmentValue(
            'CONTACT_EMAIL',
            'contact@mileo.fr',
        );

        $companyName = $this->getEnvironmentValue(
            'BILLING_COMPANY_NAME',
            'Mileo édité par Anybug',
        );

        $companyAddress = $this->getEnvironmentValue(
            'BILLING_COMPANY_ADDRESS',
            '8 Rue Beaulieu',
        );

        $companyPostalCode = $this->getEnvironmentValue(
            'BILLING_COMPANY_POSTAL_CODE',
            '17430',
        );

        $companyCity = $this->getEnvironmentValue(
            'BILLING_COMPANY_CITY',
            'Cabariot',
        );

        $companySiren = $this->getEnvironmentValue(
            'BILLING_COMPANY_SIREN',
            '517 653 531',
        );

        $companyVatNumber = $this->getEnvironmentValue(
            'BILLING_COMPANY_VAT_NUMBER',
            'FR14517653531',
        );

        $this->SetY(-26);

        $this->SetDrawColor(221, 225, 240);
        $this->SetLineWidth(0.2);

        $this->Line(
            15,
            $this->GetY(),
            195,
            $this->GetY(),
        );

        $this->SetY(-24);
        $this->SetFont('helvetica', '', 7.5);
        $this->SetTextColor(100, 105, 120);

        $footerHtml = sprintf(
            '
            <table
                width="100%%"
                border="0"
                cellspacing="0"
                cellpadding="1"
            >
                <tr>
                    <td align="center">
                        %s – %s – %s %s
                    </td>
                </tr>

                <tr>
                    <td align="center">
                        Email : %s –
                        SIREN : %s –
                        TVA : %s
                    </td>
                </tr>
            </table>
            ',
            $this->escape($companyName),
            $this->escape($companyAddress),
            $this->escape($companyPostalCode),
            $this->escape($companyCity),
            $this->escape($contactEmail),
            $this->escape($companySiren),
            $this->escape($companyVatNumber),
        );

        $this->writeHTML(
            $footerHtml,
            true,
            false,
            true,
            false,
            '',
        );

        $this->SetY(-10);
        $this->SetFont('helvetica', 'I', 8);

        $this->Cell(
            90,
            5,
            'Copyright Mileo '.date('Y'),
            0,
            0,
            'L',
        );

        $this->Cell(
            90,
            5,
            'Page '.$this->getAliasNumPage()
                .'/'.$this->getAliasNbPages(),
            0,
            0,
            'R',
        );
    }

    public function generatePdf(
        Invoice $invoice,
        TeamReportInvoicePricingDetails $pricingDetails,
    ): string {
        $this->setInvoice($invoice);

        $invoiceDate = $this->resolveInvoiceDate($invoice);

        $dueDate = $this->resolveDueDate(
            $invoice,
            $invoiceDate,
        );

        $paymentTermDays = $this->getEnvironmentInteger(
            'BILLING_PAYMENT_TERM_DAYS',
            30,
        );

        $billingPeriod = sprintf(
            '%02d/%04d',
            $invoice->getBillingMonth(),
            $invoice->getBillingYear(),
        );

        $this->SetAuthor('Mileo');
        $this->SetCreator('Mileo');
        $this->SetTitle('Facture '.$invoice->getNum());

        $this->SetSubject(
            'Facture abonnement Mileo Plan Tiers',
        );

        $this->SetKeywords(
            'Mileo, facture, IK, abonnement, équipe',
        );

        /*
         * La marge haute laisse la place au logo et au titre.
         * La marge basse protège le footer.
         */
        $this->SetMargins(15, 30, 15);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(22);
        $this->SetAutoPageBreak(true, 28);

        $this->AddPage();
        $this->SetY(30);

        $this->SetFont('helvetica', '', 9.5);
        $this->SetTextColor(32, 35, 49);

        /*
         * Informations de l’émetteur.
         */
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

        $sellerPhone = $this->getEnvironmentValue(
            'BILLING_COMPANY_PHONE',
            '05 46 89 43 44',
        );

        $sellerSiren = $this->getEnvironmentValue(
            'BILLING_COMPANY_SIREN',
            '517 653 531',
        );

        $sellerVatNumber = $this->getEnvironmentValue(
            'BILLING_COMPANY_VAT_NUMBER',
            'FR14517653531',
        );

        $contactEmail = $this->getEnvironmentValue(
            'CONTACT_EMAIL',
            'contact@mileo.fr',
        );

        /*
         * Émetteur et informations principales de la facture.
         */
        $invoiceInformationHtml = sprintf(
            '
            <table
                border="0"
                cellspacing="0"
                cellpadding="0"
            >
                <tr>
                    <td
                        width="48%%"
                        style="
                            border:1px solid %s;
                            background-color:%s;
                            padding:7px;
                        "
                    >
                        <span
                            style="
                                color:%s;
                                font-weight:bold;
                            "
                        >
                            ÉMETTEUR
                        </span>

                        <br><br>

                        <strong>%s</strong>
                        <br>
                        %s
                        <br>
                        %s %s
                        <br>
                        Tél. : %s
                        <br>
                        Email : %s
                        <br>
                        SIREN : %s
                        <br>
                        TVA intracommunautaire : %s
                    </td>

                    <td width="4%%"></td>

                    <td
                        width="48%%"
                        style="
                            border:1px solid %s;
                            background-color:%s;
                            padding:7px;
                        "
                    >
                        <span
                            style="
                                color:%s;
                                font-weight:bold;
                            "
                        >
                            INFORMATIONS DE FACTURATION
                        </span>

                        <br><br>

                        <table
                            border="0"
                            cellspacing="0"
                            cellpadding="2"
                        >
                            <tr>
                                <td width="47%%">
                                    Facture n°
                                </td>

                                <td
                                    width="53%%"
                                    align="right"
                                >
                                    <strong>%s</strong>
                                </td>
                            </tr>

                            <tr>
                                <td width="47%%">
                                    Date d’émission
                                </td>

                                <td
                                    width="53%%"
                                    align="right"
                                >
                                    <strong>%s</strong>
                                </td>
                            </tr>

                            <tr>
                                <td width="47%%">
                                    Date d’échéance
                                </td>

                                <td
                                    width="53%%"
                                    align="right"
                                >
                                    <strong>%s</strong>
                                </td>
                            </tr>

                            <tr>
                                <td width="47%%">
                                    Période facturée
                                </td>

                                <td
                                    width="53%%"
                                    align="right"
                                >
                                    <strong>%s</strong>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            ',
            self::BORDER_COLOR,
            self::BACKGROUND_COLOR,
            self::PRIMARY_COLOR,
            $this->escape($sellerName),
            $this->escape($sellerAddress),
            $this->escape($sellerPostalCode),
            $this->escape($sellerCity),
            $this->escape($sellerPhone),
            $this->escape($contactEmail),
            $this->escape($sellerSiren),
            $this->escape($sellerVatNumber),
            self::PRIMARY_COLOR,
            self::PRIMARY_LIGHT_COLOR,
            self::PRIMARY_COLOR,
            $this->escape($invoice->getNum()),
            $invoiceDate->format('d/m/Y'),
            $dueDate->format('d/m/Y'),
            $billingPeriod,
        );

        $this->writeHTML(
            $invoiceInformationHtml,
            true,
            false,
            true,
            false,
            '',
        );

        $this->Ln(3);

        /*
         * Informations du client.
         */
        $clientDetails = $this->getClientDetails($invoice);
        $clientDetailsHtml = '';

        foreach ($clientDetails as $clientDetail) {
            $clientDetailsHtml .= sprintf(
                '%s<br>',
                $this->escape($clientDetail),
            );
        }

        $clientHtml = sprintf(
            '
            <table
                border="0"
                cellspacing="0"
                cellpadding="7"
            >
                <tr>
                    <td
                        style="
                            border:1px solid %s;
                            background-color:#FFFFFF;
                        "
                    >
                        <span
                            style="
                                color:%s;
                                font-weight:bold;
                            "
                        >
                            FACTURÉ À
                        </span>

                        <br><br>

                        <strong style="font-size:11pt;">
                            %s
                        </strong>

                        <br>

                        %s
                    </td>
                </tr>
            </table>
            ',
            self::BORDER_COLOR,
            self::PRIMARY_COLOR,
            $this->escape($this->getClientName($invoice)),
            $clientDetailsHtml,
        );

        $this->writeHTML(
            $clientHtml,
            true,
            false,
            true,
            false,
            '',
        );

        $this->Ln(3);

        /*
         * Indicateurs.
         */
        $reportCount = $invoice->getReports()->count();
        $totalKm = 0.0;

        foreach ($invoice->getReports() as $report) {
            $totalKm += (float) ($report->getKm() ?? 0);
        }

        /*
         * Présentation du plan et tarifs.
         *
         * Les tarifs sont intégrés ici afin de ne pas créer
         * un bloc isolé en fin de document.
         */
        $planHtml = sprintf(
            '
            <table
                nobr="true"
                border="0"
                cellspacing="0"
                cellpadding="6"
            >
                <tr>
                    <td
                        style="
                            border:1px solid %s;
                            background-color:%s;
                            text-align:center;
                        "
                    >
                        <span
                            style="
                                color:%s;
                                font-size:13pt;
                                font-weight:bold;
                            "
                        >
                            Abonnement Mileo – Plan Tiers
                        </span>

                        <br>

                        Pilotez les IK de votre équipe et de votre
                        flotte au même endroit.

                        <br>

                        <span
                            style="
                                color:%s;
                                font-size:8.5pt;
                            "
                        >
                            Période facturée : %s
                        </span>

                        <br><br>

                        <table
                            border="0"
                            cellspacing="0"
                            cellpadding="4"
                        >
                            <tr>
                                <td
                                    colspan="3"
                                    align="left"
                                    style="
                                        color:%s;
                                        background-color:#FFFFFF;
                                        border:1px solid %s;
                                        font-weight:bold;
                                    "
                                >
                                    Tarification du Plan Tiers
                                </td>
                            </tr>

                            <tr>
                                <td
                                    width="33%%"
                                    align="center"
                                    style="
                                        background-color:#FFFFFF;
                                        border:1px solid %s;
                                        font-size:8pt;
                                    "
                                >
                                    <strong>
                                        3 à 9 collaborateurs
                                    </strong>

                                    <br>

                                    6,00 € HT/mois/
                                    <br>
                                    collaborateur
                                </td>

                                <td
                                    width="34%%"
                                    align="center"
                                    style="
                                        background-color:#FFFFFF;
                                        border:1px solid %s;
                                        font-size:8pt;
                                    "
                                >
                                    <strong>
                                        10 à 24 collaborateurs
                                    </strong>

                                    <br>

                                    5,00 € HT/mois/
                                    <br>
                                    collaborateur
                                </td>

                                <td
                                    width="33%%"
                                    align="center"
                                    style="
                                        background-color:#FFFFFF;
                                        border:1px solid %s;
                                        font-size:8pt;
                                    "
                                >
                                    <strong>
                                        25 collaborateurs et plus
                                    </strong>

                                    <br>

                                    4,00 € HT/mois/
                                    <br>
                                    collaborateur
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            ',
            self::PRIMARY_COLOR,
            self::PRIMARY_LIGHT_COLOR,
            self::PRIMARY_COLOR,
            self::MUTED_COLOR,
            $billingPeriod,
            self::PRIMARY_COLOR,
            self::BORDER_COLOR,
            self::BORDER_COLOR,
            self::BORDER_COLOR,
            self::BORDER_COLOR,
        );

        $this->writeHTML(
            $planHtml,
            true,
            false,
            true,
            false,
            '',
        );

        $this->Ln(3);

        /*
         * Les trois indicateurs.
         */
        $metricsHtml = sprintf(
            '
            <table
                border="0"
                cellspacing="0"
                cellpadding="6"
            >
                <tr>
                    <td
                        width="32%%"
                        align="center"
                        style="
                            border:1px solid %s;
                            background-color:%s;
                        "
                    >
                        <span
                            style="
                                color:%s;
                                font-size:8pt;
                            "
                        >
                            Utilisateurs-mois facturés
                        </span>

                        <br>

                        <strong style="font-size:13pt;">
                            %d
                        </strong>
                    </td>

                    <td width="2%%"></td>

                    <td
                        width="32%%"
                        align="center"
                        style="
                            border:1px solid %s;
                            background-color:%s;
                        "
                    >
                        <span
                            style="
                                color:%s;
                                font-size:8pt;
                            "
                        >
                            Rapports IK saisis
                        </span>

                        <br>

                        <strong style="font-size:13pt;">
                            %d
                        </strong>
                    </td>

                    <td width="2%%"></td>

                    <td
                        width="32%%"
                        align="center"
                        style="
                            border:1px solid %s;
                            background-color:%s;
                        "
                    >
                        <span
                            style="
                                color:%s;
                                font-size:8pt;
                            "
                        >
                            Kilomètres déclarés
                        </span>

                        <br>

                        <strong style="font-size:13pt;">
                            %s km
                        </strong>
                    </td>
                </tr>
            </table>
            ',
            self::BORDER_COLOR,
            self::BACKGROUND_COLOR,
            self::MUTED_COLOR,
            $pricingDetails->totalActiveUserMonths,
            self::BORDER_COLOR,
            self::BACKGROUND_COLOR,
            self::MUTED_COLOR,
            $reportCount,
            self::BORDER_COLOR,
            self::BACKGROUND_COLOR,
            self::MUTED_COLOR,
            number_format($totalKm, 0, ',', ' '),
        );

        $this->writeHTML(
            $metricsHtml,
            true,
            false,
            true,
            false,
            '',
        );

        $this->Ln(4);

        /*
         * Lignes de facturation.
         */
        $invoiceLinesHtml = '';

        foreach ($pricingDetails->lines as $line) {
            $invoiceLinesHtml .= sprintf(
                '
                <tr>
                    <td
                        width="34%%"
                        style="
                            border-bottom:1px solid %s;
                        "
                    >
                        <strong>Abonnement Plan Tiers</strong>

                        <br>

                        <span
                            style="
                                color:%s;
                                font-size:8pt;
                            "
                        >
                            Utilisateurs actifs avec IK saisies
                        </span>
                    </td>

                    <td
                        width="20%%"
                        align="center"
                        style="
                            border-bottom:1px solid %s;
                        "
                    >
                        %s
                    </td>

                    <td
                        width="10%%"
                        align="center"
                        style="
                            border-bottom:1px solid %s;
                        "
                    >
                        %d
                    </td>

                    <td
                        width="16%%"
                        align="right"
                        style="
                            border-bottom:1px solid %s;
                        "
                    >
                        %s
                    </td>

                    <td
                        width="20%%"
                        align="right"
                        style="
                            border-bottom:1px solid %s;
                        "
                    >
                        %s
                    </td>
                </tr>
                ',
                self::BORDER_COLOR,
                self::MUTED_COLOR,
                self::BORDER_COLOR,
                $this->escape($line->periodLabel),
                self::BORDER_COLOR,
                $line->activeUserCount,
                self::BORDER_COLOR,
                $this->formatMoney(
                    (float) $line->unitPriceMonthlyHt,
                ),
                self::BORDER_COLOR,
                $this->formatMoney(
                    (float) $line->totalHt,
                ),
            );
        }

        $invoiceTableHtml = sprintf(
            '
            <table
                border="0"
                cellspacing="0"
                cellpadding="6"
            >
                <thead>
                    <tr>
                        <th
                            width="34%%"
                            style="
                                color:#FFFFFF;
                                background-color:%s;
                                font-weight:bold;
                            "
                        >
                            Désignation
                        </th>

                        <th
                            width="20%%"
                            align="center"
                            style="
                                color:#FFFFFF;
                                background-color:%s;
                                font-weight:bold;
                            "
                        >
                            Période
                        </th>

                        <th
                            width="10%%"
                            align="center"
                            style="
                                color:#FFFFFF;
                                background-color:%s;
                                font-weight:bold;
                            "
                        >
                            Qté
                        </th>

                        <th
                            width="16%%"
                            align="right"
                            style="
                                color:#FFFFFF;
                                background-color:%s;
                                font-weight:bold;
                            "
                        >
                            PU HT
                        </th>

                        <th
                            width="20%%"
                            align="right"
                            style="
                                color:#FFFFFF;
                                background-color:%s;
                                font-weight:bold;
                            "
                        >
                            Total HT
                        </th>
                    </tr>
                </thead>

                <tbody>
                    %s
                </tbody>
            </table>
            ',
            self::PRIMARY_COLOR,
            self::PRIMARY_COLOR,
            self::PRIMARY_COLOR,
            self::PRIMARY_COLOR,
            self::PRIMARY_COLOR,
            $invoiceLinesHtml,
        );

        $this->writeHTML(
            $invoiceTableHtml,
            true,
            false,
            true,
            false,
            '',
        );

        $this->Ln(3);

        /*
         * Totaux.
         */
        $totalsHtml = sprintf(
            '
            <table
                nobr="true"
                border="0"
                cellspacing="0"
                cellpadding="5"
            >
                <tr>
                    <td width="58%%"></td>

                    <td
                        width="24%%"
                        align="right"
                    >
                        <strong>Total HT</strong>
                    </td>

                    <td
                        width="18%%"
                        align="right"
                    >
                        <strong>%s</strong>
                    </td>
                </tr>

                <tr>
                    <td width="58%%"></td>

                    <td
                        width="24%%"
                        align="right"
                    >
                        <strong>TVA 20 %%</strong>
                    </td>

                    <td
                        width="18%%"
                        align="right"
                    >
                        <strong>%s</strong>
                    </td>
                </tr>

                <tr>
                    <td width="58%%"></td>

                    <td
                        width="24%%"
                        align="right"
                        style="
                            color:%s;
                            background-color:%s;
                            border-top:1px solid %s;
                            font-size:11pt;
                            font-weight:bold;
                        "
                    >
                        Net à payer
                    </td>

                    <td
                        width="18%%"
                        align="right"
                        style="
                            color:%s;
                            background-color:%s;
                            border-top:1px solid %s;
                            font-size:11pt;
                            font-weight:bold;
                        "
                    >
                        %s
                    </td>
                </tr>
            </table>
            ',
            $this->formatMoney(
                (float) $pricingDetails->totalHt,
            ),
            $this->formatMoney(
                (float) $pricingDetails->vatAmount,
            ),
            self::PRIMARY_COLOR,
            self::PRIMARY_LIGHT_COLOR,
            self::PRIMARY_COLOR,
            self::PRIMARY_COLOR,
            self::PRIMARY_LIGHT_COLOR,
            self::PRIMARY_COLOR,
            $this->formatMoney(
                (float) $pricingDetails->totalTtc,
            ),
        );

        $this->writeHTML(
            $totalsHtml,
            true,
            false,
            true,
            false,
            '',
        );

        /*
         * Aucun AddPage() manuel ici.
         *
         * TCPDF conserve les modalités ensemble grâce à nobr="true"
         * et ne les déplace que lorsqu’elles ne tiennent réellement
         * pas dans l’espace restant.
         */
        $this->Ln(4);

        /*
         * Modalités de paiement.
         */
        $accountHolder = $this->getEnvironmentValue(
            'BILLING_BANK_ACCOUNT_HOLDER',
            'ANYBUG - MILEO',
        );

        $iban = $this->getEnvironmentValue(
            'BILLING_IBAN',
        );

        $bic = $this->getEnvironmentValue(
            'BILLING_BIC',
        );

        $latePaymentRate = $this->getEnvironmentValue(
            'BILLING_LATE_PAYMENT_RATE',
            'trois fois le taux d’intérêt légal',
        );

        $bankDetailsHtml = '';

        if ($iban !== '') {
            $bankDetailsHtml .= sprintf(
                '
                <br>
                <strong>IBAN :</strong> %s
                ',
                $this->escape($iban),
            );
        }

        if ($bic !== '') {
            $bankDetailsHtml .= sprintf(
                '
                <br>
                <strong>BIC :</strong> %s
                ',
                $this->escape($bic),
            );
        }

        if ($iban === '' && $bic === '') {
            $bankDetailsHtml .= '
                <br>

                <span
                    style="
                        color:#646978;
                        font-size:8pt;
                    "
                >
                    Coordonnées bancaires communiquées séparément.
                </span>
            ';
        }

        /*
         * Le titre et les deux colonnes sont contenus dans un même
         * tableau insécable.
         *
         * Le cellpadding est volontairement réduit à 5 afin que
         * le bloc tienne plus facilement en bas de page.
         */
        $paymentHtml = sprintf(
            '
            <table
                nobr="true"
                border="0"
                cellspacing="0"
                cellpadding="5"
            >
                <tr>
                    <td
                        colspan="3"
                        style="
                            color:%s;
                            font-size:12pt;
                            font-weight:bold;
                        "
                    >
                        Modalités de paiement
                    </td>
                </tr>

                <tr>
                    <td
                        colspan="3"
                        style="font-size:3pt;"
                    >
                        &nbsp;
                    </td>
                </tr>

                <tr>
                    <td
                        width="48%%"
                        style="
                            border:1px solid %s;
                            background-color:%s;
                            font-size:8.5pt;
                        "
                    >
                        <strong style="color:%s;">
                            Paiement par virement bancaire
                        </strong>

                        <br><br>

                        <strong>Date d’échéance :</strong>
                        %s

                        <br>

                        <strong>Délai de paiement :</strong>
                        %d jours

                        <br>

                        <strong>Référence à indiquer :</strong>
                        %s

                        <br>

                        <strong>Titulaire :</strong>
                        %s

                        %s
                    </td>

                    <td width="4%%"></td>

                    <td
                        width="48%%"
                        style="
                            border:1px solid %s;
                            background-color:%s;
                            font-size:8.5pt;
                        "
                    >
                        <strong>Conditions de règlement</strong>

                        <br><br>

                        Escompte pour paiement anticipé :
                        néant.

                        <br><br>

                        En cas de retard, des pénalités sont
                        exigibles sans rappel au taux de
                        %s.

                        <br><br>

                        Indemnité forfaitaire pour frais de
                        recouvrement :
                        <strong>40,00 €</strong>.
                    </td>
                </tr>
            </table>
            ',
            self::PRIMARY_COLOR,
            self::PRIMARY_COLOR,
            self::PRIMARY_LIGHT_COLOR,
            self::PRIMARY_COLOR,
            $dueDate->format('d/m/Y'),
            $paymentTermDays,
            $this->escape($invoice->getNum()),
            $this->escape($accountHolder),
            $bankDetailsHtml,
            self::BORDER_COLOR,
            self::BACKGROUND_COLOR,
            $this->escape($latePaymentRate),
        );

        $this->writeHTML(
            $paymentHtml,
            true,
            false,
            true,
            false,
            '',
        );

        $filename = sprintf(
            'Mileo_Facture_%s.pdf',
            $invoice->getNum(),
        );

        return $this->Output($filename, 'S');
    }
}