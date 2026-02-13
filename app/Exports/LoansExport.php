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

class LoansExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithColumnFormatting
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
            'A' => 5,   // NO
            'B' => 12,  // NIK
            'C' => 25,  // NAMA
            'D' => 15,  // DEPT
            'E' => 12,  // GROUP
            'F' => 15,  // TGL PENGAJUAN
            'G' => 15,  // TGL DISETUJUI
            'H' => 18,  // JUMLAH PINJAMAN
            'I' => 15,  // DURASI
            'J' => 15,  // CICILAN
            'K' => 10,  // BUNGA
            'L' => 15,  // TOTAL BUNGA
            'M' => 15,  // BIAYA ADMIN
            'N' => 18,  // DITERIMA
            'O' => 18,  // SISA POKOK
            'P' => 20,  // STATUS
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
                    'startColor' => ['rgb' => '0D6EFD'], // Blue for loans
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
