<?php

declare(strict_types=1);

namespace App\Utils;

use App\Entity\Invoice;
use App\Service\Billing\TeamReportInvoicePricingDetails;
use TCPDF;

class TeamReportInvoicePdf extends TCPDF
{
    private ?Invoice $invoice = null;

    private function setInvoice(Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }

    private function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function Header()
    {
        $invoice = $this->getInvoice();

        if (!$invoice) {
            return;
        }

        $manager = $invoice->getTeamManager();

        $this->setY(PDF_MARGIN_HEADER + 5);
        $this->SetFont('helvetica', 'B', 18);
        $this->Cell(0, 1, '', 'B', true, 'C');

        $this->Image('../assets/img/logo.png', 10, 2, '', 10, 'PNG');

        $this->setY(PDF_MARGIN_HEADER + 15);
        $this->SetFont('helvetica', null, 11);

        $clientName = $manager?->getCompany()
            ?: trim(($manager?->getFirstName() ?? '').' '.($manager?->getLastName() ?? ''));

        $tableHeader = '<table border="0" cellspacing="1" cellpadding="1">';
        $tableHeader .= '<tr><td width="60%"></td><td>'.htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8').'</td></tr>';
        $tableHeader .= '<tr><td width="60%"></td><td>'.htmlspecialchars((string) $manager?->getEmail(), ENT_QUOTES, 'UTF-8').'</td></tr>';
        $tableHeader .= '<tr><td></td></tr>';
        $tableHeader .= '<tr><td width="60%">Date : '.date('d/m/Y').'</td><td></td></tr>';
        $tableHeader .= '<tr><td width="60%">Facture N° '.$invoice->getNum().'</td><td></td></tr>';
        $tableHeader .= '</table>';

        $this->writeHTML($tableHeader, true, false, true, false, '');

        $this->setY(PDF_MARGIN_HEADER + 55);
        $this->Cell(0, 1, '', 'B', true, 'C');
    }

    public function Footer()
    {
        $this->SetY(-25);
        $this->SetFont('helvetica', null, 8);

        $this->Cell(0, 5, 'Copyright Mileo '.date('Y'), 'T', false, 'L');
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 5, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'R');

        $this->SetFont('helvetica', null, 8);
        $this->SetY(-18);

        $footer = '<table width="100%" border="0" cellspacing="1" cellpadding="1">';
        $footer .= '<tr><td width="100%" style="text-align:center">Mileo édité par Anybug - 8 Rue Beaulieu - 17430 Cabariot</td></tr>';
        $footer .= '<tr><td width="100%" style="text-align:center">Tel : 0546894344 - Email : '.htmlspecialchars($_ENV['CONTACT_EMAIL'] ?? '', ENT_QUOTES, 'UTF-8').'</td></tr>';
        $footer .= '<tr><td width="100%" style="text-align:center">RCS La Rochelle 517 653 531 - TVA FR14517653531</td></tr>';
        $footer .= '</table>';

