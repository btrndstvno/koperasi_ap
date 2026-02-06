<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MonthlyReportExport implements WithMultipleSheets
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

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->groupedData as $tag => $data) {
            $sheets[] = new MonthlyReportSheet($tag, $data, $this->month, $this->year);
        }

        return $sheets;
    }
}
