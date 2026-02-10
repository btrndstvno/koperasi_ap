<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * Show saving/payment history for authenticated member.
     */
    public function mySavings()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user->isMember()) {
            return redirect()->route('dashboard')->with('error', 'Akses ditolak.');
        }

        $member = $user->member;
        
        
        // For now, let's just get ALL transactions for this member
        $transactions = Transaction::where('member_id', $member->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->paginate(20);
            
        return view('members.my-savings', compact('member', 'transactions'));
    }

    /**
     * Show bulk transaction form (Input Massal/Gajian).
     * 
     * SISTEM "BUNGA POTONG DI AWAL" + GROUPING BY GROUP_TAG:
    /**
     * Show bulk transaction form (Input Massal/Gajian).
     * 
     * Logic Updated:
     * - Saldo simpanan sekarang bersifat historis (snapshot per akhir bulan yang dipilih)
     * - Pinjaman dan cicilan juga historis (hanya muncul jika aktif pada bulan tersebut)
     */
    public function createBulk(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);
        $transactionDate = sprintf('%04d-%02d-01', $year, $month);
        
        $targetDate = \Carbon\Carbon::createFromDate($year, $month, 1);
        $targetMonthStart = $targetDate->copy()->startOfMonth();
        $targetMonthEnd = $targetDate->copy()->endOfMonth();

        // Get all members with their loans and transactions
        // Eager load transactions filtered by date for optimization
        $members = Member::with(['loans', 'transactions' => function ($q) use ($targetMonthEnd) {
            $q->where('transaction_date', '<=', $targetMonthEnd->toDateString());
        }])
        ->get()
        ->map(function ($member) use ($targetDate, $targetMonthStart) {
            // Find loan that is active in the selected period
            $activeLoanInPeriod = $member->loans->filter(function($loan) use ($targetDate) {
                 // Use approved_date if available (preferred), otherwise date created
                 $baseDate = $loan->approved_date 
                    ? \Carbon\Carbon::parse($loan->approved_date) 
                    : \Carbon\Carbon::parse($loan->created_at);
                 
                 // START DEDUCTION: Next Month after approval (Bulan depan mulai potong)
                 $start = $baseDate->copy()->addMonth()->startOfMonth();

                 // Estimated end date
                 $end = $start->copy()->addMonths($loan->duration - 1)->endOfMonth();
                 
                 // Loan is candidate if target date is within range
                 return $targetDate->between($start, $end);
            })->first();

            $pot_kop = 0;
            $sisa_pinjaman = 0;
            $remaining_installments = 0;
            $loan_id = null;

            if ($activeLoanInPeriod) {
                 // Gunakan remaining_principal langsung dari loan (sudah akurat dari import/transaksi)
                 $remainingPrincipal = $activeLoanInPeriod->remaining_principal;
                 
                 // Jika masih ada sisa hutang, maka tampilkan tagihan
                 if ($remainingPrincipal > 100) { // Tolerance for floating point
                     $pot_kop = $activeLoanInPeriod->monthly_installment > 0 
                        ? $activeLoanInPeriod->monthly_installment 
                        : $activeLoanInPeriod->monthly_principal;
                        
                     $sisa_pinjaman = $remainingPrincipal;
                     
                     // Hitung sisa cicilan dari remaining_principal / monthly_installment
                     // Contoh: Rizki progress 50% = sisa Rp 5.000.000 / Rp 1.000.000 = 5 cicilan
                     if ($pot_kop > 0) {
                         $remaining_installments = (int) ceil($remainingPrincipal / $pot_kop);
                     } else {
                         // Fallback ke tenor jika tidak ada monthly_installment
                         $remaining_installments = $activeLoanInPeriod->duration;
                     }
                     
                     $loan_id = $activeLoanInPeriod->id;
                 }
            }

            // Hitung Saldo Historis (Sampai akhir bulan terpilih)
            // Ini termasuk transaksi yang mungkin SUDAH diinput di bulan ini
            $depositTypes = [
                Transaction::TYPE_SAVING_DEPOSIT,
                Transaction::TYPE_SAVING_INTEREST,
                Transaction::TYPE_SHU_REWARD
            ];

            $histDeposits = $member->transactions
                ->whereIn('type', $depositTypes)
                ->sum('total_amount');
            
            $histWithdraws = $member->transactions
                ->where('type', Transaction::TYPE_SAVING_WITHDRAW)
                ->sum('total_amount');

            $saldoHistoris = $histDeposits - $histWithdraws;
            
            // Jika tidak ada transaksi sama sekali, gunakan savings_balance dari member (data import)
            // Ini penting untuk member yang baru diimport dari Excel
            if ($member->transactions->isEmpty() && $member->savings_balance > 0) {
                $saldoHistoris = (float) $member->savings_balance;
            }

            // Logic Iuran Wajib Otomatis (Recurring)
            // 1. Ambil transaksi iuran (deduction) terakhir
            $lastDeduction = $member->transactions
                ->where('type', Transaction::TYPE_SAVING_DEPOSIT)
                ->where('payment_method', 'deduction')
                ->sortByDesc('transaction_date')
                ->first();
            
            $iur_kop_val = 0;
            if ($lastDeduction) {
                $iur_kop_val = $lastDeduction->total_amount;
            } else {
                // 2. Jika belum ada, ambil setoran awal (transaksi simpanan pertama)
                $firstDeposit = $member->transactions
                    ->where('type', Transaction::TYPE_SAVING_DEPOSIT)
                    ->sortBy('transaction_date')
                    ->first();
                if ($firstDeposit) {
                    $iur_kop_val = $firstDeposit->total_amount;
                }
            }
            
            return (object) [
                'id' => $member->id,
                'nik' => $member->nik,
                'nik_numeric' => (int) preg_replace('/[^0-9]/', '', $member->nik),
                'name' => $member->name,
                'group_tag' => $member->group_tag ?? 'Office',
                'dept' => $member->dept,
                'csd' => $member->group_tag ?? '-', 
                'savings_balance' => (float) $saldoHistoris,
                'has_loan' => $loan_id !== null,
                'loan_id' => $loan_id,
                'remaining_principal' => (float) $sisa_pinjaman,
                'remaining_installments' => $remaining_installments,
                'pot_kop' => (float) $pot_kop,
                'iur_kop' => (float) $iur_kop_val, 
                'iur_tunai' => 0,
            ];
        })
        ->sortBy('nik_numeric');

        // Group by group_tag dengan urutan: Manager, Bangunan, CSD, Office
        $groupOrder = ['Manager' => 1, 'Bangunan' => 2, 'CSD' => 3, 'Office' => 4];
        
        $groupedByTag = $members->groupBy('group_tag')->map(function ($tagMembers, $tagName) {
            return (object) [
                'name' => $tagName,
                'members' => $tagMembers->values(),
                'count' => $tagMembers->count(),
                'subtotal_pot' => $tagMembers->sum('pot_kop'),
                'subtotal_iur' => $tagMembers->sum('iur_kop'),
                'subtotal_iur_tunai' => $tagMembers->sum('iur_tunai'),
                'subtotal_jumlah' => $tagMembers->sum('pot_kop') + $tagMembers->sum('iur_kop') + $tagMembers->sum('iur_tunai'),
            ];
        })->sortBy(function ($group, $key) use ($groupOrder) {
            return $groupOrder[$key] ?? 999;
        });

        // Grand totals
        $grandTotals = (object) [
            'pot_kop' => $members->sum('pot_kop'),
            'iur_kop' => $members->sum('iur_kop'),
            'iur_tunai' => $members->sum('iur_tunai'),
            'jumlah' => $members->sum('pot_kop') + $members->sum('iur_kop') + $members->sum('iur_tunai'),
            'total_members' => $members->count(),
            'members_with_loan' => $members->where('has_loan', true)->count(),
        ];

        return view('transactions.bulk_create', compact(
            'groupedByTag',
            'grandTotals',
            'month',
            'year',
            'transactionDate'
        ));
    }

    /**
     * Store bulk transactions.
     * 
     * SISTEM "BUNGA POTONG DI AWAL":
     * - POT KOP: loan_repayment (potong gaji)
     * - IUR KOP: saving_deposit (potong gaji)
     * - IUR TUNAI: saving_deposit (cash/tunai)
     */
    public function storeBulk(Request $request)
    {
        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'transactions' => 'required|array|min:1',
            'transactions.*.member_id' => 'required|exists:members,id',
            'transactions.*.loan_id' => 'nullable|exists:loans,id',
            'transactions.*.pot_kop' => 'nullable|numeric|min:0',
            'transactions.*.iur_kop' => 'nullable|numeric|min:0',
            'transactions.*.iur_tunai' => 'nullable|numeric|min:0',
            'transactions.*.notes' => 'nullable|string|max:255',
        ]);

        $transactionDate = $validated['transaction_date'];
        $dateObj = Carbon::parse($transactionDate);
        
        // Hapus global check supaya bisa simpan per-departemen secara bertahap
        // Validation check akan dilakukan per-member di dalam loop
        
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'duplicate' => 0,
            'total_pot' => 0,
            'total_iur' => 0,
            'total_iur_tunai' => 0,
        ];

        DB::beginTransaction();

        try {
            foreach ($validated['transactions'] as $data) {
                $memberId = $data['member_id'] ?? null;
                if (!$memberId) continue;

                $member = Member::find($memberId);
                if (!$member) continue;

                // Check duplicate transaction for this member in this month
                // Cek apakah member ini SUDAH punya transaksi 'payroll_deduction' di bulan yang sama
                $alreadyExists = Transaction::where('member_id', $member->id)
                    ->whereYear('transaction_date', $dateObj->year)
                    ->whereMonth('transaction_date', $dateObj->month)
                    ->where('payment_method', 'payroll_deduction')
                    ->exists();

                if ($alreadyExists) {
                    $results['duplicate']++;
                    continue; // Skip member ini
                }

                $potKop = (float) ($data['pot_kop'] ?? 0);
                $iurKop = (float) ($data['iur_kop'] ?? 0);
                $iurTunai = (float) ($data['iur_tunai'] ?? 0);
                $notes = $data['notes'] ?? '';
                $totalAmount = $potKop + $iurKop + $iurTunai;

                // Skip if all amounts are 0
                if ($totalAmount <= 0) {
                    $results['skipped']++;
                    continue;
                }

                // Process Iuran Koperasi (Simpanan - Potong Gaji)
                if ($iurKop > 0) {
                    $member->increment('savings_balance', $iurKop);

                    Transaction::create([
                        'member_id' => $member->id,
                        'loan_id' => null,
                        'transaction_date' => $transactionDate,
                        'type' => Transaction::TYPE_SAVING_DEPOSIT,
                        'amount_saving' => $iurKop,
                        'amount_principal' => 0,
                        'amount_interest' => 0,
                        'total_amount' => $iurKop,
                        'payment_method' => 'payroll_deduction',
                        'notes' => $notes ?: 'Iuran simpanan - potong gaji',
                    ]);

                    $results['total_iur'] += $iurKop;
                }

                // Process Iuran Tunai (Simpanan - Cash)
                if ($iurTunai > 0) {
                    $member->increment('savings_balance', $iurTunai);

                    Transaction::create([
                        'member_id' => $member->id,
                        'loan_id' => null,
                        'transaction_date' => $transactionDate,
                        'type' => Transaction::TYPE_SAVING_DEPOSIT,
                        'amount_saving' => $iurTunai,
                        'amount_principal' => 0,
                        'amount_interest' => 0,
                        'total_amount' => $iurTunai,
                        'payment_method' => 'cash',
                        'notes' => $notes ?: 'Iuran simpanan - tunai',
                    ]);

                    $results['total_iur_tunai'] += $iurTunai;
                }

                // Process Potongan Koperasi (Cicilan Pinjaman)
                if ($potKop > 0) {
                    $loan = null;
                    
                    if (!empty($data['loan_id'])) {
                        $loan = Loan::find($data['loan_id']);
                    } else {
                        $loan = $member->activeLoans()->first();
                    }

                    if ($loan) {
                        $loan->reduceRemainingPrincipal($potKop);
                    }

                    Transaction::create([
                        'member_id' => $member->id,
                        'loan_id' => $loan?->id,
                        'transaction_date' => $transactionDate,
                        'type' => Transaction::TYPE_LOAN_REPAYMENT,
                        'amount_saving' => 0,
                        'amount_principal' => $potKop,
                        'amount_interest' => 0,
                        'total_amount' => $potKop,
                        'payment_method' => 'payroll_deduction',
                        'notes' => $notes ?: 'Cicilan pinjaman - potong gaji',
                    ]);

                    $results['total_pot'] += $potKop;
                }

                $results['processed']++;
            }

            DB::commit();

            $totalAll = $results['total_pot'] + $results['total_iur'] + $results['total_iur_tunai'];
            return redirect()->route('transactions.bulk.create')
                ->with('success', "Berhasil! {$results['processed']} anggota diproses. Total: Rp " . number_format($totalAll, 0, ',', '.'));

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Delete a transaction with auto-rollback.
     * 
     * SMART DELETE (Rollback Saldo):
     * - saving_deposit: KURANGI member.savings_balance
     * - saving_withdraw: TAMBAH member.savings_balance
     * - loan_repayment: TAMBAH loan.remaining_principal, cek status paid->active
     * - interest_revenue/admin_fee/loan_disbursement: Tidak bisa dihapus (sistem)
     */
    public function destroy(Transaction $transaction)
    {
        DB::beginTransaction();

        try {
            $member = $transaction->member;
            $loan = $transaction->loan;
            $type = $transaction->type;

            // Cek tipe transaksi yang tidak boleh dihapus (transaksi sistem)
            $systemTypes = [
                Transaction::TYPE_INTEREST_REVENUE,
                Transaction::TYPE_ADMIN_FEE,
                Transaction::TYPE_LOAN_DISBURSEMENT,
            ];

            if (in_array($type, $systemTypes)) {
                return back()->with('error', 'Transaksi sistem (Bunga/Admin Fee/Pencairan) tidak dapat dihapus.');
            }

            // Rollback berdasarkan tipe transaksi
            switch ($type) {
                case Transaction::TYPE_SAVING_DEPOSIT:
                    // Kurangi saldo simpanan member
                    if ($member) {
                        $member->decrement('savings_balance', $transaction->amount_saving);
                    }
                    break;

                case Transaction::TYPE_SAVING_WITHDRAW:
                    // Tambah kembali saldo simpanan member
                    if ($member) {
                        $member->increment('savings_balance', $transaction->amount_saving);
                    }
                    break;

                case Transaction::TYPE_LOAN_REPAYMENT:
                    // Tambah kembali sisa hutang
                    if ($loan) {
                        $amountToRestore = $transaction->amount_principal;
                        $loan->increment('remaining_principal', $amountToRestore);

                        // Jika loan status 'paid', kembalikan ke 'active'
                        if ($loan->status === 'paid') {
                            $loan->update(['status' => 'active']);
                        }
                    }
                    break;
            }

            // Hapus transaksi
            $transaction->delete();

            DB::commit();

            $memberName = $member ? $member->name : 'Unknown';
            return back()->with('success', "Transaksi berhasil dihapus. Saldo {$memberName} telah di-rollback.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
    
}
