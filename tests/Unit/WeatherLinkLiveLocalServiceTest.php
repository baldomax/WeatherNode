<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\Weather\WeatherLinkLiveLocalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherLinkLiveLocalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_is_configured_returns_false_when_ip_empty(): void
    {
        Setting::setValue('weatherlink.wll_ip', '', 'string', 'weatherlink');
        $service = new WeatherLinkLiveLocalService();
        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_true_for_private_ip(): void
    {
        Setting::setValue('weatherlink.wll_ip', '192.168.1.20', 'string', 'weatherlink');
        Setting::setValue('weatherlink.wll_port', '80', 'integer', 'weatherlink');

        $service = new WeatherLinkLiveLocalService();
        $this->assertTrue($service->isConfigured());
    }

    public function test_is_configured_returns_false_for_public_ip_ssrf_protection(): void
    {
        Setting::setValue('weatherlink.wll_ip', '1.2.3.4', 'string', 'weatherlink');

        $service = new WeatherLinkLiveLocalService();
        $this->assertFalse($service->isConfigured());
    }

    public function test_get_current_conditions_returns_null_when_not_configured(): void
    {
        $service = new WeatherLinkLiveLocalService();
        $this->assertNull($service->getCurrentConditions());
    }

    public function test_get_current_conditions_calls_local_device_for_private_ip(): void
    {
        Setting::setValue('weatherlink.wll_ip', '192.168.1.20', 'string', 'weatherlink');
        Setting::setValue('weatherlink.wll_port', '80', 'integer', 'weatherlink');

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'did' => 'WLL-456',
                    'ts' => time(),
                    'conditions' => [
                        ['data_structure_type' => 1, 'temp' => 71.78, 'hum' => 55],
                    ],
                ],
            ], 200),
        ]);

        $service = new WeatherLinkLiveLocalService();
        $result = $service->getCurrentConditions();

        $this->assertIsArray($result);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '192.168.1.20')
                && str_contains($request->url(), '/v1/current_conditions');
        });
    }
}
