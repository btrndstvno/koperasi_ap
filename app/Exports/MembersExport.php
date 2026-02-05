<?php

namespace App\Exports;

use App\Models\Member;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class MembersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithColumnFormatting
{
    public function collection()
    {
        return Member::with(['activeLoans'])
            ->orderBy('dept')
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'NIK',
            'NAMA',
            'DEPT',
            'STATUS',
            'SALDO_SIMPANAN',
            'SISA_HUTANG',
            'IUR_KOP',
            'POT_KOP',
            'BUNGA',
        ];
    }

    public function map($member): array
    {
        $activeLoan = $member->activeLoans->first();
        
        return [
            $member->nik,
            $member->name,
            $member->dept,
            $member->employee_status === 'monthly' ? 'BULANAN' : 'MINGGUAN',
            $member->savings_balance,
            $activeLoan?->remaining_principal ?? 0,
            10000, // Default IUR_KOP (Iuran Simpanan)
            $activeLoan?->monthly_principal ?? 0, // Default POT_KOP (Potongan Pokok)
            $activeLoan?->monthly_interest ?? 0, // Default Bunga
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,  // NIK
            'B' => 30,  // NAMA
            'C' => 15,  // DEPT
            'D' => 12,  // STATUS
            'E' => 18,  // SALDO_SIMPANAN
            'F' => 18,  // SISA_HUTANG
            'G' => 15,  // IUR_KOP
            'H' => 15,  // POT_KOP
            'I' => 15,  // BUNGA
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
                    'startColor' => ['rgb' => '2C3E50'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }

    public function title(): string
    {
        // Format: "MM YYYY" agar kompatibel dengan import
        return now()->format('m Y');
    }
}
