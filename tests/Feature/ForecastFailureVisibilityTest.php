<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Forecast\DwdService;
use App\Support\CacheFreshness;
use App\Support\ForecastCacheKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #99, second round. The reporter was still stuck on v2026.09.3 with a
 * log line that said nothing: "No forecast data received (kept existing cache)".
 */
class ForecastFailureVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('station.latitude', '52.57', 'float', 'station');
        Setting::setValue('station.longitude', '13.32', 'float', 'station');
    }

    /**
     * One failed catalogue fetch used to pin an empty station for seven days,
     * after which DWD returned nothing with no request and no log line.
     */
    public function test_a_failed_station_lookup_is_not_remembered(): void
    {
        Setting::setValue('dwd.station_id', '', 'string', 'dwd');
        Http::fake(['www.dwd.de/*' => Http::response('gone', 503)]);

        app(DwdService::class)->fetchForecast();

        $this->assertNull(
            Cache::get('dwd_nearest_station_52.57_13.32'),
            'an empty station was cached, so DWD stays dead until it expires'
        );
    }

    public function test_the_next_poll_tries_the_station_lookup_again(): void
    {
        Setting::setValue('dwd.station_id', '', 'string', 'dwd');
        Http::fake(['www.dwd.de/*' => Http::response('gone', 503)]);

        app(DwdService::class)->fetchForecast();
        app(DwdService::class)->fetchForecast();

        Http::assertSentCount(2);
    }

    /** The log said "forecast service", which named nothing. */
    public function test_the_poll_names_the_source_it_is_polling(): void
    {
        Setting::setValue('forecast.default_source', 'fct_dwd_block.php', 'string', 'forecast');
        Setting::setValue('dwd.station_id', '10382', 'string', 'dwd');
        Http::fake(['opendata.dwd.de/*' => Http::response('gone', 404)]);

        $this->artisan('weather:poll-external --source=forecast')
            ->expectsOutputToContain('DWD')
            ->run();
    }

    /**
     * Restoring the old payload is right. Restoring it with a fresh timestamp
     * is not: a source that has failed for a week reads as updated minutes ago,
     * so nothing ever reports it.
     */
    public function test_restoring_a_forecast_keeps_its_real_age(): void
    {
        Setting::setValue('forecast.default_source', 'fct_yrno_block.php', 'string', 'forecast');

        $old = now()->subDays(3);
        $payload = ['updated_at' => $old->toIso8601String(), 'forecast' => [['time' => 'then', 'symbol' => 'cloudy']]];
        CacheFreshness::put(ForecastCacheKeys::forSource('fct_yrno_block.php'), $payload, now()->addHours(2));
        Cache::put(ForecastCacheKeys::forSource('fct_yrno_block.php').'_updated_at', $old->toIso8601String(), now()->addHours(2));

        Http::fake(['*' => Http::response('down', 500)]);
        $this->artisan('weather:poll-external --source=forecast')->run();

        $stamp = Cache::get(ForecastCacheKeys::forSource('fct_yrno_block.php').'_updated_at');
        $this->assertSame(
            $old->toIso8601String(),
            $stamp,
            'the failed poll stamped the old forecast as fresh'
        );
    }
}
