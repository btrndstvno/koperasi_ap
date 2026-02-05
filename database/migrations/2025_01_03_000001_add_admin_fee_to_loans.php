<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Menambahkan kolom admin_fee untuk sistem pinjaman.
     * Admin Fee = 1% dari Pokok Pinjaman
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('admin_fee', 15, 2)
                  ->default(0)
                  ->after('total_interest')
                  ->comment('Biaya admin 1% dari pokok pinjaman');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('admin_fee');
        });
    }
};
