<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Setting;
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
                            {--year= : Tahun yang dihitung (default: tahun lalu)}
                            {--rate= : Persentase SHU (default dari pengaturan sistem)}
                            {--dry-run : Simulasi tanpa menyimpan ke database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Distribusikan SHU (Sisa Hasil Usaha) tahunan berdasarkan proporsi cicilan pinjaman';

    /**
     * Execute the console command.
     * 
     * RUMUS: 
     * SHU = Total Pinjaman × (Jumlah Cicilan di Tahun Tersebut / 10) × Rate%
     * 
     * Contoh:
     * - Pinjaman Rp 10.000.000, mulai Mei 2028 (bulan ke-5), tenor 10 bulan
     * - Cicilan di 2028 = 12 - 5 + 1 = 8 bulan (Mei s/d Desember)
     * - SHU 2028 = 10.000.000 × (8/10) × 5% = Rp 400.000
     * 
     * - Sisa cicilan di 2029 = 10 - 8 = 2 bulan (Januari & Februari)
     * - SHU 2029 = 10.000.000 × (2/10) × 5% = Rp 100.000
     */
    public function handle()
    {
        $targetYear = (int) ($this->option('year') ?? (now()->year - 1));
        $isDryRun = $this->option('dry-run');

        // Get rate from option or from settings (use historical rate at end of target year)
        $targetYearEnd = Carbon::create($targetYear, 12, 31)->toDateString();
        
        if ($this->option('rate') !== null) {
            $rate = (float) $this->option('rate');
        } else {
            // Use SHU rate from settings at the end of target year
            $rate = Setting::getRateAtDate('shu_rate', $targetYearEnd) ?? Setting::getShuRate();
        }

        $distributionDate = Carbon::create(now()->year, 1, 1)->toDateString();

        $this->info("===========================================");
        $this->info(" PEMBAGIAN SHU (SISA HASIL USAHA)");
        $this->info("===========================================");
        $this->info("Tahun Perhitungan: {$targetYear}");
        $this->info("Rumus: Pinjaman × (Cicilan di Tahun / 10) × {$rate}%");
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

        // Get all loans that have installments in the target year
        // A loan has installments in target year if:
        // 1. Started in target year, OR
        // 2. Started before target year AND end date >= start of target year
        $targetYearStart = Carbon::create($targetYear, 1, 1)->startOfDay();
        $targetYearEnd = Carbon::create($targetYear, 12, 31)->endOfDay();

        $loans = Loan::whereIn('status', [Loan::STATUS_ACTIVE, Loan::STATUS_PAID])
            ->where('created_at', '<=', $targetYearEnd) // Started on or before end of target year
            ->get();

        if ($loans->isEmpty()) {
            $this->warn("Tidak ada pinjaman yang ditemukan untuk tahun {$targetYear}.");
            return Command::SUCCESS;
        }

        // Calculate SHU per member
        $memberSHU = [];
        $loanDetails = [];

        foreach ($loans as $loan) {
            $loanStartDate = Carbon::parse($loan->created_at);
            $loanStartMonth = $loanStartDate->month;
            $loanStartYear = $loanStartDate->year;
            $duration = $loan->duration;

            // Calculate loan end date
            $loanEndDate = $loanStartDate->copy()->addMonths($duration - 1); // -1 because first month counts

            // Check if this loan has installments in target year
            if ($loanStartYear > $targetYear) {
                // Loan starts after target year, skip
                continue;
            }

            if ($loanEndDate->year < $targetYear) {
                // Loan ended before target year, skip
                continue;
            }

            // Calculate number of installments in target year
            $installmentsInYear = $this->calculateInstallmentsInYear(
                $loanStartYear,
                $loanStartMonth,
                $duration,
                $targetYear
            );

            if ($installmentsInYear <= 0) {
                continue;
            }

            // Calculate SHU contribution for this loan
            // SHU = amount × (installmentsInYear / 10) × rate%
            $shuContribution = $loan->amount * ($installmentsInYear / 10) * ($rate / 100);

            $memberId = $loan->member_id;
            if (!isset($memberSHU[$memberId])) {
                $memberSHU[$memberId] = 0;
                $loanDetails[$memberId] = [];
            }
            $memberSHU[$memberId] += $shuContribution;
            $loanDetails[$memberId][] = [
                'loan_id' => $loan->id,
                'amount' => $loan->amount,
                'start_date' => $loanStartDate->format('Y-m'),
                'duration' => $duration,
                'installments_in_year' => $installmentsInYear,
                'shu_contribution' => $shuContribution,
            ];
        }

        if (empty($memberSHU)) {
            $this->warn("Tidak ada pinjaman dengan cicilan di tahun {$targetYear}.");
            return Command::SUCCESS;
        }

        $this->info("Total anggota dengan cicilan di {$targetYear}: " . count($memberSHU));
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
            foreach ($memberSHU as $memberId => $totalSHU) {
                $member = Member::find($memberId);
                if (!$member) {
                    $results['skipped']++;
                    continue;
                }

                $shu = floor($totalSHU);

                if ($shu <= 0) {
                    $results['skipped']++;
                    continue;
                }

                // Calculate total loan basis for this member
                $loanBasis = array_sum(array_column($loanDetails[$memberId], 'amount'));

                // Build detail notes
                $detailNotes = [];
                foreach ($loanDetails[$memberId] as $detail) {
                    $detailNotes[] = sprintf(
                        "Pinjaman %s (Rp%s × %d/10 bln)",
                        $detail['start_date'],
                        number_format($detail['amount'], 0, ',', '.'),
                        $detail['installments_in_year']
                    );
                }

                $tableData[] = [
                    $member->nik,
                    $member->name,
                    number_format($loanBasis, 0, ',', '.'),
                    implode('; ', array_map(fn($d) => $d['installments_in_year'] . ' bln', $loanDetails[$memberId])),
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
                        'notes' => "Pembagian SHU Tahun {$targetYear} - " . implode('; ', $detailNotes),
                    ]);

                    // Update member savings balance
                    $member->increment('savings_balance', $shu);
                }

                $results['success']++;
                $results['total_shu'] += $shu;
                $results['total_loan_basis'] += $loanBasis;
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
                ['NIK', 'Nama', 'Total Pinjaman (Rp)', 'Cicilan di Tahun', 'SHU (Rp)'],
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
            $this->info("SHU berhasil didistribusikan dan dicatat ke mutasi simpanan.");
        }

        return Command::SUCCESS;
    }

    /**
     * Calculate number of installments in a specific year.
     * 
     * @param int $loanStartYear Year the loan started
     * @param int $loanStartMonth Month the loan started (1-12)
     * @param int $duration Total loan duration in months
     * @param int $targetYear Year to calculate installments for
     * @return int Number of installments in target year
     */
    private function calculateInstallmentsInYear(int $loanStartYear, int $loanStartMonth, int $duration, int $targetYear): int
    {
        // Calculate first and last installment month in absolute terms
        // Using months since epoch for easier calculation
        $firstInstallmentAbsolute = ($loanStartYear * 12) + $loanStartMonth;
        $lastInstallmentAbsolute = $firstInstallmentAbsolute + $duration - 1;

        // Target year boundaries in absolute months
        $targetYearFirstMonth = ($targetYear * 12) + 1;  // January of target year
        $targetYearLastMonth = ($targetYear * 12) + 12;  // December of target year

        // Find overlap between loan period and target year
        $overlapStart = max($firstInstallmentAbsolute, $targetYearFirstMonth);
        $overlapEnd = min($lastInstallmentAbsolute, $targetYearLastMonth);

        // Calculate number of installments in overlap period
        $installmentsInYear = max(0, $overlapEnd - $overlapStart + 1);

        return $installmentsInYear;
    }
}