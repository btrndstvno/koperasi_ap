<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

        // [OPTIMIZATION] Eager load everything needed
        // 1. Previous Month Transactions (for default values)
        $prevMonthDate = $targetDate->copy()->subMonth();
        $prevMonthYear = $prevMonthDate->year;
        $prevMonthMonth = $prevMonthDate->month;
        
        // 2. Current Month Transactions (for existing/edited values)
        $currentMonth = $targetDate->format('Y-m');

        // Pre-fetch transactions to avoid N+1 inside loop
        // We need: 
        // - Last Deduction (any time before target)
        // - Previous Month Deduction (specific month)
        // - Current Month Deductions (specific month)
        
        $members = Member::where('is_active', true)
        ->with(['loans', 'transactions' => function ($q) use ($targetMonthEnd) {
            // Load all transactions up to current month end
            // We need history for balance calculation too
            $q->where('transaction_date', '<=', $targetMonthEnd->toDateString())
              ->orderBy('transaction_date', 'desc'); // Order by date desc for easier "latest" finding
        }])
        ->get()
        ->map(function ($member) use ($targetDate, $prevMonthYear, $prevMonthMonth, $currentMonth) {
            
            // A. Active Loan Logic (In-Memory Filtering)
            $activeLoanInPeriod = $member->loans->first(function($loan) use ($targetDate) {
                 $baseDate = $loan->approved_date 
                    ? \Carbon\Carbon::parse($loan->approved_date) 
                    : \Carbon\Carbon::parse($loan->created_at);
                 
                 $start = $baseDate->copy()->addMonth()->startOfMonth();
                 $end = $start->copy()->addMonths($loan->duration - 1)->endOfMonth();
                 
                 return $targetDate->between($start, $end);
            });

            $pot_kop = 0;
            $sisa_pinjaman = 0;
            $remaining_installments = 0;
            $loan_id = null;

            if ($activeLoanInPeriod) {
                 $remainingPrincipal = $activeLoanInPeriod->remaining_principal;
                 
                 if ($remainingPrincipal > 100) { 
                     $pot_kop = $activeLoanInPeriod->monthly_installment > 0 
                        ? $activeLoanInPeriod->monthly_installment 
                        : $activeLoanInPeriod->monthly_principal;
                        
                     $sisa_pinjaman = $remainingPrincipal;
                     
                     // Sisa cicilan = dari progress loan (remaining_principal / cicilan per bulan)
                     // Menampilkan kondisi saat ini SEBELUM bayar bulan ini
                     // Setelah save/bayar, remaining_principal berkurang → sisa cicilan otomatis turun
                     $remaining_installments = $activeLoanInPeriod->remaining_installments;
                     
                     $loan_id = $activeLoanInPeriod->id;
                 }
            }

            // B. Saldo Historis Logic (In-Memory Calculation)
            // Filter transactions collection instead of DB query
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
            
            if ($member->transactions->isEmpty() && $member->savings_balance > 0) {
                $saldoHistoris = (float) $member->savings_balance;
            }

            // C. Iuran Wajib Default Logic (In-Memory)
            // Filter from loaded transactions
            
            // C.1. Previous Month Deduction
            $prevMonthDeduction = $member->transactions->first(function ($t) use ($prevMonthYear, $prevMonthMonth) {
                return $t->type === Transaction::TYPE_SAVING_DEPOSIT &&
                       ($t->payment_method === 'payroll_deduction' || $t->payment_method === 'deduction') &&
                       \Carbon\Carbon::parse($t->transaction_date)->year == $prevMonthYear &&
                       \Carbon\Carbon::parse($t->transaction_date)->month == $prevMonthMonth;
            });

            $iur_kop_default = 0;
            
            if ($prevMonthDeduction) {
                $iur_kop_default = $prevMonthDeduction->amount_saving;
            } else {
                // C.2. Last Deduction (Fallback)
                // Since relations are loaded desc, first match is last
                $lastDeduction = $member->transactions->first(function ($t) {
                     return $t->type === Transaction::TYPE_SAVING_DEPOSIT; // Simplified fallback
                });
                
                if ($lastDeduction) {
                    $iur_kop_default = $lastDeduction->amount_saving;
                }
            }

            // D. Current Month Existing Values (In-Memory)
            $currentMonthTrx = $member->transactions->filter(function ($t) use ($currentMonth) {
                return substr($t->transaction_date, 0, 7) === $currentMonth;
            });

            $existingPotKop = $currentMonthTrx->first(function ($t) {
                return $t->type === Transaction::TYPE_LOAN_REPAYMENT && $t->payment_method === 'payroll_deduction';
            });

            $existingIurKop = $currentMonthTrx->first(function ($t) {
                return $t->type === Transaction::TYPE_SAVING_DEPOSIT && $t->payment_method === 'payroll_deduction';
            });

            $existingIurTunai = $currentMonthTrx->first(function ($t) {
                return $t->type === Transaction::TYPE_SAVING_DEPOSIT && $t->payment_method === 'cash';
            });
                
            // Final Values
            $final_pot_kop = $existingPotKop ? $existingPotKop->amount_principal : ($activeLoanInPeriod ? $pot_kop : 0);
            
            // [FIX] Ensure pot_kop is never 0 if loan is active and no existing transaction
            if ($activeLoanInPeriod && $final_pot_kop <= 0) {
                 $final_pot_kop = $activeLoanInPeriod->monthly_installment > 0 
                    ? $activeLoanInPeriod->monthly_installment 
                    : $activeLoanInPeriod->monthly_principal;
            }

            $final_iur_kop = $existingIurKop ? $existingIurKop->amount_saving : $iur_kop_default;
            $final_iur_tunai = $existingIurTunai ? $existingIurTunai->amount_saving : 0;
            
            $notes = $existingPotKop?->notes ?? ($existingIurKop?->notes ?? ($existingIurTunai?->notes ?? ''));
            
            return (object) [
                'id' => $member->id,
                'nik' => $member->nik,
                'nik_numeric' => (int) preg_replace('/[^0-9]/', '', $member->nik),
                'name' => $member->name,
                'group_tag' => $member->group_tag ?? 'Office',
                'dept' => $member->dept,
                'csd' => !empty($member->csd) ? $member->csd : ($member->dept ?? '-'), 
                'savings_balance' => (float) $saldoHistoris,
                'has_loan' => $loan_id !== null,
                'loan_id' => $loan_id,
                'remaining_principal' => (float) $sisa_pinjaman,
                'remaining_installments' => $remaining_installments,
                'pot_kop' => (float) $final_pot_kop, 
                'iur_kop' => (float) $final_iur_kop, 
                'iur_tunai' => (float) $final_iur_tunai, 
                'notes' => $notes, 
            ];
        })
        ->sortBy('nik_numeric');

        // Grouping (Logic Tetap Sama)
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
    
        $transactions = $request->input('transactions');

        if (is_string($transactions)) {
            $transactions = json_decode($transactions, true);
        }

        $cleanTransactions = [];
        foreach ($transactions as $trx) {
            // Bersihkan titik di setiap field nominal
            // KEYS MUST MATCH INPUT FORM: iur_tunai, pot_kop, iur_kop
            if (isset($trx['iur_tunai'])) $trx['iur_tunai'] = str_replace('.', '', $trx['iur_tunai']);
            if (isset($trx['pot_kop'])) $trx['pot_kop'] = str_replace('.', '', $trx['pot_kop']);
            if (isset($trx['iur_kop'])) $trx['iur_kop'] = str_replace('.', '', $trx['iur_kop']);
            
            $cleanTransactions[] = $trx;
        }

        // Ganti input request dengan data bersih
        $request->merge(['transactions' => $cleanTransactions]);
        // [LOG] Debug jumlah data yang masuk dari form/JSON
        Log::info("Bulk Store Start. Input Count: " . count($request->input('transactions', [])));

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

                // 1. Cek Duplicate
                // Mencegah input ganda untuk member yang sama di bulan yang sama (khusus potong gaji)
                $alreadyExists = Transaction::where('member_id', $member->id)
                    ->whereYear('transaction_date', $dateObj->year)
                    ->whereMonth('transaction_date', $dateObj->month)
                    ->where('payment_method', 'payroll_deduction')
                    ->exists();

                if ($alreadyExists) {
                    $results['duplicate']++;
                    continue; 
                }

                // Ambil nilai input (default 0 jika kosong)
                $potKop = (float) ($data['pot_kop'] ?? 0);
                $iurKop = (float) ($data['iur_kop'] ?? 0);
                $iurTunai = (float) ($data['iur_tunai'] ?? 0);
                $notes = $data['notes'] ?? '';
                
                // [MODIFIKASI] Hapus pengecekan total <= 0
                // Agar data 0 tetap diproses dan masuk laporan
                /* $totalAmount = $potKop + $iurKop + $iurTunai;
                if ($totalAmount <= 0) {
                    $results['skipped']++;
                    continue;
                }
                */

                // 2. Proses Iuran Koperasi (Simpanan Wajib - Potong Gaji)
                // [MODIFIKASI] Gunakan >= 0 agar nilai 0 tetap dibuatkan record transaksinya
                if ($iurKop >= 0) {
                    // Hanya update saldo member jika ada uangnya (> 0)
                    if ($iurKop > 0) {
                        $member->increment('savings_balance', $iurKop);
                    }

                    Transaction::create([
                        'member_id' => $member->id,
                        'loan_id' => null, // Iuran tidak terikat loan
                        'transaction_date' => $transactionDate,
                        'type' => Transaction::TYPE_SAVING_DEPOSIT,
                        'amount_saving' => $iurKop,
                        'amount_principal' => 0,
                        'amount_interest' => 0,
                        'total_amount' => $iurKop,
                        'payment_method' => 'payroll_deduction',
                        'notes' => $notes ?: ($iurKop == 0 ? 'Tidak ada potongan' : 'Iuran simpanan - potong gaji'),
                    ]);

                    $results['total_iur'] += $iurKop;
                }

                // 3. Proses Iuran Tunai (Bayar Cash)
                // Tetap gunakan > 0 karena kalau cash 0 berarti memang tidak ada transaksi cash
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

                // 4. Proses Potongan Pinjaman (Cicilan)
                // Tetap gunakan > 0 agar tidak membuat record pelunasan palsu
                if ($potKop > 0) {
                    $loan = null;
                    if (!empty($data['loan_id'])) {
                        $loan = Loan::find($data['loan_id']);
                    } else {
                        // Auto-detect pinjaman aktif jika ID tidak dikirim
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

            // 5. Response JSON (Untuk AJAX/JavaScript)
            if ($request->wantsJson()) {
                 $totalAll = $results['total_pot'] + $results['total_iur'] + $results['total_iur_tunai'];
                 $msg = "Berhasil! {$results['processed']} anggota diproses. Duplicate: {$results['duplicate']}. Total: Rp " . number_format($totalAll, 0, ',', '.');
                 session()->flash('success', $msg); 
                 return response()->json(['success' => true, 'message' => $msg]);
            }

            // Fallback Redirect (Untuk submit biasa)
            $totalAll = $results['total_pot'] + $results['total_iur'] + $results['total_iur_tunai'];
            return redirect()->route('transactions.bulk.create')
                ->with('success', "Berhasil! {$results['processed']} anggota diproses. Total: Rp " . number_format($totalAll, 0, ',', '.'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Bulk Store Error: " . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            
            return back()->with('error', 'Gagal menyimpan: ' . $e->getMessage())
                ->withInput();
        }
    }
    /**
     * Update single transaction via AJAX (Auto-save).
     */
    public function updateSingleTransaction(Request $request)
    {
        $request->validate([
            'member_id' => 'required|exists:members,id',
            'transaction_date' => 'required|date',
            'field' => 'required|in:pot_kop,iur_kop,iur_tunai,notes',
            'value' => 'nullable', // string or number
            'loan_id' => 'nullable|exists:loans,id',
        ]);

        DB::beginTransaction();

        try {
            $member = Member::find($request->member_id);
            $date = $request->transaction_date;
            $field = $request->field;
            $value = $request->value;
            // Clean number format
            if (in_array($field, ['pot_kop', 'iur_kop', 'iur_tunai'])) {
                $value = (float) str_replace('.', '', $value);
            }

            // Determine Transaction Attributes based on field
            $type = null;
            $method = null;
            $isNote = false;

            if ($field === 'pot_kop') {
                $type = Transaction::TYPE_LOAN_REPAYMENT;
                $method = 'payroll_deduction';
            } elseif ($field === 'iur_kop') {
                $type = Transaction::TYPE_SAVING_DEPOSIT;
                $method = 'payroll_deduction';
            } elseif ($field === 'iur_tunai') {
                $type = Transaction::TYPE_SAVING_DEPOSIT;
                $method = 'cash';
            } elseif ($field === 'notes') {
                $isNote = true;
            }

            // For notes, we need to update ALL related transactions for this bulk entry context
            if ($isNote) {
                // Find all relevant transactions (pot_kop, iur_kop, iur_tunai) and update notes
                Transaction::where('member_id', $member->id)
                    ->where('transaction_date', $date)
                    ->whereIn('payment_method', ['payroll_deduction', 'cash'])
                    ->whereIn('type', [Transaction::TYPE_LOAN_REPAYMENT, Transaction::TYPE_SAVING_DEPOSIT])
                    ->update(['notes' => $value]);
                
                DB::commit();
                return response()->json(['success' => true]);
            }

            // Find Existing Transaction (Handle Legacy 'deduction' and Duplicates)
            $query = Transaction::where('member_id', $member->id)
                ->where('transaction_date', $date)
                ->where('type', $type);
                
            if ($method === 'payroll_deduction') {
                // Check both new and legacy methods
                $query->whereIn('payment_method', ['payroll_deduction', 'deduction']);
            } else {
                $query->where('payment_method', $method);
            }

            $transactions = $query->get();
            $trx = null;

            if ($transactions->count() > 1) {
                // [FIX] Handle Duplicates: Keep first, delete others
                $trx = $transactions->first();
                $duplicates = $transactions->slice(1);
                foreach ($duplicates as $dupe) {
                    // Logic: Just delete, we will reset the target transaction value anyway
                    $dupe->delete();
                }
            } else {
                $trx = $transactions->first();
            }

            $oldAmount = $trx ? $trx->total_amount : 0;
            $diff = $value - $oldAmount;

            // Optimization: If no change, return early
            if ($diff == 0 && $trx) {
                DB::commit();
                return response()->json(['success' => true]);
            }

            // Handling Logic
            if ($type === Transaction::TYPE_SAVING_DEPOSIT) {
                // Update Member Balance
                $member->increment('savings_balance', $diff);
                
                // Create or Update Transaction
                if ($trx) {
                    $trx->update([
                        'amount_saving' => $value,
                        'total_amount' => $value,
                        'payment_method' => $method, // Standardize to new method
                    ]);
                } else {
                    if ($value > 0) {
                        Transaction::create([
                            'member_id' => $member->id,
                            'transaction_date' => $date,
                            'type' => $type,
                            'amount_saving' => $value,
                            'total_amount' => $value,
                            'payment_method' => $method,
                            'notes' => $method === 'cash' ? 'Iuran simpanan - tunai' : 'Iuran simpanan - potong gaji',
                        ]);
                    }
                }
            } elseif ($type === Transaction::TYPE_LOAN_REPAYMENT) {
                $loan = null;
                if ($request->loan_id) {
                    $loan = Loan::find($request->loan_id);
                } else {
                    $loan = $trx ? $trx->loan : $member->activeLoans()->first();
                }

                if ($loan) {
                    // Update Remaining Principal
                    $loan->decrement('remaining_principal', $diff);
                    
                    // Check paid status
                    $loan->fresh(); 
                    if ($loan->remaining_principal <= 0 && $loan->status === 'active') {
                        $loan->update(['status' => 'paid', 'remaining_principal' => 0]);
                    } elseif ($loan->remaining_principal > 0 && $loan->status === 'paid') {
                        $loan->update(['status' => 'active']);
                    }
                }

                if ($trx) {
                    $trx->update([
                        'amount_principal' => $value,
                        'total_amount' => $value,
                        'loan_id' => $loan ? $loan->id : null,
                        'payment_method' => $method, // Standardize
                    ]);
                } else {
                    if ($value > 0) {
                        Transaction::create([
                            'member_id' => $member->id,
                            'loan_id' => $loan ? $loan->id : null,
                            'transaction_date' => $date,
                            'type' => $type,
                            'amount_principal' => $value,
                            'total_amount' => $value,
                            'payment_method' => $method,
                            'notes' => 'Cicilan pinjaman - potong gaji',
                        ]);
                    }
                }
            }
            
            // Cleanup: If transaction becomes 0, delete it
            if ($trx && $value == 0) {
                $trx->delete();
            }

            DB::commit();

            // Ensure we have a loan object to return correct remaining balance
            if (!isset($loan) || !$loan) {
                $loan = $member->activeLoans()->first();
            }

            return response()->json([
                'success' => true, 
                'new_balance' => $member->fresh()->savings_balance,
                'new_loan_remaining' => $loan ? $loan->fresh()->remaining_principal : 0
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("AutoSave Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a transaction with auto-rollback.
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
