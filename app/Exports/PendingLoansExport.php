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

class PendingLoansExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithColumnFormatting, WithEvents
{
    public function collection()
    {
        return Loan::with(['member'])
            ->where('status', Loan::STATUS_PENDING)
            ->orderBy('application_date', 'desc')
            ->get();
    }

    public function title(): string
    {
        return 'Pinjaman Menunggu';
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
            'JUMLAH PINJAMAN',
            'DURASI (BULAN)',
            'CICILAN/BULAN',
            'BUNGA (%)',
            'TOTAL BUNGA',
            'BIAYA ADMIN',
            'DITERIMA',
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
            $loan->application_date ? \Carbon\Carbon::parse($loan->application_date)->format('d/m/Y') : '-',
            $loan->amount,
            $loan->duration,
            $loan->monthly_installment,
            $loan->interest_rate,
            $loan->total_interest,
            $loan->admin_fee,
            $loan->disbursed_amount,
            'Menunggu Persetujuan',
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
            'G' => 14,  // JUMLAH PINJAMAN
            'H' => 8,   // DURASI
            'I' => 12,  // CICILAN
            'J' => 7,   // BUNGA
            'K' => 12,  // TOTAL BUNGA
            'L' => 12,  // BIAYA ADMIN
            'M' => 14,  // DITERIMA
            'N' => 14,  // STATUS
        ];
    }

    public function columnFormats(): array
    {
        return [
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'K' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'L' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'M' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
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
                    'startColor' => ['rgb' => 'E67E22'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
