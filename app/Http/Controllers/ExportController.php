<?php

namespace App\Http\Controllers;

use App\Exports\MembersExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    /**
     * Download members data as Excel.
     */
    public function members()
    {
        return Excel::download(new MembersExport, 'data_anggota_' . date('Y-m-d') . '.xlsx');
    }
}
