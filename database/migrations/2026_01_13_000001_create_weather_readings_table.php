<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores individual weather readings from Ecowitt station
     */
    public function up(): void
    {
        Schema::create('weather_readings', function (Blueprint $table) {
            $table->id();
            $table->timestamp('recorded_at')->index();
            
            // Temperature data
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('feels_like', 5, 2)->nullable();
            $table->decimal('dew_point', 5, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();
            $table->decimal('indoor_temperature', 5, 2)->nullable();
            $table->decimal('indoor_humidity', 5, 2)->nullable();
            
            // Pressure
            $table->decimal('pressure_abs', 7, 2)->nullable();
            $table->decimal('pressure_rel', 7, 2)->nullable();
            
            // Wind
            $table->decimal('wind_speed', 6, 2)->nullable();
            $table->decimal('wind_gust', 6, 2)->nullable();
            $table->unsignedSmallInteger('wind_direction')->nullable();
            $table->decimal('wind_speed_avg_10m', 6, 2)->nullable();
            
            // Rain
            $table->decimal('rain_rate', 7, 2)->nullable();
            $table->decimal('rain_hourly', 7, 2)->nullable();
            $table->decimal('rain_daily', 7, 2)->nullable();
            $table->decimal('rain_weekly', 7, 2)->nullable();
            $table->decimal('rain_monthly', 7, 2)->nullable();
            $table->decimal('rain_yearly', 7, 2)->nullable();
            
            // Solar & UV
            $table->decimal('uv_index', 4, 1)->nullable();
            $table->decimal('solar_radiation', 7, 2)->nullable();
            $table->unsignedInteger('lux')->nullable();
            
            // Extra sensors
            $table->decimal('soil_temperature', 5, 2)->nullable();
            $table->unsignedTinyInteger('soil_moisture')->nullable();
            $table->decimal('water_temperature', 5, 2)->nullable();
            
            // Lightning
            $table->unsignedInteger('lightning_distance')->nullable();
            $table->timestamp('lightning_time')->nullable();
            $table->unsignedInteger('lightning_count_daily')->nullable();
            
            // Battery levels (for sensor health monitoring)
            $table->json('battery_status')->nullable();
            
            $table->timestamps();
            
            // Index for efficient time-based queries
            $table->index(['recorded_at', 'temperature']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_readings');
    }
};
