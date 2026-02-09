<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class KoperasiSeeder extends Seeder
{
    public function run(): void
    {
        // 1. SEED MEMBERS & USERS
        $this->command->info('Seeding Members & User Accounts...');
        
        $departments = ['PRODUKSI', 'HRD', 'FINANCE', 'MARKETING', 'IT', 'WAREHOUSE', 'QC', 'MAINTENANCE'];
        $members = [];
        $nikCounter = 1001;

        foreach ($departments as $dept) {
            $count = rand(5, 8); // Jumlah member per dept
            $groupTags = ['Manager', 'Bangunan', 'CSD', 'Office'];
            
            for ($i = 0; $i < $count; $i++) {
                $nik = 'EMP' . str_pad($nikCounter++, 4, '0', STR_PAD_LEFT);
                $name = fake()->name();

                // A. Buat Data Member
                $member = Member::create([
                    'nik' => $nik,
                    'name' => $name,
                    'group_tag' => fake()->randomElement($groupTags),
                    'csd' => fake()->randomElement(['CSD-A', 'CSD-B', 'CSD-C', null]),
                    'dept' => $dept,
                    'employee_status' => fake()->randomElement(['monthly', 'monthly', 'monthly', 'weekly']), 
                    'savings_balance' => 0, // Nanti diupdate otomatis dari transaksi
                ]);

                $members[] = $member;
            }
        }

        $this->command->info('Created ' . count($members) . ' members.');

        // 2. SEED SAVINGS (Generating 6 Months History)
        // Ini kuncinya: Kita buat transaksi rutin mundur 6 bulan ke belakang
        $this->command->info('Seeding Historical Savings (6 Months)...');
        
        foreach ($members as $member) {
            $totalSavings = 0;
            
            // Loop dari 6 bulan lalu sampai bulan ini (0)
            for ($i = 6; $i >= 0; $i--) {
                $trxDate = Carbon::now()->subMonths($i)->startOfMonth(); // Tgl 1 setiap bulan
                $amount = 20000; // Simpanan Wajib 20rb/bulan

                Transaction::create([
                    'member_id' => $member->id,
                    'transaction_date' => $trxDate,
                    'type' => 'saving_deposit',
                    'amount_saving' => $amount,
                    'amount_principal' => 0,
                    'amount_interest' => 0, 
                    'total_amount' => $amount,
                    'payment_method' => 'salary_deduction',
                    'notes' => 'Simpanan Wajib ' . $trxDate->format('M Y'),
                ]);

                $totalSavings += $amount;
            }
            
            // Update saldo terakhir di tabel member
            $member->update(['savings_balance' => $totalSavings]);
        }

        // 3. SEED LOANS (Pinjaman)
        $this->command->info('Seeding Loans...');
        $membersWithLoans = collect($members)->random(intval(count($members) * 0.3));
        
        foreach ($membersWithLoans as $member) {
            $amount = fake()->randomElement([1000000, 2000000, 5000000]);
            $duration = 10;
            
            // Anggaplah pinjaman ini dibuat 3 bulan yang lalu
            $loanStartDate = Carbon::now()->subMonths(3)->startOfMonth();
            $paidMonths = 3; // Sudah bayar 3x
            
            $monthlyPrincipal = $amount / $duration;
            $remainingPrincipal = $amount - ($monthlyPrincipal * $paidMonths);
            
            $loan = Loan::create([
                'member_id' => $member->id,
                'amount' => $amount,
                'interest_rate' => 10,
                'duration' => $duration,
                'monthly_installment' => $monthlyPrincipal,
                'remaining_principal' => max(0, $remainingPrincipal),
                'status' => 'active',
                'created_at' => $loanStartDate, // Penting untuk laporan historis
            ]);

            // Buat transaksi bayar cicilan 3 bulan terakhir
            for ($m = 0; $m < $paidMonths; $m++) {
                $payDate = $loanStartDate->copy()->addMonths($m + 1); // Bayar bulan depannya
                
                Transaction::create([
                    'member_id' => $member->id,
                    'loan_id' => $loan->id,
                    'transaction_date' => $payDate,
                    'type' => 'loan_repayment',
                    'amount_saving' => 0,
                    'amount_principal' => $monthlyPrincipal,
                    'amount_interest' => 0,
                    'total_amount' => $monthlyPrincipal,
                    'payment_method' => 'salary_deduction',
                    'notes' => 'Angsuran ke-' . ($m + 1),
                ]);
            }
        }
        
        // 4. Create ADMIN
        User::firstOrCreate(
            ['email' => 'admin@koperasi.com'],
            [
                'name' => 'Admin Koperasi',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'member_id' => null,
            ]
        );
        
        $this->command->info('Seeding Completed!');
    }
}