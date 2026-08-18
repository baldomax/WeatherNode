<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Earthquake;

use App\Models\Setting;
use App\Services\Earthquake\EarthquakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The query carried no geographic or time bound, so the API returned the last N
 * events worldwide and the radius filter ran afterwards on that set. Global
 * seismicity being what it is, those N events span about an hour, so a nearby
 * quake essentially never appeared. Checked against the live API while writing
 * this: 30 events at M2.5+ covered 66 minutes, across Hawaii, Chile, India and
 * Indonesia.
 */
class EarthquakeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Setting::setValue('station.latitude', '52.5164', 'float', 'station');
        Setting::setValue('station.longitude', '4.7079', 'float', 'station');
        Setting::setValue('earthquakes.radius_km', '500', 'integer', 'earthquakes');
        Setting::setValue('earthquakes.min_magnitude', '2.5', 'float', 'earthquakes');
    }

    /** Http::fake() merges stubs, so an empty response must not be registered globally. */
    private function fakeNoEvents(): void
    {
        Http::fake(['seismicportal.eu/*' => Http::response(['features' => []])]);
    }

    private function queryOf(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query;
    }

    public function test_a_nearby_lookup_asks_the_api_for_that_area(): void
    {
        $this->fakeNoEvents();
        app(EarthquakeService::class)->fetchEarthquakes(30, true);

        Http::assertSent(function (Request $request) {
            $q = $this->queryOf($request);

            // maxradius is in degrees, not kilometres: 500 / 111.195.
            return abs((float) $q['lat'] - 52.5164) < 0.001
                && abs((float) $q['lon'] - 4.7079) < 0.001
                && abs((float) $q['maxradius'] - 4.4966) < 0.01;
        });
    }

    public function test_a_worldwide_lookup_asks_for_a_time_window_instead_of_the_last_n_events(): void
    {
        $this->fakeNoEvents();
        app(EarthquakeService::class)->fetchEarthquakes(100, false);

        Http::assertSent(function (Request $request) {
            $q = $this->queryOf($request);

            return !isset($q['maxradius'])
                && isset($q['start'])
                && strtotime($q['start']) < strtotime('-24 hours');
        });
    }

    public function test_the_magnitude_threshold_is_still_applied(): void
    {
        $this->fakeNoEvents();
        app(EarthquakeService::class)->fetchEarthquakes(30, true);

        Http::assertSent(fn (Request $request) => (float) $this->queryOf($request)['minmag'] === 2.5);
    }

    /** The client-side distance check stays as a backstop on whatever comes back. */
    public function test_anything_outside_the_radius_is_still_discarded(): void
    {
        Http::fake(['seismicportal.eu/*' => Http::response(['features' => [
            ['id' => 'near', 'properties' => ['mag' => 3.0, 'time' => '2026-08-18T10:00:00Z', 'flynn_region' => 'NEARBY'],
             'geometry' => ['coordinates' => [4.9, 52.4, 10]]],
            ['id' => 'far', 'properties' => ['mag' => 6.0, 'time' => '2026-08-18T10:00:00Z', 'flynn_region' => 'CHILE'],
             'geometry' => ['coordinates' => [-70.0, -23.0, 10]]],
        ]])]);

        $result = app(EarthquakeService::class)->fetchEarthquakes(30, true);

        $this->assertCount(1, $result);
        $this->assertSame('NEARBY', $result[0]['location']);
    }
}
