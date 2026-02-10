<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Mengubah kolom status untuk support 'write_off'
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending', 'active', 'paid', 'rejected', 'write_off') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
