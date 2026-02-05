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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('nik')->unique()->comment('Nomor Induk Karyawan');
            $table->string('name');
            $table->string('dept')->comment('Departemen/Bagian');
            $table->enum('employee_status', ['monthly', 'weekly'])->default('monthly')->comment('Status Karyawan: Bulanan/Mingguan');
            $table->decimal('savings_balance', 15, 2)->default(0)->comment('Saldo Simpanan');
            $table->timestamps();

            // Index untuk pencarian cepat
            $table->index('dept');
            $table->index('employee_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
