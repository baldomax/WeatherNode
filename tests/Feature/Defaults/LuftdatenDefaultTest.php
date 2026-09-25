<?php

declare(strict_types=1);

namespace Tests\Feature\Defaults;

use App\Models\Setting;
use App\Services\AirQuality\LuftdatenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #105. A fresh install shipped with Luftdaten switched on and pointed at
 * sensor 69616, which belongs to somebody else. Every new install polled that
 * sensor and presented its readings as the owner's own air quality.
 */
class LuftdatenDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_does_not_ship_a_sensor_id(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->assertSame('', (string) Setting::getValue('luftdaten.sensor_id'));
        $this->assertFalse((bool) Setting::getValue('luftdaten.enabled'));
    }

    /** The row can be blank and the code default still handed out the sensor. */
    public function test_the_service_has_no_sensor_when_nothing_is_configured(): void
    {
        Http::fake();

        app(LuftdatenService::class)->fetchSensorData();

        Http::assertNothingSent();
    }

    public function test_nothing_reaches_out_to_a_stranger_sensor(): void
    {
        Http::fake();
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        app(LuftdatenService::class)->fetchSensorData();
        $this->getJson('/api/data/luftdaten');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '69616'));
    }

    /** A configured sensor must still work. */
    public function test_a_sensor_the_owner_sets_is_used(): void
    {
        Http::fake(['data.sensor.community/*' => Http::response([], 200)]);
        Setting::setValue('luftdaten.sensor_id', '12345', 'string', 'airquality');

        app(LuftdatenService::class)->fetchSensorData();

        Http::assertSent(fn ($request) => str_contains($request->url(), '12345'));
    }
}
