<?php

namespace App\Console\Commands;

use App\Models\DailySummary;
use App\Models\WeatherReading;
use App\Services\Weather\SunshineHoursCalculator;
use Illuminate\Console\Command;

class GenerateDailySummary extends Command
{
    protected $signature = 'weather:summarize {date? : Date to summarize (Y-m-d format)}';
    protected $description = 'Generate daily summary from weather readings';

    public function handle(SunshineHoursCalculator $sunshineHours): int
    {
        $date = $this->argument('date') ?? now()->subDay()->toDateString();

        $this->info("Generating summary for {$date}...");

        $readings = WeatherReading::whereDate('recorded_at', $date)->get();

        if ($readings->isEmpty()) {
            $this->warn("No readings found for {$date}");
            return Command::SUCCESS;
        }

        $windDirections = $readings
            ->pluck('wind_direction')
            ->filter(static fn ($direction) => $direction !== null);
        $windDominantDirection = null;
        if ($windDirections->isNotEmpty()) {
            $sinSum = 0.0;
            $cosSum = 0.0;
            foreach ($windDirections as $direction) {
                $radians = deg2rad((float) $direction);
                $sinSum += sin($radians);
                $cosSum += cos($radians);
            }
            if (abs($sinSum) > 0.000001 || abs($cosSum) > 0.000001) {
                $degrees = rad2deg(atan2($sinSum, $cosSum));
                if ($degrees < 0) {
                    $degrees += 360;
                }
                $windDominantDirection = ((int) round($degrees)) % 360;
            }
        }

        $humidityMax = $readings->max('humidity');
        $humidityMin = $readings->min('humidity');
        $humidityAvg = $readings->avg('humidity');

        $data = [
            'temp_high'              => $readings->max('temperature'),
            'temp_high_time'         => $readings->where('temperature', $readings->max('temperature'))->first()->recorded_at->format('H:i:s'),
            'temp_low'               => $readings->min('temperature'),
            'temp_low_time'          => $readings->where('temperature', $readings->min('temperature'))->first()->recorded_at->format('H:i:s'),
            'temp_avg'               => round($readings->avg('temperature'), 1),
            'humidity_high'          => $humidityMax !== null ? (int) $humidityMax : null,
            'humidity_low'           => $humidityMin !== null ? (int) $humidityMin : null,
            'humidity_avg'           => $humidityAvg !== null ? (int) round($humidityAvg) : null,
            'pressure_high'          => $readings->max('pressure_rel'),
            'pressure_low'           => $readings->min('pressure_rel'),
            'pressure_avg'           => round($readings->avg('pressure_rel'), 1),
            'wind_max'               => $readings->max('wind_gust'),
            'wind_max_time'          => $readings->where('wind_gust', $readings->max('wind_gust'))->first()->recorded_at->format('H:i:s'),
            'wind_avg'               => round($readings->avg('wind_speed'), 1),
            'wind_dominant_direction' => $windDominantDirection,
            'rain_total'             => $readings->max('rain_daily'),
            'rain_rate_max'          => $readings->max('rain_rate'),
            'uv_max'                 => $readings->max('uv_index'),
            'solar_max'              => $readings->max('solar_radiation'),
            'solar_hours'            => $sunshineHours->resolveFromReadings($readings),
        ];

        // Use whereDate for reliable lookup on both SQLite and MySQL
        $summary = DailySummary::whereDate('date', $date)->first();
        if ($summary) {
            $summary->fill($data)->save();
        } else {
            $summary = DailySummary::create(array_merge(['date' => $date], $data));
        }

        $this->info("Summary generated: High {$summary->temp_high}°C, Low {$summary->temp_low}°C, Humidity low {$summary->humidity_low}%, Rain {$summary->rain_total}mm");

        return Command::SUCCESS;
    }
}
