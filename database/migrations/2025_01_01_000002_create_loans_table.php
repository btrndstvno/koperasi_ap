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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->decimal('amount', 15, 2)->comment('Pokok Pinjaman Awal');
            $table->float('interest_rate')->default(0)->comment('Persentase Bunga per Bulan (%)');
            $table->integer('duration')->comment('Durasi Pinjaman dalam Bulan');
            $table->decimal('remaining_principal', 15, 2)->comment('Sisa Pokok Utang');
            $table->enum('status', ['active', 'paid'])->default('active')->comment('Status Pinjaman');
            $table->timestamps();

            // Index untuk filter pinjaman aktif
            $table->index('status');
            $table->index(['member_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
