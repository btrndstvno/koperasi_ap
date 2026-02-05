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
        Schema::table('users', function (Blueprint $table) {
            // Menambahkan kolom role (admin/member)
            $table->string('role')->default('member')->after('email'); 
            
            // Menambahkan relasi ke member (bisa null jika user itu murni admin)
            $table->foreignId('member_id')->nullable()->after('role')->constrained('members')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->dropColumn(['role', 'member_id']);
        });
    }
};