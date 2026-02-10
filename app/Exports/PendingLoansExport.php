<?php

namespace App\Exports;

use App\Models\Loan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class PendingLoansExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithColumnFormatting
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
            'A' => 5,   // NO
            'B' => 12,  // NIK
            'C' => 25,  // NAMA
            'D' => 12,  // DEPT
            'E' => 10,  // GROUP
            'F' => 15,  // TGL PENGAJUAN
            'G' => 18,  // JUMLAH PINJAMAN
            'H' => 15,  // DURASI
            'I' => 15,  // CICILAN
            'J' => 10,  // BUNGA
            'K' => 15,  // TOTAL BUNGA
            'L' => 15,  // BIAYA ADMIN
            'M' => 18,  // DITERIMA
            'N' => 20,  // STATUS
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

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row style
            1 => [
                'font' => [
                    'bold' => true,
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
