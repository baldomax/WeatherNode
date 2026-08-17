<?php

namespace App\Services\Forecast;

use App\Contracts\Forecast\ForecastServiceInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AemetService implements ForecastServiceInterface
{
    /** Seconds to wait before retrying after a failed fetch, so a slow or down AEMET is not re-hit per request. */
    private const FAILURE_COOLDOWN = 300;

    private const REQUEST_TIMEOUT = 15;

    private string $apiKey;
    private string $municipio;
    private string $baseUrl = 'https://opendata.aemet.es/opendata/api/';

    public function __construct()
    {
        // getValue() returns null for an encrypted setting holding an empty
        // value, which is what the seeder writes, so coalesce before assigning.
        $this->apiKey = trim((string) (Setting::getValue('aemet.api_key', '') ?? ''));
        $this->municipio = trim((string) (Setting::getValue('aemet.municipio', '') ?? ''));
    }

    public function fetchForecast(): ?array
    {
        if ($this->apiKey === '' || $this->municipio === '') {
            Log::warning('AEMET API key or municipio not configured');
            return null;
        }

        // The municipio is interpolated into the request path, so it has to be
        // the 5 digit INE code the setting asks for and nothing else.
        if (!preg_match('/^\d{5}$/', $this->municipio)) {
            Log::error('AEMET municipio must be a 5 digit INE code', ['municipio' => $this->municipio]);
            return null;
        }

        $cacheKey = "aemet_forecast_{$this->municipio}";
        $cooldownKey = $cacheKey . '_cooldown';

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        // Cache::remember() treats a cached null as a miss, so caching the
        // failure there would re-run this on every call.
        if (Cache::has($cooldownKey)) {
            return null;
        }

        try {
            $dailyData = $this->fetchAemetEndpoint("prediccion/especifica/municipio/diaria/{$this->municipio}");
            $hourlyData = $this->fetchAemetEndpoint("prediccion/especifica/municipio/horaria/{$this->municipio}");

            if ($dailyData && $hourlyData) {
                $raw = [
                    'daily' => $dailyData,
                    'hourly' => $hourlyData,
                    'updated_at' => now()->toIso8601String(),
                ];
                $raw['forecast'] = $this->parseHourlyEntries($hourlyData);

                Cache::put($cacheKey, $raw, 1800);

                return $raw;
            }
        } catch (\Exception $e) {
            Log::error('AEMET API exception', ['error' => $e->getMessage()]);
        }

        Cache::put($cooldownKey, true, self::FAILURE_COOLDOWN);

        return null;
    }

    private function fetchAemetEndpoint(string $endpoint): ?array
    {
        $response = Http::timeout(self::REQUEST_TIMEOUT)
            ->withHeaders(['api_key' => $this->apiKey])
            ->get($this->baseUrl . $endpoint);

        if (!$response->successful()) {
            Log::error('AEMET API request failed', [
                'step' => 'index',
                'status' => $response->status(),
                'endpoint' => $endpoint,
            ]);

            return null;
        }

        $data = $response->json();

        if (!isset($data['datos']) || (int) ($data['estado'] ?? 0) !== 200) {
            Log::error('AEMET API returned no data URL', [
                'step' => 'index',
                'estado' => $data['estado'] ?? null,
                'endpoint' => $endpoint,
            ]);

            return null;
        }

        // AEMET answers with a URL where the actual JSON is published.
        $dataResponse = Http::timeout(self::REQUEST_TIMEOUT)->get($data['datos']);

        if (!$dataResponse->successful()) {
            Log::error('AEMET data URL request failed', [
                'step' => 'datos',
                'status' => $dataResponse->status(),
                'endpoint' => $endpoint,
            ]);

            return null;
        }

        return $dataResponse->json();
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
            $dateStr = substr((string) ($dia['fecha'] ?? ''), 0, 10);
            if ($dateStr === '') {
                continue;
            }

            // AEMET hourly arrays
            $temps = $this->parseHourlyArray($dia['temperatura'] ?? []);
            $winds = $this->parseHourlyArray($dia['vientoAndRachaMax'] ?? []);
            $precip = $this->parseHourlyArray($dia['precipitacion'] ?? []);
            // Keep the whole entry: the symbol needs both value and descripcion,
            // which parseHourlyArray() flattens away.
            $sky = $this->parseHourlyItems($dia['estadoCielo'] ?? []);

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
                    // The entry is either the wind record itself or a list of
                    // them, and velocidad/direccion are single element arrays.
                    $wind = $winds[$hourKey];
                    if (!array_key_exists('velocidad', $wind) && isset($wind[0]) && is_array($wind[0])) {
                        $wind = $wind[0];
                    }

                    $speed = $this->firstScalar($wind['velocidad'] ?? null);
                    $windSpeedKmh = $speed === null ? null : (float) $speed;
                    $windDir = $this->directionStringToDegrees((string) ($this->firstScalar($wind['direccion'] ?? null) ?? ''));
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

    /** Like parseHourlyArray(), but keeps the whole entry rather than its value. */
    private function parseHourlyItems(array $aemetArray): array
    {
        $result = [];
        foreach ($aemetArray as $item) {
            if (is_array($item) && isset($item['periodo'])) {
                $result[$item['periodo']] = $item;
            }
        }

        return $result;
    }

    /** AEMET wraps some scalars in a single element array; take either shape. */
    private function firstScalar(mixed $value): string|int|float|null
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_scalar($value) ? $value : null;
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
