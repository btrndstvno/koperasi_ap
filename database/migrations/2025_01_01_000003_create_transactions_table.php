<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('loan_id')->nullable()->constrained('loans')->onDelete('set null')->comment('FK ke Pinjaman (nullable untuk transaksi simpanan murni)');
            $table->date('transaction_date')->comment('Tanggal Transaksi');
            
            // Tipe Transaksi
            $table->enum('type', [
                'saving_deposit',    // Setoran Simpanan
                'loan_repayment',    // Pembayaran Pinjaman (Pokok + Bunga)
                'saving_withdraw',   // Penarikan Simpanan
                'interest_revenue'   // Pendapatan Bunga/Jasa
            ])->comment('Jenis Transaksi');

            // Kolom Finansial - Dipisah untuk kejelasan akuntansi
            $table->decimal('amount_saving', 15, 2)->default(0)->comment('Uang masuk ke Saldo Simpanan');
            $table->decimal('amount_principal', 15, 2)->default(0)->comment('Uang mengurangi Sisa Pokok Pinjaman');
            $table->decimal('amount_interest', 15, 2)->default(0)->comment('Uang Bunga/Jasa Pinjaman - Input Manual');
            $table->decimal('total_amount', 15, 2)->default(0)->comment('Total uang fisik = saving + principal + interest');

            $table->text('notes')->nullable()->comment('Catatan Transaksi');
            $table->timestamps();

            // Index untuk laporan dan filter
            $table->index('transaction_date');
            $table->index('type');
            $table->index(['member_id', 'transaction_date']);
            $table->index(['loan_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
