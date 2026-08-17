<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\Weather\AmbientWeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmbientWeatherServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_service_requires_api_and_application_keys(): void
    {
        Setting::setValue('ambient.api_key', 'api-key', 'encrypted', 'ambient');

        $this->assertFalse(app(AmbientWeatherService::class)->isConfigured());

        Setting::setValue('ambient.application_key', 'application-key', 'encrypted', 'ambient');

        $this->assertTrue(app(AmbientWeatherService::class)->isConfigured());
    }

    public function test_devices_request_uses_both_configured_credentials(): void
    {
        $this->configureCredentials();
        Http::fake([
            'rt.ambientweather.net/v1/devices*' => Http::response([], 200),
        ]);

        app(AmbientWeatherService::class)->getDevices();

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://rt.ambientweather.net/v1/devices?apiKey=api-key&applicationKey=application-key';
        });
    }

    public function test_current_conditions_selects_configured_mac_address(): void
    {
        $this->configureCredentials();
        Setting::setValue('ambient.mac_address', 'AA:BB:CC:DD:EE:02', 'string', 'ambient');
        Http::fake([
            'rt.ambientweather.net/v1/devices*' => Http::response([
                $this->device('AA:BB:CC:DD:EE:01', 'First station', 50.0),
                $this->device('AA:BB:CC:DD:EE:02', 'Selected station', 68.0),
            ], 200),
        ]);

        $conditions = app(AmbientWeatherService::class)->getCurrentConditions();

        $this->assertSame('Selected station', $conditions['device']['name']);
        $this->assertSame('AA:BB:CC:DD:EE:02', $conditions['device']['mac_address']);
        $this->assertSame(20.0, $conditions['outdoor']['temperature']);
    }

    public function test_current_conditions_cache_expires_before_sensor_health_threshold(): void
    {
        $this->configureCredentials();
        Carbon::setTestNow('2026-08-17 12:00:00');
        Http::fakeSequence()
            ->push([$this->device('AA:BB:CC:DD:EE:01', 'Station', 50.0)], 200)
            ->push([$this->device('AA:BB:CC:DD:EE:01', 'Station', 68.0)], 200);

        $service = app(AmbientWeatherService::class);

        $this->assertSame(10.0, $service->getCurrentConditions()['outdoor']['temperature']);

        Carbon::setTestNow(now()->addSeconds(29));
        $this->assertSame(10.0, $service->getCurrentConditions()['outdoor']['temperature']);

        Carbon::setTestNow(now()->addSeconds(2));
        $this->assertSame(20.0, $service->getCurrentConditions()['outdoor']['temperature']);
        Http::assertSentCount(2);
    }

    private function configureCredentials(): void
    {
        Setting::setValue('ambient.api_key', 'api-key', 'encrypted', 'ambient');
        Setting::setValue('ambient.application_key', 'application-key', 'encrypted', 'ambient');
    }

    private function device(string $mac, string $name, float $temperature): array
    {
        return [
            'macAddress' => $mac,
            'info' => ['name' => $name],
            'lastData' => ['tempf' => $temperature, 'dateutc' => 1_700_000_000_000],
        ];
    }
}
