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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insert default settings
        DB::table('settings')->insert([
            [
                'key' => 'saving_interest_rate',
                'value' => '0.5',
                'description' => 'Bunga Tabungan per bulan (%)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'shu_rate',
                'value' => '5',
                'description' => 'Bunga SHU tahunan (%)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'loan_interest_rate',
                'value' => '1',
                'description' => 'Bunga Pinjaman (%)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create history table to track rate changes
        Schema::create('setting_histories', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('old_value');
            $table->string('new_value');
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('effective_from');
            $table->timestamps();

            $table->index('key');
            $table->index('effective_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setting_histories');
        Schema::dropIfExists('settings');
    }
};
