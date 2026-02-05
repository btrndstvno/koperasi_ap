<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DistributeSHU extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:distribute-shu 
                            {--year= : Tahun pinjaman yang dihitung (default: tahun lalu)}
                            {--rate=5 : Persentase SHU (default 5%)}
                            {--dry-run : Simulasi tanpa menyimpan ke database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Distribusikan SHU (Sisa Hasil Usaha) tahunan berdasarkan total pinjaman anggota';

    /**
     * Execute the console command.
     * 
     * RUMUS USER: Total Pinjaman Anggota (Selama Setahun) × (10/12) × 5%
     * Contoh: Pinjaman 10.000.000 × (10/12) × 5% = 416.667
     */
    public function handle()
    {
        $targetYear = $this->option('year') ?? (now()->year - 1);
        $rate = (float) $this->option('rate');
        $isDryRun = $this->option('dry-run');

        $distributionDate = Carbon::create(now()->year, 1, 1)->toDateString();

        $this->info("===========================================");
        $this->info(" PEMBAGIAN SHU (SISA HASIL USAHA)");
        $this->info("===========================================");
        $this->info("Tahun Pinjaman Dihitung: {$targetYear}");
        $this->info("Rumus: Total Pinjaman × (10/12) × {$rate}%");
        $this->info("Mode: " . ($isDryRun ? 'DRY RUN (Simulasi)' : 'PRODUCTION'));
        $this->newLine();

        // Check if already distributed for this year
        $existingCount = Transaction::where('type', Transaction::TYPE_SHU_REWARD)
            ->where('notes', 'like', "%{$targetYear}%")
            ->count();

        if ($existingCount > 0 && !$isDryRun) {
            $this->error("SHU untuk tahun {$targetYear} sudah pernah didistribusikan ({$existingCount} transaksi).");
            $this->error("Jalankan ulang dengan --dry-run untuk simulasi, atau pilih tahun berbeda.");
            return Command::FAILURE;
        }

        // Get all loans created in target year
        $loansData = Loan::whereYear('created_at', $targetYear)
            ->select('member_id', DB::raw('SUM(amount) as total_loan'))
            ->groupBy('member_id')
            ->get();

        if ($loansData->isEmpty()) {
            $this->warn("Tidak ada pinjaman yang tercatat di tahun {$targetYear}.");
            return Command::SUCCESS;
        }

        $this->info("Total anggota dengan pinjaman di {$targetYear}: {$loansData->count()}");
        $this->newLine();

        $results = [
            'success' => 0,
            'skipped' => 0,
            'total_shu' => 0,
            'total_loan_basis' => 0,
        ];

        $tableData = [];

        DB::beginTransaction();

        try {
            foreach ($loansData as $data) {
                $member = Member::find($data->member_id);
                if (!$member) {
                    $results['skipped']++;
                    continue;
                }

                $totalLoan = (float) $data->total_loan;

                // Rumus: Total Pinjaman × (10/12) × Rate%
                // (10/12) adalah faktor konversi tenor efektif
                $shu = floor($totalLoan * (10 / 12) * ($rate / 100));

                if ($shu <= 0) {
                    $results['skipped']++;
                    continue;
                }

                $tableData[] = [
                    $member->nik,
                    $member->name,
                    number_format($totalLoan, 0, ',', '.'),
                    number_format($shu, 0, ',', '.'),
                ];

                if (!$isDryRun) {
                    // Create transaction record
                    Transaction::create([
                        'member_id' => $member->id,
                        'loan_id' => null,
                        'transaction_date' => $distributionDate,
                        'type' => Transaction::TYPE_SHU_REWARD,
                        'amount_saving' => $shu,
                        'amount_principal' => 0,
                        'amount_interest' => 0,
                        'total_amount' => $shu,
                        'payment_method' => 'system_auto',
                        'notes' => "Pembagian SHU Tahun {$targetYear}",
                    ]);

                    // Update member savings balance
                    $member->increment('savings_balance', $shu);
                }

                $results['success']++;
                $results['total_shu'] += $shu;
                $results['total_loan_basis'] += $totalLoan;
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
                ['NIK', 'Nama', 'Total Pinjaman (Rp)', 'SHU (Rp)'],
                $tableData
            );
        }

        $this->newLine();
        $this->info("===========================================");
        $this->info(" HASIL PEMBAGIAN SHU");
        $this->info("===========================================");
        $this->info("Anggota Mendapat SHU: {$results['success']}");
        $this->info("Anggota Dilewati: {$results['skipped']}");
        $this->info("Basis Total Pinjaman: Rp " . number_format($results['total_loan_basis'], 0, ',', '.'));
        $this->info("Total SHU Dibagikan: Rp " . number_format($results['total_shu'], 0, ',', '.'));
        
        if ($isDryRun) {
            $this->newLine();
            $this->warn(">>> INI ADALAH SIMULASI. Tidak ada data yang disimpan. <<<");
            $this->warn(">>> Jalankan tanpa --dry-run untuk menyimpan ke database. <<<");
        } else {
            $this->newLine();
            $this->info("✅ SHU berhasil didistribusikan dan dicatat ke mutasi simpanan.");
        }

        return Command::SUCCESS;
    }
}
