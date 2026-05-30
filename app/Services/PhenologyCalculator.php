<?php

namespace App\Services;

use App\Models\DailySummary;
use Illuminate\Support\Collection;

/**
 * Calculates phenological / seasonal milestones from DailySummary data.
 *
 * Day-type definitions follow KNMI (Royal Netherlands Meteorological Institute) standards:
 *  - Frost day     : T_min  <   0 °C
 *  - Ice day       : T_max  <   0 °C
 *  - Spring day    : T_max  ≥  15 °C
 *  - Summer day    : T_max  ≥  25 °C
 *  - Tropical day  : T_max  ≥  30 °C
 *  - Precip day    : rain   ≥   0.1 mm
 *
 * Also computes Growing Degree Day (GDD) accumulation using base 10 °C.
 */
class PhenologyCalculator
{
    // KNMI temperature thresholds (°C)
    const FROST_THRESHOLD    = 0.0;
    const ICE_THRESHOLD      = 0.0;
    const SPRING_THRESHOLD   = 15.0;
    const SUMMER_THRESHOLD   = 25.0;
    const TROPICAL_THRESHOLD = 30.0;
    const PRECIP_THRESHOLD   = 0.1;   // mm
    const GDD_BASE           = 10.0;  // °C, standard Netherlands agriculture base
    const GDD_PEAK_WINDOW    = 14;    // days, useful for spotting sustained warm spells

    /**
     * Return all phenology data for the given year.
     */
    public function getForYear(string $year): array
    {
        $summaries = DailySummary::whereYear('date', $year)->orderBy('date')->get();

        if ($summaries->isEmpty()) {
            return ['has_data' => false, 'year' => $year];
        }

        // Load all historical data once for average calculations (exclude current year)
        $allSummaries    = DailySummary::orderBy('date')->get();
        $historicalAvgs  = $this->historicalDoyAverages($allSummaries, $year);

        return [
            'has_data'   => true,
            'year'       => $year,
            'day_counts' => $this->dayCounts($summaries),
            'milestones' => $this->milestones($summaries, $historicalAvgs),
            'gdd'        => $this->gddAccumulation($summaries),
        ];
    }

    // -------------------------------------------------------------------------
    // Day-type counts
    // -------------------------------------------------------------------------

    protected function dayCounts(Collection $summaries): array
    {
        return [
            'frost_days'    => $summaries->filter(fn($s) => $s->temp_low  !== null && $s->temp_low  <  self::FROST_THRESHOLD)->count(),
            'ice_days'      => $summaries->filter(fn($s) => $s->temp_high !== null && $s->temp_high <  self::ICE_THRESHOLD)->count(),
            'spring_days'   => $summaries->filter(fn($s) => $s->temp_high !== null && $s->temp_high >= self::SPRING_THRESHOLD)->count(),
            'summer_days'   => $summaries->filter(fn($s) => $s->temp_high !== null && $s->temp_high >= self::SUMMER_THRESHOLD)->count(),
            'tropical_days' => $summaries->filter(fn($s) => $s->temp_high !== null && $s->temp_high >= self::TROPICAL_THRESHOLD)->count(),
            'precip_days'   => $summaries->filter(fn($s) => $s->rain_total !== null && $s->rain_total >= self::PRECIP_THRESHOLD)->count(),
            'total_days'    => $summaries->count(),
        ];
    }

    // -------------------------------------------------------------------------
    // Seasonal milestones
    // -------------------------------------------------------------------------

