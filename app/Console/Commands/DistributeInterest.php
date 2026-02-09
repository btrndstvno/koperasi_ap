<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Setting;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DistributeInterest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:distribute-interest 
                            {--month= : Bulan (1-12), default bulan ini}
                            {--year= : Tahun, default tahun ini}
                            {--rate= : Persentase bunga tahunan (default dari pengaturan sistem)}
                            {--dry-run : Simulasi tanpa menyimpan ke database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Distribusikan bunga tabungan bulanan ke semua anggota dengan saldo > 0';

    /**
     * Execute the console command.
     * 
     * LOGIC: (Saldo Simpanan × Bunga% / 12)
     * Contoh: Saldo 1.000.000 × 5% / 12 = 4.166,67 → floor = 4.166
     */
    public function handle()
    {
        $month = $this->option('month') ?? now()->month;
        $year = $this->option('year') ?? now()->year;
        
        // Get rate from option or from settings (use historical rate at period date)
        $periodDate = Carbon::create($year, $month, 28);
        
        if ($this->option('rate') !== null) {
            $rate = (float) $this->option('rate');
        } else {
            // Use saving interest rate from settings at the time of period
            $rate = Setting::getRateAtDate('saving_interest_rate', $periodDate->toDateString()) ?? Setting::getSavingInterestRate();
        }
        
        $isDryRun = $this->option('dry-run');

        $monthName = $periodDate->translatedFormat('F Y');

        $this->info("===========================================");
        $this->info(" DISTRIBUSI BUNGA TABUNGAN BULANAN");
        $this->info("===========================================");
        $this->info("Periode: {$monthName}");
        $this->info("Bunga Tahunan: {$rate}%");
        $this->info("Mode: " . ($isDryRun ? 'DRY RUN (Simulasi)' : 'PRODUCTION'));
        $this->newLine();

        // Check if already distributed for this period
        $existingCount = Transaction::where('type', Transaction::TYPE_SAVING_INTEREST)
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->count();

        if ($existingCount > 0 && !$isDryRun) {
            $this->error("Bunga untuk periode {$monthName} sudah pernah didistribusikan ({$existingCount} transaksi).");
            $this->error("Jalankan ulang dengan --dry-run untuk simulasi, atau pilih periode berbeda.");
            return Command::FAILURE;
        }

        // Get all members with savings > 0
        $members = Member::where('savings_balance', '>', 0)->get();

        if ($members->isEmpty()) {
            $this->warn("Tidak ada anggota dengan saldo simpanan > 0.");
            return Command::SUCCESS;
        }

        $this->info("Total anggota dengan saldo > 0: {$members->count()}");
        $this->newLine();

        $results = [
            'success' => 0,
            'skipped' => 0,
            'total_interest' => 0,
        ];

        $tableData = [];

        DB::beginTransaction();

        try {
            foreach ($members as $member) {
                $saldo = (float) $member->savings_balance;
                
                // Bunga = (Saldo × Rate%) / 12
                $bunga = floor($saldo * ($rate / 100) / 12);

                if ($bunga <= 0) {
                    $results['skipped']++;
                    continue;
                }

                $tableData[] = [
                    $member->nik,
                    $member->name,
                    number_format($saldo, 0, ',', '.'),
                    number_format($bunga, 0, ',', '.'),
                ];

                if (!$isDryRun) {
                    // Create transaction record
                    Transaction::create([
                        'member_id' => $member->id,
                        'loan_id' => null,
                        'transaction_date' => $periodDate->toDateString(),
                        'type' => Transaction::TYPE_SAVING_INTEREST,
                        'amount_saving' => $bunga,
                        'amount_principal' => 0,
                        'amount_interest' => 0,
                        'total_amount' => $bunga,
                        'payment_method' => 'system_auto',
                        'notes' => "Bunga Tabungan Bulan {$monthName}",
                    ]);

                    // Update member savings balance
                    $member->increment('savings_balance', $bunga);
                }

                $results['success']++;
                $results['total_interest'] += $bunga;
            }

            if (!$isDryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Terjadi kesalahan: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Display results table
        if (!empty($tableData)) {
            $this->table(
                ['NIK', 'Nama', 'Saldo (Rp)', 'Bunga (Rp)'],
                $tableData
            );
        }

        $this->newLine();
        $this->info("===========================================");
        $this->info(" HASIL DISTRIBUSI");
        $this->info("===========================================");
        $this->info("Anggota Diproses: {$results['success']}");
        $this->info("Anggota Dilewati (bunga < 1): {$results['skipped']}");
        $this->info("Total Bunga Diberikan: Rp " . number_format($results['total_interest'], 0, ',', '.'));
        
        if ($isDryRun) {
            $this->newLine();
            $this->warn(">>> INI ADALAH SIMULASI. Tidak ada data yang disimpan. <<<");
            $this->warn(">>> Jalankan tanpa --dry-run untuk menyimpan ke database. <<<");
        } else {
            $this->newLine();
            $this->info("✅ Bunga berhasil didistribusikan dan dicatat ke mutasi simpanan.");
        }

        return Command::SUCCESS;
    }
}
