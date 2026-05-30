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
        Schema::table('daily_summaries', function (Blueprint $table) {
            $table->decimal('pressure_avg', 7, 2)->nullable()->after('pressure_low');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_summaries', function (Blueprint $table) {
            $table->dropColumn('pressure_avg');
        });
    }
};
