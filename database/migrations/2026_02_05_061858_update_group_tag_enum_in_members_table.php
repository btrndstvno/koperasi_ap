<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * PERUBAHAN GROUP TAG:
     * - Sebelumnya: ['Manager', 'Bangunan', 'Karyawan']
     * - Sekarang: ['Manager', 'Bangunan', 'CSD', 'Office']
     * - Data 'Karyawan' akan diubah default ke 'Office'
     */
    public function up(): void
    {
        // Step 1: Alter ENUM column to include new values (MySQL specific)
        DB::statement("ALTER TABLE members MODIFY COLUMN group_tag ENUM('Manager', 'Bangunan', 'Karyawan', 'CSD', 'Office') DEFAULT 'Office'");

        // Step 2: Ubah data 'Karyawan' menjadi 'Office' (default)
        DB::table('members')
            ->where('group_tag', 'Karyawan')
            ->update(['group_tag' => 'Office']);

        // Step 3: Remove 'Karyawan' from ENUM (optional - keep for backward compatibility)
        // Jika ingin hapus 'Karyawan' dari enum, uncomment baris berikut:
        // DB::statement("ALTER TABLE members MODIFY COLUMN group_tag ENUM('Manager', 'Bangunan', 'CSD', 'Office') DEFAULT 'Office'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback: Ubah 'CSD' dan 'Office' kembali ke 'Karyawan'
        DB::table('members')
            ->whereIn('group_tag', ['CSD', 'Office'])
            ->update(['group_tag' => 'Karyawan']);

        // Rollback ENUM
        DB::statement("ALTER TABLE members MODIFY COLUMN group_tag ENUM('Manager', 'Bangunan', 'Karyawan') DEFAULT 'Karyawan'");
    }
};
