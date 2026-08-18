<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EarthquakesPageScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('station.latitude', '52.5164', 'float', 'station');
        Setting::setValue('station.longitude', '4.7079', 'float', 'station');
        Setting::setValue('earthquakes.radius_km', '500', 'integer', 'earthquakes');
        Setting::setValue('earthquakes.enabled', true, 'boolean', 'earthquakes');

        Cache::put("earthquakes_52.5164_4.7079", [
            ['id' => 'n1', 'magnitude' => 3.1, 'location' => 'NEARBY PLACE', 'distance' => 120, 'time' => '2026-08-18T10:00:00Z'],
        ], 600);
        Cache::put('earthquakes_all', [
            ['id' => 'n1', 'magnitude' => 3.1, 'location' => 'NEARBY PLACE', 'distance' => 120, 'time' => '2026-08-18T10:00:00Z'],
            ['id' => 'f1', 'magnitude' => 6.2, 'location' => 'FAR AWAY PLACE', 'distance' => 9000, 'time' => '2026-08-18T11:00:00Z'],
        ], 600);
    }

    /** A site with a configured radius should not open on events 9000km away. */
    public function test_it_defaults_to_nearby(): void
    {
        $response = $this->get('/earthquakes');

        $response->assertOk();
        $response->assertSee('NEARBY PLACE');
        $response->assertDontSee('FAR AWAY PLACE');
    }

    public function test_worldwide_is_available_on_request(): void
    {
        $response = $this->get('/earthquakes?scope=all');

        $response->assertOk();
        $response->assertSee('FAR AWAY PLACE');
    }

    public function test_an_unknown_scope_falls_back_to_nearby(): void
    {
        $this->get('/earthquakes?scope=' . urlencode('../etc'))
            ->assertOk()
            ->assertDontSee('FAR AWAY PLACE');
    }

    /**
     * "Nearby" is already translated in some locales as the METAR
     * nearest-airport label, so the earthquakes UI must not reuse that string.
     * In Italian it reads "Aeroporto Vicino", ie "Nearby Airport".
     */
    public function test_the_nearby_label_is_not_the_metar_airport_translation(): void
    {
        // LocaleUnitsMiddleware sets the locale per request, so setLocale()
        // beforehand does not survive. Configure it the way the app does.
        Setting::setValue('display.language', 'it-it', 'select', 'display');

        $response = $this->get('/earthquakes');

        $response->assertOk();
        $response->assertSee('Vicino a te');
        $response->assertDontSee('Aeroporto Vicino');
    }

    public function test_switching_scope_keeps_the_chosen_sort(): void
    {
        $response = $this->get('/earthquakes?scope=all&sort=magnitude');

        $response->assertOk();
        $response->assertSee('?scope=nearby&sort=magnitude', false);
    }
}
