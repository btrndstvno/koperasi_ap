<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class KoperasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. SEED MEMBERS
        $this->command->info('Seeding Members...');
        
        $departments = ['PRODUKSI', 'HRD', 'FINANCE', 'MARKETING', 'IT', 'WAREHOUSE', 'QC', 'MAINTENANCE'];
        $members = [];
        $nikCounter = 1001;

        foreach ($departments as $dept) {
            $count = rand(5, 15);
            $groupTags = ['Manager', 'Bangunan', 'CSD', 'Office'];
            
            for ($i = 0; $i < $count; $i++) {
                $members[] = Member::create([
                    'nik' => 'EMP' . str_pad($nikCounter++, 4, '0', STR_PAD_LEFT),
                    'name' => fake()->name(),
                    'group_tag' => fake()->randomElement($groupTags),
                    'csd' => fake()->randomElement(['CSD-A', 'CSD-B', 'CSD-C', null]),
                    'dept' => $dept,
                    'employee_status' => fake()->randomElement(['monthly', 'monthly', 'monthly', 'weekly']), 
                    'savings_balance' => fake()->randomFloat(2, 100000, 2000000),
                ]);
            }
        }

        $this->command->info('Created ' . count($members) . ' members.');

        // 2. SEED LOANS
        $this->command->info('Seeding Loans...');
        
        // Ambil 40% member secara acak untuk diberi pinjaman
        $membersWithLoans = collect($members)->random(intval(count($members) * 0.4));
        
        foreach ($membersWithLoans as $member) {
            $amount = fake()->randomElement([1000000, 2000000, 3000000, 5000000, 10000000]);
            $duration = fake()->randomElement([6, 10, 12, 18, 24]);
            $paidMonths = fake()->numberBetween(0, $duration - 1);
            
            // Hitung cicilan pokok
            $monthlyPrincipal = $amount / $duration;
            $remainingPrincipal = $amount - ($monthlyPrincipal * $paidMonths);
            
            $loan = Loan::create([
                'member_id' => $member->id,
                'amount' => $amount,
                'interest_rate' => fake()->randomElement([1, 1.5, 2]),
                'duration' => $duration,
                'monthly_installment' => $monthlyPrincipal, // PENTING: Jangan lupa disimpan
                'remaining_principal' => max(0, $remainingPrincipal),
                'status' => $remainingPrincipal > 0 ? 'active' : 'paid',
            ]);

            // Buat transaksi pembayaran untuk bulan yang sudah lewat
            for ($month = 1; $month <= $paidMonths; $month++) {
                $transactionDate = Carbon::now()->subMonths($paidMonths - $month + 1)->startOfMonth();
                
                Transaction::create([
                    'member_id' => $member->id,
                    'loan_id' => $loan->id,
                    'transaction_date' => $transactionDate,
                    'type' => 'loan_repayment',
                    'amount_saving' => 0,
                    'amount_principal' => $monthlyPrincipal,
                    'amount_interest' => 0, 
                    'total_amount' => $monthlyPrincipal,
                    'payment_method' => 'salary_deduction',
                    'notes' => 'Angsuran bulan ke-' . $month,
                ]);
            }
        }

        $this->command->info('Created ' . $membersWithLoans->count() . ' loans with transactions.');

        // 3. SEED SAVINGS TRANSACTIONS
        $this->command->info('Seeding Saving Transactions...');
        
        foreach ($members as $member) {
            $months = rand(3, 6);
            
            for ($month = 1; $month <= $months; $month++) {
                $transactionDate = Carbon::now()->subMonths($months - $month + 1)->startOfMonth();
                $savingAmount = fake()->randomElement([10000, 20000, 50000]);
                
                Transaction::create([
                    'member_id' => $member->id,
                    'loan_id' => null,
                    'transaction_date' => $transactionDate,
                    'type' => 'saving_deposit',
                    'amount_saving' => $savingAmount,
                    'amount_principal' => 0,
                    'amount_interest' => 0,
                    'total_amount' => $savingAmount,
                    'payment_method' => fake()->randomElement(['salary_deduction', 'salary_deduction', 'cash']),
                    'notes' => 'Iuran bulanan',
                ]);
            }
        }

        $this->command->info('Seeding completed!');
        $this->command->info('Total Members: ' . Member::count());
        $this->command->info('Total Loans: ' . Loan::count());
        $this->command->info('Total Transactions: ' . Transaction::count());
    }
}