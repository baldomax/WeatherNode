<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WeatherReading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomeHybridSsrTest extends TestCase
{
    use RefreshDatabase;

    public function test_flag_off_keeps_current_behavior(): void
    {
        Setting::setValue('dashboard.hybrid_ssr_enabled', false, 'boolean', 'advanced');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('window.__METEO_DASHBOARD_INITIAL__', false);
        $response->assertDontSee('ssr-fallback-block', false);
    }

    public function test_flag_on_renders_initial_payload_script(): void
    {
        Setting::setValue('dashboard.hybrid_ssr_enabled', true, 'boolean', 'advanced');
        $this->seedDashboardFixtureData();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('window.__METEO_DASHBOARD_INITIAL__', false);
        $response->assertSee('"success":true', false);
    }

    public function test_flag_on_renders_widget_values_server_side(): void
    {
        Setting::setValue('dashboard.hybrid_ssr_enabled', true, 'boolean', 'advanced');
        $this->seedDashboardFixtureData();
        $expectedForecastDate = Carbon::now(Setting::timezone())->toDateString();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('17.7', false);
        $response->assertSee('66%', false);
        $response->assertSee('1007.3', false);
        $response->assertSee($expectedForecastDate, false);
        $response->assertSee('SSR Test Alert', false);
        $response->assertSee('ssr-fallback-block', false);
    }

    public function test_flag_on_renders_ssr_fallback_cards_for_all_planned_widgets(): void
    {
        Setting::setValue('dashboard.hybrid_ssr_enabled', true, 'boolean', 'advanced');
        Setting::setValue('widgets.enabled', [
            'current', 'forecast', 'hourly', 'wind', 'pressure', 'rain',
            'sun', 'moon', 'uv', 'solar',
            'airquality', 'pollen', 'tide', 'metar', 'earthquakes', 'alerts',
            'lightning', 'indoor', 'extra_temps', 'soil', 'pm25', 'co2',
            'battery', 'radar', 'webcam', 'aurora', 'astro_events', 'ads',
        ], 'json', 'widgets');
        $this->seedDashboardFixtureData();

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('x-show="ssrFallbackVisible"', false);

        $content = (string) $response->getContent();
        $expectedCardIds = [
            'current', 'forecast', 'hourly', 'wind', 'pressure', 'rain',
            'sun_moon', 'uv_solar', 'airquality', 'pollen', 'tide', 'metar',
            'earthquakes', 'alerts', 'lightning', 'indoor', 'extra_temps',
            'soil', 'pm25', 'co2', 'battery', 'radar', 'webcam', 'aurora',
            'astro_events', 'ads',
        ];

        foreach ($expectedCardIds as $cardId) {
            $pattern = '/ssr-fallback-block[^>]*data-widget="' . preg_quote($cardId, '/') . '"/';
            $this->assertMatchesRegularExpression($pattern, $content);
        }
    }

    public function test_json_ld_present_and_valid_structure(): void
    {
        Setting::setValue('dashboard.hybrid_ssr_enabled', true, 'boolean', 'advanced');
        $this->seedDashboardFixtureData();

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('<script type="application/ld+json">', false);

        $content = $response->getContent();
        $matched = preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', (string) $content, $matches);
        $this->assertSame(1, $matched);

        $decoded = json_decode($matches[1] ?? '', true);
        $this->assertIsArray($decoded);
        $this->assertSame('https://schema.org', $decoded['@context'] ?? null);
        $this->assertIsArray($decoded['@graph'] ?? null);

        $graphByType = [];
        foreach ($decoded['@graph'] as $entry) {
            if (is_array($entry) && isset($entry['@type'])) {
                $graphByType[$entry['@type']] = $entry;
            }
        }

        $this->assertArrayHasKey('WebPage', $graphByType);
        $this->assertArrayHasKey('Place', $graphByType);
        $this->assertArrayHasKey('Dataset', $graphByType);
        $this->assertNotEmpty($graphByType['WebPage']['dateModified'] ?? null);
        $this->assertNotEmpty($graphByType['Place']['name'] ?? null);
        $this->assertNotEmpty($graphByType['Dataset']['dateModified'] ?? null);
    }

    public function test_dashboard_js_fetches_immediately_when_ssr_payload_exists(): void
    {
        $jsPath = resource_path('js/pages/dashboard.js');
        $contents = file_get_contents($jsPath);

        $this->assertIsString($contents);
        $this->assertStringContainsString('const hasInitialPayload = hybridSsrEnabled && initialPayload && initialPayload.success;', $contents);
        $this->assertStringContainsString('this.fetchData({ force: true, silent: true });', $contents);
        $this->assertStringNotContainsString('}, 15000);', $contents);
        $this->assertStringContainsString('lastDataTime: null,', $contents);
        $this->assertStringContainsString('if (!this.lastDataTime) return false;', $contents);
        $this->assertStringContainsString('this.lastUpdateTime = Date.now();', $contents);
        $this->assertStringContainsString("if (source === 'sensor' && this.lastDataTime)", $contents);
        $this->assertStringContainsString("window.addEventListener('online', this._onlineListener);", $contents);
        $this->assertStringContainsString("window.addEventListener('offline', this._offlineListener);", $contents);
        $this->assertStringContainsString('if (pausedDuration > 15000 || !this.lastUpdateTime || this.dataIsStale)', $contents);
    }

    public function test_zero_state_ssr_fallback_widgets_are_gated_by_hydration_flag(): void
    {
        $viewPath = resource_path('views/weather/dashboard.blade.php');
        $content = file_get_contents($viewPath);

        $this->assertIsString($content);
        $this->assertStringContainsString("x-show=\"ssrFallbackVisible && isWidgetEnabled('alerts') && alerts.length === 0\"", $content);
        $this->assertStringContainsString("x-show=\"ssrFallbackVisible && isWidgetEnabled('earthquakes') && earthquakes.length === 0\"", $content);
        $this->assertStringContainsString("x-show=\"ssrFallbackVisible && isWidgetEnabled('astro_events') && astronomicalEvents.length === 0\"", $content);
    }

    private function seedDashboardFixtureData(): void
    {
        WeatherReading::create([
            'recorded_at' => Carbon::now()->subMinute(),
            'temperature' => 17.7,
            'temperature_indoor' => 20.1,
            'feels_like' => 17.0,
            'humidity' => 66.0,
            'humidity_indoor' => 54.0,
            'dew_point' => 11.1,
            'wet_bulb' => 13.2,
            'pressure_rel' => 1007.3,
            'pressure_abs' => 1006.9,
            'wind_speed' => 18.6,
            'wind_gust' => 24.8,
            'wind_gust_max_daily' => 29.2,
            'wind_direction' => 225,
            'rain_rate' => 0.0,
            'rain_hourly' => 0.0,
            'rain_daily' => 0.6,
            'rain_monthly' => 12.4,
            'rain_yearly' => 158.2,
            'rain_total' => 2334.1,
            'uv_index' => 3.5,
            'solar_radiation' => 188.0,
            'battery_status' => ['wh65batt' => 0],
            'station_type' => 'ecowitt',
            'station_model' => 'GW2000',
        ]);

        $nowUtc = Carbon::now('UTC')->startOfHour();
        $forecast = [];

        for ($hour = 0; $hour < 30; $hour += 3) {
            $forecast[] = [
                'time' => $nowUtc->copy()->addHours($hour)->toIso8601String(),
                'temperature' => 10 + ($hour / 3),
                'symbol' => 'clearsky_day',
                'precipitation_1h' => $hour === 0 ? 0.2 : 0.0,
                'precipitation_6h' => $hour === 0 ? 0.2 : 0.0,
                'wind_speed' => 12 + ($hour / 6),
            ];
        }

        $forecast[] = [
            'time' => $nowUtc->copy()->addDay()->setTime(12, 0)->toIso8601String(),
            'temperature' => 19.5,
            'symbol' => 'partlycloudy_day',
            'precipitation_1h' => 0.0,
            'precipitation_6h' => 0.0,
            'wind_speed' => 14.0,
        ];

        $lat = Setting::latitude();
        $lon = Setting::longitude();
        Cache::put("yrno_forecast_{$lat}_{$lon}", ['forecast' => $forecast], now()->addMinutes(20));
        Cache::put('weather:last_update', Carbon::now()->toIso8601String(), now()->addMinutes(20));
        Cache::put('weather_alerts', [[
            'title' => 'SSR Test Alert',
            'warning_type' => 'wind',
            'warning_type_label' => 'Wind',
            'severity' => 2,
            'severity_color' => '#F59E0B',
            'link' => 'https://example.test/alert',
        ]], now()->addMinutes(20));
    }
}
