<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\WeatherReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LightningLastStrikeTest extends TestCase
{
    use RefreshDatabase;

    private function reading(Carbon $at, ?int $countDaily, ?Carbon $sensorTime = null): void
    {
        WeatherReading::create([
            'recorded_at' => $at,
            'lightning_count_daily' => $countDaily,
            'lightning_time' => $sensorTime,
        ]);
    }

    public function test_prefers_sensor_provided_strike_timestamp(): void
    {
        $sensorTime = now()->subMinutes(5)->startOfSecond();
        $this->reading(now()->subMinutes(20), 1);
        $this->reading(now(), 2, $sensorTime); // latest reading carries a real strike time

        $this->assertSame(
            $sensorTime->toIso8601String(),
            WeatherReading::lastStrikeTime()?->toIso8601String()
        );
    }

    public function test_derives_strike_time_from_daily_counter_increase(): void
    {
        // Daily counter over time: 0, 0, 1, 1, 2 — the last increase (1 -> 2) marks the
        // most recent strike. No sensor timestamp is provided on any reading.
        $this->reading(now()->subMinutes(25), 0);
        $this->reading(now()->subMinutes(20), 0);
        $this->reading(now()->subMinutes(15), 1);   // strike (0 -> 1)
        $this->reading(now()->subMinutes(10), 1);
        $strikeAt = now()->subMinutes(5)->startOfSecond();
        $this->reading($strikeAt, 2);               // strike (1 -> 2) — the latest one

        $this->assertSame(
            $strikeAt->toIso8601String(),
            WeatherReading::lastStrikeTime()?->toIso8601String()
        );
    }

    public function test_returns_null_when_no_strikes_today(): void
    {
        $this->reading(now()->subMinutes(10), 0);
        $this->reading(now(), 0);

        $this->assertNull(WeatherReading::lastStrikeTime());
    }
}
