<?php

namespace App\Services\Forecast;

use App\Contracts\Forecast\ForecastServiceInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AemetService implements ForecastServiceInterface
{
    private string $apiKey;
    private string $municipio;
    private string $baseUrl = 'https://opendata.aemet.es/opendata/api/';

    public function __construct()
    {
        $this->apiKey = Setting::getValue('aemet.api_key', '');
        $this->municipio = Setting::getValue('aemet.municipio', '');
    }

    public function fetchForecast(): ?array
    {
        if (empty($this->apiKey) || empty($this->municipio)) {
            Log::error('AEMET API key or municipio not configured');
            return null;
        }

        $cacheKey = "aemet_forecast_{$this->municipio}";
        
        return Cache::remember($cacheKey, 1800, function () {
            try {
                // Fetch daily forecast
                $dailyData = $this->fetchAemetEndpoint("prediccion/especifica/municipio/diaria/{$this->municipio}");
                // Fetch hourly forecast
                $hourlyData = $this->fetchAemetEndpoint("prediccion/especifica/municipio/horaria/{$this->municipio}");

                if ($dailyData && $hourlyData) {
                    $raw = [
                        'daily' => $dailyData,
                        'hourly' => $hourlyData,
                        'updated_at' => now()->toIso8601String(),
                    ];
                    $raw['forecast'] = $this->parseHourlyEntries($hourlyData);
                    return $raw;
                }
            } catch (\Exception $e) {
                Log::error('AEMET API exception', ['error' => $e->getMessage()]);
            }

            return null;
        });
    }

    private function fetchAemetEndpoint(string $endpoint): ?array
    {
        $response = Http::withHeaders([
            'api_key' => $this->apiKey,
        ])->get($this->baseUrl . $endpoint);

        if ($response->successful()) {
            $data = $response->json();
            
            if (isset($data['datos']) && isset($data['estado']) && $data['estado'] == 200) {
                // AEMET returns a URL where the actual JSON data is stored
                $dataResponse = Http::get($data['datos']);
                if ($dataResponse->successful()) {
                    return $dataResponse->json();
                }
            }
        }

        Log::error('AEMET API request failed or invalid response', [
            'status' => $response->status(),
            'body' => $response->body(),
            'endpoint' => $endpoint
        ]);

        return null;
    }

    public function getHourlyForecast(int $hours = 48): array
    {
        $data = $this->fetchForecast();
        if (!$data) {
            return [];
        }

        if (isset($data['forecast']) && is_array($data['forecast'])) {
            return array_slice($data['forecast'], 0, $hours);
        }

        return $this->parseHourlyEntries($data['hourly'] ?? [], $hours);
    }

    private function parseHourlyEntries(array $hourlyRawData, int $hours = 48): array
    {
        $forecast = [];
        $municipioData = $hourlyRawData[0] ?? [];
        $dias = $municipioData['prediccion']['dia'] ?? [];

        foreach ($dias as $dia) {
            $dateStr = substr($dia['fecha'], 0, 10);
            
            // AEMET hourly arrays
            $temps = $this->parseHourlyArray($dia['temperatura'] ?? []);
            $winds = $this->parseHourlyArray($dia['vientoAndRachaMax'] ?? []);
            $precip = $this->parseHourlyArray($dia['precipitacion'] ?? []);
            $sky = $this->parseHourlyArray($dia['estadoCielo'] ?? []);

            for ($h = 0; $h < 24; $h++) {
                $hourKey = str_pad($h, 2, '0', STR_PAD_LEFT);
                
                // If we don't have temperature for this hour, skip it
                if (!isset($temps[$hourKey])) {
                    continue;
                }

                // Wind speed is given in km/h by AEMET, we store as km/h
                $windSpeedKmh = null;
                $windDir = null;
                if (isset($winds[$hourKey]) && is_array($winds[$hourKey])) {
                    $windSpeedKmh = isset($winds[$hourKey][0]['velocidad']) ? (float)$winds[$hourKey][0]['velocidad'] : null;
                    $windDirString = $winds[$hourKey][0]['direccion'] ?? '';
                    $windDir = $this->directionStringToDegrees($windDirString);
                }

                $time = $dateStr . 'T' . $hourKey . ':00:00Z';
                
                // Only add future/current hours (roughly)
                if (strtotime($time) < time() - 3600) {
                    continue;
                }

                $forecast[] = [
                    'time' => $time,
                    'temperature' => (float)$temps[$hourKey],
                    'humidity' => null, 
                    'pressure' => null,
                    'wind_speed' => $windSpeedKmh,
                    'wind_direction' => $windDir,
                    'cloud_cover' => null,
                    'symbol' => $this->mapIconToSymbol($sky[$hourKey]['value'] ?? '', $sky[$hourKey]['descripcion'] ?? ''),
                    'precipitation_1h' => isset($precip[$hourKey]) ? (float)$precip[$hourKey] : 0,
                    'precipitation_6h' => null,
                ];

                if (count($forecast) >= $hours) {
                    break 2;
                }
            }
        }

        return $forecast;
    }

    public function getDailyForecast(int $days = 7): array
    {
        $data = $this->fetchForecast();
        if (!$data || empty($data['daily'])) {
            return [];
        }

        $forecast = [];
        $municipioData = $data['daily'][0] ?? [];
        $dias = $municipioData['prediccion']['dia'] ?? [];

        foreach ($dias as $index => $dia) {
            $date = substr($dia['fecha'], 0, 10);
            
            $tempMax = $dia['temperatura']['maxima'] ?? null;
            $tempLow = $dia['temperatura']['minima'] ?? null;
            
            // Get dominant symbol for the day
            $estadosCielo = $dia['estadoCielo'] ?? [];
            $dominantEstado = $estadosCielo[0] ?? []; 
            $symbol = $this->mapIconToSymbol($dominantEstado['value'] ?? '', $dominantEstado['descripcion'] ?? '');

            // Average wind speed for the day
            $windSpeedKmh = null;
            $windDir = null;
            $vientos = $dia['viento'] ?? [];
            if (!empty($vientos)) {
                $firstWind = $vientos[0];
                if (isset($firstWind['velocidad'])) {
                    $windSpeedKmh = (float)$firstWind['velocidad'];
                    $windDir = $this->directionStringToDegrees($firstWind['direccion'] ?? '');
                }
            }
            
            $precipAmount = 0; 

            $forecast[] = [
                'date' => $date,
                'temp_high' => $tempMax,
                'temp_low' => $tempLow,
                'symbol' => $symbol,
                'precipitation' => $precipAmount,
                'wind_speed' => $windSpeedKmh,
                'wind_direction' => $windDir,
            ];

            if (count($forecast) >= $days) {
                break;
            }
        }

        return $forecast;
    }

    private function parseHourlyArray(array $aemetArray): array
    {
        $result = [];
        foreach ($aemetArray as $item) {
            if (isset($item['periodo'])) {
                $result[$item['periodo']] = $item['value'] ?? $item;
            }
        }
        return $result;
    }

    private function mapIconToSymbol(string $aemetCode, string $description = ''): string
    {
        $code = str_replace('n', '', $aemetCode);
        $isNight = strpos($aemetCode, 'n') !== false;

        $map = [
            '11' => 'clearsky',
            '11n' => 'clearsky_night',
            '12' => 'fair',
            '12n' => 'fair_night',
            '13' => 'partlycloudy',
            '13n' => 'partlycloudy_night',
            '14' => 'cloudy',
            '15' => 'cloudy',
            '16' => 'cloudy',
            '17' => 'cloudy',
            '43' => 'rain',
            '44' => 'rain',
            '45' => 'heavyrain',
            '46' => 'heavyrain',
            '51' => 'rainandthunder',
            '52' => 'rainandthunder',
            '53' => 'rainandthunder',
            '54' => 'rainandthunder',
            '61' => 'rainandthunder',
            '62' => 'rainandthunder',
            '63' => 'rainandthunder',
            '64' => 'rainandthunder',
            '71' => 'snow',
            '72' => 'snow',
            '73' => 'heavysnow',
            '74' => 'heavysnow',
            '81' => 'fog',
            '82' => 'fog',
        ];

        if (isset($map[$aemetCode])) {
            return $map[$aemetCode];
        }

        if (isset($map[$code])) {
            $mapped = $map[$code];
            if ($isNight && in_array($mapped, ['clearsky', 'fair', 'partlycloudy'])) {
                return $mapped . '_night';
            } elseif (!$isNight && in_array($mapped, ['clearsky', 'fair', 'partlycloudy'])) {
                return $mapped . '_day';
            }
            return $mapped;
        }

        $desc = strtolower($description);
        if (strpos($desc, 'tormenta') !== false) return 'rainandthunder';
        if (strpos($desc, 'nieve') !== false) return 'snow';
        if (strpos($desc, 'lluvia') !== false) return 'rain';
        if (strpos($desc, 'nuboso') !== false || strpos($desc, 'cubierto') !== false) return 'cloudy';

        return $isNight ? 'partlycloudy_night' : 'partlycloudy_day';
    }

    private function directionStringToDegrees(string $dir): ?int
    {
        $map = [
            'N' => 0, 'NNE' => 22, 'NE' => 45, 'ENE' => 67,
            'E' => 90, 'ESE' => 112, 'SE' => 135, 'SSE' => 157,
            'S' => 180, 'SSW' => 202, 'SW' => 225, 'WSW' => 247,
            'W' => 270, 'WNW' => 292, 'NW' => 315, 'NNW' => 337,
            'C' => null,
        ];

        return $map[strtoupper($dir)] ?? null;
    }
}
