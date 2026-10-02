<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Utils;
use \TCPDF;
use \IntlDateFormatter;
use \DateTime;
use \DateTimeZone;

class ReportPdf extends TCPDF
{
    private $period;
    private $reports;
    private $vehiculesTotals;
    private $type;

    private function setReports($reports)
    {
        $this->reports = $reports;
    }

    private function getReports()
    {
        return $this->reports;
    }

    public function generateFilename()
    {
        $reports = $this->getReports();
        $entity = $reports[0];

        // Cas PDF annuel
        if ($this->type === 'year') {

            // Cas 1 : période = ["2025"]
            if (count($this->period) === 1) {
                $year = $this->period[0];
                return sprintf(
                    'Fiche_kilometrique_%s_annee_%s.pdf',
                    $entity->getUser(),
                    $year
                );
            }

            // Cas 2 : période = ["Jan 2025", "Dec 2025"]
            if (count($this->period) === 2) {

                // On convertit "Jan 2025" → DateTime
                $from = \DateTime::createFromFormat('M Y', $this->period[0]);
                $to   = \DateTime::createFromFormat('M Y', $this->period[1]);

                if (!$from || !$to) {
                    throw new \Exception("Format de période annuel invalide : ".json_encode($this->period));
                }

                $fromMonth = $this->translateMonth($from->format('F'));  // janvier
                $toMonth   = $this->translateMonth($to->format('F'));    // décembre
                $year      = $to->format('Y');

                return sprintf(
                    'Fiche_kilometrique_%s_%s_%s_%s.pdf',
                    $entity->getUser(),
                    $fromMonth,
                    $toMonth,
                    $year
                );
            }

            throw new \Exception("Période annuelle non supportée : ".json_encode($this->period));
        }

        // Cas PDF mensuel
        // période = ["January", "2025"]
        $from = $this->translateMonth($this->period[0]);
        $to = $this->period[1];

        return sprintf(
            'Fiche_kilometrique_%s_%s_%s.pdf',
            $entity->getUser(),
            $from,
            $to
        );
    }


    public function translateMonth(string $month): string
    {
        $date = DateTime::createFromFormat('F', $month, new DateTimeZone('UTC'));

        if (!$date) {
            throw new \InvalidArgumentException("Mois invalide : $month");
        }

        $formatter = new IntlDateFormatter(
            'fr_FR',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'UTC',
            null,
            'MMMM'
        );

        return $formatter->format($date);
    }

