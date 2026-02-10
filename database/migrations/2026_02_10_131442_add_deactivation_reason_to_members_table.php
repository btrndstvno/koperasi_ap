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
            $table->text('deactivation_reason')->nullable()->after('is_active');
            $table->date('deactivation_date')->nullable()->after('deactivation_reason');
        });
    }

    public function down()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['deactivation_reason', 'deactivation_date']);
        });
    }
};
