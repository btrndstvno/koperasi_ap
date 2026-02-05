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
     * LOAN APPROVAL WORKFLOW:
     * - Sebelumnya: ['active', 'paid']
     * - Sekarang: ['pending', 'active', 'paid', 'rejected']
     * - Default: 'pending' (draft, belum dicairkan)
     */
    public function up(): void
    {
        // Alter ENUM to include new statuses (MySQL specific)
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending', 'active', 'paid', 'rejected') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback to original ENUM
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('active', 'paid') DEFAULT 'active'");
    }
};
