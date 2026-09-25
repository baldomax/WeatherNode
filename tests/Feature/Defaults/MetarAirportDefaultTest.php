<?php

declare(strict_types=1);

namespace Tests\Feature\Defaults;

use App\Models\Setting;
use App\Services\Aviation\MetarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Issue #105. METAR shipped switched on and pointed at EHAM, Amsterdam
 * Schiphol, described as 34km away. Every fresh install reported the weather
 * at a Dutch airport as its nearest one, whatever continent it was on.
 */
class MetarAirportDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_has_no_airport(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->assertSame('', (string) Setting::getValue('metar.primary_icao'));
        $this->assertSame('', (string) Setting::getValue('metar.airport_name'));
        $this->assertFalse((bool) Setting::getValue('metar.enabled'));
    }

    /** The distance to Schiphol is only true from one village. */
    public function test_a_fresh_install_claims_no_distance(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->assertSame(0, (int) Setting::getValue('metar.airport_distance'));
    }

    public function test_nothing_fetches_schiphol_when_nothing_is_configured(): void
    {
        Http::fake();
        Setting::setValue('metar.api_key', 'test-key', 'encrypted', 'aviation');

        app(MetarService::class)->fetchMetar([]);

        Http::assertNotSent(fn ($request) => str_contains(strtoupper($request->url()), 'EHAM'));
    }

    /**
     * With no airport, the page built its social image from an empty ICAO and
     * the route threw a missing parameter error, taking the page with it.
     */
    public function test_the_aviation_page_loads_with_no_airport_configured(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->get('/aviation')->assertOk();
    }

    public function test_the_aviation_page_still_works_for_a_chosen_airport(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->get('/aviation/EGLL')->assertOk();
    }

    /** An airport the owner sets must still work. */
    public function test_a_configured_airport_is_used(): void
    {
        Http::fake(['api.checkwx.com/*' => Http::response(['data' => []], 200)]);
        Setting::setValue('metar.api_key', 'test-key', 'encrypted', 'aviation');

        app(MetarService::class)->fetchMetar(['EGLL']);

        Http::assertSent(fn ($request) => str_contains(strtoupper($request->url()), 'EGLL'));
    }
}
