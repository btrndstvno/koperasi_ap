<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MonthlyReportExport implements WithEvents, WithColumnWidths
{
    use Exportable;

    protected $groupedData;
    protected $grandTotal;
    protected $month;
    protected $year;

    public function __construct($groupedData, $grandTotal, $month, $year)
    {
        $this->groupedData = $groupedData;
        $this->grandTotal = $grandTotal;
        $this->month = $month;
        $this->year = $year;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 4,   // NO
            'B' => 10,  // NIK
            'C' => 22,  // NAMA
            'D' => 10,  // DEPT
            'E' => 16,  // POT KOP
            'F' => 16,  // IUR KOP
            'G' => 14,  // IUR TUNAI
            'H' => 18,  // JUMLAH
            'I' => 18,  // SISA PINJAMAN
            'J' => 18,  // SALDO KOP
            'K' => 10,  // STATUS
            'L' => 16,  // KET
        ];
    }

    private function getHeadings(): array
    {
        return ['NO', 'NIK', 'NAMA', 'DEPT', 'POT KOP', 'IUR KOP', 'IUR TUNAI', 'JUMLAH', 'SISA PINJAMAN', 'SALDO KOP', 'STATUS', 'KET'];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getPageMargins()->setLeft(0.2);
                $sheet->getPageMargins()->setRight(0.2);
                $sheet->getPageMargins()->setTop(0.3);
                $sheet->getPageMargins()->setBottom(0.3);
                $sheet->getDefaultRowDimension()->setRowHeight(14);

                // Wrap text for NAMA and SISA PINJAMAN columns
                $sheet->getStyle('C:C')->getAlignment()->setWrapText(true);
                $sheet->getStyle('I:I')->getAlignment()->setWrapText(true);

                $currentRow = 1;
                $headingRows = [];
                $subtotalRows = [];
                $groupHeaderRows = [];

                $monthName = \Carbon\Carbon::create($this->year, $this->month)->translatedFormat('F Y');

                // Title row
                $sheet->setCellValue('A' . $currentRow, 'LAPORAN KOPERASI BULAN ' . strtoupper($monthName));
                $sheet->mergeCells('A' . $currentRow . ':L' . $currentRow);
                $sheet->getStyle('A' . $currentRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $currentRow += 2;

                foreach ($this->groupedData as $tag => $data) {
                    // Group header row
                    $groupHeaderRows[] = $currentRow;
                    $sheet->setCellValue('A' . $currentRow, strtoupper($tag));
                    $sheet->mergeCells('A' . $currentRow . ':L' . $currentRow);
                    $sheet->getStyle('A' . $currentRow)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A5276']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    ]);
                    $currentRow++;

                    // Column headings
                    $headingRows[] = $currentRow;
                    $headings = $this->getHeadings();
                    foreach ($headings as $colIndex => $heading) {
                        $colLetter = chr(65 + $colIndex); // A, B, C...
                        $sheet->setCellValue($colLetter . $currentRow, $heading);
                    }
                    $sheet->getStyle('A' . $currentRow . ':L' . $currentRow)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $currentRow++;

                    // Data rows
                    $rowNum = 1;
                    foreach ($data->members as $member) {
                        $sheet->setCellValue('A' . $currentRow, $rowNum++);
                        $sheet->setCellValue('B' . $currentRow, $member->nik);
                        $sheet->setCellValue('C' . $currentRow, $member->name);
                        $sheet->setCellValue('D' . $currentRow, $member->dept);
                        $sheet->setCellValue('E' . $currentRow, $member->pot_kop);
                        $sheet->setCellValue('F' . $currentRow, $member->iur_kop);
                        $sheet->setCellValue('G' . $currentRow, $member->iur_tunai);
                        $sheet->setCellValue('H' . $currentRow, $member->total);
                        $sisaPinjamanText = number_format($member->sisa_pinjaman, 0, ',', '.') .
                            ($member->sisa_tenor > 0 ? "\n({$member->sisa_tenor}x)" : '');
                        $sheet->setCellValue('I' . $currentRow, $sisaPinjamanText);
                        $sheet->setCellValue('J' . $currentRow, $member->saldo_kop);
                        $sheet->setCellValue('K' . $currentRow, $member->member_status ?? '');
                        $sheet->setCellValue('L' . $currentRow, $member->notes ?? '');
                        $currentRow++;
                    }

                    // Format number columns for data rows in this group
                    $dataStartRow = $currentRow - count($data->members);
                    $dataEndRow = $currentRow - 1;
                    if (count($data->members) > 0) {
                        foreach (['E', 'F', 'G', 'H', 'J'] as $col) {
                            $sheet->getStyle($col . $dataStartRow . ':' . $col . $dataEndRow)
                                ->getNumberFormat()
                                ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                        }
                    }

                    // Subtotal row
                    $subtotalRows[] = $currentRow;
                    $sheet->setCellValue('C' . $currentRow, 'SUBTOTAL ' . strtoupper($tag));
                    $sheet->setCellValue('E' . $currentRow, $data->subtotal_pot);
                    $sheet->setCellValue('F' . $currentRow, $data->subtotal_iur);
                    $sheet->setCellValue('G' . $currentRow, $data->subtotal_iur_tunai);
                    $sheet->setCellValue('H' . $currentRow, $data->subtotal_total);
                    $sheet->setCellValue('I' . $currentRow, $data->subtotal_sisa_pinjaman);
                    $sheet->setCellValue('J' . $currentRow, $data->subtotal_saldo_kop);

                    $sheet->getStyle('A' . $currentRow . ':L' . $currentRow)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEEEEE']],
                        'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                    foreach (['E', 'F', 'G', 'H', 'I', 'J'] as $col) {
                        $sheet->getStyle($col . $currentRow)
                            ->getNumberFormat()
                            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                    }

                    $currentRow += 2; // Blank row separator between groups
                }

                // Grand Total row
                $sheet->setCellValue('C' . $currentRow, 'GRAND TOTAL');
                $sheet->setCellValue('E' . $currentRow, $this->grandTotal->pot_kop);
                $sheet->setCellValue('F' . $currentRow, $this->grandTotal->iur_kop);
                $sheet->setCellValue('G' . $currentRow, $this->grandTotal->iur_tunai);
                $sheet->setCellValue('H' . $currentRow, $this->grandTotal->total);
                $sheet->setCellValue('I' . $currentRow, $this->grandTotal->sisa_pinjaman);
                $sheet->setCellValue('J' . $currentRow, $this->grandTotal->saldo_kop);

                $sheet->getStyle('A' . $currentRow . ':L' . $currentRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A5276']],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_MEDIUM],
                        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM],
                    ],
                ]);
                foreach (['E', 'F', 'G', 'H', 'I', 'J'] as $col) {
                    $sheet->getStyle($col . $currentRow)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                }
            },
        ];
    }
}
