<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\Weather\WeatherLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_is_configured_returns_false_when_no_settings(): void
    {
        $service = new WeatherLinkService();
        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_true_in_demo_mode_with_api_key_and_secret(): void
    {
        Setting::setValue('weatherlink.demo_mode', '1', 'boolean', 'weatherlink');
        Setting::setValue('weatherlink.api_key', 'test-key', 'string', 'weatherlink');
        Setting::setValue('weatherlink.api_secret', 'test-secret', 'string', 'weatherlink');

        $service = new WeatherLinkService();
        $this->assertTrue($service->isConfigured());
    }

    public function test_is_configured_returns_false_in_demo_mode_without_api_secret(): void
    {
        Setting::setValue('weatherlink.demo_mode', '1', 'boolean', 'weatherlink');
        Setting::setValue('weatherlink.api_key', 'test-key', 'string', 'weatherlink');

        $service = new WeatherLinkService();
        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_false_in_production_without_secret_and_station(): void
    {
        Setting::setValue('weatherlink.demo_mode', '0', 'boolean', 'weatherlink');
        Setting::setValue('weatherlink.api_key', 'test-key', 'string', 'weatherlink');

        $service = new WeatherLinkService();
        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_true_in_production_with_all_credentials(): void
    {
        Setting::setValue('weatherlink.demo_mode', '0', 'boolean', 'weatherlink');
        Setting::setValue('weatherlink.api_key', 'key', 'string', 'weatherlink');
        Setting::setValue('weatherlink.api_secret', 'secret', 'string', 'weatherlink');
        Setting::setValue('weatherlink.station_id', '12345', 'string', 'weatherlink');

        $service = new WeatherLinkService();
        $this->assertTrue($service->isConfigured());
    }

    public function test_get_current_conditions_returns_null_when_not_configured(): void
    {
        $service = new WeatherLinkService();
        $this->assertNull($service->getCurrentConditions());
    }

    public function test_get_current_conditions_uses_demo_station_and_demo_param_in_demo_mode(): void
    {
        Setting::setValue('weatherlink.demo_mode', '1', 'boolean', 'weatherlink');
        Setting::setValue('weatherlink.api_key', 'demo-key', 'string', 'weatherlink');
        Setting::setValue('weatherlink.api_secret', 'demo-secret', 'string', 'weatherlink');

        Http::fake([
            'api.weatherlink.com/v2/current/*' => Http::response([
                'sensors' => [
                    [
                        'lsid' => 123,
                        'data_structure_type' => 4,
                        'temp' => 20.5,
                        'hum' => 65,
                    ],
                ],
            ], 200),
        ]);

        $service = new WeatherLinkService();
        $result = $service->getCurrentConditions();

        $this->assertIsArray($result);
        Http::assertSent(function ($request) {
            $url = $request->url();
            return str_contains($url, 'current/9722cfc3-a4ef-47b9-befb-72f52592d6ed')
                && str_contains($url, 'demo=true')
                && $request->hasHeader('X-Api-Secret')
                && $request->header('X-Api-Secret')[0] === 'demo-secret';
        });
    }

    public function test_get_current_conditions_sends_x_api_secret_in_production(): void
    {
        Setting::setValue('weatherlink.demo_mode', '0', 'boolean', 'weatherlink');
        Setting::setValue('weatherlink.api_key', 'key', 'string', 'weatherlink');
        Setting::setValue('weatherlink.api_secret', 'secret', 'string', 'weatherlink');
        Setting::setValue('weatherlink.station_id', 'station-uuid', 'string', 'weatherlink');

        Http::fake([
            'api.weatherlink.com/v2/current/*' => Http::response(['sensors' => []], 200),
        ]);

        $service = new WeatherLinkService();
        $service->getCurrentConditions();

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Api-Secret')
                && $request->header('X-Api-Secret')[0] === 'secret';
        });
    }
}
