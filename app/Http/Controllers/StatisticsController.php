<?php

namespace App\Http\Controllers;

use App\Models\ClimateRecord;
use App\Models\DailySummary;
use App\Services\PhenologyCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        // Get available years from the database - database agnostic
        $availableYears = $this->getAvailableYears();

        if (empty($availableYears)) {
            $availableYears = [(string) now()->year];
        }

        // Determine default year: use current year if it has data, otherwise use most recent year with data
        $currentYear = (string) now()->year;
        $defaultYear = in_array($currentYear, $availableYears) ? $currentYear : $availableYears[0];

        // Get year from request, or use default
        $year = $request->input('year');
        $year = $year !== null ? (string) $year : $defaultYear;

        // Validate that the requested year actually has data
        if (!in_array($year, $availableYears)) {
            $year = $defaultYear;
        }

        $yearlyStats = $this->getYearlyStats($year);
        $monthlyStats = $this->getMonthlyStats($year);
        $records = $this->getAllTimeRecords();
        $climateData = $this->getClimateNormals($year);
        $phenology = $this->getPhenology($year);

        return view('weather.statistics', compact(
            'year',
            'availableYears',
            'yearlyStats',
            'monthlyStats',
            'records',
            'climateData',
            'phenology'
        ));
    }

    /**
     * JSON endpoint for comparing two periods
     */
    public function compare(Request $request)
    {
        $type = $request->input('type', 'year');
        $a = $request->input('a');
        $b = $request->input('b');

        if (!$a || !$b) {
            return response()->json(['error' => 'Both periods (a and b) are required'], 422);
        }

        if ($type === 'month') {
            $dataA = $this->getMonthDailyData($a);
            $dataB = $this->getMonthDailyData($b);
        } else {
            $dataA = $this->getMonthlyStats(substr($a, 0, 4));
            $dataB = $this->getMonthlyStats(substr($b, 0, 4));
        }

        $summaryA = $type === 'month' ? $this->getMonthSummary($a) : $this->getYearlyStats(substr($a, 0, 4));
        $summaryB = $type === 'month' ? $this->getMonthSummary($b) : $this->getYearlyStats(substr($b, 0, 4));

        return response()->json([
            'type' => $type,
            'a' => ['label' => $a, 'data' => $dataA, 'summary' => $summaryA],
            'b' => ['label' => $b, 'data' => $dataB, 'summary' => $summaryB],
        ]);
    }

    protected function getAvailableYears(): array
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            return DailySummary::selectRaw('DISTINCT strftime("%Y", date) as year')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->map(fn($y) => (string) $y)
                ->toArray();
        }
        return DailySummary::selectRaw('DISTINCT YEAR(date) as year')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->map(fn($y) => (string) $y)
            ->toArray();
    }

    protected function getYearlyStats($year)
    {
        $summaries = $this->getSummariesForYear((string) $year);

        if ($summaries->isEmpty()) {
            return null;
        }

        $maxTempRecord = $summaries->sortByDesc('temp_high')->first();
        $minTempRecord = $summaries->sortBy('temp_low')->first();
        $maxWindRecord = $summaries->sortByDesc('wind_max')->first();

        return [
            'temp_high' => $summaries->max('temp_high'),
            'temp_high_date' => $maxTempRecord?->date?->format('d M'),
            'temp_low' => $summaries->min('temp_low'),
            'temp_low_date' => $minTempRecord?->date?->format('d M'),
            'rain_total' => round($summaries->sum('rain_total'), 1),
            'rain_days' => $summaries->filter(fn ($s) => $s->rain_total !== null && $s->rain_total >= 0.1)->count(),
            'wind_max' => $summaries->max('wind_max'),
            'wind_max_date' => $maxWindRecord?->date?->format('d M'),
            'days_count' => $summaries->count(),
        ];
    }

    protected function getMonthlyStats($year)
    {
        $year = (string) $year;
        $driver = DB::connection()->getDriverName();
        $months = [];

        for ($m = 1; $m <= 12; $m++) {
            if ($driver === 'sqlite') {
                $summaries = DailySummary::whereRaw('strftime("%Y", date) = ?', [$year])
                    ->whereRaw('strftime("%m", date) = ?', [str_pad($m, 2, '0', STR_PAD_LEFT)])
                    ->get();
            } else {
                $summaries = DailySummary::whereRaw('YEAR(date) = ?', [$year])
                    ->whereRaw('MONTH(date) = ?', [$m])
                    ->get();
            }

            if ($summaries->isEmpty()) {
                $months[$m] = null;
                continue;
            }

            $months[$m] = [
                'temp_avg' => round($summaries->avg('temp_avg'), 1),
                'temp_high' => round($summaries->max('temp_high'), 1),
                'temp_low' => round($summaries->min('temp_low'), 1),
                'rain_total' => round($summaries->sum('rain_total'), 1),
                'rain_days' => $summaries->where('rain_total', '>', 0)->count(),
            ];
        }

        return $months;
    }

    protected function getAllTimeRecords()
    {
        $summaries = DailySummary::all();

        if ($summaries->isEmpty()) {
            return null;
        }

        return [
            'temperature' => [
                'highest' => $this->buildRecordList($summaries, 'temp_high', 'desc'),
                'lowest' => $this->buildRecordList($summaries, 'temp_low', 'asc'),
                'warmest_avg' => $this->buildRecordList($summaries, 'temp_avg', 'desc'),
                'coldest_avg' => $this->buildRecordList($summaries, 'temp_avg', 'asc'),
                'largest_range' => $this->buildComputedRecordList($summaries, function ($s) {
                    return ($s->temp_high !== null && $s->temp_low !== null) ? round($s->temp_high - $s->temp_low, 1) : null;
                }, 'desc'),
            ],
            'precipitation' => [
                'wettest_day' => $this->buildRecordList($summaries, 'rain_total', 'desc'),
                'highest_rate' => $this->buildRecordList($summaries, 'rain_rate_max', 'desc'),
            ],
            'wind' => [
                'strongest_gust' => $this->buildRecordList($summaries, 'wind_max', 'desc'),
                'highest_avg' => $this->buildRecordList($summaries, 'wind_avg', 'desc'),
            ],
            'pressure' => [
                'highest' => $this->buildRecordList($summaries, 'pressure_high', 'desc'),
                'lowest' => $this->buildRecordList($summaries->filter(fn($s) => $s->pressure_low > 0), 'pressure_low', 'asc'),
            ],
            'humidity' => [
                'highest' => $this->buildRecordList($summaries, 'humidity_high', 'desc'),
                'lowest' => $this->buildRecordList($summaries->filter(fn($s) => $s->humidity_low > 0), 'humidity_low', 'asc'),
            ],
            'solar' => [
                'highest_uv' => $this->buildRecordList($summaries, 'uv_max', 'desc'),
                'highest_solar' => $this->buildRecordList($summaries, 'solar_max', 'desc'),
                'most_solar_hours' => $this->buildRecordList($summaries, 'solar_hours', 'desc'),
            ],
        ];
    }

    /**
     * Build a top-5 record list for a simple field
     */
    protected function buildRecordList($summaries, string $field, string $direction, int $limit = 5): array
    {
        $filtered = $summaries->filter(fn($s) => $s->$field !== null);

        if ($filtered->isEmpty()) {
            return ['top' => null, 'list' => []];
        }

        $sorted = $direction === 'desc'
            ? $filtered->sortByDesc($field)
            : $filtered->sortBy($field);

        $top = $sorted->take($limit)->values()->map(fn($s) => [
            'value' => $s->$field,
            'date' => $s->date?->format('d M Y'),
        ])->toArray();

        return [
            'top' => $top[0] ?? null,
            'list' => $top,
        ];
    }

    /**
     * Build a top-5 record list for a computed value (e.g., temperature range)
     */
    protected function buildComputedRecordList($summaries, callable $compute, string $direction, int $limit = 5): array
    {
        $computed = $summaries->map(function ($s) use ($compute) {
            $value = $compute($s);
            return $value !== null ? ['value' => $value, 'date' => $s->date?->format('d M Y')] : null;
        })->filter()->values();

        if ($computed->isEmpty()) {
            return ['top' => null, 'list' => []];
        }

        $sorted = $direction === 'desc'
            ? $computed->sortByDesc('value')
            : $computed->sortBy('value');

        $top = $sorted->take($limit)->values()->toArray();

        return [
            'top' => $top[0] ?? null,
            'list' => $top,
        ];
    }

    /**
     * Get climate normals and departure for a given year
     */
    protected function getClimateNormals(string $year): array
    {
        $normals = ClimateRecord::all();
        $summaries = $this->getSummariesForYear($year);

        if ($normals->isEmpty() || $summaries->isEmpty()) {
            return ['has_data' => false];
        }

        // Check if any averages are actually populated
        $hasAverages = $normals->contains(fn($n) => $n->avg_temp !== null);

        if (!$hasAverages) {
            return ['has_data' => false];
        }

        // Build monthly normals from ClimateRecord (average the per-day normals for each month)
        $monthlyNormals = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthRecords = $normals->where('month', $m);
            if ($monthRecords->isEmpty() || $monthRecords->every(fn($r) => $r->avg_high === null)) {
                $monthlyNormals[$m] = null;
                continue;
            }
            $monthlyNormals[$m] = [
                'avg_high' => round($monthRecords->avg('avg_high'), 1),
                'avg_low' => round($monthRecords->avg('avg_low'), 1),
                'avg_temp' => round($monthRecords->avg('avg_temp'), 1),
                'avg_precipitation' => round($monthRecords->sum('avg_precipitation'), 1),
            ];
        }

        // Build monthly actuals from DailySummary
        $driver = DB::connection()->getDriverName();
        $monthlyActuals = [];
        for ($m = 1; $m <= 12; $m++) {
            if ($driver === 'sqlite') {
                $monthSummaries = $summaries->filter(fn($s) => (int) $s->date->format('m') === $m);
            } else {
                $monthSummaries = $summaries->filter(fn($s) => (int) $s->date->format('m') === $m);
            }

            if ($monthSummaries->isEmpty()) {
                $monthlyActuals[$m] = null;
                continue;
            }

            $monthlyActuals[$m] = [
                'avg_high' => round($monthSummaries->avg('temp_high'), 1),
                'avg_low' => round($monthSummaries->avg('temp_low'), 1),
                'avg_temp' => round($monthSummaries->avg('temp_avg'), 1),
                'rain_total' => round($monthSummaries->sum('rain_total'), 1),
            ];
        }

        // Build departure data
        $departures = [];
        for ($m = 1; $m <= 12; $m++) {
            if (!isset($monthlyNormals[$m]) || !isset($monthlyActuals[$m])) {
                $departures[$m] = null;
                continue;
            }
            $departures[$m] = [
                'temp' => round($monthlyActuals[$m]['avg_temp'] - $monthlyNormals[$m]['avg_temp'], 1),
                'rain' => round($monthlyActuals[$m]['rain_total'] - $monthlyNormals[$m]['avg_precipitation'], 1),
            ];
        }

        // Build chart data arrays (1-indexed by month)
        $chartData = [
            'months' => range(1, 12),
            'normal_high' => [],
            'normal_low' => [],
            'actual_high' => [],
            'actual_low' => [],
        ];

        for ($m = 1; $m <= 12; $m++) {
            $chartData['normal_high'][] = $monthlyNormals[$m]['avg_high'] ?? null;
            $chartData['normal_low'][] = $monthlyNormals[$m]['avg_low'] ?? null;
            $chartData['actual_high'][] = $monthlyActuals[$m]['avg_high'] ?? null;
            $chartData['actual_low'][] = $monthlyActuals[$m]['avg_low'] ?? null;
        }

        return [
            'has_data' => true,
            'normals' => $monthlyNormals,
            'actuals' => $monthlyActuals,
            'departures' => $departures,
            'chart' => $chartData,
        ];
    }

    /**
     * Get daily data for a specific month (for comparison)
     */
    protected function getMonthDailyData(string $yearMonth): array
    {
        $parts = explode('-', $yearMonth);
        if (count($parts) !== 2) {
            return [];
        }

        [$year, $month] = $parts;
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $summaries = DailySummary::whereRaw('strftime("%Y", date) = ?', [$year])
                ->whereRaw('strftime("%m", date) = ?', [str_pad($month, 2, '0', STR_PAD_LEFT)])
                ->orderBy('date')
                ->get();
        } else {
            $summaries = DailySummary::whereRaw('YEAR(date) = ?', [$year])
                ->whereRaw('MONTH(date) = ?', [(int) $month])
                ->orderBy('date')
                ->get();
        }

        $days = [];
        foreach ($summaries as $s) {
            $day = (int) $s->date->format('d');
            $days[$day] = [
                'temp_high' => $s->temp_high,
                'temp_low' => $s->temp_low,
                'temp_avg' => $s->temp_avg,
                'rain_total' => $s->rain_total,
                'wind_max' => $s->wind_max,
            ];
        }

        return $days;
    }

    /**
     * Get summary stats for a specific month
     */
    protected function getMonthSummary(string $yearMonth): ?array
    {
        $parts = explode('-', $yearMonth);
        if (count($parts) !== 2) {
            return null;
        }

        [$year, $month] = $parts;
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $summaries = DailySummary::whereRaw('strftime("%Y", date) = ?', [$year])
                ->whereRaw('strftime("%m", date) = ?', [str_pad($month, 2, '0', STR_PAD_LEFT)])
                ->get();
        } else {
            $summaries = DailySummary::whereRaw('YEAR(date) = ?', [$year])
                ->whereRaw('MONTH(date) = ?', [(int) $month])
                ->get();
        }

        if ($summaries->isEmpty()) {
            return null;
        }

        return [
            'temp_high' => $summaries->max('temp_high'),
            'temp_low' => $summaries->min('temp_low'),
            'temp_avg' => round($summaries->avg('temp_avg'), 1),
            'rain_total' => round($summaries->sum('rain_total'), 1),
            'wind_max' => $summaries->max('wind_max'),
            'days_count' => $summaries->count(),
        ];
    }

    /**
     * Helper to get all daily summaries for a year
     */
    protected function getSummariesForYear(string $year)
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            return DailySummary::whereRaw('strftime("%Y", date) = ?', [$year])->get();
        }
        return DailySummary::whereRaw('YEAR(date) = ?', [$year])->get();
    }

    /**
     * Phenology / Season Tracker data for the given year.
     * Cached until 00:10 the next day (DailySummary only changes after weather:summarize at 00:05).
     */
    protected function getPhenology(string $year): array
    {
        $expiry = now()->addDay()->startOfDay()->addMinutes(10);
        $key    = "phenology_{$year}";

        return Cache::remember($key, $expiry, function () use ($year) {
            return app(PhenologyCalculator::class)->getForYear($year);
        });
    }
}
