<?php

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Carbon\Carbon;

$nik = 'EMP1001';
$targetYear = 2025; // Assuming this is the year in question

$member = Member::where('nik', $nik)->first();
if (!$member) {
    echo "Member not found\n";
    exit;
}

echo "Member: {$member->name} ({$member->nik})\n";

$targetYearEnd = Carbon::create($targetYear, 12, 31)->endOfDay();
echo "Target Year End: {$targetYearEnd}\n";

// Manual Saldo Calculation
$depositTypes = [
    Transaction::TYPE_SAVING_DEPOSIT,
    Transaction::TYPE_SAVING_INTEREST,
    Transaction::TYPE_SHU_REWARD
];

$historicalDeposits = Transaction::where('member_id', $member->id)
    ->whereIn('type', $depositTypes)
    ->where('transaction_date', '<=', $targetYearEnd)
    ->sum('total_amount');

$historicalWithdraws = Transaction::where('member_id', $member->id)
    ->where('type', Transaction::TYPE_SAVING_WITHDRAW)
    ->where('transaction_date', '<=', $targetYearEnd)
    ->sum('total_amount');

$saldo = $historicalDeposits - $historicalWithdraws;
echo "Calculated Historical Saldo (End of $targetYear): " . number_format($saldo) . "\n";
echo "Current DB Saldo: " . number_format($member->savings_balance) . "\n";

// Loan Calculation Debug
$loans = Loan::where('member_id', $member->id)
    ->whereIn('status', ['active', 'paid'])
    ->get();

foreach ($loans as $loan) {
    echo "Loan ID: {$loan->id}, Amount: " . number_format($loan->amount) . "\n";
    echo "Created At: {$loan->created_at}\n";
    
    $loanStartDate = Carbon::parse($loan->created_at);
    $loanStartMonth = $loanStartDate->month;
    $loanStartYear = $loanStartDate->year;
    $duration = $loan->duration;
    
    echo "Start: $loanStartMonth/$loanStartYear, Duration: $duration months\n";
    
    // Check intersection
    echo "Checking intersection for Year $targetYear...\n";
    
    $firstInstallmentAbsolute = ($loanStartYear * 12) + $loanStartMonth;
    $lastInstallmentAbsolute = $firstInstallmentAbsolute + $duration - 1;

    $targetYearFirstMonth = ($targetYear * 12) + 1;
    $targetYearLastMonth = ($targetYear * 12) + 12;

    $overlapStart = max($firstInstallmentAbsolute, $targetYearFirstMonth);
    $overlapEnd = min($lastInstallmentAbsolute, $targetYearLastMonth);

    $installmentsInYear = max(0, $overlapEnd - $overlapStart + 1);
    
    echo "First Inst Abs: $firstInstallmentAbsolute\n";
    echo "Last Inst Abs: $lastInstallmentAbsolute\n";
    echo "Target First: $targetYearFirstMonth\n";
    echo "Target Last: $targetYearLastMonth\n";
    echo "Overlap Start: $overlapStart\n";
    echo "Overlap End: $overlapEnd\n";
    echo "Installments In Year: $installmentsInYear\n";
    
    $shu = $loan->amount * ($installmentsInYear / 10) * 0.05;
    echo "SHU: " . number_format($shu) . "\n";
    echo "-------------------\n";
}
