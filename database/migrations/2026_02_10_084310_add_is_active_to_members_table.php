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
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('employee_status')->comment('Status anggota aktif/nonaktif');
            $table->timestamp('deactivated_at')->nullable()->after('is_active')->comment('Tanggal nonaktifkan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'deactivated_at']);
        });
    }
};
