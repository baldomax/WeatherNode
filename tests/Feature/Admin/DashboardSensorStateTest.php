<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Models\WeatherReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSensorStateTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * A sensor that has gone quiet must stay visible with a failed state
     * instead of silently disappearing from the list.
     */
    public function test_shows_a_silent_sensor_as_failed_instead_of_dropping_it(): void
    {
        Setting::setValue('sensor_health.fail_minutes', '30', 'integer', 'sensor_health');

        WeatherReading::create([
            'recorded_at' => now()->subHours(4),
            'temperature' => 17.2,
            'wind_speed' => 9.0,
        ]);
        WeatherReading::create([
            'recorded_at' => now()->subMinute(),
            'temperature' => 18.1,
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Wind sensor');
        $response->assertSee('Outdoor temp/humidity');
        $response->assertViewHas('sensorStates', function (array $states) {
            $byId = collect($states)->keyBy('id');

            return $byId['wind']['state'] === 'failed'
                && $byId['outdoor_temp_humidity']['state'] === 'ok';
        });
    }

    public function test_reports_every_sensor_ok_while_all_are_reporting(): void
    {
        Setting::setValue('sensor_health.fail_minutes', '30', 'integer', 'sensor_health');

        WeatherReading::create([
            'recorded_at' => now()->subMinute(),
            'temperature' => 18.1,
            'wind_speed' => 11.0,
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('sensorStates', function (array $states) {
            return collect($states)->every(fn ($s) => $s['state'] === 'ok');
        });
    }
}
