<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Ubah kolom 'payment_method' menjadi VARCHAR(50)
        // Supaya bisa menerima 'system_auto'
        DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_method VARCHAR(50) NOT NULL");
    }

    public function down()
    {
        // Opsional: Kembalikan ke Enum jika rollback
        // DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_method ENUM('cash', 'transfer', 'salary_deduction') NOT NULL");
    }
};