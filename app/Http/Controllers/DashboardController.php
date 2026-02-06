<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Traffic Controller - Redirect berdasarkan role user.
     */
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            return $this->adminDashboard();
        }

        return $this->memberDashboard();
    }

    /**
     * Admin Dashboard dengan statistik lengkap.
     */
    protected function adminDashboard()
    {
        $totalMembers = Member::count();
        $totalSavings = Member::sum('savings_balance');
        $totalLoans = Loan::where('status', 'active')->sum('remaining_principal');
        $activeLoans = Loan::where('status', 'active')->count();
        $pendingLoansCount = Loan::where('status', 'pending')->count();
        $pendingWithdrawalsCount = Withdrawal::where('status', 'pending')->count();
        
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

        return view('dashboard.admin', compact(
            'totalMembers',
            'totalSavings',
            'totalLoans',
            'activeLoans',
            'pendingLoansCount',
            'pendingWithdrawalsCount',
            'recentTransactions',
            'savingsByDept'
        ));
    }

    /**
     * Member Dashboard - Lihat simpanan & pinjaman sendiri.
     */
    protected function memberDashboard()
    {
        $user = Auth::user();
        $member = $user->member;

        if (!$member) {
            return view('dashboard.member', [
                'member' => null,
                'activeLoan' => null,
                'pendingLoan' => null,
            ]);
        }

        $activeLoan = $member->loans()->where('status', 'active')->first();
        $pendingLoan = $member->loans()->where('status', 'pending')->first();

        return view('dashboard.member', compact('member', 'activeLoan', 'pendingLoan'));
    }
}
