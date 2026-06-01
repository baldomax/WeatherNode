<?php

namespace Tests\Feature;

use App\Services\Nlg\ForecastNarrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastNarratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_forecast_generates_text(): void
    {
        $narrator = app(ForecastNarrator::class);

        $text = $narrator->daily([
            'min_temp_c' => 8.2,
            'max_temp_c' => 13.4,
            'wind_ms' => 7.0,
            'wind_dir_deg' => 260,
            'precip_prob_pct' => 60,
            'precip_mm' => 2.8,
            'precip_type' => 'rain',
            'cloud_pct' => 80,
        ]);

        $this->assertIsString($text);
        $this->assertStringContainsString('Expect', $text);
        $this->assertStringContainsString('Temperatures', $text);
    }

    public function test_period_forecast_mentions_dry_when_no_precip(): void
    {
        $narrator = app(ForecastNarrator::class);

        $text = $narrator->periods([
            'periods' => [
                ['name' => 'morning', 'temp_c' => 8, 'wind_ms' => 3, 'precip_prob_pct' => 5, 'precip_mm' => 0, 'precip_type' => 'none', 'cloud_pct' => 30],
                ['name' => 'afternoon', 'temp_c' => 12, 'wind_ms' => 4, 'precip_prob_pct' => 10, 'precip_mm' => 0, 'precip_type' => 'none', 'cloud_pct' => 40],
                ['name' => 'evening', 'temp_c' => 10, 'wind_ms' => 2, 'precip_prob_pct' => 5, 'precip_mm' => 0, 'precip_type' => 'none', 'cloud_pct' => 20],
            ]
        ]);

        $this->assertStringContainsString('dry', strtolower($text));
    }

    public function test_narrate_delegates_to_periods_when_periods_present(): void
    {
        $narrator = app(ForecastNarrator::class);

        $text = $narrator->narrate([
            'periods' => [
                ['name' => 'morning', 'temp_c' => 8, 'wind_ms' => 3, 'precip_prob_pct' => 5, 'precip_mm' => 0, 'precip_type' => 'none', 'cloud_pct' => 30],
            ]
        ]);

        $this->assertIsString($text);
        $this->assertNotEmpty($text);
    }

    public function test_narrate_delegates_to_daily_when_no_periods(): void
    {
        $narrator = app(ForecastNarrator::class);

        $text = $narrator->narrate([
            'min_temp_c' => 8.2,
            'max_temp_c' => 13.4,
            'wind_ms' => 7.0,
            'cloud_pct' => 80,
        ]);

        $this->assertIsString($text);
        $this->assertNotEmpty($text);
    }

    public function test_wind_direction_conversion(): void
    {
        $narrator = app(ForecastNarrator::class);

        $text = $narrator->daily([
            'wind_ms' => 5.0,
            'wind_dir_deg' => 270, // West
            'cloud_pct' => 50,
        ]);

        $this->assertStringContainsString('W', $text);
    }

    public function test_precipitation_pattern_morning_easing(): void
    {
        $narrator = app(ForecastNarrator::class);

        $text = $narrator->periods([
            'periods' => [
                ['name' => 'morning', 'temp_c' => 8, 'wind_ms' => 3, 'precip_prob_pct' => 70, 'precip_mm' => 2.0, 'precip_type' => 'rain', 'cloud_pct' => 90],
                ['name' => 'afternoon', 'temp_c' => 12, 'wind_ms' => 4, 'precip_prob_pct' => 10, 'precip_mm' => 0, 'precip_type' => 'none', 'cloud_pct' => 40],
                ['name' => 'evening', 'temp_c' => 10, 'wind_ms' => 2, 'precip_prob_pct' => 5, 'precip_mm' => 0, 'precip_type' => 'none', 'cloud_pct' => 20],
            ]
        ]);

        $this->assertStringContainsString('morning', strtolower($text));
    }
}
