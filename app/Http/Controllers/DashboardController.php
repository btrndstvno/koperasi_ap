<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $totalMembers = Member::count();
        $totalSavings = Member::sum('savings_balance');
        $totalLoans = Loan::where('status', 'active')->sum('remaining_principal');
        $activeLoans = Loan::where('status', 'active')->count();
        
        $recentTransactions = Transaction::with('member')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        $savingsByDept = Member::selectRaw('dept, SUM(savings_balance) as total')
            ->groupBy('dept')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'totalMembers',
            'totalSavings',
            'totalLoans',
            'activeLoans',
            'recentTransactions',
            'savingsByDept'
        ));
    }
}
