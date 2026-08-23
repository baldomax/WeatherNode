<?php

namespace App\Http\Controllers;

use App\Models\DailySummary;
use App\Models\Setting;
use App\Models\WeatherReading;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HistoryController extends Controller
{
    /**
     * Show history page
     */
    public function index(Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $summaries = DailySummary::forMonth($year, $month)
            ->orderBy('date')
            ->get();

        // Get available years - database agnostic
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $availableYears = DailySummary::selectRaw('DISTINCT strftime("%Y", date) as year')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->toArray();
        } else {
            // MySQL, PostgreSQL, etc.
            $availableYears = DailySummary::selectRaw('DISTINCT YEAR(date) as year')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->toArray();
        }

        if (empty($availableYears)) {
            $availableYears = [now()->year];
        }

        $monthlyStats = [
            'temp_high' => $summaries->max('temp_high'),
            'temp_low' => $summaries->min('temp_low'),
            'temp_avg' => round($summaries->avg('temp_avg'), 1),
            'rain_total' => round($summaries->sum('rain_total'), 1),
            'wind_max' => $summaries->max('wind_max'),
            'days_with_rain' => $summaries->where('rain_total', '>', 0)->count(),
        ];

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = (clone $monthStart)->endOfMonth()->endOfDay();
        $daysInMonth = $monthStart->daysInMonth;
        $summaryByDay = $summaries->keyBy(fn ($summary) => (int) $summary->date->format('j'));
        $fallbackByDate = WeatherReading::query()
            ->whereBetween('recorded_at', [$monthStart, $monthEnd])
            ->selectRaw('DATE(recorded_at) as day_date')
            ->selectRaw('AVG(pressure_rel) as pressure_avg')
            ->selectRaw('AVG(wind_direction) as wind_dir_avg')
            ->selectRaw('AVG(dew_point) as dew_point_avg')
            ->groupBy('day_date')
            ->get()
            ->keyBy('day_date');
        $chartSeries = [
            'temp_high' => [],
            'temp_avg' => [],
            'temp_low' => [],
            'rain_total' => [],
            'rain_rate_max' => [],
            'wind_max' => [],
            'wind_avg' => [],
            'wind_dir' => [],
            'pressure_avg' => [],
            'humidity_avg' => [],
            'dew_point_avg' => [],
            'uv_max' => [],
            'solar_max' => [],
        ];
        $chartDays = [];
        $chartDates = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            $dateKey = $date->toDateString();
            $summary = $summaryByDay->get($day);
            $fallback = $fallbackByDate->get($dateKey);

            $pressureAvg = $summary?->pressure_avg;
            if ($pressureAvg === null && $fallback?->pressure_avg !== null) {
                $pressureAvg = round((float) $fallback->pressure_avg, 1);
            }
            if ($pressureAvg === null && $summary?->pressure_high !== null && $summary?->pressure_low !== null) {
                $pressureAvg = round((((float) $summary->pressure_high) + ((float) $summary->pressure_low)) / 2, 1);
            }

            $windDominantDirection = $summary?->wind_dominant_direction;
            if ($windDominantDirection === null && $fallback?->wind_dir_avg !== null) {
                $windDominantDirection = ((int) round((float) $fallback->wind_dir_avg) % 360 + 360) % 360;
            }

            $dewPointAvg = $fallback?->dew_point_avg !== null ? round((float) $fallback->dew_point_avg, 1) : null;

            $chartDays[] = $day;
            $chartDates[] = $date->format('Y-m-d');
            $chartSeries['temp_high'][] = $summary?->temp_high;
            $chartSeries['temp_avg'][] = $summary?->temp_avg;
            $chartSeries['temp_low'][] = $summary?->temp_low;
            $chartSeries['rain_total'][] = $summary?->rain_total;
            $chartSeries['rain_rate_max'][] = $summary?->rain_rate_max;
            $chartSeries['wind_max'][] = $summary?->wind_max;
            $chartSeries['wind_avg'][] = $summary?->wind_avg;
            $chartSeries['wind_dir'][] = $windDominantDirection;
            $chartSeries['pressure_avg'][] = $pressureAvg;
            $chartSeries['humidity_avg'][] = $summary?->humidity_avg;
            $chartSeries['dew_point_avg'][] = $dewPointAvg;
            $chartSeries['uv_max'][] = $summary?->uv_max;
            $chartSeries['solar_max'][] = $summary?->solar_max;
        }

        $historyChart = [
            'days' => $chartDays,
            'dates' => $chartDates,
            'series' => $chartSeries,
        ];

        $allChartKeys = ['temperature', 'wind', 'solar_uv', 'precipitation', 'humidity', 'soil', 'leaf_wetness', 'air_quality', 'co2', 'lightning', 'water_temp', 'extra_sensors'];
        $chartSettings = Setting::getValue('charts.day_visible', $allChartKeys);
        if (!is_array($chartSettings)) {
            $chartSettings = $allChartKeys;
        }

        return view('weather.history', compact(
            'summaries',
            'year',
            'month',
            'availableYears',
            'monthlyStats',
            'historyChart',
            'chartSettings'
        ));
    }

    /**
     * Show specific day detail
     */
    /**
     * The oldest day with a reading, or null when nothing has been recorded
     * yet. Cached because it bounds every day page and never moves backwards.
     */
    private function firstRecordedDay(): ?Carbon
    {
        $first = Cache::remember('history.first_recorded_day', now()->addHours(12), function () {
            return WeatherReading::min('recorded_at');
        });

        if (!$first) {
            return null;
        }

        return Carbon::parse($first)->startOfDay();
    }

    public function day(Request $request, string $date)
    {
        // Strict date parsing (prevents weird inputs + keeps routes predictable)
        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            abort(404);
        }
        $dateString = $dateObj->format('Y-m-d');

        // Outside the recorded period there is nothing to show, and the day
        // page used to link one day further back forever. A crawler could walk
        // from the first real day back to the year 1800, in every language.
        $firstRecordedDay = $this->firstRecordedDay();

        if ($dateObj->isAfter(now()->startOfDay())
            || ($firstRecordedDay !== null && $dateObj->lt($firstRecordedDay))) {
            abort(404);
        }

        $summary = DailySummary::whereDate('date', $dateString)->first();

        // IMPORTANT: do NOT eager-load all readings here (can be thousands of rows).
        // We'll load the big table asynchronously via `dayReadings()` to keep the initial page fast.
        $readingsQuery = WeatherReading::whereDate('recorded_at', $dateString);
        $readingsCount = (clone $readingsQuery)->count();

        // Initial table pagination state (URL-shareable via query params)
        $readingsPage = max(1, (int) $request->query('readings_page', 1));
        $readingsPerPage = (int) $request->query('readings_per_page', 60);
        $readingsPerPage = max(60, min(2000, $readingsPerPage));
        
        // Recalculate summary if it's missing key fields (like pressure_avg from older data)
        $needsRecalculation = $summary && $readingsCount > 0 && (
            $summary->pressure_avg === null || 
            $summary->humidity_avg === null ||
            $summary->wind_avg === null
        );
        
        // If no summary exists but we have readings, calculate it on-the-fly
        // OR if summary exists but is missing newer fields, recalculate
        if ((!$summary || $needsRecalculation) && $readingsCount > 0) {
            try {
                $baseAgg = (clone $readingsQuery)->selectRaw('
                    MAX(temperature) as temp_high,
                    MIN(temperature) as temp_low,
                    AVG(temperature) as temp_avg,
                    MAX(humidity) as humidity_high,
                    MIN(humidity) as humidity_low,
                    AVG(humidity) as humidity_avg,
                    MAX(pressure_rel) as pressure_high,
                    MIN(pressure_rel) as pressure_low,
                    AVG(pressure_rel) as pressure_avg,
                    MAX(wind_gust) as wind_gust_max,
                    MAX(wind_speed) as wind_speed_max,
                    AVG(wind_speed) as wind_avg,
                    AVG(wind_direction) as wind_direction_avg,
                    MAX(rain_daily) as rain_total,
                    MAX(rain_rate) as rain_rate_max,
                    MAX(uv_index) as uv_max,
                    MAX(solar_radiation) as solar_max
                ')->first();

                $tempHigh = $baseAgg?->temp_high;
                $tempLow = $baseAgg?->temp_low;
                $windMax = $baseAgg?->wind_gust_max ?? $baseAgg?->wind_speed_max;

                $tempHighTime = null;
                if ($tempHigh !== null) {
                    $tempHighTime = (clone $readingsQuery)
                        ->where('temperature', $tempHigh)
                        ->orderBy('recorded_at')
                        ->value(DB::raw("TIME(recorded_at)"));
                }
                $tempLowTime = null;
                if ($tempLow !== null) {
                    $tempLowTime = (clone $readingsQuery)
                        ->where('temperature', $tempLow)
                        ->orderBy('recorded_at')
                        ->value(DB::raw("TIME(recorded_at)"));
                }
                $windMaxTime = null;
                if ($windMax !== null) {
                    // Prefer gust time if gust is present, else wind_speed time
                    if ($baseAgg?->wind_gust_max !== null) {
                        $windMaxTime = (clone $readingsQuery)
                            ->where('wind_gust', $baseAgg->wind_gust_max)
                            ->orderBy('recorded_at')
                            ->value(DB::raw("TIME(recorded_at)"));
                    } else {
                        $windMaxTime = (clone $readingsQuery)
                            ->where('wind_speed', $baseAgg->wind_speed_max)
                            ->orderBy('recorded_at')
                            ->value(DB::raw("TIME(recorded_at)"));
                    }
                }

                $summary = DailySummary::updateOrCreate(
                    ['date' => $dateString],
                    [
                        'temp_high' => $tempHigh,
                        'temp_high_time' => $tempHighTime,
                        'temp_low' => $tempLow,
                        'temp_low_time' => $tempLowTime,
                        'temp_avg' => $baseAgg?->temp_avg !== null ? round((float) $baseAgg->temp_avg, 1) : null,
                        'humidity_high' => $baseAgg?->humidity_high !== null ? (int) $baseAgg->humidity_high : null,
                        'humidity_low' => $baseAgg?->humidity_low !== null ? (int) $baseAgg->humidity_low : null,
                        'humidity_avg' => $baseAgg?->humidity_avg !== null ? (int) round((float) $baseAgg->humidity_avg) : null,
                        'pressure_high' => $baseAgg?->pressure_high,
                        'pressure_low' => $baseAgg?->pressure_low,
                        'pressure_avg' => $baseAgg?->pressure_avg !== null ? round((float) $baseAgg->pressure_avg, 1) : null,
                        'wind_max' => $windMax,
                        'wind_max_time' => $windMaxTime,
                        'wind_avg' => $baseAgg?->wind_avg !== null ? round((float) $baseAgg->wind_avg, 1) : null,
                        'wind_dominant_direction' => $baseAgg?->wind_direction_avg !== null
                            ? (((int) round((float) $baseAgg->wind_direction_avg) % 360) + 360) % 360
                            : null,
                        'rain_total' => $baseAgg?->rain_total,
                        'rain_rate_max' => $baseAgg?->rain_rate_max,
                        'uv_max' => $baseAgg?->uv_max,
                        'solar_max' => $baseAgg?->solar_max,
                    ]
                );
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle race condition: if record was created by another request, fetch it
                if ($e->getCode() == 23000) { // Integrity constraint violation
                    $summary = DailySummary::whereDate('date', $dateString)->first();
                } else {
                    throw $e;
                }
            }
        }
        
        // If still no summary, create a placeholder
        if (!$summary) {
            $summary = new DailySummary(['date' => $dateString]);
        }

        // Prepare hourly chart data for the day (similar to history page but hourly instead of daily)
        $chartTimes = [];
        $chartDates = [];
        $chartSeries = [
            'temp_high' => [],
            'temp_avg' => [],
            'temp_low' => [],
            'feels_like' => [],
            'humidity_avg' => [],
            'dew_point' => [],
            'rain_total' => [],
            'rain_rate_max' => [],
            'wind_avg' => [],
            'wind_gust_max' => [],
            'wind_dir' => [],
            'pressure_avg' => [],
            'uv_max' => [],
            'solar_max' => [],
        ];

        $driver = DB::connection()->getDriverName();
        $hourExpr = match ($driver) {
            'sqlite' => "strftime('%H', recorded_at)",
            'pgsql' => "to_char(recorded_at, 'HH24')",
            default => "DATE_FORMAT(recorded_at, '%H')", // mysql / mariadb
        };

        $hourlyRows = (clone $readingsQuery)
            ->selectRaw("
                {$hourExpr} as hour,
                MAX(temperature) as temp_high,
                MIN(temperature) as temp_low,
                AVG(temperature) as temp_avg,
                AVG(wind_speed) as wind_speed_avg,
                MAX(wind_gust) as wind_gust_max,
                MAX(wind_speed) as wind_speed_max,
                AVG(wind_direction_avg_10m) as wind_dir_avg_10m,
                AVG(wind_direction) as wind_dir_avg,
                AVG(pressure_rel) as pressure_avg,
                MAX(rain_daily) as rain_daily_max,
                SUM(rain_rate) as rain_rate_sum,
                AVG(feels_like) as feels_like_avg,
                AVG(humidity) as humidity_avg,
                AVG(dew_point) as dew_point_avg,
                MAX(rain_rate) as rain_rate_max,
                MAX(uv_index) as uv_max,
                MAX(solar_radiation) as solar_max
            ")
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        // Create data points for each hour (0-23)
        $previousRainDaily = null;
        for ($hour = 0; $hour < 24; $hour++) {
            $hourStr = str_pad($hour, 2, '0', STR_PAD_LEFT);
            $row = $hourlyRows->get($hourStr);
            
            $chartTimes[] = $hourStr . ':00';
            $chartDates[] = $dateObj->copy()->setTime($hour, 0)->format('Y-m-d H:i:s');
            
            if ($row) {
                $chartSeries['temp_high'][] = $row->temp_high !== null ? (float) $row->temp_high : null;
                $chartSeries['temp_avg'][] = $row->temp_avg !== null ? round((float) $row->temp_avg, 1) : null;
                $chartSeries['temp_low'][] = $row->temp_low !== null ? (float) $row->temp_low : null;

                // Rain bars should be "per hour". We derive it from cumulative `rain_daily` if present.
                $rainHourly = null;
                if ($row->rain_daily_max !== null) {
                    $current = (float) $row->rain_daily_max;
                    if ($previousRainDaily === null) {
                        $rainHourly = 0.0;
                    } else {
                        $rainHourly = max(0.0, $current - $previousRainDaily);
                    }
                    $previousRainDaily = $current;
                } elseif ($row->rain_rate_sum !== null) {
                    // Fallback: sum of rates (unit depends on station; better than empty)
                    $rainHourly = round((float) $row->rain_rate_sum, 2);
                }
                $chartSeries['rain_total'][] = $rainHourly;

                $chartSeries['wind_avg'][] = $row->wind_speed_avg !== null ? round((float) $row->wind_speed_avg, 1) : null;
                $chartSeries['wind_gust_max'][] = $row->wind_gust_max ?? $row->wind_speed_max;

                $dir = $row->wind_dir_avg_10m ?? $row->wind_dir_avg;
                if ($dir !== null) {
                    // Normalize to 0..360 for charting
                    $dir = fmod((float) $dir, 360.0);
                    if ($dir < 0) $dir += 360.0;
                }
                $chartSeries['wind_dir'][] = $dir !== null ? round($dir, 0) : null;
                $chartSeries['pressure_avg'][] = $row->pressure_avg !== null ? round((float) $row->pressure_avg, 1) : null;
                $chartSeries['feels_like'][] = $row->feels_like_avg !== null ? round((float) $row->feels_like_avg, 1) : null;
                $chartSeries['humidity_avg'][] = $row->humidity_avg !== null ? round((float) $row->humidity_avg, 0) : null;
                $chartSeries['dew_point'][] = $row->dew_point_avg !== null ? round((float) $row->dew_point_avg, 1) : null;
                $chartSeries['rain_rate_max'][] = $row->rain_rate_max !== null ? round((float) $row->rain_rate_max, 1) : null;
                $chartSeries['uv_max'][] = $row->uv_max !== null ? round((float) $row->uv_max, 1) : null;
                $chartSeries['solar_max'][] = $row->solar_max !== null ? round((float) $row->solar_max, 0) : null;
            } else {
                $chartSeries['temp_high'][] = null;
                $chartSeries['temp_avg'][] = null;
                $chartSeries['temp_low'][] = null;
                $chartSeries['feels_like'][] = null;
                $chartSeries['humidity_avg'][] = null;
                $chartSeries['dew_point'][] = null;
                $chartSeries['rain_total'][] = null;
                $chartSeries['rain_rate_max'][] = null;
                $chartSeries['wind_avg'][] = null;
                $chartSeries['wind_gust_max'][] = null;
                $chartSeries['wind_dir'][] = null;
                $chartSeries['pressure_avg'][] = null;
                $chartSeries['uv_max'][] = null;
                $chartSeries['solar_max'][] = null;
            }
        }

        // Detect which specialized sensors have data for this day
        $availableSensors = [
            'soil' => false,
            'leaf_wetness' => false,
            'air_quality' => false,
            'co2' => false,
            'lightning' => false,
            'water_temp' => false,
            'extra_sensors' => false,
        ];

        if ($readingsCount > 0) {
            $sensorCheck = (clone $readingsQuery)->selectRaw('
                MAX(soil_moisture_1) as has_soil,
                MAX(leaf_wetness_1) as has_leaf,
                MAX(pm25_ch1) as has_pm25,
                MAX(co2) as has_co2,
                MAX(lightning_count) as has_lightning,
                MAX(water_temperature) as has_water,
                MAX(temp_1) as has_extra_temp
            ')->first();

            $availableSensors['soil'] = $sensorCheck->has_soil !== null;
            $availableSensors['leaf_wetness'] = $sensorCheck->has_leaf !== null;
            $availableSensors['air_quality'] = $sensorCheck->has_pm25 !== null;
            $availableSensors['co2'] = $sensorCheck->has_co2 !== null;
            $availableSensors['lightning'] = $sensorCheck->has_lightning !== null;
            $availableSensors['water_temp'] = $sensorCheck->has_water !== null;
            $availableSensors['extra_sensors'] = $sensorCheck->has_extra_temp !== null;

            // Build sensor-specific hourly aggregations for detected sensors
            foreach ($availableSensors as $sensor => $hasData) {
                if (!$hasData) continue;

                $sensorFields = match ($sensor) {
                    'soil' => $this->buildSoilSelect($readingsQuery, $hourExpr),
                    'leaf_wetness' => $this->buildMultiSensorSelect($readingsQuery, $hourExpr, 'leaf_wetness', 8, 'AVG'),
                    'air_quality' => $this->buildAirQualitySelect($readingsQuery, $hourExpr),
                    'co2' => $this->buildSimpleSelect($readingsQuery, $hourExpr, ['AVG(co2) as co2_avg', 'AVG(co2_temp) as co2_temp_avg', 'AVG(co2_humidity) as co2_humidity_avg']),
                    'lightning' => $this->buildSimpleSelect($readingsQuery, $hourExpr, ['SUM(lightning_count) as lightning_count', 'AVG(lightning_distance) as lightning_distance_avg']),
                    'water_temp' => $this->buildSimpleSelect($readingsQuery, $hourExpr, ['AVG(water_temperature) as water_temp_avg']),
                    'extra_sensors' => $this->buildExtraSensorsSelect($readingsQuery, $hourExpr),
                    default => null,
                };

                if ($sensorFields) {
                    $chartSeries["sensor_{$sensor}"] = $this->fillHourlySensorData($sensorFields, $sensor);
                }
            }
        }

        // Get chart visibility settings from admin
        $allChartKeys = ['temperature', 'wind', 'solar_uv', 'precipitation', 'humidity', 'soil', 'leaf_wetness', 'air_quality', 'co2', 'lightning', 'water_temp', 'extra_sensors'];
        $chartSettings = Setting::getValue('charts.day_visible', $allChartKeys);
        if (!is_array($chartSettings)) {
            $chartSettings = $allChartKeys;
        }

        $dayChart = [
            'times' => $chartTimes,
            'dates' => $chartDates,
            'series' => $chartSeries,
        ];

        return view('weather.day', compact(
            'firstRecordedDay',
            'summary',
            'date',
            'dayChart',
            'readingsCount',
            'readingsPage',
            'readingsPerPage',
            'availableSensors',
            'chartSettings'
        ));
    }

    /**
     * Show year overview with monthly aggregated charts.
     */
    public function year(Request $request)
    {
        $year = $request->input('year', now()->year);

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $availableYears = DailySummary::selectRaw('DISTINCT strftime("%Y", date) as year')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->toArray();
        } else {
            $availableYears = DailySummary::selectRaw('DISTINCT YEAR(date) as year')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->toArray();
        }

        if (empty($availableYears)) {
            $availableYears = [now()->year];
        }

        $summaries = DailySummary::forYear($year)->orderBy('date')->get();

        // Group by month and aggregate
        $byMonth = $summaries->groupBy(fn ($s) => (int) $s->date->format('n'));

        $chartMonths = [];
        $chartMonthLabels = [];
        $chartSeries = [
            'temp_high' => [],
            'temp_avg' => [],
            'temp_low' => [],
            'rain_total' => [],
            'rain_rate_max' => [],
            'wind_max' => [],
            'wind_avg' => [],
            'wind_dir' => [],
            'pressure_avg' => [],
            'humidity_avg' => [],
            'dew_point_avg' => [],
            'uv_max' => [],
            'solar_max' => [],
        ];

        // Monthly summaries for the table
        $monthlySummaries = [];

        for ($m = 1; $m <= 12; $m++) {
            $monthData = $byMonth->get($m, collect());
            $chartMonths[] = $m;
            $chartMonthLabels[] = Carbon::create($year, $m, 1)->format('M');

            if ($monthData->isEmpty()) {
                foreach (array_keys($chartSeries) as $key) {
                    $chartSeries[$key][] = null;
                }
                $monthlySummaries[$m] = null;
                continue;
            }

            $chartSeries['temp_high'][] = $monthData->max('temp_high');
            $chartSeries['temp_low'][] = $monthData->min('temp_low');
            $chartSeries['temp_avg'][] = $monthData->avg('temp_avg') !== null ? round($monthData->avg('temp_avg'), 1) : null;
            $chartSeries['rain_total'][] = round($monthData->sum('rain_total'), 1);
            $chartSeries['rain_rate_max'][] = $monthData->max('rain_rate_max');
            $chartSeries['wind_max'][] = $monthData->max('wind_max');
            $chartSeries['wind_avg'][] = $monthData->avg('wind_avg') !== null ? round($monthData->avg('wind_avg'), 1) : null;
            $chartSeries['pressure_avg'][] = $monthData->avg('pressure_avg') !== null ? round($monthData->avg('pressure_avg'), 1) : null;
            $chartSeries['humidity_avg'][] = $monthData->avg('humidity_avg') !== null ? round($monthData->avg('humidity_avg'), 0) : null;
            $chartSeries['uv_max'][] = $monthData->max('uv_max');
            $chartSeries['solar_max'][] = $monthData->max('solar_max');

            // Wind direction: circular average of dominant directions
            $dirs = $monthData->pluck('wind_dominant_direction')->filter()->values();
            if ($dirs->isNotEmpty()) {
                $sinSum = $dirs->sum(fn ($d) => sin(deg2rad($d)));
                $cosSum = $dirs->sum(fn ($d) => cos(deg2rad($d)));
                $avgDir = fmod(rad2deg(atan2($sinSum, $cosSum)) + 360, 360);
                $chartSeries['wind_dir'][] = round($avgDir, 0);
            } else {
                $chartSeries['wind_dir'][] = null;
            }

            // Dew point: not on DailySummary, leave null for year view
            $chartSeries['dew_point_avg'][] = null;

            $monthlySummaries[$m] = [
                'temp_high' => $monthData->max('temp_high'),
                'temp_low' => $monthData->min('temp_low'),
                'temp_avg' => $monthData->avg('temp_avg') !== null ? round($monthData->avg('temp_avg'), 1) : null,
                'rain_total' => round($monthData->sum('rain_total'), 1),
                'wind_max' => $monthData->max('wind_max'),
                'days_with_rain' => $monthData->where('rain_total', '>', 0)->count(),
                'days_count' => $monthData->count(),
            ];
        }

        $yearlyStats = [
            'temp_high' => $summaries->max('temp_high'),
            'temp_low' => $summaries->min('temp_low'),
            'temp_avg' => $summaries->avg('temp_avg') !== null ? round($summaries->avg('temp_avg'), 1) : null,
            'rain_total' => round($summaries->sum('rain_total'), 1),
            'wind_max' => $summaries->max('wind_max'),
            'days_with_rain' => $summaries->where('rain_total', '>', 0)->count(),
        ];

        $historyChart = [
            'days' => $chartMonths,
            'dates' => $chartMonthLabels,
            'series' => $chartSeries,
        ];

        $allChartKeys = ['temperature', 'wind', 'solar_uv', 'precipitation', 'humidity', 'soil', 'leaf_wetness', 'air_quality', 'co2', 'lightning', 'water_temp', 'extra_sensors'];
        $chartSettings = Setting::getValue('charts.day_visible', $allChartKeys);
        if (!is_array($chartSettings)) {
            $chartSettings = $allChartKeys;
        }

        return view('weather.history-year', compact(
            'year',
            'availableYears',
            'yearlyStats',
            'historyChart',
            'monthlySummaries',
            'chartSettings'
        ));
    }

    /**
     * Return paged readings for a given day (used to progressively render the large table).
     */
    public function dayReadings(Request $request, string $date): JsonResponse
    {
        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            abort(404);
        }
        $dateString = $dateObj->format('Y-m-d');

        $perPage = (int) $request->query('per_page', 60);
        $perPage = max(60, min(2000, $perPage));

        $paginator = WeatherReading::whereDate('recorded_at', $dateString)
            ->orderBy('recorded_at')
            ->select([
                'recorded_at',
                'temperature',
                'feels_like',
                'dew_point',
                'humidity',
                'wind_speed',
                'wind_gust',
                'wind_direction',
                'wind_speed_avg_10m',
                'wind_direction_avg_10m',
                'pressure_abs',
                'pressure_rel',
                'rain_rate',
                'rain_hourly',
                'rain_daily',
                'uv_index',
                'solar_radiation',
                'lux',
            ])
            ->paginate($perPage);

        $data = $paginator->getCollection()->map(static function (WeatherReading $reading): array {
            return [
                'time' => $reading->recorded_at?->format('H:i'),
                'temperature' => $reading->temperature,
                'feels_like' => $reading->feels_like,
                'dew_point' => $reading->dew_point,
                'humidity' => $reading->humidity,
                'wind_speed' => $reading->wind_speed,
                'wind_gust' => $reading->wind_gust,
                'wind_direction' => $reading->wind_direction,
                'wind_speed_avg_10m' => $reading->wind_speed_avg_10m,
                'wind_direction_avg_10m' => $reading->wind_direction_avg_10m,
                'pressure_abs' => $reading->pressure_abs,
                'pressure_rel' => $reading->pressure_rel,
                'rain_rate' => $reading->rain_rate,
                'rain_hourly' => $reading->rain_hourly,
                'rain_daily' => $reading->rain_daily,
                'uv_index' => $reading->uv_index,
                'solar_radiation' => $reading->solar_radiation,
                'lux' => $reading->lux,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'next_page_url' => $paginator->nextPageUrl(),
        ]);
    }

    /**
     * Build hourly soil sensor aggregation.
     */
    private function buildSoilSelect($readingsQuery, string $hourExpr): \Illuminate\Support\Collection
    {
        $fields = ["{$hourExpr} as hour"];
        for ($i = 1; $i <= 8; $i++) {
            $fields[] = "AVG(soil_moisture_{$i}) as soil_moisture_{$i}";
            $fields[] = "AVG(soil_temp_{$i}) as soil_temp_{$i}";
        }

        return (clone $readingsQuery)
            ->selectRaw(implode(', ', $fields))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');
    }

    /**
     * Build hourly multi-sensor aggregation (e.g. leaf_wetness_1..8).
     */
    private function buildMultiSensorSelect($readingsQuery, string $hourExpr, string $prefix, int $count, string $agg): \Illuminate\Support\Collection
    {
        $fields = ["{$hourExpr} as hour"];
        for ($i = 1; $i <= $count; $i++) {
            $fields[] = "{$agg}({$prefix}_{$i}) as {$prefix}_{$i}";
        }

        return (clone $readingsQuery)
            ->selectRaw(implode(', ', $fields))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');
    }

    /**
     * Build hourly air quality aggregation.
     */
    private function buildAirQualitySelect($readingsQuery, string $hourExpr): \Illuminate\Support\Collection
    {
        $fields = ["{$hourExpr} as hour"];
        for ($i = 1; $i <= 4; $i++) {
            $fields[] = "AVG(pm25_ch{$i}) as pm25_ch{$i}";
        }
        $fields[] = 'AVG(pm10) as pm10_avg';

        return (clone $readingsQuery)
            ->selectRaw(implode(', ', $fields))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');
    }

    /**
     * Build hourly aggregation with arbitrary select expressions.
     */
    private function buildSimpleSelect($readingsQuery, string $hourExpr, array $expressions): \Illuminate\Support\Collection
    {
        $fields = ["{$hourExpr} as hour", ...array_values($expressions)];

        return (clone $readingsQuery)
            ->selectRaw(implode(', ', $fields))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');
    }

    /**
     * Build hourly extra temp/humidity sensor aggregation.
     */
    private function buildExtraSensorsSelect($readingsQuery, string $hourExpr): \Illuminate\Support\Collection
    {
        $fields = ["{$hourExpr} as hour"];
        for ($i = 1; $i <= 8; $i++) {
            $fields[] = "AVG(temp_{$i}) as temp_{$i}";
            $fields[] = "AVG(humidity_{$i}) as humidity_{$i}";
        }

        return (clone $readingsQuery)
            ->selectRaw(implode(', ', $fields))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');
    }

    /**
     * Fill 24-hour array from hourly sensor query results.
     */
    private function fillHourlySensorData(\Illuminate\Support\Collection $hourlyRows, string $sensor): array
    {
        $data = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourStr = str_pad($hour, 2, '0', STR_PAD_LEFT);
            $row = $hourlyRows->get($hourStr);

            if (!$row) {
                $data[$hour] = null;
                continue;
            }

            $hourData = [];
            $rowArr = $row->getAttributes();
            foreach ($rowArr as $key => $value) {
                if ($key === 'hour') continue;
                $hourData[$key] = $value !== null ? round((float) $value, 1) : null;
            }
            // Only include if at least one value is non-null
            $hasAny = collect($hourData)->contains(fn ($v) => $v !== null);
            $data[$hour] = $hasAny ? $hourData : null;
        }

        return $data;
    }
}
