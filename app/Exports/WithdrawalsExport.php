<?php

namespace App\Exports;

use App\Models\Withdrawal;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class WithdrawalsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithColumnFormatting
{
    protected ?string $status;

    public function __construct(?string $status = null)
    {
        $this->status = $status;
    }

    public function collection()
    {
        $query = Withdrawal::with('member')->orderByDesc('request_date');

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'NIK',
            'Nama Anggota',
            'Departemen',
            'Kategori',
            'Nominal Penarikan',
            'Tanggal Pengajuan',
            'Tanggal Disetujui',
            'Status',
            'Catatan',
        ];
    }

    public function map($withdrawal): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $withdrawal->member->nik ?? '-',
            $withdrawal->member->name ?? '-',
            $withdrawal->member->dept ?? '-',
            $withdrawal->member->group_tag ?? '-',
            $withdrawal->amount,
            $withdrawal->request_date?->format('d/m/Y') ?? '-',
            $withdrawal->approved_date?->format('d/m/Y') ?? '-',
            $withdrawal->status_label,
            $withdrawal->notes ?? '-',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 12,  // NIK
            'C' => 28,  // Nama
            'D' => 15,  // Dept
            'E' => 12,  // Kategori
            'F' => 20,  // Nominal
            'G' => 16,  // Tgl Pengajuan
            'H' => 16,  // Tgl Disetujui
            'I' => 14,  // Status
            'J' => 25,  // Catatan
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DC3545'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Penarikan Saldo';
    }
}
