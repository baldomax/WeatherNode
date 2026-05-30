<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\Weather\AirLinkLocalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AirLinkLocalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Setting::setValue('davis_aq.enabled', true, 'boolean', 'davis_aq');
    }

    public function test_is_configured_returns_false_when_ip_empty(): void
    {
        Setting::setValue('weatherlink.airlink_ip', '', 'string', 'weatherlink');
        $service = new AirLinkLocalService();
        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_true_for_private_ip(): void
    {
        Setting::setValue('weatherlink.airlink_ip', '192.168.1.10', 'string', 'weatherlink');
        Setting::setValue('weatherlink.airlink_port', '80', 'integer', 'weatherlink');

        $service = new AirLinkLocalService();
        $this->assertTrue($service->isConfigured());
    }

    public function test_is_configured_returns_false_for_public_ip_ssrf_protection(): void
    {
        // Public IPs are rejected to prevent SSRF
        Setting::setValue('weatherlink.airlink_ip', '8.8.8.8', 'string', 'weatherlink');

        $service = new AirLinkLocalService();
        $this->assertFalse($service->isConfigured());
    }

    public function test_get_current_conditions_returns_null_when_not_configured(): void
    {
        $service = new AirLinkLocalService();
        $this->assertNull($service->getCurrentConditions());
    }

    public function test_get_current_conditions_calls_local_device_for_private_ip(): void
    {
        Setting::setValue('weatherlink.airlink_ip', '192.168.1.10', 'string', 'weatherlink');
        Setting::setValue('weatherlink.airlink_port', '80', 'integer', 'weatherlink');

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'did' => 'AIRLINK-123',
                    'ts' => time(),
                    'conditions' => [
                        ['data_structure_type' => 6, 'temp' => 71.78, 'hum' => 55, 'pm_2p5_last' => 5],
                    ],
                ],
            ], 200),
        ]);

        $service = new AirLinkLocalService();
        $result = $service->getCurrentConditions();

        $this->assertIsArray($result);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '192.168.1.10')
                && str_contains($request->url(), '/v1/current_conditions');
        });
    }
}
