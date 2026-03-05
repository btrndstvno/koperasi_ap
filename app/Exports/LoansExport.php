<?php

namespace App\Exports;

use App\Models\Loan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class LoansExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithColumnFormatting, WithEvents
{
    protected ?string $status;

    public function __construct(?string $status = null)
    {
        $this->status = $status;
    }

    public function collection()
    {
        $query = Loan::with(['member'])
            ->orderBy('application_date', 'desc');

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->get();
    }

    public function title(): string
    {
        return 'Data Pinjaman ' . ($this->status ? ucfirst($this->status) : 'Semua');
    }

    public function headings(): array
    {
        return [
            'NO',
            'NIK',
            'NAMA',
            'DEPT',
            'GROUP',
            'TGL PENGAJUAN',
            'TGL DISETUJUI',
            'JUMLAH PINJAMAN',
            'DURASI (BULAN)',
            'CICILAN/BULAN',
            'BUNGA (%)',
            'TOTAL BUNGA',
            'BIAYA ADMIN',
            'DITERIMA', 
            'SISA POKOK', 
            'STATUS',
        ];
    }

    protected int $rowNumber = 0;

    public function map($loan): array
    {
        $this->rowNumber++;
        
        return [
            $this->rowNumber,
            $loan->member->nik ?? '-',
            $loan->member->name ?? '-',
            $loan->member->dept ?? '-',
            $loan->member->group_tag ?? '-',
            $loan->application_date ? $loan->application_date->format('d/m/Y') : '-',
            $loan->approved_date ? $loan->approved_date->format('d/m/Y') : '-',
            $loan->amount,
            $loan->duration,
            $loan->monthly_installment,
            $loan->interest_rate,
            $loan->total_interest,
            $loan->admin_fee,
            $loan->disbursed_amount,
            $loan->remaining_principal,
            $loan->status_label,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 4,   // NO
            'B' => 10,  // NIK
            'C' => 18,  // NAMA
            'D' => 10,  // DEPT
            'E' => 8,   // GROUP
            'F' => 11,  // TGL PENGAJUAN
            'G' => 11,  // TGL DISETUJUI
            'H' => 14,  // JUMLAH PINJAMAN
            'I' => 8,   // DURASI
            'J' => 12,  // CICILAN
            'K' => 7,   // BUNGA
            'L' => 12,  // TOTAL BUNGA
            'M' => 12,  // BIAYA ADMIN
            'N' => 14,  // DITERIMA
            'O' => 14,  // SISA POKOK
            'P' => 14,  // STATUS
        ];
    }

    public function columnFormats(): array
    {
        return [
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'L' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'M' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'N' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'O' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
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
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(12);

        return [
            // Header row style
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 8,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0D6EFD'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
