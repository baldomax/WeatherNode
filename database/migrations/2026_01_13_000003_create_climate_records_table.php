<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * All-time climate records and averages by day of year
     */
    public function up(): void
    {
        Schema::create('climate_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');
            
            // Temperature records
            $table->decimal('record_high', 5, 2)->nullable();
            $table->year('record_high_year')->nullable();
            $table->decimal('record_low', 5, 2)->nullable();
            $table->year('record_low_year')->nullable();
            
            // Averages (calculated from historical data)
            $table->decimal('avg_high', 5, 2)->nullable();
            $table->decimal('avg_low', 5, 2)->nullable();
            $table->decimal('avg_temp', 5, 2)->nullable();
            $table->decimal('avg_precipitation', 6, 2)->nullable();
            
            // Wind records
            $table->decimal('record_wind', 6, 2)->nullable();
            $table->year('record_wind_year')->nullable();
            
            // Rain records
            $table->decimal('record_rain', 7, 2)->nullable();
            $table->year('record_rain_year')->nullable();
            
            $table->timestamps();
            
            $table->unique(['month', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('climate_records');
    }
};
