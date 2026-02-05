<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Ubah kolom 'type' dari ENUM menjadi VARCHAR(50)
        // Agar bisa menerima 'saving_interest', 'shu_reward', dll tanpa error
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type VARCHAR(50) NOT NULL");
    }

    public function down()
    {
        // Kembalikan ke Enum jika rollback (Opsional, sesuaikan dengan nilai lamamu)
        // DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('loan_repayment', 'saving_deposit') NOT NULL");
    }
};