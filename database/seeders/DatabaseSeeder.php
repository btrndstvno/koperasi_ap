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

        // Create Member User (link to first member)
        $firstMember = Member::first();
        if ($firstMember) {
            User::create([
                'name' => $firstMember->name,
                'email' => 'member1@koperasi.com',
                'password' => Hash::make('11111111'),
                'role' => 'member',
                'member_id' => $firstMember->id,
            ]);
        }
    }
}
