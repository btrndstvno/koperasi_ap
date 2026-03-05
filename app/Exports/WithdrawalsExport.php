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
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class WithdrawalsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithColumnFormatting, WithEvents
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
            'A' => 4,   // No
            'B' => 10,  // NIK
            'C' => 20,  // Nama
            'D' => 10,  // Dept
            'E' => 10,  // Kategori
            'F' => 14,  // Nominal
            'G' => 11,  // Tgl Pengajuan
            'H' => 11,  // Tgl Disetujui
            'I' => 10,  // Status
            'J' => 18,  // Catatan
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
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
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 8,
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
