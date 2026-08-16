<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Weather;

use App\Models\WeatherReading;
use App\Services\Weather\SensorTrackerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SensorTrackerServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The reading window is large (a row per minute for days), so it must be
     * walked once per computation, not once per helper.
     */
    public function test_scans_the_reading_window_only_once(): void
    {
        for ($i = 0; $i < 5; $i++) {
            WeatherReading::create([
                'recorded_at' => now()->subMinutes(10 - $i),
                'temperature' => 18.0,
                'wind_speed' => 5.0,
            ]);
        }

        DB::enableQueryLog();
        (new SensorTrackerService())->getFailedSensors(7, 30);
        $selects = collect(DB::getRawQueryLog())
            ->filter(fn ($q) => str_contains($q['raw_query'], 'from "weather_readings"'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(1, $selects);
    }

    public function test_states_report_ok_stale_and_failed(): void
    {
        WeatherReading::create([
            'recorded_at' => now()->subHours(4),
            'temperature' => 17.2,
            'wind_speed' => 9.0,
        ]);
        WeatherReading::create([
            'recorded_at' => now()->subMinutes(20),
            'solar_radiation' => 120.0,
        ]);
        WeatherReading::create([
            'recorded_at' => now()->subMinute(),
            'temperature' => 18.1,
        ]);

        $states = collect((new SensorTrackerService())->getSensorStates(7, 30))->keyBy('id');

        $this->assertSame('ok', $states['outdoor_temp_humidity']['state']);
        $this->assertSame('stale', $states['solar']['state']);
        $this->assertSame('failed', $states['wind']['state']);
    }

    public function test_cached_states_are_served_without_touching_the_readings_table(): void
    {
        WeatherReading::create([
            'recorded_at' => now()->subMinute(),
            'temperature' => 18.1,
        ]);

        $tracker = new SensorTrackerService();
        $tracker->refreshSensorStates(7, 30);

        DB::enableQueryLog();
        $states = $tracker->getCachedSensorStates(7, 30);
        $selects = collect(DB::getRawQueryLog())
            ->filter(fn ($q) => str_contains($q['raw_query'], 'from "weather_readings"'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(0, $selects);
        $this->assertSame('outdoor_temp_humidity', $states[0]['id']);
    }

    /**
     * getLastSeenForSensors() seeds its map with nulls and previously guarded
     * writes with isset(), which is false for null — so no sensor ever got a
     * last_seen and getFailedSensors() could never report anything.
     */
    public function test_reports_a_sensor_that_stopped_reporting(): void
    {
        WeatherReading::create([
            'recorded_at' => now()->subHours(4),
            'temperature' => 17.2,
            'wind_speed' => 9.0,
        ]);
        WeatherReading::create([
            'recorded_at' => now()->subMinute(),
            'temperature' => 18.1,
        ]);

        $failed = (new SensorTrackerService())->getFailedSensors(7, 60);

        $this->assertSame(['wind'], array_column($failed, 'id'));
        $this->assertEqualsWithDelta(
            now()->subHours(4)->timestamp,
            $failed[0]['last_seen']->timestamp,
            5
        );
    }

    public function test_tracks_outdoor_thermo_hygrometer_from_normalized_fields(): void
    {
        $reading = new WeatherReading(['temperature' => 18.4, 'humidity' => 71]);

        $ids = SensorTrackerService::getSensorIdsFromReading($reading);

        $this->assertContains('outdoor_temp_humidity', $ids);
    }

    public function test_tracks_wind_rain_and_solar_independently(): void
    {
        $reading = new WeatherReading([
            'wind_speed' => 12.0,
            'rain_daily' => 0.0,
            'solar_radiation' => 430.5,
            'uv_index' => 4.0,
        ]);

        $ids = SensorTrackerService::getSensorIdsFromReading($reading);

        $this->assertContains('wind', $ids);
        $this->assertContains('rain', $ids);
        $this->assertContains('solar', $ids);
        $this->assertContains('uv', $ids);
    }

    /**
     * A station that keeps publishing indoor values while the outdoor array is
     * silent must not look healthy: the outdoor sensors have to drop out of the
     * tracked set so their last_seen stops advancing.
     */
    public function test_indoor_only_reading_does_not_report_outdoor_sensors(): void
    {
        $reading = new WeatherReading([
            'temperature_indoor' => 26.9,
            'humidity_indoor' => 61,
            'pressure_rel' => 1013.0,
        ]);

        $ids = SensorTrackerService::getSensorIdsFromReading($reading);

        $this->assertContains('indoor_temp_humidity', $ids);
        $this->assertContains('barometer', $ids);
        $this->assertNotContains('outdoor_temp_humidity', $ids);
        $this->assertNotContains('wind', $ids);
        $this->assertNotContains('rain', $ids);
    }

    public function test_zero_is_a_reading_not_a_missing_sensor(): void
    {
        $reading = new WeatherReading(['temperature' => 0.0, 'wind_speed' => 0.0]);

        $ids = SensorTrackerService::getSensorIdsFromReading($reading);

        $this->assertContains('outdoor_temp_humidity', $ids);
        $this->assertContains('wind', $ids);
    }

    public function test_still_tracks_ecowitt_battery_keys_and_extra_channels(): void
    {
        $reading = new WeatherReading([
            'battery_status' => ['wh65batt' => 0, 'soilbatt1' => 1],
            'temp_1' => 21.0,
            'soil_moisture_2' => 34,
        ]);

        $ids = SensorTrackerService::getSensorIdsFromReading($reading);

        $this->assertContains('wh65batt', $ids);
        $this->assertContains('soilbatt1', $ids);
        $this->assertContains('temp_1', $ids);
        $this->assertContains('soil_2', $ids);
    }

    public function test_labels_primary_sensors_without_exposing_raw_ids(): void
    {
        $this->assertSame('Outdoor temp/humidity', SensorTrackerService::sensorIdToLabel('outdoor_temp_humidity'));
        $this->assertSame('Wind sensor', SensorTrackerService::sensorIdToLabel('wind'));
        $this->assertSame('Rain gauge', SensorTrackerService::sensorIdToLabel('rain'));
    }
}
