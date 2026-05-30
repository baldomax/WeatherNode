<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Daily aggregated weather statistics
     */
    public function up(): void
    {
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            
            // Temperature
            $table->decimal('temp_high', 5, 2)->nullable();
            $table->time('temp_high_time')->nullable();
            $table->decimal('temp_low', 5, 2)->nullable();
            $table->time('temp_low_time')->nullable();
            $table->decimal('temp_avg', 5, 2)->nullable();
            
            // Humidity
            $table->unsignedTinyInteger('humidity_high')->nullable();
            $table->unsignedTinyInteger('humidity_low')->nullable();
            $table->unsignedTinyInteger('humidity_avg')->nullable();
            
            // Pressure
            $table->decimal('pressure_high', 7, 2)->nullable();
            $table->decimal('pressure_low', 7, 2)->nullable();
            
            // Wind
            $table->decimal('wind_max', 6, 2)->nullable();
            $table->time('wind_max_time')->nullable();
            $table->decimal('wind_avg', 6, 2)->nullable();
            $table->unsignedSmallInteger('wind_dominant_direction')->nullable();
            
            // Rain
            $table->decimal('rain_total', 7, 2)->nullable();
            $table->decimal('rain_rate_max', 7, 2)->nullable();
            
            // Solar & UV
            $table->decimal('uv_max', 4, 1)->nullable();
            $table->decimal('solar_max', 7, 2)->nullable();
            $table->decimal('solar_hours', 5, 2)->nullable(); // Estimated sunshine hours
            
            // Calculated values
            $table->decimal('heating_degree_days', 6, 2)->nullable();
            $table->decimal('cooling_degree_days', 6, 2)->nullable();
            
            $table->timestamps();
            
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_summaries');
    }
};
