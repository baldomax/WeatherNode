<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds all possible Ecowitt sensor fields
     */
    public function up(): void
    {
        // Add humidity_indoor first (if it doesn't exist)
        if (!Schema::hasColumn('weather_readings', 'humidity_indoor')) {
            Schema::table('weather_readings', function (Blueprint $table) {
                $table->decimal('humidity_indoor', 5, 2)->nullable()->after('indoor_humidity');
            });
        }
        
        // Then add temperature_indoor after humidity_indoor (if it doesn't exist)
        if (!Schema::hasColumn('weather_readings', 'temperature_indoor')) {
            Schema::table('weather_readings', function (Blueprint $table) {
                $table->decimal('temperature_indoor', 5, 2)->nullable()->after('humidity_indoor');
            });
        }
        
        // Continue with rest of the fields
        Schema::table('weather_readings', function (Blueprint $table) {
            
            // Extra temperature sensors (up to 8)
            for ($i = 1; $i <= 8; $i++) {
                if (!Schema::hasColumn('weather_readings', "temp_{$i}")) {
                    $table->decimal("temp_{$i}", 5, 2)->nullable();
                }
            }
            
            // Extra humidity sensors (up to 8)
            for ($i = 1; $i <= 8; $i++) {
                if (!Schema::hasColumn('weather_readings', "humidity_{$i}")) {
                    $table->unsignedTinyInteger("humidity_{$i}")->nullable();
                }
            }
            
            // Soil moisture sensors (up to 8)
            for ($i = 1; $i <= 8; $i++) {
                if (!Schema::hasColumn('weather_readings', "soil_moisture_{$i}")) {
                    $table->unsignedTinyInteger("soil_moisture_{$i}")->nullable();
                }
            }
            
            // Soil temperature sensors (up to 8)
            for ($i = 1; $i <= 8; $i++) {
                if (!Schema::hasColumn('weather_readings', "soil_temp_{$i}")) {
                    $table->decimal("soil_temp_{$i}", 5, 2)->nullable();
                }
            }
            
            // Leaf wetness sensors (up to 8)
            for ($i = 1; $i <= 8; $i++) {
                if (!Schema::hasColumn('weather_readings', "leaf_wetness_{$i}")) {
                    $table->unsignedTinyInteger("leaf_wetness_{$i}")->nullable();
                }
            }
            
            // PM2.5 air quality sensors (up to 4 channels)
            for ($i = 1; $i <= 4; $i++) {
                if (!Schema::hasColumn('weather_readings', "pm25_ch{$i}")) {
                    $table->decimal("pm25_ch{$i}", 6, 1)->nullable();
                }
                if (!Schema::hasColumn('weather_readings', "pm25_avg_24h_ch{$i}")) {
                    $table->decimal("pm25_avg_24h_ch{$i}", 6, 1)->nullable();
                }
            }
            
            // PM10 sensors
            if (!Schema::hasColumn('weather_readings', 'pm10')) {
                $table->decimal('pm10', 6, 1)->nullable();
            }
            if (!Schema::hasColumn('weather_readings', 'pm10_avg_24h')) {
                $table->decimal('pm10_avg_24h', 6, 1)->nullable();
            }
            
            // CO2 sensor data
            if (!Schema::hasColumn('weather_readings', 'co2')) {
                $table->unsignedInteger('co2')->nullable();
            }
            if (!Schema::hasColumn('weather_readings', 'co2_avg_24h')) {
                $table->unsignedInteger('co2_avg_24h')->nullable();
            }
            if (!Schema::hasColumn('weather_readings', 'co2_temp')) {
                $table->decimal('co2_temp', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('weather_readings', 'co2_humidity')) {
                $table->unsignedTinyInteger('co2_humidity')->nullable();
            }
            
            // Water leak sensors (up to 4)
            for ($i = 1; $i <= 4; $i++) {
                if (!Schema::hasColumn('weather_readings', "leak_ch{$i}")) {
                    $table->boolean("leak_ch{$i}")->nullable();
                }
            }
            
            // Extended wind data
            if (!Schema::hasColumn('weather_readings', 'wind_direction_avg_10m')) {
                $table->unsignedSmallInteger('wind_direction_avg_10m')->nullable();
            }
            if (!Schema::hasColumn('weather_readings', 'wind_gust_max_daily')) {
                $table->decimal('wind_gust_max_daily', 6, 2)->nullable();
            }
            
            // Event rain (since last reset)
            if (!Schema::hasColumn('weather_readings', 'rain_event')) {
                $table->decimal('rain_event', 7, 2)->nullable();
            }
            if (!Schema::hasColumn('weather_readings', 'rain_total')) {
                $table->decimal('rain_total', 8, 2)->nullable();
            }
            
            // Additional lightning data
            if (!Schema::hasColumn('weather_readings', 'lightning_count')) {
                $table->unsignedInteger('lightning_count')->nullable();
            }
            
            // Heat index and wind chill (calculated values)
            if (!Schema::hasColumn('weather_readings', 'heat_index')) {
                $table->decimal('heat_index', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('weather_readings', 'wind_chill')) {
                $table->decimal('wind_chill', 5, 2)->nullable();
            }
            
            // Wet bulb temperature
            if (!Schema::hasColumn('weather_readings', 'wet_bulb')) {
                $table->decimal('wet_bulb', 5, 2)->nullable();
            }
            
            // Station metadata
            if (!Schema::hasColumn('weather_readings', 'station_type')) {
                $table->string('station_type', 50)->nullable();
            }
            if (!Schema::hasColumn('weather_readings', 'station_model')) {
                $table->string('station_model', 50)->nullable();
            }
            if (!Schema::hasColumn('weather_readings', 'station_runtime')) {
                $table->unsignedBigInteger('station_runtime')->nullable();
            }
            if (!Schema::hasColumn('weather_readings', 'station_freq')) {
                $table->string('station_freq', 10)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weather_readings', function (Blueprint $table) {
            // Extra temperature sensors
            for ($i = 1; $i <= 8; $i++) {
                $table->dropColumn("temp_{$i}");
            }
            
            // Extra humidity sensors
            for ($i = 1; $i <= 8; $i++) {
                $table->dropColumn("humidity_{$i}");
            }
            
            // Soil moisture sensors
            for ($i = 1; $i <= 8; $i++) {
                $table->dropColumn("soil_moisture_{$i}");
            }
            
            // Soil temperature sensors
            for ($i = 1; $i <= 8; $i++) {
                $table->dropColumn("soil_temp_{$i}");
            }
            
            // Leaf wetness sensors
            for ($i = 1; $i <= 8; $i++) {
                $table->dropColumn("leaf_wetness_{$i}");
            }
            
            // PM2.5 sensors
            for ($i = 1; $i <= 4; $i++) {
                $table->dropColumn("pm25_ch{$i}");
                $table->dropColumn("pm25_avg_24h_ch{$i}");
            }
            
            // PM10
            $table->dropColumn(['pm10', 'pm10_avg_24h']);
            
            // CO2
            $table->dropColumn(['co2', 'co2_avg_24h', 'co2_temp', 'co2_humidity']);
            
            // Water leak sensors
            for ($i = 1; $i <= 4; $i++) {
                $table->dropColumn("leak_ch{$i}");
            }
            
            // Extended wind
            $table->dropColumn(['wind_direction_avg_10m', 'wind_gust_max_daily']);
            
            // Rain
            $table->dropColumn(['rain_event', 'rain_total']);
            
            // Lightning
            $table->dropColumn('lightning_count');
            
            // Calculated
            $table->dropColumn(['heat_index', 'wind_chill', 'wet_bulb']);
            
            // Station
            $table->dropColumn(['station_type', 'station_model', 'station_runtime', 'station_freq']);
        });
    }
};
