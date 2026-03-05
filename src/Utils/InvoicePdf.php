<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Utils;
use \TCPDF;

class InvoicePdf extends TCPDF
{

    private $entity;
    private $projectDir;

    private function setEntity($entity)
    {
        $this->entity = $entity;
    }

    private function getEntity()
    {
        return $this->entity;
    }

    public function getProjectDir()
    {
        return $this->projectDir;
    }

    public function setProjectDir($projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function Header() 
    {
        if($this->getEntity()){
            $order = $this->getEntity();
            
            // Title
            // Set font
            $this->setY(PDF_MARGIN_HEADER+5);
            $this->SetFont('helvetica', 'B', 18);
            $this->Cell(0, 1,'', 'B', true, 'C', 0, '', 0, false, 'M', 'M');
            $this->Image('../assets/img/logo.png', 10, 2, '', 10, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);         
        
            $this->setY(PDF_MARGIN_HEADER+15);
            $this->SetFont('helvetica', null, 11);
            $table_header = '<table border="0" cellspacing="1" cellpadding="1">';
            $table_header .= '<tr><td width="60%"></td><td>'.$order->getBillingName().'</td></tr>';
            $table_header .= '<tr><td width="60%"></td><td>'.$order->getBillingAddress().'</td></tr>';
            $table_header .= '<tr><td width="60%"></td><td>'.$order->getBillingPostCode().' '.$order->getBillingCity().'</td></tr><tr><td></td></tr>';
            $table_header .= '<tr><td width="60%">Date : '.$order->getCreatedAt()->format('d/m/Y').'</td><td></td></tr>';
            $table_header .= '<tr><td width="60%">Facture N° '.$order->getInvoice()->getNum().'</td><td></td></tr>';
            $table_header .= '</table>';
            $this->writeHTML($table_header, true, false, true, false, '');
            $this->setY($this->getY()-2);
            $this->setY(PDF_MARGIN_HEADER+34);
            //$this->Cell(170, 4,'Synthèse globale: '.$visit->calculateResult().'%', 0, true, 'R', 0, '', 0, false, 'M', 'M');
            //$this->writeHTMLCell(0, '', '', $this->getY(), '<i>Validée le '.$report->getValidatedAt()->format('d/m/Y').' par '.$report->getValidator().'</i>', $border=0, $ln=1, $fill=0, $reseth=true, $align='L', $autopadding=true);
            $this->setY(PDF_MARGIN_HEADER+55);
            $this->Cell(0, 1,'', 'B', true, 'C', 0, '', 0, false, 'M', 'M');
            
        }
        
        // Logo
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-25);
        // Set font
        $this->SetFont('helvetica', null, 8);
        // Page number
        $this->Cell(0, 5,'Copyright Mileo '.date('Y'), 'T', false, 'L', 0, '', 0, false, 'T', 'M');
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 5,'Page '.$this->getAliasNumPage()."/".$this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
        $this->SetFont('helvetica', null, 8);
        $this->SetY(-18);
        $footer = '<table width="100%" border="0" cellspacing="1" cellpadding="1">';
        $footer .= '<tr><td width="100%" style="text-align:center">Anybug/Mileo – 8 Rue Beaulieu - 17430 Cabariot</td></tr>';
        $footer .= '<tr><td width="100%" style="text-align:center">Tel : 0546894344 - Email : ' . $_ENV['CONTACT_EMAIL'] . '</td></tr>';
        $footer .= '<tr><td width="100%" style="text-align:center">RCS La Rochelle 517 653 531 - TVA FR14517653531</td></tr>';
        $footer .= '</table>';
        $this->writeHTML($footer, true, false, true, false,'');
    }

    public function generatePdf($entity)
    {
        $this->setEntity($entity);
        //$title = $this->generatePdfTitle();
        $pdf = $this;
        //$this->setTitle($title);

        $pdf->SetAuthor('Mileo');
        $pdf->SetTitle('Fiche');
        $pdf->SetSubject('Kilometrique');  

        $pdf->AddPage();  
        $pdf->setY(PDF_MARGIN_HEADER+60);
        
        $pdf->SetFont('helvetica', null, 10);
        $table_header = '<style>'
                . '.title {font-size: 10pt; color: #ffffff; background-color: #6174d1; font-weight: bold; text-align: center}'
                . '.libelé {font-size: 10pt; color: #ffffff; background-color: #6174d1; font-weight: bold; text-align: left}'
                . '.title_footer {font-size: 10pt; font-weight: bold; text-align: right}'
                . '.content_footer {font-size: 10pt; font-weight: bold; text-align: center}'
                . '.line {font-size: 9pt; border-bottom: 1px solid #ccc; color: #222 }'
                . '</style>';
        
        $table_header .= '<table border="0" cellspacing="1" cellpadding="1">';
        $table_header .= '<thead><tr>';
        $table_header .= '<th class="libelé" width="75%">Libellé</th>';
        $table_header .= '<th class="title" width="25%">Montant HT</th>';
        $table_header .= '</tr></thead>';
        
        $table_body = '<tbody>';

        $table_body .= '<tr>';
        $table_body .= '<td class="line" width="75%"><p>'.$entity->getPlan()."<br/> Valide jusqu'au ".$entity->getSubscriptionEnd()->format("d/m/Y").'</p></td>';
        $table_body .= '<td class="line" width="25%" align="center">'.number_format($entity->getTotalHt(), 2)." €</td>";
        $table_body .= '</tr>';
        
        $table_body .= '</tbody>';
        
        $table_footer = '<tfoot>';
        $table_footer .= '<tr><td colspan="1" class="title_footer">Total HT</td><td colspan="2" class="content_footer">'.number_format($entity->getTotalHt(),2) .' €</td></tr>';
        $table_footer .= '<tr><td colspan="1" class="title_footer">Montant TVA 20%</td><td colspan="2" class="content_footer">'.number_format($entity->getVatAmount(),2) .' €</td></tr>';
        $table_footer .= '<tr><td colspan="1" class="title_footer">Total TTC net à payer</td><td colspan="2" class="content_footer">'.number_format($entity->getTotalTTC(),2).' €</td></tr>';
        $table_footer .= '</tfoot></table>';
        
        $pdf->writeHTML($table_header.$table_body.$table_footer, true, false, true, false, '');
                
        $filename = 'Mileo_Facture_'.$entity->getInvoice()->getNum().'.pdf';

        $pdfContent = $pdf->Output($filename, 'S');
 
        return $pdfContent;

    }

}


