<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Refactoring: Sistem "Bunga Potong di Awal" (Interest Deducted Upfront)
     * - loans.monthly_installment: Cicilan tetap (Pokok/Tenor) karena bunga sudah dipotong di awal
     * - transactions.payment_method: Metode pembayaran untuk tracking
     */
    public function up(): void
    {
        // Update tabel loans
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('monthly_installment', 15, 2)
                  ->default(0)
                  ->after('remaining_principal')
                  ->comment('Cicilan tetap bulanan (Pokok/Tenor) - Bunga sudah lunas di awal');
            
            $table->decimal('total_interest', 15, 2)
                  ->default(0)
                  ->after('monthly_installment')
                  ->comment('Total bunga yang dipotong di awal pencairan');
            
            $table->decimal('disbursed_amount', 15, 2)
                  ->default(0)
                  ->after('total_interest')
                  ->comment('Uang cair bersih (Pokok - Total Bunga)');
        });

        // Update tabel transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('payment_method', [
                'payroll_deduction',  // Potong gaji (input massal)
                'cash',               // Bayar tunai
                'transfer',           // Transfer bank
                'deduction'           // Potongan otomatis (bunga di awal)
            ])->default('payroll_deduction')
              ->after('total_amount')
              ->comment('Metode pembayaran transaksi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['monthly_installment', 'total_interest', 'disbursed_amount']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
