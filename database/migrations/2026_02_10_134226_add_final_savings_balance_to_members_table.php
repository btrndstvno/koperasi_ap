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
        Schema::table('members', function (Blueprint $table) {
            // Kolom untuk menyimpan saldo terakhir sebelum dinolkan
            $table->decimal('final_savings_balance', 15, 2)->default(0)->after('savings_balance');
        });
    }

    public function down()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('final_savings_balance');
        });
    }
};
