<?php

namespace App\Services\Forecast;

use App\Contracts\Forecast\ForecastServiceInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class WxsimService implements ForecastServiceInterface
{
    private string $filePath;

    public function __construct()
    {
        // WXSIM typically outputs to a plaintext.txt file
        // Check if path is configured, otherwise use default
        $this->filePath = Setting::getValue('wxsim.file_path', storage_path('app/wxsim/plaintext.txt'));
    }

    /**
     * Fetch weather forecast from WXSIM plaintext file
     */
    public function fetchForecast(): ?array
    {
        $cacheKey = "wxsim_forecast_" . md5($this->filePath);
        
        return Cache::remember($cacheKey, 1800, function () {
            if (!File::exists($this->filePath)) {
                Log::warning('WXSIM forecast file not found', ['path' => $this->filePath]);
                return null;
            }

            try {
                $content = File::get($this->filePath);
                return $this->parseForecast($content);
            } catch (\Exception $e) {
                Log::error('WXSIM forecast parse error', [
                    'path' => $this->filePath,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
     * Parse WXSIM plaintext forecast file
     */
    private function parseForecast(string $content): array
    {
        $forecast = [];
        $lines = explode("\n", $content);
        
        // WXSIM format: Each period is separated by blank lines
        // Format: Period name, description, temperature, wind, precipitation, etc.
        $currentPeriod = null;
        $periodData = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                if ($currentPeriod && !empty($periodData)) {
                    $forecast[] = $this->parsePeriod($currentPeriod, $periodData);
                    $periodData = [];
                }
                continue;
            }

            // Check if this is a period header (e.g., "Tonight:", "Tomorrow:", "Monday:")
            if (preg_match('/^(Tonight|Today|Tomorrow|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)[:]/i', $line, $matches)) {
                if ($currentPeriod && !empty($periodData)) {
                    $forecast[] = $this->parsePeriod($currentPeriod, $periodData);
                }
                $currentPeriod = $matches[1];
                $periodData = [$line];
            } else {
                $periodData[] = $line;
            }
        }

        // Process last period
        if ($currentPeriod && !empty($periodData)) {
            $forecast[] = $this->parsePeriod($currentPeriod, $periodData);
        }

        return [
            'updated_at' => File::lastModified($this->filePath) ? date('c', File::lastModified($this->filePath)) : now()->toIso8601String(),
            'forecast' => $forecast,
        ];
    }

    /**
     * Parse a single WXSIM period
     */
    private function parsePeriod(string $period, array $lines): array
    {
        $text = implode(' ', $lines);
        
        // Extract temperature (high/low)
        $tempHigh = null;
        $tempLow = null;
        if (preg_match('/high\s+([-+]?\d+)/i', $text, $matches)) {
            $tempHigh = (float)$matches[1];
        }
        if (preg_match('/low\s+([-+]?\d+)/i', $text, $matches)) {
            $tempLow = (float)$matches[1];
        }
        $temperature = $tempHigh ?? $tempLow ?? null;

        // Extract wind speed and direction
        $windSpeed = null;
        $windDirection = null;
        
        // Pattern: "wind [direction] [speed] [units]" or "winds [speed] [units] from [direction]"
        if (preg_match('/wind[s]?\s+(?:from\s+)?([NSEW]{1,3}|north|south|east|west|northeast|northwest|southeast|southwest)[\s,]+(\d+)\s*(mph|kph|km\/h|m\/s|kts)/i', $text, $matches)) {
            $windDirection = $this->cardinalToDegrees($matches[1]);
            $windSpeed = (float)$matches[2];
            $unit = strtolower($matches[3]);
            
            // Convert to km/h
            if ($unit === 'mph') {
                $windSpeed = $windSpeed * 1.60934;
            } elseif ($unit === 'm/s' || $unit === 'mps') {
                $windSpeed = $windSpeed * 3.6;
            } elseif ($unit === 'kts' || $unit === 'knots') {
                $windSpeed = $windSpeed * 1.852;
            }
        }

        // Extract precipitation
        $precipitation = 0;
        if (preg_match('/(\d+(?:\.\d+)?)\s*(mm|in|inches?)\s+(?:of\s+)?(?:rain|precipitation)/i', $text, $matches)) {
            $precip = (float)$matches[1];
            $unit = strtolower($matches[2]);
            if ($unit === 'in' || $unit === 'inch' || $unit === 'inches') {
                $precipitation = $precip * 25.4; // Convert inches to mm
            } else {
                $precipitation = $precip;
            }
        }

        // Determine symbol from text
        $symbol = $this->textToSymbol($text);

        // Estimate time for this period
        $time = $this->periodToTime($period);

        return [
            'time' => $time,
            'temperature' => $temperature,
            'humidity' => null,
            'pressure' => null,
            'wind_speed' => $windSpeed,
            'wind_direction' => $windDirection,
            'cloud_cover' => null,
            'symbol' => $symbol,
            'precipitation_1h' => $precipitation / 12, // Estimate hourly from period total
            'precipitation_6h' => $precipitation / 2,  // Estimate 6h from period total
        ];
    }

    /**
     * Convert period name to ISO time string
     */
    private function periodToTime(string $period): string
    {
        $now = now();
        $period = strtolower($period);
        
        if ($period === 'tonight') {
            return $now->setTime(20, 0)->toIso8601String();
        } elseif ($period === 'today') {
            return $now->setTime(12, 0)->toIso8601String();
        } elseif ($period === 'tomorrow') {
            return $now->addDay()->setTime(12, 0)->toIso8601String();
        } else {
            // Day of week - find next occurrence
            $dayMap = [
                'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0,
            ];
            $targetDay = $dayMap[$period] ?? null;
            if ($targetDay !== null) {
                $daysUntil = ($targetDay - $now->dayOfWeek + 7) % 7;
                if ($daysUntil === 0) {
                    $daysUntil = 7; // Next week
                }
                return $now->addDays($daysUntil)->setTime(12, 0)->toIso8601String();
            }
        }
        
        return $now->toIso8601String();
    }

    /**
     * Convert cardinal direction to degrees
     */
    private function cardinalToDegrees(string $cardinal): ?int
    {
        $cardinal = strtolower(trim($cardinal));
        
        $directions = [
            'n' => 0, 'north' => 0,
            'nne' => 22, 'north-northeast' => 22,
            'ne' => 45, 'northeast' => 45,
            'ene' => 67, 'east-northeast' => 67,
            'e' => 90, 'east' => 90,
            'ese' => 112, 'east-southeast' => 112,
            'se' => 135, 'southeast' => 135,
            'sse' => 157, 'south-southeast' => 157,
            's' => 180, 'south' => 180,
            'ssw' => 202, 'south-southwest' => 202,
            'sw' => 225, 'southwest' => 225,
            'wsw' => 247, 'west-southwest' => 247,
            'w' => 270, 'west' => 270,
            'wnw' => 292, 'west-northwest' => 292,
            'nw' => 315, 'northwest' => 315,
            'nnw' => 337, 'north-northwest' => 337,
        ];

        return $directions[$cardinal] ?? null;
    }

    /**
     * Convert text description to symbol code
     */
    private function textToSymbol(string $text): string
    {
        $text = strtolower($text);
        
        if (preg_match('/clear|sunny/i', $text)) {
            return 'clearsky_day';
        } elseif (preg_match('/partly\s+cloudy|partly\s+sunny/i', $text)) {
            return 'partlycloudy_day';
        } elseif (preg_match('/cloudy|overcast/i', $text)) {
            return 'cloudy';
        } elseif (preg_match('/rain|shower/i', $text)) {
            if (preg_match('/heavy|thunder/i', $text)) {
                return 'heavyrainandthunder';
            } elseif (preg_match('/light/i', $text)) {
                return 'lightrain';
            }
            return 'rain';
        } elseif (preg_match('/snow/i', $text)) {
            if (preg_match('/heavy/i', $text)) {
                return 'heavysnow';
            }
            return 'snow';
        } elseif (preg_match('/sleet|freezing\s+rain/i', $text)) {
            return 'sleet';
        } elseif (preg_match('/fog|mist|haze/i', $text)) {
            return 'fog';
        } elseif (preg_match('/thunder/i', $text)) {
            return 'heavyrainandthunder';
        }
        
        return 'partlycloudy_day';
    }

    /**
     * Get hourly forecast for specified number of hours
     */
    public function getHourlyForecast(int $hours = 48): array
    {
        $data = $this->fetchForecast();
        if (!$data) {
            return [];
        }

        // WXSIM provides period-based forecasts, not hourly
        // We'll return what we have, but it won't be true hourly data
        return array_slice($data['forecast'], 0, min($hours, count($data['forecast'])));
    }

    /**
     * Get daily forecast summary
     */
    public function getDailyForecast(int $days = 7): array
    {
        $data = $this->fetchForecast();
        if (!$data) {
            return [];
        }

        $forecast = $data['forecast'] ?? [];
        $daily = [];

        foreach ($forecast as $entry) {
            $date = substr($entry['time'], 0, 10);
            
            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'date' => $date,
                    'temp_high' => $entry['temperature'],
                    'temp_low' => $entry['temperature'],
                    'temps' => [],
                    'symbols' => [],
                    'precipitation' => 0,
                    'wind_speeds' => [],
                    'wind_directions' => [],
                ];
            }

            if ($entry['temperature'] !== null) {
                $daily[$date]['temps'][] = $entry['temperature'];
                if ($entry['temperature'] > $daily[$date]['temp_high']) {
                    $daily[$date]['temp_high'] = $entry['temperature'];
                }
                if ($entry['temperature'] < $daily[$date]['temp_low']) {
                    $daily[$date]['temp_low'] = $entry['temperature'];
                }
            }
            
            if ($entry['symbol']) {
                $daily[$date]['symbols'][] = $entry['symbol'];
            }
            
            $daily[$date]['precipitation'] += ($entry['precipitation_1h'] ?? 0) * 12; // Convert hourly estimate back to period
            
            // Collect wind data
            if (isset($entry['wind_speed']) && $entry['wind_speed'] !== null) {
                $daily[$date]['wind_speeds'][] = $entry['wind_speed'];
            }
            if (isset($entry['wind_direction']) && $entry['wind_direction'] !== null) {
                $daily[$date]['wind_directions'][] = $entry['wind_direction'];
            }
        }

        // Calculate dominant symbol and average wind for each day
        foreach ($daily as &$day) {
            $day['temp_avg'] = count($day['temps']) > 0 ? array_sum($day['temps']) / count($day['temps']) : null;
            $day['symbol'] = $this->getDominantSymbol($day['symbols']);
            
            // Calculate average wind speed
            $day['wind_speed'] = !empty($day['wind_speeds']) 
                ? array_sum($day['wind_speeds']) / count($day['wind_speeds']) 
                : null;
            
            // Calculate average wind direction (circular mean)
            $day['wind_direction'] = null;
            if (!empty($day['wind_directions'])) {
                $sinSum = 0;
                $cosSum = 0;
                foreach ($day['wind_directions'] as $dir) {
                    $rad = deg2rad($dir);
                    $sinSum += sin($rad);
                    $cosSum += cos($rad);
                }
                $avgRad = atan2($sinSum / count($day['wind_directions']), $cosSum / count($day['wind_directions']));
                $day['wind_direction'] = round(rad2deg($avgRad));
                // Normalize to 0-360
                if ($day['wind_direction'] < 0) {
                    $day['wind_direction'] += 360;
                }
            }
            
            unset($day['temps'], $day['symbols'], $day['wind_speeds'], $day['wind_directions']);
        }

        return array_slice(array_values($daily), 0, $days);
    }

    /**
     * Get most common weather symbol for a day
     */
    private function getDominantSymbol(array $symbols): ?string
    {
        if (empty($symbols)) {
            return null;
        }

        $counts = array_count_values($symbols);
        arsort($counts);
        return array_key_first($counts);
    }
}
