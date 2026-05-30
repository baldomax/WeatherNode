<?php

namespace App\Console\Commands;

use App\Models\DailySummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ImportFromWeatherUnderground extends Command
{
    protected $signature = 'weather:import-wu 
                            {--api-key= : Weather Underground API key}
                            {--station= : Station ID (default: IUITGE8)}
                            {--year= : Year to import (default: 2025)}
                            {--start-date= : Start date (YYYY-MM-DD)}
                            {--end-date= : End date (YYYY-MM-DD)}
                            {--skip-existing : Skip days that already have data}';

    protected $description = 'Import historical weather data from Weather Underground API';

    protected int $imported = 0;
    protected int $skipped = 0;
    protected int $errors = 0;

    public function handle(): int
    {
        $apiKey = $this->option('api-key');
        $stationId = $this->option('station') ?? 'IUITGE8';
        
        if (!$apiKey) {
            $this->error('API key is required. Use --api-key=YOUR_KEY');
            return 1;
        }

        // Determine date range
        $year = $this->option('year') ?? 2025;
        
        if ($this->option('start-date')) {
            $startDate = Carbon::parse($this->option('start-date'));
        } else {
            $startDate = Carbon::createFromDate($year, 1, 1);
        }
        
        if ($this->option('end-date')) {
            $endDate = Carbon::parse($this->option('end-date'));
        } else {
            // End at Dec 31 of the year or today, whichever is earlier
            $endDate = Carbon::createFromDate($year, 12, 31);
            if ($endDate->isFuture()) {
                $endDate = Carbon::yesterday();
            }
        }

        $this->info("Importing data from Weather Underground");
        $this->info("Station: {$stationId}");
        $this->info("Date range: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        $this->newLine();

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $bar = $this->output->createProgressBar($totalDays);
        $bar->start();

        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            
            // Check if we should skip existing
            $existing = DailySummary::whereDate('date', $dateStr)->first();
            if ($this->option('skip-existing') && $existing && $existing->temp_high !== null) {
                $this->skipped++;
                $currentDate->addDay();
                $bar->advance();
                continue;
            }

            // Fetch from WU API
            $data = $this->fetchDayFromWU($stationId, $currentDate, $apiKey);
            
            if ($data) {
                $this->saveDailySummary($dateStr, $data);
                $this->imported++;
            } else {
                $this->errors++;
            }

            $currentDate->addDay();
            $bar->advance();
            
            // Rate limiting - WU allows 1500 calls/day, be conservative
            usleep(100000); // 100ms delay between requests
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Import complete!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Imported', $this->imported],
                ['Skipped (existing)', $this->skipped],
                ['Errors/No data', $this->errors],
            ]
        );

        return 0;
    }

    protected function fetchDayFromWU(string $stationId, Carbon $date, string $apiKey): ?array
    {
        $url = sprintf(
            'https://api.weather.com/v2/pws/history/daily?stationId=%s&format=json&units=m&date=%s&apiKey=%s',
            $stationId,
            $date->format('Ymd'),
            $apiKey
        );

        try {
            $response = Http::withOptions(['verify' => false])->get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['observations']) && count($data['observations']) > 0) {
                    return $data['observations'][0];
                }
            }
        } catch (\Exception $e) {
            $this->error("Error fetching {$date->format('Y-m-d')}: " . $e->getMessage());
        }

        return null;
    }

    protected function saveDailySummary(string $date, array $data): void
    {
        $metric = $data['metric'] ?? [];
        
        // Find existing or create new
        $summary = DailySummary::whereDate('date', $date)->first();
        
        if (!$summary) {
            $summary = new DailySummary(['date' => $date]);
        }
        
        $summary->fill([
            'temp_high' => $metric['tempHigh'] ?? null,
            'temp_low' => $metric['tempLow'] ?? null,
            'temp_avg' => $metric['tempAvg'] ?? null,
            'humidity_high' => $data['humidityHigh'] ?? null,
            'humidity_low' => $data['humidityLow'] ?? null,
            'humidity_avg' => $data['humidityAvg'] ?? null,
            'pressure_high' => $metric['pressureMax'] ?? null,
            'pressure_low' => $metric['pressureMin'] ?? null,
            'pressure_avg' => $metric['pressureAvg']
                ?? (isset($metric['pressureMax'], $metric['pressureMin'])
                    ? round((((float) $metric['pressureMax']) + ((float) $metric['pressureMin'])) / 2, 1)
                    : null),
            'wind_max' => $metric['windgustHigh'] ?? $metric['windspeedHigh'] ?? null,
            'wind_avg' => $metric['windspeedAvg'] ?? null,
            'wind_dominant_direction' => $data['winddirAvg'] ?? null,
            'rain_total' => $metric['precipTotal'] ?? 0,
            'rain_rate_max' => $metric['precipRate'] ?? null,
            'uv_max' => $data['uvHigh'] ?? null,
            'solar_max' => $data['solarRadiationHigh'] ?? null,
        ]);
        
        $summary->save();
    }
}
