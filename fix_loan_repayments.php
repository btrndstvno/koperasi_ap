<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Loan;
use App\Models\Transaction;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Script perbaikan data transaksi cicilan pinjaman.
 * 
 * Masalah: createBulk() pernah memilih loan yang salah (loan lama/lunas)
 * untuk member yang punya >1 pinjaman. Akibatnya:
 * - amount_principal tidak sesuai dengan monthly_installment loan yang benar
 * - loan_id menunjuk ke loan yang salah
 * - remaining_principal loan jadi tidak akurat
 * 
 * Script ini:
 * 1. Mendeteksi semua transaksi loan_repayment yang amount-nya tidak cocok
 * 2. Menentukan loan yang benar berdasarkan tanggal & status
 * 3. Memindahkan transaksi ke loan yang benar dengan amount yang benar
 * 4. Merecalculate remaining_principal untuk semua loan yang terpengaruh
 * 
 * AMAN dijalankan berulang kali - jika tidak ada masalah, tidak ada yang diubah.
 * 
 * Cara pakai: php fix_loan_repayments.php
 * Tambah --dry-run untuk preview tanpa mengubah data
 */

$dryRun = in_array('--dry-run', $argv ?? []);
if ($dryRun) {
    echo "*** DRY RUN MODE - tidak ada data yang diubah ***\n\n";
}

echo "=== STEP 1: Mencari transaksi cicilan dengan amount tidak cocok ===\n\n";

// Ambil semua loan_repayment dan join dengan loan-nya
$problems = [];

$transactions = Transaction::where('type', 'loan_repayment')
    ->whereNotNull('loan_id')
    ->with('loan')
    ->get();

foreach ($transactions as $trx) {
    if (!$trx->loan) continue;
    
    // Cek apakah amount cocok dengan monthly_installment loan
    $expectedAmount = (float) $trx->loan->monthly_installment;
    $actualAmount = (float) $trx->amount_principal;
    
    if ($expectedAmount > 0 && abs($actualAmount - $expectedAmount) > 1) {
        $member = Member::find($trx->member_id);
        
        // Cari semua loan member ini
        $memberLoans = Loan::where('member_id', $trx->member_id)
            ->orderBy('approved_date')
            ->get();
        
        if ($memberLoans->count() < 2) continue; // Perlu >1 loan untuk ada masalah
        
        // Cari loan yang benar: loan yang monthly_installment cocok dengan amount transaksi
        // ATAU tentukan berdasarkan tanggal
        $trxDate = \Carbon\Carbon::parse($trx->transaction_date);
        
        $correctLoan = null;
        foreach ($memberLoans as $loan) {
            if ((float) $loan->monthly_installment == $actualAmount) {
                // Amount cocok dengan loan ini - tapi cek apakah tanggalnya masuk akal
                $loanStart = $loan->approved_date 
                    ? \Carbon\Carbon::parse($loan->approved_date)->addMonth()->startOfMonth() 
                    : \Carbon\Carbon::parse($loan->created_at)->addMonth()->startOfMonth();
                
                // Transaksi seharusnya pada/setelah first repayment month
                // ATAU sebelumnya (karena bug createBulk menggunakan amount yang salah)
                $correctLoan = $loan;
            }
        }
        
        // Jika amount tidak cocok dengan loan manapun, tentukan berdasarkan waktu
        if (!$correctLoan) {
            // Cari loan yang seharusnya aktif pada tanggal transaksi
            foreach ($memberLoans->sortByDesc('approved_date') as $loan) {
                $loanStart = $loan->approved_date 
                    ? \Carbon\Carbon::parse($loan->approved_date)->addMonth()->startOfMonth() 
                    : \Carbon\Carbon::parse($loan->created_at)->addMonth()->startOfMonth();
                
                if ($trxDate->gte($loanStart)) {
                    $correctLoan = $loan;
                    break;
                }
            }
            // Fallback ke loan terlama jika transaksi sebelum semua loan
            if (!$correctLoan) {
                $correctLoan = $memberLoans->first();
            }
        }
        
        // Tentukan loan yang benar berdasarkan tanggal transaksi
        // Transaksi SEBELUM first repayment new loan -> harus di old loan
        $bestLoan = null;
        foreach ($memberLoans->sortByDesc('approved_date') as $loan) {
            $loanFirstRepay = $loan->approved_date 
                ? \Carbon\Carbon::parse($loan->approved_date)->addMonth()->startOfMonth() 
                : \Carbon\Carbon::parse($loan->created_at)->addMonth()->startOfMonth();
            
            if ($trxDate->gte($loanFirstRepay)) {
                $bestLoan = $loan;
                break;
            }
        }
        if (!$bestLoan) {
            $bestLoan = $memberLoans->first();
        }
        
        $problems[] = [
            'trx_id' => $trx->id,
            'trx_date' => $trx->transaction_date,
            'member_nik' => $member->nik ?? '?',
            'member_name' => $member->name ?? '?',
            'current_loan_id' => $trx->loan_id,
            'current_amount' => $actualAmount,
            'current_loan_mi' => $expectedAmount,
            'correct_loan_id' => $bestLoan->id,
            'correct_amount' => (float) $bestLoan->monthly_installment,
        ];
        
        echo sprintf("  MASALAH: TRX=%d | %s | %s %s\n",
            $trx->id,
            \Carbon\Carbon::parse($trx->transaction_date)->format('Y-m-d'),
            $member->nik ?? '?', $member->name ?? '?'
        );
        echo sprintf("    Saat ini: loan_id=%d (mi=%s), amount=%s\n",
            $trx->loan_id,
            number_format($expectedAmount),
            number_format($actualAmount)
        );
        echo sprintf("    Seharusnya: loan_id=%d (mi=%s), amount=%s\n",
            $bestLoan->id,
            number_format((float) $bestLoan->monthly_installment),
            number_format((float) $bestLoan->monthly_installment)
        );
    }
}

