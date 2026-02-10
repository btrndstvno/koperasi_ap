<?php

namespace App\Http\Controllers;

use App\Exports\MembersExport;
use App\Exports\PendingLoansExport;
use App\Exports\WithdrawalsExport;
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

    /**
     * Download pending loans as Excel.
     */
    public function pendingLoans()
    {
        return Excel::download(new PendingLoansExport, 'pinjaman_menunggu_' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Download withdrawals data as Excel.
     */
    public function withdrawals(Request $request)
    {
        $status = $request->query('status');
        return Excel::download(new WithdrawalsExport($status), 'penarikan_saldo_' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Download loans data as Excel.
     */
    public function loans(Request $request)
    {
        $status = $request->query('status');
        return Excel::download(new \App\Exports\LoansExport($status), 'data_pinjaman_' . date('Y-m-d') . '.xlsx');
    }
}