    public function Header() 
    {
        // Title
        // Set font
        $this->Image('assets/img/logo.png', 10, 7, '', 10, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $this->setY(PDF_MARGIN_HEADER+6);
        $this->SetFont('helvetica', 'B', 18);
        $this->Cell($w=0, 0, $txt="Rapport d'indemnités kilométriques", $border=false, $ln=true, $align='C', $fill=0, $link='', $stretch=0, $ignore_min_height=false, $caling='M', $valign='B');
        $this->Cell(0, 1,'', 'B', true, 'C', 0, '', 0, false, 'M', 'M');
        
        if($this->getReports()){
            $reports = $this->getReports();

            $this->setY(PDF_MARGIN_HEADER+17);
            $this->SetFont('helvetica', null, 11);
            
            $table_header = '<table border="0" cellspacing="1" cellpadding="1">';
            $table_header .= '<tr><td width="90" bgcolor="#6174d1" color="#ffffff"><strong>Nom</strong></td><td>'.$reports[0]->getUser().'</td></tr>';

            if ($this->type === 'year') {

                // Cas 1 : période = ["2025"]
                if (count($this->period) === 1) {
                    $year = $this->period[0];
                    $firstDay = new \DateTime("first day of January $year");
                    $lastDay  = new \DateTime("last day of December $year");
                }

                // Cas 2 : période = ["Jan 2025", "Dec 2025"]
                elseif (count($this->period) === 2) {

                    // Convert "Jan 2025" → "January 2025"
                    $from = \DateTime::createFromFormat('M Y', $this->period[0]);
                    $to   = \DateTime::createFromFormat('M Y', $this->period[1]);

                    if (!$from || !$to) {
                        throw new \Exception("Période invalide : ".json_encode($this->period));
                    }

                    $firstDay = new \DateTime("first day of ".$from->format('F Y'));
                    $lastDay  = new \DateTime("last day of ".$to->format('F Y'));
                }

                else {
                    throw new \Exception("Format de période annuel non supporté : ".json_encode($this->period));
                }
            }

            else if ($this->type === 'month') {
                // période = ["January", "2025"]
                $firstDay = new \DateTime("first day of ".$this->period[0].' '.$this->period[1]);
                $lastDay  = new \DateTime("last day of ".$this->period[0].' '.$this->period[1]);
            }

            $table_header .= '<tr><td bgcolor="#6174d1" color="#ffffff"><strong>Période</strong></td><td>du '.$firstDay->format('d/m/Y').' au '.$lastDay->format('d/m/Y').'</td></tr>';
            $table_header .= '</table>';

            $this->writeHTML($table_header, true, false, true, false, '');
            $this->setY(PDF_MARGIN_HEADER+29);
            //Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='', $stretch=0, $ignore_min_height=false, $calign='T', $valign='M')
            $this->Cell(0, 1, '', 'B', 0, 'C', 0, '', 0, false, 'M', 'M');
            //$this->SetY(44);
        }
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', null, 8);
        // Page number
        $this->Cell(0, 5,'Copyright Mileo - Rapport généré le '.date('d/m/Y à H:i'), 'T', false, 'L', 0, '', 0, false, 'T', 'M');
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 5,'Page '.$this->getAliasNumPage()."/".$this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }

    public function generatePdf($reports,$period,$type)
    {
        $linesPerVehicule = [];
        $key=0;
        foreach ($reports as $report) {
           if($report->getLines() && count($report->getLines()) > 0){
                foreach ($report->getLines() as $line) {
                    $linesPerVehicule[$line->getVehicule()->__toString()][$key] = $line;
                    $key++;
                }
           } 
           
        }

        $totals = ['km' => 0, 'amount' => 0];
        $vehiculesTotals = [];
        foreach ($reports as $report) 
        {
            $totals['km'] += $report->getVehiculesReportsTotalKm();
            $totals['amount'] += $report->getVehiculesReportsTotalAmount();

            foreach ($report->getVehiculesReports() as $vr) 
            {
                $vid = $vr->getVehicule()->getId();
                if(isset($vehiculesTotals[$vid])){
                    $vehiculesTotals[$vid]['km'] += $vr->getKm();
                    $vehiculesTotals[$vid]['amount'] += $vr->getTotal();
                }else{
                    $vehiculesTotals[$vid]['Vehicule'] = $vr->getVehicule();
                    $vehiculesTotals[$vid]['Scale'] = $vr->getScale();
                    $vehiculesTotals[$vid]['Vr'] = $vr;
                    $vehiculesTotals[$vid]['km'] = $vr->getKm();
                    $vehiculesTotals[$vid]['amount'] = $vr->getTotal();
                }
            }
        }

        $this->type = $type;
        $this->vehiculesTotals = $vehiculesTotals;
        $this->setReports($reports);
        $this->period = $period;
        
        $pdf = $this;
        //$this->setTitle($title);

        $pdf->SetAuthor('Mileo');
        $pdf->SetTitle('Fiche');
        $pdf->SetSubject('Kilometrique');  
        $pdf->SetMargins(PDF_MARGIN_LEFT-5, PDF_MARGIN_TOP+16, PDF_MARGIN_RIGHT-5);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        $pdf->AddPage();

        $pdf->SetFont('helvetica', null, 10);
        
        /** Détails lignes */

        $table_style = '<style>' 
                . '.title {font-size: 10pt; color: #ffffff; background-color: #6174d1; font-weight: bold; text-align: center}'
                . '.subtitle {font-size: 10pt; color: #6174d1; border-bottom-color: #6174d1; font-weight: bold;}'
                . '.title_footer {font-size: 11pt; color: #ffffff; background-color: #6174d1; font-weight: bold; text-align: right}'
                . '.subtitle_footer {font-size: 10pt; color: #6174d1; border-bottom-color: #6174d1; font-weight: bold; text-align: right}'
                . '.line {font-size: 9pt; border-bottom: 1px solid #ccc; color: #222;font-weight: normal; }'
                . '</style>';

        $table_header = '<table border="0" cellspacing="1" cellpadding="1">';
        $table_header .= '<thead><tr>';
        $table_header .= '<th class="title" width="10%">Date</th>';
        $table_header .= '<th class="title" width="23%">Départ</th>';
        $table_header .= '<th class="title" width="23%">Arrivée</th>';
        $table_header .= '<th class="title" width="23%">Motif</th>';
        $table_header .= '<th class="title" width="9%">Distance</th>';
        $table_header .= '<th class="title" width="4%">A/R</th>';
        $table_header .= '<th class="title" width="8%">Total</th>';
        $table_header .= '</tr></thead>';
        
        $table_body = '<tbody>';
        foreach ($linesPerVehicule as $key=>$vehicule) {
            $table_body .= '<tr><td colspan="7" height="6"></td></tr>';
            $table_body .= '<tr><td colspan="7" class="subtitle">';
            $table_body .= $key.' ('.$vehicule[array_key_first($vehicule)]->getVehicule()->getPower().')';
            $table_body .= $vehicule[array_key_first($vehicule)]->getVehicule()->getKilometres() ? ' - '.$vehicule[array_key_first($vehicule)]->getVehicule()->getKilometres().'km' : '';
            $table_body .= '</td></tr>';
            foreach($vehicule as $line){
                $is_return = $line->getIsReturn() ? '<img height="12" src="assets/img/icons/validated_pdf.png">' : '<img height="12" src="assets/img/icons/unvalidated_pdf.png">';
                $table_body .= '<tr>';
                $table_body .= '<td class="line" width="10%" align="center">'.$line->getTravelDate()->format('d/m/Y').'</td>';
                $table_body .= '<td class="line" width="23%">'.$line->getStartAdress().'</td>';
                $table_body .= '<td class="line" width="23%">'.$line->getEndAdress().'</td>';
                $table_body .= '<td class="line" width="23%">'.nl2br(htmlspecialchars($line->getComment())).'</td>';
                $table_body .= '<td class="line" width="9%" align="center">'.$line->getKm().' km</td>';
                $table_body .= '<td class="line" width="4%" align="center">'.$is_return.'</td>';
                $table_body .= '<td class="line" width="8%" align="center">'.$line->getKmTotal().' km</td>';
                $table_body .= '</tr>';
            }
        }
        $table_body .= '</tbody>';
        $table_body .= '</table>';

        /**Totaux */
        $table_footer = '<table border="0" cellspacing="1" cellpadding="3">';
        $table_footer .= '<tfoot>';
        $table_footer .= '<tr>';
        $table_footer .= '<td width="72%" class="title_footer" align="left">Distance totale parcourue</td>';
        $table_footer .= '<td width="28%" class="title_footer">'.$totals['km'].' km</td>';
        $table_footer .= '</tr>';
        $table_footer .= '<tr>';
        $table_footer .= '<td width="72%" class="title_footer" align="left">Montant indemnités kilométriques</td>';
        $table_footer .= '<td width="28%" class="title_footer">'.number_format($totals['amount'],2, ',', ' ').' €</td>';
        $table_footer .= '</tr>';

        foreach ($this->vehiculesTotals as $vehicule) {
            $vscale = $vehicule['Vehicule']->getPower().': '.$vehicule['Vehicule']->getScale();
            $vscale .= $vehicule['Vehicule']->isElectric() ? ' +20%': '';
            $table_footer .= '<tr>';
            $table_footer .= '<td width="72%" class="subtitle_footer" align="left">'.$vehicule['Vehicule'].' <i><span class="line">'.$vscale.'</i></span></td>';
            $table_footer .= '<td width="14%" class="subtitle_footer">'.$vehicule['km'].' km</td>';
            $table_footer .= '<td width="14%" class="subtitle_footer">'.number_format($vehicule['amount'], 2, ',', ' ').' €</td>';
            $table_footer .= '</tr>';
        }
        
        $table_footer .= '</tfoot>';
        $table_footer .= '</table>';
                
        $pdf->writeHTML($table_style.$table_header.$table_body.$table_footer, true, false, true, false, '');

        $pdfContent = $pdf->Output($this->generateFilename(), 'S');
 
        return $pdfContent;

    }

}


