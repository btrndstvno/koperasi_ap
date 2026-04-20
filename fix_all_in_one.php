<?php
/**
 * ============================================================
 *  SCRIPT FIX ALL-IN-ONE — Koperasi AP
 *  Gabungan semua perbaikan database untuk PC lain.
 * ============================================================
 * 
 * PERBAIKAN YANG DILAKUKAN:
 * 
 *  1. Fix Witono (004289): Hapus duplikat transaksi loan_repayment Februari 2026
 *     (ada 2 transaksi @1M, yang benar cuma 1)
 *  
 *  2. Fix payment_method: Khoirul Hadi & Dian Sari 
 *     "Saldo Awal Simpanan" Feb 2026: cash → payroll_deduction
 *     (Sudarmaji tetap cash)
 * 
 *  3. Fix 19 orphan transaksi Maret 2026: Assign loan_id yang benar
 *     (Transaksi loan_repayment tanpa loan_id → dihubungkan ke loan yang tepat)
 * 
 * CARA PAKAI:
 *   php fix_all_in_one.php              → Preview (dry run)
 *   php fix_all_in_one.php --apply      → Eksekusi perubahan
 * 
 * CATATAN: Jalankan SEKALI saja. Script akan skip otomatis jika fix sudah pernah dijalankan.
 * ============================================================
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Loan;
use App\Models\Transaction;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv ?? []);

echo "============================================================\n";
echo "  FIX ALL-IN-ONE — KOPERASI AP\n";
echo "  Mode: " . ($apply ? "*** APPLY ***" : "DRY RUN (preview)") . "\n";
echo "============================================================\n\n";

$changes = 0;

if ($apply) DB::beginTransaction();

try {

    // ============================================================
    // FIX 1: Witono (004289) — Hapus duplikat POT KOP Februari 2026
    // ============================================================
    echo "--- FIX 1: Witono (004289) duplikat POT KOP Februari ---\n";

    $witono = Member::where('nik', '004289')->first();
    if ($witono) {
        $febTrx = Transaction::where('member_id', $witono->id)
            ->where('type', Transaction::TYPE_LOAN_REPAYMENT)
            ->whereMonth('transaction_date', 2)
            ->whereYear('transaction_date', 2026)
            ->orderBy('id')
            ->get();
        
        if ($febTrx->count() > 1) {
            // Hapus yang terakhir (duplikat)
            $duplikat = $febTrx->last();
            echo "  HAPUS TRX #{$duplikat->id} | Rp " . number_format($duplikat->amount_principal, 0, ',', '.') . " (duplikat)\n";
            if ($apply) $duplikat->delete();
            $changes++;
        } else {
            echo "  SKIP: Hanya ada {$febTrx->count()} transaksi (sudah benar)\n";
        }
    } else {
        echo "  SKIP: NIK 004289 tidak ditemukan\n";
    }

    // ============================================================
    // FIX 2: Payment method — Saldo Awal Simpanan Februari 2026
    // cash → payroll_deduction untuk Khoirul Hadi & Dian Sari
    // ============================================================
    echo "\n--- FIX 2: Payment method Saldo Awal Simpanan ---\n";

    // Cari transaksi "Saldo Awal Simpanan" Februari 2026 yang masih cash
    // Target: saving_deposit, payment_method=cash, Februari 2026, notes mengandung "Saldo Awal"
    $saldoAwalTrx = Transaction::where('type', Transaction::TYPE_SAVING_DEPOSIT)
        ->where('payment_method', 'cash')
        ->whereMonth('transaction_date', 2)
        ->whereYear('transaction_date', 2026)
        ->where('notes', 'like', '%Saldo Awal%')
        ->with('member')
        ->get();

    foreach ($saldoAwalTrx as $trx) {
        $nik = $trx->member->nik ?? '';
        $name = $trx->member->name ?? '';
        
        // Sudarmaji tetap cash
        if (stripos($name, 'Sudarmaji') !== false || stripos($name, 'sudarmaji') !== false) {
            echo "  SKIP: TRX #{$trx->id} {$nik} {$name} (tetap cash)\n";
            continue;
        }

        echo "  UPDATE: TRX #{$trx->id} {$nik} {$name} | cash → payroll_deduction\n";
        if ($apply) {
            $trx->payment_method = 'payroll_deduction';
            $trx->save();
        }
        $changes++;
    }

    if ($saldoAwalTrx->isEmpty()) {
        echo "  SKIP: Tidak ada Saldo Awal cash Februari (sudah fix atau beda DB)\n";
    }

    // ============================================================
    // FIX 3: Assign loan_id ke orphan transaksi Maret 2026
    // (loan_repayment tanpa loan_id yang seharusnya terhubung ke loan)
    // ============================================================
    echo "\n--- FIX 3: Assign loan_id ke orphan transaksi Maret ---\n";

    $orphanMarchTrx = Transaction::where('type', Transaction::TYPE_LOAN_REPAYMENT)
        ->whereNull('loan_id')
        ->whereMonth('transaction_date', 3)
        ->whereYear('transaction_date', 2026)
        ->with('member')
        ->get();

    if ($orphanMarchTrx->isEmpty()) {
        echo "  SKIP: Tidak ada orphan transaksi Maret (sudah fix atau beda DB)\n";
    }

    foreach ($orphanMarchTrx as $trx) {
        $member = $trx->member;
        if (!$member) continue;

        // Cari loan terbaru (active atau paid)
        $loan = Loan::where('member_id', $member->id)
            ->whereIn('status', ['active', 'paid'])
            ->orderByDesc('created_at')
            ->first();

        if ($loan) {
            echo "  ASSIGN: TRX #{$trx->id} {$member->nik} {$member->name} → loan_id={$loan->id}\n";
            if ($apply) {
                $trx->loan_id = $loan->id;
                $trx->save();
            }
            $changes++;
        } else {
            echo "  WARN: TRX #{$trx->id} {$member->nik} {$member->name} — tidak ada loan valid!\n";
        }
    }

    // ============================================================
    // DONE
    // ============================================================
    if ($apply) DB::commit();

} catch (\Exception $e) {
    if ($apply) DB::rollBack();
    echo "\n!!! ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n============================================================\n";
echo "  " . ($apply ? "SELESAI!" : "DRY RUN SELESAI.") . "\n";
echo "  Total perubahan: {$changes}\n";
if (!$apply && $changes > 0) {
    echo "\n  Jalankan dengan --apply untuk eksekusi:\n";
    echo "    php fix_all_in_one.php --apply\n";
}
echo "============================================================\n";
