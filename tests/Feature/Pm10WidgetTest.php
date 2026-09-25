<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WeatherReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #101. Stations that measure PM10 have had it stored on every reading
 * all along, but nothing ever showed it. The PM10 on the air quality card
 * comes from the outside sources, not from the station's own sensor.
 */
class Pm10WidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('widgets.enabled', json_encode(['current', 'pm25']), 'string', 'widgets');
        Setting::setValue('dashboard.hybrid_ssr_enabled', true, 'boolean', 'dashboard');
    }

    private function reading(array $attributes): void
    {
        WeatherReading::create(array_merge([
            'recorded_at' => now(),
            'temperature' => 18.0,
        ], $attributes));
    }

    public function test_a_station_that_measures_pm10_gets_it_in_the_payload(): void
    {
        $this->reading(['pm25_ch1' => 8.4, 'pm10' => 7.6, 'pm10_avg_24h' => 9.0]);

        $payload = WeatherReading::latest('recorded_at')->first()->toApiArray();

        $this->assertSame(7.6, $payload['pm10']['value'] ?? null);
        $this->assertSame(9.0, $payload['pm10']['avg_24h'] ?? null);
    }

    public function test_a_station_without_pm10_gets_nothing(): void
    {
        $this->reading(['pm25_ch1' => 8.4]);

        $payload = WeatherReading::latest('recorded_at')->first()->toApiArray();

        $this->assertNull($payload['pm10'] ?? null, 'an empty PM10 block would draw an empty row');
    }

    public function test_the_widget_shows_the_pm10_reading(): void
    {
        $this->reading(['pm25_ch1' => 8.4, 'pm10' => 7.6]);

        $this->get('/')
            ->assertOk()
            ->assertSee('PM10: 7.6 µg/m³', false);
    }

    public function test_the_widget_leaves_pm10_out_when_the_station_has_none(): void
    {
        $this->reading(['pm25_ch1' => 8.4]);

        $this->get('/')->assertDontSee('PM10:', false);
    }

    /** A PM10 only station still deserves the card. */
    public function test_pm10_alone_is_enough_to_show_the_card(): void
    {
        $this->reading(['pm10' => 7.6]);

        $this->get('/')->assertSee('PM10: 7.6 µg/m³', false);
    }
}
