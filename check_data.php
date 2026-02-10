<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();


$output = "";

$nik = '000P02';
$member = App\Models\Member::where('nik', $nik)->first();

if (!$member) {
    $output .= "Member not found: $nik\n";
} else {
    $output .= "Member: {$member->name} (ID: {$member->id})\n";
    $output .= "Group Tag: " . ($member->group_tag ?? 'NULL') . "\n";
    $output .= "Dept: " . ($member->dept ?? 'NULL') . "\n";

    $transactions = App\Models\Transaction::where('member_id', $member->id)
        ->whereYear('transaction_date', 2026)
        ->whereMonth('transaction_date', 2)
        ->get();

    $output .= "Transactions count for Feb 2026: " . $transactions->count() . "\n";

    foreach ($transactions as $trx) {
        $output .= "ID: {$trx->id} | Date: {$trx->transaction_date} | Type: {$trx->type} | Method: {$trx->payment_method} | Amount: " . number_format($trx->total_amount) . "\n";
    }
}

// Check other Office members too just in case
$officeCount = App\Models\Member::where('group_tag', 'Office')->count();
$output .= "\nTotal Office Members: $officeCount\n";

$officeTrxCount = App\Models\Transaction::whereHas('member', function($q) {
        $q->where('group_tag', 'Office');
    })
    ->whereYear('transaction_date', 2026)
    ->whereMonth('transaction_date', 2)
    ->count();

$output .= "Total Transactions for Office (Feb 2026): $officeTrxCount\n";

file_put_contents('check_result.txt', $output);