        $this->writeHTML($footer, true, false, true, false, '');
    }

    public function generatePdf(
        Invoice $invoice,
        TeamReportInvoicePricingDetails $pricingDetails,
    ): string {
        $this->setInvoice($invoice);

        $pdf = $this;

        $pdf->SetAuthor('Mileo');
        $pdf->SetTitle('Facture '.$invoice->getNum());
        $pdf->SetSubject('Facture abonnement Team');
        $pdf->SetAutoPageBreak(true, 32);

        $pdf->AddPage();
        $pdf->setY(PDF_MARGIN_HEADER + 60);

        $pdf->SetFont('helvetica', null, 10);

        $billingPeriod = sprintf(
            '%02d/%04d',
            $invoice->getBillingMonth(),
            $invoice->getBillingYear(),
        );

        $reportCount = $invoice->getReports()->count();

        $totalKm = 0.0;

        foreach ($invoice->getReports() as $report) {
            $totalKm += (float) ($report->getKm() ?? 0);
        }

        $calculationDetails = [];

        foreach ($pricingDetails->lines as $line) {
            $calculationDetails[] = sprintf(
                '%s : %d utilisateur(s) actif(s) x %s € = %s € HT',
                htmlspecialchars($line->periodLabel, ENT_QUOTES, 'UTF-8'),
                $line->activeUserCount,
                number_format($line->unitPriceMonthlyHt, 2, ',', ' '),
                number_format($line->totalHt, 2, ',', ' '),
            );
        }

        $calculationDetailsText = implode(' ; ', $calculationDetails);

        $html = '<style>'
            . '.title {font-size: 10pt; color: #ffffff; background-color: #6174d1; font-weight: bold; text-align: center}'
            . '.label {font-size: 10pt; color: #ffffff; background-color: #6174d1; font-weight: bold; text-align: left}'
            . '.line {font-size: 9pt; border-bottom: 1px solid #ccc; color: #222}'
            . '.right {text-align: right}'
            . '.center {text-align: center}'
            . '.box {border: 1px solid #ddd; padding: 8px;}'
            . '.muted {color: #666; font-size: 9pt;}'
            . '.total-label {font-size: 10pt; font-weight: bold; text-align: right}'
            . '.total-value {font-size: 10pt; font-weight: bold; text-align: right}'
            . '.grand-total {font-size: 12pt; font-weight: bold; text-align: right; background-color: #f1f3ff;}'
            . '</style>';

        $html .= '<p><strong>Mois de facturation :</strong> '.$billingPeriod.'</p>';

        $html .= '<table border="0" cellspacing="0" cellpadding="6">';
        $html .= '<tr>';
        $html .= '<td class="box" width="33%"><span class="muted">Utilisateur-mois facturés</span><br><strong>'.$pricingDetails->totalActiveUserMonths.'</strong></td>';
        $html .= '<td class="box" width="33%"><span class="muted">Rapports IK saisis</span><br><strong>'.$reportCount.'</strong></td>';
        $html .= '<td class="box" width="34%"><span class="muted">Kilomètres déclarés</span><br><strong>'.number_format($totalKm, 0, ',', ' ').' km</strong></td>';
        $html .= '</tr>';
        $html .= '</table>';

        $html .= '<br>';

        $html .= '<table border="0" cellspacing="1" cellpadding="5">';
        $html .= '<thead><tr>';
        $html .= '<th class="label" width="45%">Libellé</th>';
        $html .= '<th class="title" width="20%">Base de calcul</th>';
        $html .= '<th class="title" width="15%">Quantité</th>';
        $html .= '<th class="title" width="20%">Montant HT</th>';
        $html .= '</tr></thead>';

        $html .= '<tbody>';
        $html .= '<tr>';
        $html .= '<td class="line" width="45%">Abonnement Team - utilisateurs actifs IK<br><span class="muted">Facturation globale des IK saisies sur '.$billingPeriod.'</span></td>';
        $html .= '<td class="line center" width="20%">Calcul mois par mois</td>';
        $html .= '<td class="line center" width="15%">'.$pricingDetails->totalActiveUserMonths.'</td>';
        $html .= '<td class="line right" width="20%">'.number_format($pricingDetails->totalHt, 2, ',', ' ').' €</td>';
        $html .= '</tr>';
        $html .= '</tbody>';
        $html .= '</table>';

        if ($calculationDetailsText !== '') {
            $html .= '<br>';
            $html .= '<p class="muted">';
            $html .= '<strong>Détail du calcul :</strong> ';
            $html .= $calculationDetailsText;
            $html .= '.';
            $html .= '</p>';
        }

        $html .= '<br>';

        $html .= '<table border="0" cellspacing="1" cellpadding="5">';
        $html .= '<tr>';
        $html .= '<td width="65%"></td>';
        $html .= '<td width="20%" class="total-label">Total HT</td>';
        $html .= '<td width="15%" class="total-value">'.number_format($pricingDetails->totalHt, 2, ',', ' ').' €</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td width="65%"></td>';
        $html .= '<td width="20%" class="total-label">TVA 20%</td>';
        $html .= '<td width="15%" class="total-value">'.number_format($pricingDetails->vatAmount, 2, ',', ' ').' €</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td width="65%"></td>';
        $html .= '<td width="20%" class="grand-total">Net à payer</td>';
        $html .= '<td width="15%" class="grand-total">'.number_format($pricingDetails->totalTtc, 2, ',', ' ').' €</td>';
        $html .= '</tr>';
        $html .= '</table>';

        $html .= '<br><br>';

        $html .= '<p class="muted">';
        $html .= 'Tarification Team : utilisateurs actifs avec IK saisies, calculés mois par mois. ';
        $html .= '3 à 9 utilisateurs actifs : 6,00 € HT/mois/utilisateur ; ';
        $html .= '10 à 24 utilisateurs actifs : 5,00 € HT/mois/utilisateur ; ';
        $html .= '25 utilisateurs actifs et plus : sur devis.';
        $html .= '</p>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'Mileo_Facture_'.$invoice->getNum().'.pdf';

        return $pdf->Output($filename, 'S');
    }
}