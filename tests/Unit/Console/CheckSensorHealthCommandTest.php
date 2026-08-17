<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Models\Setting;
use App\Models\WeatherReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckSensorHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('notifications.enabled', '1', 'boolean', 'notifications');
        Setting::setValue('notifications.method', 'email', 'select', 'notifications');
        Setting::setValue('notifications.email', 'ops@example.com', 'string', 'notifications');
        Setting::setValue('sensor_health.fail_minutes', '30', 'integer', 'sensor_health');
    }

    /** Decoded so quoted-printable soft line breaks don't split the sensor labels. */
    private function sentBodies(): array
    {
        return Mail::mailer()->getSymfonyTransport()->messages()
            ->map(fn ($message) => quoted_printable_decode($message->getOriginalMessage()->toString()))
            ->all();
    }

    private function assertMailSentContaining(string $needle): void
    {
        foreach ($this->sentBodies() as $body) {
            if (str_contains($body, $needle)) {
                $this->assertTrue(true);
                return;
            }
        }

        $this->fail("No notification containing \"{$needle}\" was sent.");
    }

    private function assertNoMailSentContaining(string $needle): void
    {
        foreach ($this->sentBodies() as $body) {
            if (str_contains($body, $needle)) {
                $this->fail("Unexpected notification containing \"{$needle}\" was sent.");
            }
        }

        $this->assertTrue(true);
    }

    /**
     * The station keeps publishing indoor values while the outdoor array is
     * silent — the failure mode that produced no alert on 2026-08-15.
     */
    public function test_alerts_when_outdoor_sensors_stop_while_station_keeps_reporting(): void
    {
        WeatherReading::create([
            'recorded_at' => now()->subHours(4),
            'temperature' => 17.2,
            'humidity' => 68,
            'wind_speed' => 9.0,
            'rain_daily' => 0.2,
            'temperature_indoor' => 26.0,
        ]);
        WeatherReading::create([
            'recorded_at' => now()->subMinute(),
            'temperature_indoor' => 26.9,
            'humidity_indoor' => 61,
        ]);

        $this->artisan('weather:check-sensor-health')->assertExitCode(0);

        $this->assertMailSentContaining('Outdoor temp/humidity');
        $this->assertMailSentContaining('Wind sensor');
    }

    public function test_does_not_alert_while_every_sensor_is_still_reporting(): void
    {
        WeatherReading::create([
            'recorded_at' => now()->subHours(4),
            'temperature' => 17.2,
            'wind_speed' => 9.0,
        ]);
        WeatherReading::create([
            'recorded_at' => now()->subMinute(),
            'temperature' => 18.1,
            'wind_speed' => 11.0,
        ]);

        $this->artisan('weather:check-sensor-health')->assertExitCode(0);

        $this->assertNoMailSentContaining('stopped reporting');
    }

    public function test_sends_recovery_notice_once_sensors_report_again(): void
    {
        WeatherReading::create([
            'recorded_at' => now()->subHours(4),
            'temperature' => 17.2,
            'wind_speed' => 9.0,
        ]);
        WeatherReading::create([
            'recorded_at' => now()->subMinute(),
            'temperature_indoor' => 26.9,
        ]);

        $this->artisan('weather:check-sensor-health')->assertExitCode(0);
        $this->assertMailSentContaining('Wind sensor');

        WeatherReading::create([
            'recorded_at' => now(),
            'temperature' => 18.4,
            'wind_speed' => 10.0,
        ]);

        $this->artisan('weather:check-sensor-health')->assertExitCode(0);

        $this->assertMailSentContaining('reporting again');
    }

    /**
     * The "already alerted" flag used to be set even when delivery failed, so a
     * failure detected before notifications were configured silenced the alert
     * for 24 hours after they were.
     */
    public function test_retries_the_alert_after_a_failed_delivery(): void
    {
        Setting::setValue('notifications.enabled', '0', 'boolean', 'notifications');

        WeatherReading::create([
            'recorded_at' => now()->subHours(4),
            'temperature' => 17.2,
            'wind_speed' => 9.0,
        ]);
        WeatherReading::create([
            'recorded_at' => now()->subMinute(),
            'temperature' => 18.1,
        ]);

        $this->artisan('weather:check-sensor-health')->assertExitCode(0);
        $this->assertNoMailSentContaining('Wind sensor');

        Setting::setValue('notifications.enabled', '1', 'boolean', 'notifications');

        $this->artisan('weather:check-sensor-health')->assertExitCode(0);

        $this->assertMailSentContaining('Wind sensor');
    }

    public function test_alerts_again_when_a_further_sensor_stops(): void
    {
        WeatherReading::create([
            'recorded_at' => now()->subHours(4),
            'temperature' => 17.2,
            'wind_speed' => 9.0,
        ]);
        WeatherReading::create([
            'recorded_at' => now()->subMinutes(10),
            'temperature' => 18.1,
            'rain_daily' => 0.4,
        ]);

        $this->artisan('weather:check-sensor-health')->assertExitCode(0);
        $this->assertMailSentContaining('Wind sensor');
        $this->assertNoMailSentContaining('Rain gauge');

        // The rain gauge now falls past the threshold too.
        $this->travel(35)->minutes();

        $this->artisan('weather:check-sensor-health')->assertExitCode(0);

        $this->assertMailSentContaining('Rain gauge');
    }

    public function test_honours_the_configured_failure_threshold(): void
    {
        Setting::setValue('sensor_health.fail_minutes', '360', 'integer', 'sensor_health');

        WeatherReading::create([
            'recorded_at' => now()->subHours(4),
            'temperature' => 17.2,
            'wind_speed' => 9.0,
        ]);
        WeatherReading::create([
            'recorded_at' => now()->subMinute(),
            'temperature_indoor' => 26.9,
        ]);

        $this->artisan('weather:check-sensor-health')->assertExitCode(0);

        $this->assertNoMailSentContaining('Wind sensor');
    }
}
