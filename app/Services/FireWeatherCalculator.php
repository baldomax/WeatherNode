<?php

namespace App\Services;

use App\Models\DailySummary;
use Carbon\Carbon;

/**
 * Calculates fire weather and drought indices from on-site DailySummary data.
 *
 * Indices implemented:
 *  - Angström Index (European fire risk, based on max temp + min humidity)
 *  - Consecutive dry days (days with < 1 mm precipitation)
 *  - 30-day rolling precipitation total
 */
class FireWeatherCalculator
{
    // --- Angström Index -----------------------------------------------------------

    /**
     * Angström Fire Index: A = (H / 20) + (27 - T) / 10
     *
     * H  = minimum relative humidity of the day (%)
     * T  = maximum temperature of the day (°C)
     *
     * < 2.5 → high risk  |  2.5–4.0 → moderate  |  > 4.0 → low risk
     */
    public function angstromIndex(?float $tempMax, ?float $humidityMin): ?float
    {
        if ($tempMax === null || $humidityMin === null) {
            return null;
        }

        return round(($humidityMin / 20) + ((27 - $tempMax) / 10), 2);
    }

    /**
     * Danger level string from an Angström Index value.
     */
    public function dangerLevel(?float $index): string
    {
        if ($index === null) return 'unknown';
        if ($index < 1.0)  return 'extreme';
        if ($index < 2.5)  return 'high';
        if ($index < 4.0)  return 'moderate';
        return 'low';
    }

    /**
     * Tailwind text colour class for a danger level.
     */
    public function dangerColor(string $level): string
    {
        return match ($level) {
            'extreme'  => 'text-red-600',
            'high'     => 'text-orange-500',
            'moderate' => 'text-yellow-400',
            'low'      => 'text-green-400',
            default    => 'text-gray-400',
        };
    }

    /**
     * Tailwind background colour class for a danger level badge.
     */
    public function dangerBgColor(string $level): string
    {
        return match ($level) {
            'extreme'  => 'bg-red-600/20 border-red-600/40',
            'high'     => 'bg-orange-500/20 border-orange-500/40',
            'moderate' => 'bg-yellow-400/20 border-yellow-400/40',
            'low'      => 'bg-green-400/20 border-green-400/40',
            default    => 'bg-gray-600/20 border-gray-600/40',
        };
    }

    // --- Drought indicators -------------------------------------------------------

    /**
     * Count consecutive days with < 1 mm of rain ending today (or last data day).
     */
    public function consecutiveDryDays(): int
    {
        $rows = DailySummary::orderByDesc('date')
            ->take(180)
            ->pluck('rain_total', 'date');

        $count = 0;
        foreach ($rows as $rain) {
            if ($rain !== null && $rain >= 1.0) {
                break;
            }
            $count++;
        }

        return $count;
    }

    /**
     * Rolling precipitation total for the last $days days.
     */
    public function rollingRainTotal(int $days = 30): ?float
    {
        $since = Carbon::today()->subDays($days - 1)->toDateString();

        $total = DailySummary::where('date', '>=', $since)
            ->sum('rain_total');

        return $total !== null ? round((float) $total, 1) : null;
    }

    // --- Current state -----------------------------------------------------------

    /**
     * Return all current fire weather data from the most recent DailySummary.
     */
    public function currentIndices(): array
    {
        $today = DailySummary::orderByDesc('date')->first();

        $index   = $today ? $this->angstromIndex($today->temp_high, $today->humidity_low) : null;
        $level   = $this->dangerLevel($index);
        $cdd     = $this->consecutiveDryDays();
        $rain30  = $this->rollingRainTotal(30);
        $rain7   = $this->rollingRainTotal(7);

        return [
            'date'              => $today?->date?->toDateString(),
            'temp_high'         => $today?->temp_high,
            'humidity_low'      => $today?->humidity_low,
            'angstrom_index'    => $index,
            'danger_level'      => $level,
            'danger_color'      => $this->dangerColor($level),
            'danger_bg_color'   => $this->dangerBgColor($level),
            'consecutive_dry'   => $cdd,
            'rain_7d'           => $rain7,
            'rain_30d'          => $rain30,
        ];
    }

    // --- Historical chart data ---------------------------------------------------

    /**
     * Return daily Angström index values for the last $daysBack days, for charting.
     *
     * @return array{dates: string[], values: (float|null)[]}
     */
    public function historicalData(int $daysBack = 90): array
    {
        $since = Carbon::today()->subDays($daysBack - 1)->toDateString();

        $rows = DailySummary::where('date', '>=', $since)
            ->orderBy('date')
            ->get(['date', 'temp_high', 'humidity_low']);

        $dates  = [];
        $values = [];

        foreach ($rows as $row) {
            $dates[]  = $row->date->toDateString();
            $values[] = $this->angstromIndex($row->temp_high, $row->humidity_low);
        }

        return [
            'dates'  => $dates,
            'values' => $values,
        ];
    }
}
