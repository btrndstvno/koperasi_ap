<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Display monthly report page.
     */
    public function index(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        // Summary statistik
        $totalMembers = Member::count();
        $totalSavings = Member::sum('savings_balance');
        $totalActiveLoans = Loan::where('status', 'active')->sum('remaining_principal');
        $activeLoanCount = Loan::where('status', 'active')->count();

        // Cash Flow bulan ini
        $cashFlow = Transaction::query()
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->selectRaw("
                SUM(amount_saving) as total_saving,
                SUM(amount_principal) as total_principal,
                SUM(amount_interest) as total_interest,
                SUM(total_amount) as grand_total,
                COUNT(*) as transaction_count
            ")
            ->first();

        // Rekap per Departemen
        $rekapDept = Member::query()
            ->selectRaw("
                dept,
                COUNT(*) as member_count,
                SUM(savings_balance) as total_savings
            ")
            ->groupBy('dept')
            ->orderByDesc('total_savings')
            ->get();

        // Rekap hutang per Departemen
        $loansByDept = Loan::query()
            ->join('members', 'loans.member_id', '=', 'members.id')
            ->where('loans.status', 'active')
            ->selectRaw("
                members.dept,
                COUNT(loans.id) as loan_count,
                SUM(loans.remaining_principal) as total_debt
            ")
            ->groupBy('members.dept')
            ->orderByDesc('total_debt')
            ->get();

        // Transaksi bulan ini
        $transactions = Transaction::with('member')
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->paginate(50);

        // Cash Flow per tipe transaksi
        $cashFlowByType = Transaction::query()
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->selectRaw("
                type,
                SUM(total_amount) as total,
                COUNT(*) as count
            ")
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return view('reports.index', compact(
            'month',
            'year',
            'totalMembers',
            'totalSavings',
            'totalActiveLoans',
            'activeLoanCount',
            'cashFlow',
            'rekapDept',
            'loansByDept',
            'transactions',
            'cashFlowByType'
        ));
    }

    /**
     * Display monthly read-only report (Historical).
     */
    public function monthly(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        // Ambil semua Member dengan transactions bulan ini (Eager Load dengan Filter)
        $members = Member::with(['transactions' => function($query) use ($month, $year) {
            $query->whereMonth('transaction_date', $month)
                  ->whereYear('transaction_date', $year);
        }])->get();

        // Process data manual
        $processedMembers = $members->map(function ($member) {
            // Calculate totals dari transactions yang sudah difilter
            $pot_kop = $member->transactions
                ->where('type', Transaction::TYPE_LOAN_REPAYMENT)
                ->sum('total_amount');

            // Iur Kop = Saving deposit via potong gaji
            $iur_kop = $member->transactions
                ->where('type', Transaction::TYPE_SAVING_DEPOSIT)
                ->where('payment_method', 'salary_deduction')
                ->sum('total_amount');

            // Iur Tunai = Saving deposit via cash/transfer
            $iur_tunai = $member->transactions
                ->where('type', Transaction::TYPE_SAVING_DEPOSIT)
                ->whereIn('payment_method', ['cash', 'transfer'])
                ->sum('total_amount');

            return (object) [
                'id' => $member->id,
                'nik' => $member->nik,
                'nik_numeric' => (int) preg_replace('/[^0-9]/', '', $member->nik),
                'name' => $member->name,
                'group_tag' => $member->group_tag ?? 'Office',
                'dept' => $member->dept,
                'csd' => $member->csd ?? '-',
                'pot_kop' => $pot_kop,
                'iur_kop' => $iur_kop,
                'iur_tunai' => $iur_tunai,
                'total' => $pot_kop + $iur_kop + $iur_tunai,
            ];
        })->sortBy('nik_numeric'); // Urutkan berdasarkan numeric NIK

        // Grouping
        $groupOrder = ['Manager' => 1, 'Bangunan' => 2, 'CSD' => 3, 'Office' => 4];
        
        $groupedData = $processedMembers->groupBy('group_tag')->map(function ($items, $tag) {
            return (object) [
                'name' => $tag,
                'members' => $items,
                'subtotal_pot' => $items->sum('pot_kop'),
                'subtotal_iur' => $items->sum('iur_kop'),
                'subtotal_iur_tunai' => $items->sum('iur_tunai'),
                'subtotal_total' => $items->sum('total'),
            ];
        })->sortBy(function ($group, $key) use ($groupOrder) {
            return $groupOrder[$key] ?? 999;
        });

        // Grand Total
        $grandTotal = (object) [
            'pot_kop' => $processedMembers->sum('pot_kop'),
            'iur_kop' => $processedMembers->sum('iur_kop'),
            'iur_tunai' => $processedMembers->sum('iur_tunai'),
            'total' => $processedMembers->sum('total'),
        ];

        return view('reports.monthly', compact('groupedData', 'grandTotal', 'month', 'year'));
    }
}

