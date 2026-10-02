<?php
namespace App\Service;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\Translation\TranslatorInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class XlsxExporter
{
    public function __construct()
    {
    }

    public function export(array $data, string $filename = 'export.xlsx', string $type = 'Xlsx'): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = array_keys($data[0]);
        $sheet->fromArray($headers, null, 'A1');

        // Figer la première ligne
        $sheet->freezePane('A2');

        // Style d'en-tête : gras + centré
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        // Data
        $rowNum = 2;
        foreach ($data as $item) {
            $colIndex = 1;
            foreach ($item as $value) {
                $col = Coordinate::stringFromColumnIndex($colIndex);
                if(is_numeric($value)){
                    $sheet->setCellValueExplicit("{$col}{$rowNum}", $value, DataType::TYPE_NUMERIC);
                }else{
                    $sheet->getStyle("{$col}{$rowNum}")->getAlignment()->setWrapText(true);
                    $sheet->setCellValue("{$col}{$rowNum}", $value);
                }
                
                $colIndex++;
            }
            $rowNum++;
        }

        // Bordures
        $finalRow = $rowNum - 1;
        $fullRange = "A1:{$lastCol}{$finalRow}";
        $sheet->getStyle($fullRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // largeur des cellules
        foreach (range(1, count($headers)) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        // Générer le fichier
        if($type == 'csv'){
            $writer = new Csv($spreadsheet);
            $writer->setUseBOM(true);
            $writer->setDelimiter(';');
            $writer->setEnclosure('"');
            $writer->setLineEnding("\r\n");
            $mimeType = 'text/csv;charset=UTF-8';
        }else{
            $writer = new Xlsx($spreadsheet); 
            $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }
        $response = new StreamedResponse(function () use ($writer, $spreadsheet) {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($writer);
        });

        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Disposition', (new ResponseHeaderBag())->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        ));

        return $response;
    }
    
}
