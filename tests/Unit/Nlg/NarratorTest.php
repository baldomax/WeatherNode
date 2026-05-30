<?php

namespace Tests\Unit\Nlg;

use App\Services\Nlg\ForecastNarrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NarratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_cast_nulls_to_zero_in_ranges(): void
    {
        $n = new ForecastNarrator();

        $text = $n->periods([
            'periods' => [
                ['name' => 'morning', 'temp_c' => null, 'wind_ms' => 3, 'precip_prob_pct' => 5, 'precip_mm' => 0, 'precip_type' => 'none', 'cloud_pct' => 30],
                ['name' => 'afternoon', 'temp_c' => 12, 'wind_ms' => 4, 'precip_prob_pct' => 10, 'precip_mm' => 0, 'precip_type' => 'none', 'cloud_pct' => 40],
            ]
        ]);

        // Should not contain 0°C in temperature range
        $this->assertStringNotContainsString('0°C', $text);
        // Should contain 12°C
        $this->assertStringContainsString('12°C', $text);
    }

    public function test_it_skips_noisy_probability_sentence(): void
    {
        $n = new ForecastNarrator();

        // Low probability + no amount + type is none = should skip
        $text = $n->daily([
            'precip_type' => 'none',
            'precip_mm' => 0.0,
            'precip_prob_pct' => 10, // Below threshold
            'cloud_pct' => 50,
        ]);

        // Should not mention precipitation at all
        $this->assertStringNotContainsString('precipitation', strtolower($text));
        $this->assertStringNotContainsString('unlikely', strtolower($text));
    }

    public function test_numeric_probability_controls_output(): void
    {
        $n = new ForecastNarrator();

        // High probability but no amount = should mention
        $text = $n->daily([
            'precip_type' => 'rain',
            'precip_mm' => 0.0,
            'precip_prob_pct' => 80, // Above threshold
            'cloud_pct' => 50,
        ]);

        $this->assertStringContainsString('good chance', strtolower($text));
        $this->assertStringContainsString('rain', strtolower($text));
    }

    public function test_it_handles_missing_fields_gracefully(): void
    {
        $n = new ForecastNarrator();

        $text = $n->daily([
            'cloud_pct' => 50,
        ]);

        $this->assertIsString($text);
        $this->assertNotEmpty($text);
    }

    public function test_null_filtering_in_wind_calculation(): void
    {
        $n = new ForecastNarrator();

        $text = $n->periods([
            'periods' => [
                ['name' => 'morning', 'temp_c' => 8, 'wind_ms' => null, 'precip_prob_pct' => 5, 'precip_mm' => 0, 'precip_type' => 'none', 'cloud_pct' => 30],
                ['name' => 'afternoon', 'temp_c' => 12, 'wind_ms' => 4, 'precip_prob_pct' => 10, 'precip_mm' => 0, 'precip_type' => 'none', 'cloud_pct' => 40],
            ]
        ]);

        // Should not crash and should produce valid output
        $this->assertIsString($text);
        $this->assertNotEmpty($text);
    }

    public function test_amount_below_threshold_skips_sentence(): void
    {
        $n = new ForecastNarrator();

        // Very small amount + low probability = should skip
        $text = $n->daily([
            'precip_type' => 'rain',
            'precip_mm' => 0.05, // Below min_amount_to_mention (0.1)
            'precip_prob_pct' => 30, // Below min_prob_to_mention_without_amount (60)
            'cloud_pct' => 50,
        ]);

        // Should not mention precipitation
        $this->assertStringNotContainsString('rain', strtolower($text));
    }

    public function test_amount_above_threshold_includes_sentence(): void
    {
        $n = new ForecastNarrator();

        // Amount above threshold = should mention
        $text = $n->daily([
            'precip_type' => 'rain',
            'precip_mm' => 2.0, // Above min_amount_to_mention
            'precip_prob_pct' => 50,
            'cloud_pct' => 50,
        ]);

        $this->assertStringContainsString('rain', strtolower($text));
    }
}
