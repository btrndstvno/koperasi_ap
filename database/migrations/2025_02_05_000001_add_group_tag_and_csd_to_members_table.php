<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Menambahkan kolom:
     * - group_tag: Kategori anggota (Manager, Bangunan, Karyawan)
     * - csd: Posisi/jabatan anggota
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Group Tag: Kategori besar anggota
            $table->enum('group_tag', ['Manager', 'Bangunan', 'Karyawan'])
                  ->default('Karyawan')
                  ->after('name')
                  ->comment('Kategori grup anggota');

            // CSD: Posisi/jabatan
            $table->string('csd', 100)
                  ->nullable()
                  ->after('group_tag')
                  ->comment('Posisi/Jabatan anggota');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['group_tag', 'csd']);
        });
    }
};
