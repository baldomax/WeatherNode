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

    public function test_uses_counter_increase_when_sensor_timestamp_is_stale(): void
    {
        // The real-world bug: the sensor's lightning_time sticks on an early strike
        // (~2h ago) while the daily counter keeps incrementing. The recent strike wins.
        $staleSensorTime = now()->subHours(2)->startOfSecond();
        $this->reading(now()->subMinutes(15), 7);
        $strikeAt = now()->subMinutes(5)->startOfSecond();
        $this->reading($strikeAt, 8, $staleSensorTime); // count ticked up; sensor time is stale

        $this->assertSame(
            $strikeAt->toIso8601String(),
            WeatherReading::lastStrikeTime()?->toIso8601String()
        );
    }

    public function test_uses_sensor_timestamp_when_more_recent_than_counter(): void
    {
        // Counter last increased 40 min ago, but the sensor reports a fresh strike.
        $this->reading(now()->subMinutes(50), 2);
        $this->reading(now()->subMinutes(40), 3); // last counter increase
        $sensorTime = now()->subMinutes(5)->startOfSecond();
        $this->reading(now()->subMinutes(1), 3, $sensorTime); // no new count, fresh sensor time

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
