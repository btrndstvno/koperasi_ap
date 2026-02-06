<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User; // <--- Jangan lupa import ini
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash; // <--- Import Hash
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
            $count = rand(5, 10); // Kurangi dikit biar gak kebanyakan nunggu
            $groupTags = ['Manager', 'Bangunan', 'CSD', 'Office'];
            
            for ($i = 0; $i < $count; $i++) {
                $nik = 'EMP' . str_pad($nikCounter++, 4, '0', STR_PAD_LEFT);
                $email = strtolower($nik) . '@koperasi.com'; // Email dari NIK
                $name = fake()->name();

                // A. Buat Data Member
                $member = Member::create([
                    'nik' => $nik,
                    'name' => $name,
                    'email' => $email, // Isi kolom email di table member
                    'group_tag' => fake()->randomElement($groupTags),
                    'csd' => fake()->randomElement(['CSD-A', 'CSD-B', 'CSD-C', null]),
                    'dept' => $dept,
                    'employee_status' => fake()->randomElement(['monthly', 'monthly', 'monthly', 'weekly']), 
                    'savings_balance' => fake()->randomFloat(2, 100000, 2000000),
                ]);

                // B. Buat Akun Login (User) untuk Member ini
                User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make('password'), // Password default: 'password'
                    'role' => 'member',
                    'member_id' => $member->id,
                ]);

                $members[] = $member;
            }
        }

        $this->command->info('Created ' . count($members) . ' members with login accounts.');

        // 2. SEED LOANS (Sama seperti sebelumnya)
        $this->command->info('Seeding Loans...');
        $membersWithLoans = collect($members)->random(intval(count($members) * 0.4));
        
        foreach ($membersWithLoans as $member) {
            $amount = fake()->randomElement([1000000, 2000000, 3000000]);
            $duration = 10; // Fix 10 bulan biar gampang cek report
            $paidMonths = fake()->numberBetween(0, 5);
            
            $monthlyPrincipal = $amount / $duration;
            $remainingPrincipal = $amount - ($monthlyPrincipal * $paidMonths);
            
            $loan = Loan::create([
                'member_id' => $member->id,
                'amount' => $amount,
                'interest_rate' => 10,
                'duration' => $duration,
                'monthly_installment' => $monthlyPrincipal,
                'remaining_principal' => max(0, $remainingPrincipal),
                'status' => $remainingPrincipal > 0 ? 'active' : 'paid',
            ]);

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

        // 3. SEED SAVINGS (Sama seperti sebelumnya)
        $this->command->info('Seeding Saving Transactions...');
        foreach ($members as $member) {
            Transaction::create([
                'member_id' => $member->id,
                'transaction_date' => Carbon::now()->subMonth(),
                'type' => 'saving_deposit',
                'amount_saving' => 10000,
                'amount_principal' => 0,
                'amount_interest' => 0,
                'total_amount' => 10000,
                'payment_method' => 'salary_deduction',
                'notes' => 'Iuran Wajib',
            ]);
        }
        
        // 4. Create ADMIN Account
        User::firstOrCreate([
            'name' => 'Admin Koperasi',
            'email' => 'admin@koperasi.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'member_id' => null,
        ]);
        
        $this->command->info('ALL DONE! Admin: admin@koperasi.com | Member: emp1001@koperasi.com (Pass: password)');
    }
}