    protected function milestones(Collection $summaries, array $historicalAvgs): array
    {
        return [
            // First spring day (T_max ≥ 15, from February onwards to skip fluke Jan days)
            'first_spring' => $this->firstOccurrence(
                $summaries->filter(fn($s) => $s->date->month >= 2),
                fn($s) => $s->temp_high !== null && $s->temp_high >= self::SPRING_THRESHOLD,
                'first_spring', $historicalAvgs
            ),
            // First summer day (T_max ≥ 25)
            'first_summer' => $this->firstOccurrence(
                $summaries,
                fn($s) => $s->temp_high !== null && $s->temp_high >= self::SUMMER_THRESHOLD,
                'first_summer', $historicalAvgs
            ),
            // First tropical day (T_max ≥ 30)
            'first_tropical' => $this->firstOccurrence(
                $summaries,
                fn($s) => $s->temp_high !== null && $s->temp_high >= self::TROPICAL_THRESHOLD,
                'first_tropical', $historicalAvgs
            ),
            // Last spring frost (last T_min < 0 in Jan–Jun = end of frost season)
            'last_spring_frost' => $this->lastOccurrence(
                $summaries->filter(fn($s) => $s->date->month <= 6),
                fn($s) => $s->temp_low !== null && $s->temp_low < self::FROST_THRESHOLD,
                'last_spring_frost', $historicalAvgs
            ),
            // First autumn frost (first T_min < 0 from Aug onwards = start of frost season)
            'first_autumn_frost' => $this->firstOccurrence(
                $summaries->filter(fn($s) => $s->date->month >= 8),
                fn($s) => $s->temp_low !== null && $s->temp_low < self::FROST_THRESHOLD,
                'first_autumn_frost', $historicalAvgs
            ),
            // First ice day (T_max < 0)
            'first_ice' => $this->firstOccurrence(
                $summaries,
                fn($s) => $s->temp_high !== null && $s->temp_high < self::ICE_THRESHOLD,
                'first_ice', $historicalAvgs
            ),
        ];
    }

    protected function firstOccurrence(Collection $summaries, callable $test, string $key, array $avgs): ?array
    {
        $match = $summaries->first($test);
        if (!$match) {
            return null;
        }

        return $this->buildMilestoneEntry($match, $key, $avgs);
    }

    protected function lastOccurrence(Collection $summaries, callable $test, string $key, array $avgs): ?array
    {
        $match = $summaries->filter($test)->last();
        if (!$match) {
            return null;
        }

        return $this->buildMilestoneEntry($match, $key, $avgs);
    }

    protected function buildMilestoneEntry($summary, string $key, array $avgs): array
    {
        $doy    = (int) $summary->date->format('z') + 1; // 1-based day of year
        $avgDoy = $avgs[$key] ?? null;
        $diff   = $avgDoy !== null ? (int) round($doy - $avgDoy) : null;

        return [
            'date'      => $summary->date->toDateString(),
            'formatted' => $summary->date->format('d M'),
            'doy'       => $doy,
            'avg_doy'   => $avgDoy,
            'diff_days' => $diff,
        ];
    }

    // -------------------------------------------------------------------------
    // Historical averages (day-of-year) per milestone key, excluding given year
    // -------------------------------------------------------------------------

    protected function historicalDoyAverages(Collection $all, string $excludeYear): array
    {
        // groupBy on an Eloquent Collection returns an Eloquent Collection of sub-Collections.
        // Using ->except() would call getKey() on each sub-Collection (not a model) → exception.
        // Use filter() instead to exclude the current year by key.
        $years = $all->groupBy(fn($s) => $s->date->year)
                     ->filter(fn($group, $yr) => $yr !== (int) $excludeYear);

        if ($years->isEmpty()) {
            return [];
        }

        $tests = [
            'first_spring'       => [false, fn($s) => $s->temp_high !== null && $s->temp_high >= self::SPRING_THRESHOLD   && $s->date->month >= 2],
            'first_summer'       => [false, fn($s) => $s->temp_high !== null && $s->temp_high >= self::SUMMER_THRESHOLD],
            'first_tropical'     => [false, fn($s) => $s->temp_high !== null && $s->temp_high >= self::TROPICAL_THRESHOLD],
            'last_spring_frost'  => [true,  fn($s) => $s->temp_low  !== null && $s->temp_low  <  self::FROST_THRESHOLD    && $s->date->month <= 6],
            'first_autumn_frost' => [false, fn($s) => $s->temp_low  !== null && $s->temp_low  <  self::FROST_THRESHOLD    && $s->date->month >= 8],
            'first_ice'          => [false, fn($s) => $s->temp_high !== null && $s->temp_high <  self::ICE_THRESHOLD],
        ];

        $accumulator = array_fill_keys(array_keys($tests), []);

        foreach ($years as $yearGroup) {
            $sorted = $yearGroup->sortBy('date');
            foreach ($tests as $key => [$useLast, $test]) {
                $match = $useLast
                    ? $sorted->filter($test)->last()
                    : $sorted->first($test);
                if ($match) {
                    $accumulator[$key][] = (int) $match->date->format('z') + 1;
                }
            }
        }

        $averages = [];
        foreach ($accumulator as $key => $doys) {
            $averages[$key] = !empty($doys) ? (int) round(array_sum($doys) / count($doys)) : null;
        }

        return $averages;
    }

