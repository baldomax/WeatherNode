<?php

namespace App\Services\Weather;

use Illuminate\Support\Collection;

class SunshineHoursCalculator
{
    /**
     * WMO sunshine threshold: solar radiation at or above this value counts as sunshine.
     */
    public const RADIATION_THRESHOLD_WM2 = 120.0;

    /**
     * Prefer station-reported daily sunshine hours; otherwise estimate from solar radiation.
     */
    public function resolveFromReadings(Collection $readings): ?float
    {
        $reported = $readings
            ->pluck('solar_hours')
            ->filter(static fn ($value) => $value !== null)
            ->max();

        if ($reported !== null) {
            return round((float) $reported, 2);
        }

        return $this->estimateFromSolarRadiation($readings);
    }

    /**
     * Estimate sunshine hours by integrating intervals where radiation meets the WMO threshold.
     */
    public function estimateFromSolarRadiation(Collection $readings): ?float
    {
        $ordered = $readings
            ->filter(static fn ($reading) => $reading->recorded_at !== null && $reading->solar_radiation !== null)
            ->sortBy(static fn ($reading) => $reading->recorded_at->timestamp)
            ->values();

        if ($ordered->count() < 2) {
            return null;
        }

        $hours = 0.0;
        for ($i = 1; $i < $ordered->count(); $i++) {
            $previous = $ordered[$i - 1];
            $current = $ordered[$i];

            if ((float) $previous->solar_radiation < self::RADIATION_THRESHOLD_WM2) {
                continue;
            }

            $deltaSeconds = $current->recorded_at->getTimestamp() - $previous->recorded_at->getTimestamp();
            if ($deltaSeconds <= 0 || $deltaSeconds > 6 * 3600) {
                continue;
            }

            $hours += $deltaSeconds / 3600;
        }

        return $hours > 0 ? round($hours, 2) : null;
    }
}
