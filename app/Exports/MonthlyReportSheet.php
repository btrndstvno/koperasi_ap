<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MonthlyReportSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithColumnFormatting, WithEvents
{
    protected $tag;
    protected $data;
    protected $month;
    protected $year;

    public function __construct($tag, $data, $month, $year)
    {
        $this->tag = $tag;
        $this->data = $data;
        $this->month = $month;
        $this->year = $year;
    }

    public function collection()
    {
        $rows = collect();

        // Data rows
        foreach ($this->data->members as $index => $member) {
            $rows->push([ 
                $index + 1,
                $member->nik,
                $member->name,
                $member->dept,
                $member->pot_kop,
                $member->iur_kop,
                $member->iur_tunai,
                $member->total,
                number_format($member->sisa_pinjaman, 0, ',', '.') . "\n" . ($member->sisa_tenor > 0 ? "({$member->sisa_tenor}x)" : ''),
                $member->saldo_kop,
                $member->member_status ?? '',
                ''
            ]);
        }

        // Subtotal row
        $rows->push([
            '', // No
            '', // NIK
            'SUBTOTAL ' . strtoupper($this->tag), // Nama
            '', // Dept
            $this->data->subtotal_pot,
            $this->data->subtotal_iur,
            $this->data->subtotal_iur_tunai,
            $this->data->subtotal_total,
            $this->data->subtotal_sisa_pinjaman,
            $this->data->subtotal_saldo_kop,
            ''
        ]);

        return $rows;
    }

    public function headings(): array
    {
        return [
            'NO',
            'NIK',
            'NAMA',
            'DEPT',
            'POT KOP',
            'IUR KOP',
            'IUR TUNAI',
            'JUMLAH',
            'SISA PINJAMAN',
            'SALDO KOP',
            'STATUS',
            'KET'
        ];
    }

    public function title(): string
    {
        return substr($this->tag, 0, 30); // Excel sheet name max 31 chars
    }

    public function columnWidths(): array
    {
        return [
            'A' => 4,   // NO
            'B' => 10,  // NIK
            'C' => 22,  // NAMA
            'D' => 10,  // DEPT
            'E' => 12,  // POT KOP
            'F' => 12,  // IUR KOP
            'G' => 12,  // IUR TUNAI
            'H' => 12,  // JUMLAH
            'I' => 14,  // SISA PINJAMAN
            'J' => 14,  // SALDO KOP
            'K' => 8,   // STATUS
            'L' => 8,   // KET
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
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
            },
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->data->members) + 2; // +1 heading +1 subtotal

        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(12);

        $styles = [
            1 => [
                'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            // Enable wrap text for Name and Sisa Pinjaman
            'C' => ['alignment' => ['wrapText' => true]],
            'I' => ['alignment' => ['wrapText' => true]],
        ];
        
        // Subtotal row style
        $styles[$lastRow] = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEEEEE']],
            'borders' => ['top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ];

        return $styles;
    }
}
