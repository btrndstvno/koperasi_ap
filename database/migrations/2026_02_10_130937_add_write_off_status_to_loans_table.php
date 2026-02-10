<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Merubah kolom status agar menerima 'write_off'
        // Kita mendefinisikan ulang seluruh enum yang diizinkan
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending', 'active', 'paid', 'rejected', 'write_off') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Mengembalikan ke kondisi sebelumnya (tanpa write_off)
        // Hati-hati: ini akan gagal jika ada data yang sudah berstatus 'write_off'
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending', 'active', 'paid', 'rejected') NOT NULL DEFAULT 'pending'");
    }
};