if (empty($problems)) {
    echo "  Tidak ada masalah ditemukan! Semua transaksi sudah benar.\n";
    exit(0);
}

echo sprintf("\nDitemukan %d transaksi bermasalah.\n", count($problems));

// ========================================
echo "\n=== STEP 2: Memperbaiki transaksi ===\n\n";

if ($dryRun) {
    echo "DRY RUN - tidak ada perubahan.\n";
    echo "Jalankan tanpa --dry-run untuk memperbaiki data.\n";
    exit(0);
}

$affectedLoanIds = [];

DB::beginTransaction();
try {
    foreach ($problems as $p) {
        $trx = Transaction::find($p['trx_id']);
        $oldLoanId = $p['current_loan_id'];
        $newLoanId = $p['correct_loan_id'];
        $oldAmount = $p['current_amount'];
        $newAmount = $p['correct_amount'];
        
        // Skip jika ternyata sama
        if ($oldLoanId == $newLoanId && abs($oldAmount - $newAmount) < 1) continue;
        
        // 1. Kembalikan amount ke loan lama (reverse)
        if ($oldLoanId != $newLoanId) {
            Loan::where('id', $oldLoanId)->increment('remaining_principal', $oldAmount);
            $affectedLoanIds[$oldLoanId] = true;
        }
        
        // 2. Kurangi remaining_principal di loan yang benar
        if ($oldLoanId != $newLoanId) {
            Loan::where('id', $newLoanId)->decrement('remaining_principal', $newAmount);
            $affectedLoanIds[$newLoanId] = true;
        } elseif (abs($oldAmount - $newAmount) > 1) {
            // Loan sama tapi amount beda
            $diff = $newAmount - $oldAmount;
            if ($diff > 0) {
                Loan::where('id', $newLoanId)->decrement('remaining_principal', $diff);
            } else {
                Loan::where('id', $newLoanId)->increment('remaining_principal', abs($diff));
            }
            $affectedLoanIds[$newLoanId] = true;
        }
        
        // 3. Update transaksi
        $trx->update([
            'loan_id' => $newLoanId,
            'amount_principal' => $newAmount,
            'total_amount' => $newAmount,
        ]);
        
        echo sprintf("  DIPERBAIKI: TRX=%d | %s | %s %s | loan %d->%d | %s->%s\n",
            $p['trx_id'],
            \Carbon\Carbon::parse($p['trx_date'])->format('Y-m-d'),
            $p['member_nik'], $p['member_name'],
            $oldLoanId, $newLoanId,
            number_format($oldAmount), number_format($newAmount)
        );
    }
    
    // ========================================
    echo "\n=== STEP 3: Recalculate remaining_principal ===\n\n";
    
    foreach (array_keys($affectedLoanIds) as $loanId) {
        $loan = Loan::find($loanId);
        if (!$loan) continue;
        
        $totalRepaid = Transaction::where('loan_id', $loanId)
            ->where('type', 'loan_repayment')
            ->sum('amount_principal');
        
        $correctRemaining = max(0, (float) $loan->amount - $totalRepaid);
        $correctStatus = ($correctRemaining <= 0) ? 'paid' : 'active';
        
        // Hanya update jika berubah
        if (abs((float) $loan->remaining_principal - $correctRemaining) > 1 || $loan->status !== $correctStatus) {
            $member = Member::find($loan->member_id);
            echo sprintf("  Loan %d (%s %s): remaining %s -> %s, status %s -> %s\n",
                $loanId,
                $member->nik ?? '?', $member->name ?? '?',
                number_format((float) $loan->remaining_principal),
                number_format($correctRemaining),
                $loan->status, $correctStatus
            );
            
            $loan->update([
                'remaining_principal' => $correctRemaining,
                'status' => $correctStatus,
            ]);
        }
    }
    
    DB::commit();
    echo "\nSemua perbaikan berhasil disimpan!\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

// ========================================
echo "\n=== STEP 4: Verifikasi ===\n\n";

// Cek ulang apakah masih ada masalah
$remaining = 0;
$transactions2 = Transaction::where('type', 'loan_repayment')
    ->whereNotNull('loan_id')
    ->with('loan')
    ->get();

foreach ($transactions2 as $trx) {
    if (!$trx->loan) continue;
    $expected = (float) $trx->loan->monthly_installment;
    $actual = (float) $trx->amount_principal;
    if ($expected > 0 && abs($actual - $expected) > 1) {
        $remaining++;
    }
}

if ($remaining === 0) {
    echo "  SUKSES! Semua transaksi cicilan sudah cocok dengan loan-nya.\n";
} else {
    echo "  PERINGATAN: Masih ada $remaining transaksi yang tidak cocok.\n";
    echo "  Jalankan script ini lagi atau periksa secara manual.\n";
}

// Cek loan dengan remaining_principal negatif
$negativeLoans = Loan::where('remaining_principal', '<', 0)->get();
if ($negativeLoans->count() > 0) {
    echo "\n  PERINGATAN: Ada loan dengan remaining_principal negatif:\n";
    foreach ($negativeLoans as $l) {
        $m = Member::find($l->member_id);
        echo sprintf("    Loan %d (%s %s): remaining=%s\n",
            $l->id, $m->nik ?? '?', $m->name ?? '?',
            number_format((float) $l->remaining_principal)
        );
    }
} else {
    echo "  Tidak ada loan dengan remaining_principal negatif.\n";
}

echo "\nSelesai.\n";
