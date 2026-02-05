<?php

namespace App\Http\Controllers;

use App\Exports\MembersExport;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    /**
     * Export members data to Excel.
     */
    public function members()
    {
        $filename = 'Data_Anggota_Koperasi_' . now()->format('Y-m-d_His') . '.xlsx';
        
        return Excel::download(new MembersExport, $filename);
    }
}