    // -------------------------------------------------------------------------
    // Growing Degree Days accumulation
    // -------------------------------------------------------------------------

    /**
     * Cumulative GDD from January 1, using base 10 °C.
     * Daily GDD = max(0, ((T_max + T_min) / 2) − base)
     *
     * @return array{
     *     dates: string[],
     *     values: float[],
     *     daily_values: float[],
     *     total: float,
     *     peak_window_days: int,
     *     peak_window_values: array<int, float|null>,
     *     best_period: array{
     *         start_date: string,
     *         end_date: string,
     *         total: float,
     *         average_per_day: float
     *     }|null
     * }
     */
    public function gddAccumulation(Collection $summaries): array
    {
        $dates            = [];
        $values           = [];
        $dailyValues      = [];
        $rawDailyValues   = [];
        $cumulative       = 0.0;

        foreach ($summaries as $s) {
            if ($s->temp_high === null || $s->temp_low === null) {
                continue;
            }

            $daily = max(0.0, (($s->temp_high + $s->temp_low) / 2) - self::GDD_BASE);

            $rawDailyValues[] = $daily;
            $dailyValues[]    = round($daily, 1);
            $cumulative      += $daily;
            $dates[]          = $s->date->toDateString();
            $values[]         = round($cumulative, 1);
        }

        if (empty($dates)) {
            return [
                'dates'              => [],
                'values'             => [],
                'daily_values'       => [],
                'total'              => 0,
                'peak_window_days'   => self::GDD_PEAK_WINDOW,
                'peak_window_values' => [],
                'best_period'        => null,
            ];
        }

        $windowDays      = min(self::GDD_PEAK_WINDOW, count($rawDailyValues));
        $rollingValues   = [];
        $bestWindowTotal = null;
        $bestWindowEnd   = null;
        $windowSum       = 0.0;

        foreach ($rawDailyValues as $index => $daily) {
            $windowSum += $daily;

            if ($index >= $windowDays) {
                $windowSum -= $rawDailyValues[$index - $windowDays];
            }

            if ($index >= ($windowDays - 1)) {
                $rollingValues[] = round($windowSum, 1);

                if ($bestWindowTotal === null || $windowSum > $bestWindowTotal) {
                    $bestWindowTotal = $windowSum;
                    $bestWindowEnd   = $index;
                }

                continue;
            }

            $rollingValues[] = null;
        }

        $bestPeriod = null;
        if ($bestWindowTotal !== null && $bestWindowEnd !== null) {
            $bestWindowStart = $bestWindowEnd - $windowDays + 1;

            $bestPeriod = [
                'start_date'      => $dates[$bestWindowStart],
                'end_date'        => $dates[$bestWindowEnd],
                'total'           => round($bestWindowTotal, 1),
                'average_per_day' => round($bestWindowTotal / $windowDays, 1),
            ];
        }

        return [
            'dates'              => $dates,
            'values'             => $values,
            'daily_values'       => $dailyValues,
            'total'              => (int) round($cumulative),
            'peak_window_days'   => $windowDays,
            'peak_window_values' => $rollingValues,
            'best_period'        => $bestPeriod,
        ];
    }
}
