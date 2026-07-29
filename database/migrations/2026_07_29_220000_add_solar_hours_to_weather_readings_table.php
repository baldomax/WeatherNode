<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('weather_readings', 'solar_hours')) {
            Schema::table('weather_readings', function (Blueprint $table) {
                $table->decimal('solar_hours', 5, 2)->nullable()->after('solar_radiation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('weather_readings', 'solar_hours')) {
            Schema::table('weather_readings', function (Blueprint $table) {
                $table->dropColumn('solar_hours');
            });
        }
    }
};
