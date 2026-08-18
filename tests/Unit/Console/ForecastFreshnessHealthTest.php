<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Models\Setting;
use App\Support\CacheFreshness;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ForecastFreshnessHealthTest extends TestCase
{
    use RefreshDatabase;

    private function forecastKey(): string
    {
        return 'yrno_forecast_' . Setting::latitude() . '_' . Setting::longitude();
    }

    private function health(): array
    {
        $this->artisan('weather:check-sensor-health')->assertExitCode(0);

        return Cache::get('data_source_health', [])['forecast'] ?? [];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reports_when_the_forecast_was_last_written(): void
    {
        CacheFreshness::put($this->forecastKey(), ['some' => 'forecast'], now()->addMinutes(120));

        $forecast = $this->health();

        $this->assertNotNull($forecast['last_update'], 'last_update was structurally always null.');
        $this->assertNotNull($forecast['age_minutes']);
        $this->assertFalse($forecast['is_stale']);
    }

    /**
     * The payload TTL is 120 minutes, longer than the 60 minute staleness
     * threshold, so old-but-present data used to report healthy for two hours.
     */
    public function test_marks_the_forecast_stale_once_the_cached_payload_is_old(): void
    {
        Carbon::setTestNow(now()->subMinutes(90));
        CacheFreshness::put($this->forecastKey(), ['some' => 'forecast'], now()->addMinutes(120));
        Carbon::setTestNow();

        $forecast = $this->health();

        $this->assertTrue($forecast['is_stale'], 'A 90 minute old forecast must not report healthy.');
        $this->assertEqualsWithDelta(90, $forecast['age_minutes'], 1);
    }
}
