<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        User::create([
            'name' => 'Admin Koperasi',
            'email' => 'admin@koperasi.com',
            'password' => Hash::make('12341234'),
            'role' => 'admin',
            'member_id' => null,
        ]);

        // Seed Koperasi Data (Members, Loans, Transactions)
        $this->call([
            KoperasiSeeder::class,
        ]);
    }
}
