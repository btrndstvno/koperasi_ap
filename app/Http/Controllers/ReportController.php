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
     * Display monthly read-only report (Historical Snapshot).
     * 
     * CRITICAL LOGIC:
     * - Saldo Simpanan Historis = Sum transaksi saving_deposit s/d akhir bulan terpilih
     * - Sisa Pinjaman Historis = Pokok Awal - Sum transaksi loan_repayment s/d akhir bulan
     * - Sisa Tenor Historis = Tenor Awal - Count pembayaran s/d akhir bulan
     */
    public function monthly(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        // Tanggal akhir bulan terpilih (untuk snapshot historis)
        $endOfMonth = \Carbon\Carbon::create($year, $month)->endOfMonth()->toDateString();
        $startOfMonth = \Carbon\Carbon::create($year, $month)->startOfMonth()->toDateString();

        // Ambil semua Member dengan loans dan ALL transactions (untuk kalkulasi historis)
        $members = Member::with(['loans', 'transactions'])->get();

        // Process data dengan kalkulasi historis
        $processedMembers = $members->map(function ($member) use ($month, $year, $endOfMonth, $startOfMonth) {
            // ========== TRANSAKSI BULAN INI ==========
            $monthlyTransactions = $member->transactions->filter(function ($trx) use ($month, $year) {
                return $trx->transaction_date->month == $month && $trx->transaction_date->year == $year;
            });

            // POT KOP = Cicilan pinjaman bulan ini
            $pot_kop = $monthlyTransactions
                ->where('type', Transaction::TYPE_LOAN_REPAYMENT)
                ->sum('amount_principal');

            // IUR KOP = Simpanan potong gaji bulan ini
            $iur_kop = $monthlyTransactions
                ->where('type', Transaction::TYPE_SAVING_DEPOSIT)
                ->where('payment_method', 'payroll_deduction')
                ->sum('total_amount');

            // IUR TUNAI = Simpanan tunai/transfer bulan ini
            $iur_tunai = $monthlyTransactions
                ->where('type', Transaction::TYPE_SAVING_DEPOSIT)
                ->filter(fn($t) => in_array($t->payment_method, ['cash', 'transfer']))
                ->sum('total_amount');

            // ========== SALDO HISTORIS (Snapshot s/d akhir bulan) ==========
            // Sum semua deposit (Simpanan, Bunga Simpanan, SHU) - sum semua withdraw sampai akhir bulan
            $depositTypes = [
                Transaction::TYPE_SAVING_DEPOSIT,
                Transaction::TYPE_SAVING_INTEREST,
                Transaction::TYPE_SHU_REWARD
            ];
            
            $historicalDeposits = $member->transactions
                ->whereIn('type', $depositTypes)
                // Filter hanya simpanan yang sudah terjadi s/d akhir bulan laporan
                ->where('transaction_date', '<=', $endOfMonth)
                ->sum('total_amount');

            $historicalWithdraws = $member->transactions
                ->where('type', Transaction::TYPE_SAVING_WITHDRAW)
                ->where('transaction_date', '<=', $endOfMonth)
                ->sum('total_amount');

            $saldoHistoris = $historicalDeposits - $historicalWithdraws;

            // ========== SISA PINJAMAN HISTORIS ==========
            // Cari pinjaman yang aktif pada periode tersebut
            $activeLoan = $member->loans
                ->filter(function ($loan) use ($endOfMonth) {
                    return $loan->created_at->toDateString() <= $endOfMonth 
                        && in_array($loan->status, ['active', 'paid']);
                })
                ->sortByDesc('created_at')
                ->first();

            $sisaPinjamanHistoris = 0;
            $sisaTenorHistoris = 0;

            if ($activeLoan) {
                // Sum pembayaran pokok sampai akhir bulan terpilih
                $totalPaidPrincipal = $member->transactions
                    ->where('loan_id', $activeLoan->id)
                    ->where('type', Transaction::TYPE_LOAN_REPAYMENT)
                    ->where('transaction_date', '<=', $endOfMonth)
                    ->sum('amount_principal');

                $sisaPinjamanHistoris = max(0, $activeLoan->amount - $totalPaidPrincipal);

                // Count jumlah pembayaran untuk hitung sisa tenor
                $paidInstallments = $member->transactions
                    ->where('loan_id', $activeLoan->id)
                    ->where('type', Transaction::TYPE_LOAN_REPAYMENT)
                    ->where('transaction_date', '<=', $endOfMonth)
                    ->count();

                $sisaTenorHistoris = max(0, $activeLoan->duration - $paidInstallments);
            }

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
                // JUMLAH = Total tagihan ke gaji (Potongan + Iuran Wajib/Sukarela Potong Gaji)
                // Tidak termasuk Iuran Tunai (karena Iuran Tunai dibayar cash, bukan potong gaji)
                'total' => $pot_kop + $iur_kop, 
                'sisa_pinjaman' => $sisaPinjamanHistoris,
                'sisa_tenor' => $sisaTenorHistoris,
                'saldo_kop' => $saldoHistoris,
            ];
        })->sortBy('nik_numeric');

        // Grouping
        $groupOrder = ['Manager' => 1, 'Bangunan' => 2, 'CSD' => 3, 'Office' => 4];
        
        $groupedData = $processedMembers->groupBy('group_tag')->map(function ($items, $tag) {
            return (object) [
                'name' => $tag,
                'members' => $items,
                'subtotal_pot' => $items->sum('pot_kop'),
                'subtotal_iur' => $items->sum('iur_kop'),
                'subtotal_iur_tunai' => $items->sum('iur_tunai'),
                // Subtotal Total harus match dengan logic per member (pot + iur)
                'subtotal_total' => $items->sum('total'), 
                'subtotal_sisa_pinjaman' => $items->sum('sisa_pinjaman'),
                'subtotal_saldo_kop' => $items->sum('saldo_kop'),
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
            'sisa_pinjaman' => $processedMembers->sum('sisa_pinjaman'),
            'saldo_kop' => $processedMembers->sum('saldo_kop'),
        ];

        return view('reports.monthly', compact('groupedData', 'grandTotal', 'month', 'year'));
    }
}

