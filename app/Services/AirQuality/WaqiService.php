<?php

namespace App\Services\AirQuality;

use App\Models\Setting;
use App\Support\CacheFreshness;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WaqiService
{
    private string $apiKey;
    private float $latitude;
    private float $longitude;
    private string $stationMode;
    private ?string $stationId;
    private string $indexType;
    private string $baseUrl = 'https://api.waqi.info/';

    /**
     * US EPA AQI breakpoints for converting AQI back to concentration
     * Format: [aqi_lo, aqi_hi, conc_lo, conc_hi] for each pollutant
     * Concentrations in µg/m³
     */
    private const EPA_AQI_BREAKPOINTS = [
        'pm25' => [
            [0, 50, 0.0, 12.0],
            [51, 100, 12.1, 35.4],
            [101, 150, 35.5, 55.4],
            [151, 200, 55.5, 150.4],
            [201, 300, 150.5, 250.4],
            [301, 500, 250.5, 500.4],
        ],
        'pm10' => [
            [0, 50, 0, 54],
            [51, 100, 55, 154],
            [101, 150, 155, 254],
            [151, 200, 255, 354],
            [201, 300, 355, 424],
            [301, 500, 425, 604],
        ],
        'o3' => [
            // 8-hour O3 in µg/m³ (converted from ppb: ppb * 1.96)
            [0, 50, 0, 106],
            [51, 100, 108, 137],
            [101, 150, 139, 167],
            [151, 200, 169, 206],
            [201, 300, 208, 392],
            [301, 500, 394, 785],
        ],
        'no2' => [
            // 1-hour NO2 in µg/m³ (converted from ppb: ppb * 1.88)
            [0, 50, 0, 100],
            [51, 100, 102, 188],
            [101, 150, 190, 677],
            [151, 200, 679, 1221],
            [201, 300, 1223, 2350],
            [301, 500, 2352, 3855],
        ],
        'so2' => [
            // 1-hour SO2 in µg/m³ (converted from ppb: ppb * 2.62)
            [0, 50, 0, 92],
            [51, 100, 94, 196],
            [101, 150, 199, 485],
            [151, 200, 487, 797],
            [201, 300, 799, 1583],
            [301, 500, 1586, 2632],
        ],
    ];

    /**
     * European EEA Index breakpoints (µg/m³)
     * 6 levels: Good (1), Fair (2), Moderate (3), Poor (4), Very Poor (5), Extremely Poor (6)
     */
    private const EEA_BREAKPOINTS = [
        'pm25' => [
            ['min' => 0, 'max' => 10, 'level' => 1],
            ['min' => 10.1, 'max' => 20, 'level' => 2],
            ['min' => 20.1, 'max' => 25, 'level' => 3],
            ['min' => 25.1, 'max' => 50, 'level' => 4],
            ['min' => 50.1, 'max' => 75, 'level' => 5],
            ['min' => 75.1, 'max' => 9999, 'level' => 6],
        ],
        'pm10' => [
            ['min' => 0, 'max' => 20, 'level' => 1],
            ['min' => 20.1, 'max' => 40, 'level' => 2],
            ['min' => 40.1, 'max' => 50, 'level' => 3],
            ['min' => 50.1, 'max' => 100, 'level' => 4],
            ['min' => 100.1, 'max' => 150, 'level' => 5],
            ['min' => 150.1, 'max' => 9999, 'level' => 6],
        ],
        'o3' => [
            ['min' => 0, 'max' => 50, 'level' => 1],
            ['min' => 50.1, 'max' => 100, 'level' => 2],
            ['min' => 100.1, 'max' => 130, 'level' => 3],
            ['min' => 130.1, 'max' => 240, 'level' => 4],
            ['min' => 240.1, 'max' => 380, 'level' => 5],
            ['min' => 380.1, 'max' => 9999, 'level' => 6],
        ],
        'no2' => [
            ['min' => 0, 'max' => 40, 'level' => 1],
            ['min' => 40.1, 'max' => 90, 'level' => 2],
            ['min' => 90.1, 'max' => 120, 'level' => 3],
            ['min' => 120.1, 'max' => 230, 'level' => 4],
            ['min' => 230.1, 'max' => 340, 'level' => 5],
            ['min' => 340.1, 'max' => 9999, 'level' => 6],
        ],
        'so2' => [
            ['min' => 0, 'max' => 100, 'level' => 1],
            ['min' => 100.1, 'max' => 200, 'level' => 2],
            ['min' => 200.1, 'max' => 350, 'level' => 3],
            ['min' => 350.1, 'max' => 500, 'level' => 4],
            ['min' => 500.1, 'max' => 750, 'level' => 5],
            ['min' => 750.1, 'max' => 9999, 'level' => 6],
        ],
    ];

    /**
     * UK DAQI breakpoints (µg/m³)
     * 10 levels: Low (1-3), Moderate (4-6), High (7-9), Very High (10)
     */
    private const UK_DAQI_BREAKPOINTS = [
        'pm25' => [
            ['min' => 0, 'max' => 11, 'level' => 1],
            ['min' => 12, 'max' => 23, 'level' => 2],
            ['min' => 24, 'max' => 35, 'level' => 3],
            ['min' => 36, 'max' => 41, 'level' => 4],
            ['min' => 42, 'max' => 47, 'level' => 5],
            ['min' => 48, 'max' => 53, 'level' => 6],
            ['min' => 54, 'max' => 58, 'level' => 7],
            ['min' => 59, 'max' => 64, 'level' => 8],
            ['min' => 65, 'max' => 70, 'level' => 9],
            ['min' => 71, 'max' => 9999, 'level' => 10],
        ],
        'pm10' => [
            ['min' => 0, 'max' => 16, 'level' => 1],
            ['min' => 17, 'max' => 33, 'level' => 2],
            ['min' => 34, 'max' => 50, 'level' => 3],
            ['min' => 51, 'max' => 58, 'level' => 4],
            ['min' => 59, 'max' => 66, 'level' => 5],
            ['min' => 67, 'max' => 75, 'level' => 6],
            ['min' => 76, 'max' => 83, 'level' => 7],
            ['min' => 84, 'max' => 91, 'level' => 8],
            ['min' => 92, 'max' => 100, 'level' => 9],
            ['min' => 101, 'max' => 9999, 'level' => 10],
        ],
        'o3' => [
            ['min' => 0, 'max' => 33, 'level' => 1],
            ['min' => 34, 'max' => 66, 'level' => 2],
            ['min' => 67, 'max' => 100, 'level' => 3],
            ['min' => 101, 'max' => 120, 'level' => 4],
            ['min' => 121, 'max' => 140, 'level' => 5],
            ['min' => 141, 'max' => 160, 'level' => 6],
            ['min' => 161, 'max' => 187, 'level' => 7],
            ['min' => 188, 'max' => 213, 'level' => 8],
            ['min' => 214, 'max' => 240, 'level' => 9],
            ['min' => 241, 'max' => 9999, 'level' => 10],
        ],
        'no2' => [
            ['min' => 0, 'max' => 67, 'level' => 1],
            ['min' => 68, 'max' => 134, 'level' => 2],
            ['min' => 135, 'max' => 200, 'level' => 3],
            ['min' => 201, 'max' => 267, 'level' => 4],
            ['min' => 268, 'max' => 334, 'level' => 5],
            ['min' => 335, 'max' => 400, 'level' => 6],
            ['min' => 401, 'max' => 467, 'level' => 7],
            ['min' => 468, 'max' => 534, 'level' => 8],
            ['min' => 535, 'max' => 600, 'level' => 9],
            ['min' => 601, 'max' => 9999, 'level' => 10],
        ],
        'so2' => [
            ['min' => 0, 'max' => 88, 'level' => 1],
            ['min' => 89, 'max' => 177, 'level' => 2],
            ['min' => 178, 'max' => 266, 'level' => 3],
            ['min' => 267, 'max' => 354, 'level' => 4],
            ['min' => 355, 'max' => 443, 'level' => 5],
            ['min' => 444, 'max' => 532, 'level' => 6],
            ['min' => 533, 'max' => 710, 'level' => 7],
            ['min' => 711, 'max' => 887, 'level' => 8],
            ['min' => 888, 'max' => 1064, 'level' => 9],
            ['min' => 1065, 'max' => 9999, 'level' => 10],
        ],
    ];

    /**
     * EEA Index level categories
     */
    private const EEA_CATEGORIES = [
        1 => ['level' => 'Good', 'color' => '#50f0e6', 'description' => 'Air quality is considered satisfactory.'],
        2 => ['level' => 'Fair', 'color' => '#50ccaa', 'description' => 'Air quality is acceptable.'],
        3 => ['level' => 'Moderate', 'color' => '#f0e641', 'description' => 'Sensitive groups may experience health effects.'],
        4 => ['level' => 'Poor', 'color' => '#ff5050', 'description' => 'Everyone may begin to experience health effects.'],
        5 => ['level' => 'Very Poor', 'color' => '#960032', 'description' => 'Health warnings of emergency conditions.'],
        6 => ['level' => 'Extremely Poor', 'color' => '#7d2181', 'description' => 'Health alert: everyone may experience serious effects.'],
    ];

    /**
     * UK DAQI level categories
     */
    private const UK_DAQI_CATEGORIES = [
        1 => ['level' => 'Low', 'color' => '#9cff9c', 'description' => 'Enjoy your usual outdoor activities.'],
        2 => ['level' => 'Low', 'color' => '#31ff00', 'description' => 'Enjoy your usual outdoor activities.'],
        3 => ['level' => 'Low', 'color' => '#31cf00', 'description' => 'Enjoy your usual outdoor activities.'],
        4 => ['level' => 'Moderate', 'color' => '#ff0', 'description' => 'Adults and children with lung or heart problems should reduce strenuous activity.'],
        5 => ['level' => 'Moderate', 'color' => '#ffcf00', 'description' => 'Adults and children with lung or heart problems should reduce strenuous activity.'],
        6 => ['level' => 'Moderate', 'color' => '#ff9a00', 'description' => 'Adults and children with lung or heart problems should reduce strenuous activity.'],
        7 => ['level' => 'High', 'color' => '#ff6464', 'description' => 'Anyone experiencing discomfort should reduce physical activity.'],
        8 => ['level' => 'High', 'color' => '#ff0000', 'description' => 'Anyone experiencing discomfort should reduce physical activity.'],
        9 => ['level' => 'High', 'color' => '#990000', 'description' => 'Anyone experiencing discomfort should reduce physical activity.'],
        10 => ['level' => 'Very High', 'color' => '#ce30ff', 'description' => 'Reduce physical exertion, particularly outdoors.'],
    ];

    /**
     * US EPA AQI categories (default WAQI scale)
     */
    private const US_EPA_CATEGORIES = [
        'good' => ['level' => 'Good', 'color' => '#00e400', 'description' => 'Air quality is considered satisfactory.'],
        'moderate' => ['level' => 'Moderate', 'color' => '#ffff00', 'description' => 'Acceptable; some pollutants may be a concern for sensitive groups.'],
        'usg' => ['level' => 'Unhealthy for Sensitive Groups', 'color' => '#ff7e00', 'description' => 'Sensitive groups may experience health effects.'],
        'unhealthy' => ['level' => 'Unhealthy', 'color' => '#ff0000', 'description' => 'Everyone may begin to experience health effects.'],
        'very_unhealthy' => ['level' => 'Very Unhealthy', 'color' => '#8f3f97', 'description' => 'Health warnings of emergency conditions.'],
        'hazardous' => ['level' => 'Hazardous', 'color' => '#7e0023', 'description' => 'Health alert: everyone may experience serious effects.'],
    ];

    public function __construct()
    {
        try {
            $this->apiKey = Setting::getValue('waqi.api_key', '') ?? '';
        } catch (\Exception $e) {
            Log::warning('Failed to get WAQI API key (decryption error)', ['error' => $e->getMessage()]);
            $this->apiKey = '';
        }
        $this->latitude = Setting::latitude();
        $this->longitude = Setting::longitude();
        $this->stationMode = Setting::getValue('waqi.station_mode', 'auto') ?? 'auto';
        $this->stationId = Setting::getValue('waqi.station_id', '') ?: null;
        $this->indexType = Setting::getValue('airquality.index_type', 'us') ?? 'us';
    }

    /**
     * Get the configured index type
     */
    public function getIndexType(): string
    {
        return $this->indexType;
    }

    /**
     * Get the feed URL based on station mode
     */
    private function getFeedUrl(): string
    {
        if ($this->stationMode === 'manual' && !empty($this->stationId)) {
            $station = $this->stationId;
            if (!str_starts_with($station, '@')) {
                return $this->baseUrl . 'feed/' . urlencode($station) . '/';
            }
            return $this->baseUrl . 'feed/' . $station . '/';
        }

        return $this->baseUrl . 'feed/geo:' . $this->latitude . ';' . $this->longitude . '/';
    }

    /**
     * Convert EPA AQI sub-index back to concentration (µg/m³)
     *
     * Uses the inverse of the EPA AQI formula:
     * C = ((AQI - AQI_lo) / (AQI_hi - AQI_lo)) * (C_hi - C_lo) + C_lo
     *
     * @param float $aqi The AQI sub-index value
     * @param string $pollutant The pollutant type (pm25, pm10, o3, no2, so2)
     * @return float|null The estimated concentration in µg/m³
     */
    private function aqiToConcentration(float $aqi, string $pollutant): ?float
    {
        if (!isset(self::EPA_AQI_BREAKPOINTS[$pollutant])) {
            return null;
        }

        $breakpoints = self::EPA_AQI_BREAKPOINTS[$pollutant];

        foreach ($breakpoints as $bp) {
            [$aqiLo, $aqiHi, $concLo, $concHi] = $bp;

            if ($aqi >= $aqiLo && $aqi <= $aqiHi) {
                // Inverse AQI formula
                $concentration = (($aqi - $aqiLo) / ($aqiHi - $aqiLo)) * ($concHi - $concLo) + $concLo;
                return round($concentration, 1);
            }
        }

        // If AQI is above 500, extrapolate from the last breakpoint
        if ($aqi > 500) {
            $lastBp = end($breakpoints);
            [$aqiLo, $aqiHi, $concLo, $concHi] = $lastBp;
            $concentration = (($aqi - $aqiLo) / ($aqiHi - $aqiLo)) * ($concHi - $concLo) + $concLo;
            return round($concentration, 1);
        }

        return null;
    }

    /**
     * Convert all pollutant AQI sub-indices to concentrations
     *
     * @param array $aqiValues Array of AQI sub-index values by pollutant
     * @return array Array of concentrations in µg/m³
     */
    private function convertAllToConcentrations(array $aqiValues): array
    {
        $concentrations = [];

        foreach (['pm25', 'pm10', 'o3', 'no2', 'so2'] as $pollutant) {
            $aqi = $aqiValues[$pollutant] ?? null;
            if ($aqi !== null) {
                $concentrations[$pollutant] = $this->aqiToConcentration((float) $aqi, $pollutant);
            } else {
                $concentrations[$pollutant] = null;
            }
        }

        // CO is not typically used for EEA/UK calculations
        $concentrations['co'] = $aqiValues['co'] ?? null;

        return $concentrations;
    }

    /**
     * Fetch air quality data from WAQI
     */
    public function fetchAirQuality(): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('WAQI API key not configured');
            return null;
        }

        // Include index type in cache key so changing index type invalidates cache
        $cacheKey = $this->stationMode === 'manual' && !empty($this->stationId)
            ? "waqi_station_{$this->stationId}_{$this->indexType}"
            : "waqi_{$this->latitude}_{$this->longitude}_{$this->indexType}";

        try {
            $http = Http::timeout(15);
            if (!app()->environment('production') && env('HTTP_SKIP_TLS_VERIFY')) {
                $http = $http->withoutVerifying();
            }

            $response = $http
                ->get($this->getFeedUrl(), [
                    'token' => $this->apiKey,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['status']) && $data['status'] === 'ok') {
                    $result = $this->parseAirQuality($data['data']);
                    CacheFreshness::put($cacheKey, $result, 1800);
                    return $result;
                }
            }

            Log::error('WAQI API request failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 200),
            ]);

        } catch (\Exception $e) {
            Log::error('WAQI API exception', ['error' => $e->getMessage()]);
        }

        return Cache::get($cacheKey);
    }

    /**
     * Parse WAQI response
     */
    private function parseAirQuality(array $data): array
    {
        $iaqi = $data['iaqi'] ?? [];

        // WAQI returns AQI sub-index values for each pollutant (not raw concentrations)
        $aqiSubIndices = [
            'pm25' => $iaqi['pm25']['v'] ?? null,
            'pm10' => $iaqi['pm10']['v'] ?? null,
            'o3' => $iaqi['o3']['v'] ?? null,
            'no2' => $iaqi['no2']['v'] ?? null,
            'so2' => $iaqi['so2']['v'] ?? null,
            'co' => $iaqi['co']['v'] ?? null,
        ];

        $waqiAqi = $data['aqi'] ?? 0;

        // For US EPA, use the native WAQI AQI value directly
        if ($this->indexType === 'us') {
            $aqi = $waqiAqi;
            $dominantCalc = $data['dominentpol'] ?? null;
            $concentrations = $aqiSubIndices; // Keep as AQI values for display
        } else {
            // For EEA/UK, convert AQI sub-indices back to concentrations first
            $concentrations = $this->convertAllToConcentrations($aqiSubIndices);

            // Then calculate the appropriate index from concentrations
            $calculatedIndex = $this->calculateIndex($concentrations);
            $aqi = $calculatedIndex['value'];
            $dominantCalc = $calculatedIndex['dominant_pollutant'];
        }

        $category = $this->getAqiCategory($aqi, $this->indexType);

        return [
            'aqi' => $aqi,
            'aqi_waqi' => $waqiAqi,
            'index_type' => $this->indexType,
            'dominant_pollutant_calculated' => $dominantCalc,
            'station' => $data['city']['name'] ?? null,
            'updated_at' => $data['time']['iso'] ?? null,
            'dominant_pollutant' => $data['dominentpol'] ?? null,
            'pollutants' => $this->indexType === 'us' ? $aqiSubIndices : $concentrations,
            'pollutants_aqi' => $aqiSubIndices,
            'pollutants_concentration' => $this->indexType === 'us'
                ? $this->convertAllToConcentrations($aqiSubIndices)
                : $concentrations,
            'weather' => [
                'temperature' => $iaqi['t']['v'] ?? null,
                'humidity' => $iaqi['h']['v'] ?? null,
                'pressure' => $iaqi['p']['v'] ?? null,
                'wind' => $iaqi['w']['v'] ?? null,
            ],
            'category' => $category,
        ];
    }

    /**
     * Calculate index from pollutant concentrations based on index type
     *
     * @param array $concentrations Array of pollutant concentrations in µg/m³
     * @param string|null $indexType Optional index type override ('us', 'eea', 'uk')
     * @return array ['value' => int, 'dominant_pollutant' => string|null]
     */
    public function calculateIndex(array $concentrations, ?string $indexType = null): array
    {
        $type = $indexType ?? $this->indexType;

        // For US EPA, calculate AQI from concentrations
        if ($type === 'us') {
            return $this->calculateUsEpaFromConcentrations($concentrations);
        }

        $breakpoints = $type === 'eea' ? self::EEA_BREAKPOINTS : self::UK_DAQI_BREAKPOINTS;
        $maxLevel = 0;
        $dominant = null;

        foreach ($breakpoints as $pollutant => $levels) {
            $value = $concentrations[$pollutant] ?? null;
            if ($value === null) {
                continue;
            }

            $level = $this->findLevel((float) $value, $levels);
            if ($level > $maxLevel) {
                $maxLevel = $level;
                $dominant = $pollutant;
            }
        }

        return [
            'value' => $maxLevel ?: 1,
            'dominant_pollutant' => $dominant,
        ];
    }

    /**
     * Calculate US EPA AQI from pollutant concentrations
     */
    private function calculateUsEpaFromConcentrations(array $concentrations): array
    {
        $maxAqi = 0;
        $dominant = null;

        // PM2.5 breakpoints (µg/m³ -> AQI)
        $pm25Breakpoints = [
            [0, 12, 0, 50],
            [12.1, 35.4, 51, 100],
            [35.5, 55.4, 101, 150],
            [55.5, 150.4, 151, 200],
            [150.5, 250.4, 201, 300],
            [250.5, 500.4, 301, 500],
        ];

        // PM10 breakpoints (µg/m³ -> AQI)
        $pm10Breakpoints = [
            [0, 54, 0, 50],
            [55, 154, 51, 100],
            [155, 254, 101, 150],
            [255, 354, 151, 200],
            [355, 424, 201, 300],
            [425, 604, 301, 500],
        ];

        // Calculate for PM2.5
        $pm25 = $concentrations['pm25'] ?? null;
        if ($pm25 !== null) {
            foreach ($pm25Breakpoints as $bp) {
                [$concLo, $concHi, $aqiLo, $aqiHi] = $bp;
                if ($pm25 >= $concLo && $pm25 <= $concHi) {
                    $aqi = (($aqiHi - $aqiLo) / ($concHi - $concLo)) * ($pm25 - $concLo) + $aqiLo;
                    if ($aqi > $maxAqi) {
                        $maxAqi = round($aqi);
                        $dominant = 'pm25';
                    }
                    break;
                }
            }
        }

        // Calculate for PM10
        $pm10 = $concentrations['pm10'] ?? null;
        if ($pm10 !== null) {
            foreach ($pm10Breakpoints as $bp) {
                [$concLo, $concHi, $aqiLo, $aqiHi] = $bp;
                if ($pm10 >= $concLo && $pm10 <= $concHi) {
                    $aqi = (($aqiHi - $aqiLo) / ($concHi - $concLo)) * ($pm10 - $concLo) + $aqiLo;
                    if ($aqi > $maxAqi) {
                        $maxAqi = round($aqi);
                        $dominant = 'pm10';
                    }
                    break;
                }
            }
        }

        return [
            'value' => $maxAqi ?: 0,
            'dominant_pollutant' => $dominant,
        ];
    }

    /**
     * Find the level for a given pollutant concentration
     */
    private function findLevel(float $value, array $levels): int
    {
        foreach ($levels as $level) {
            if ($value >= $level['min'] && $value <= $level['max']) {
                return $level['level'];
            }
        }

        // If above max, return highest level
        return end($levels)['level'];
    }

    /**
     * Get AQI category based on index value and type
     *
     * @param int $aqi The AQI/index value
     * @param string|null $indexType Optional index type override
     * @return array Category information with level, color, and description
     */
    public function getAqiCategory(int $aqi, ?string $indexType = null): array
    {
        $type = $indexType ?? $this->indexType;

        if ($type === 'eea') {
            $level = min(max($aqi, 1), 6);
            return self::EEA_CATEGORIES[$level];
        }

        if ($type === 'uk') {
            $level = min(max($aqi, 1), 10);
            return self::UK_DAQI_CATEGORIES[$level];
        }

        // US EPA (default)
        return match (true) {
            $aqi <= 50 => self::US_EPA_CATEGORIES['good'],
            $aqi <= 100 => self::US_EPA_CATEGORIES['moderate'],
            $aqi <= 150 => self::US_EPA_CATEGORIES['usg'],
            $aqi <= 200 => self::US_EPA_CATEGORIES['unhealthy'],
            $aqi <= 300 => self::US_EPA_CATEGORIES['very_unhealthy'],
            default => self::US_EPA_CATEGORIES['hazardous'],
        };
    }

    /**
     * Get all categories for the current index type (for legend display)
     */
    public function getAllCategories(): array
    {
        return match ($this->indexType) {
            'eea' => self::EEA_CATEGORIES,
            'uk' => self::UK_DAQI_CATEGORIES,
            default => [
                1 => self::US_EPA_CATEGORIES['good'],
                2 => self::US_EPA_CATEGORIES['moderate'],
                3 => self::US_EPA_CATEGORIES['usg'],
                4 => self::US_EPA_CATEGORIES['unhealthy'],
                5 => self::US_EPA_CATEGORIES['very_unhealthy'],
                6 => self::US_EPA_CATEGORIES['hazardous'],
            ],
        };
    }

    /**
     * Get the maximum index value for the current index type
     */
    public function getMaxIndexValue(): int
    {
        return match ($this->indexType) {
            'eea' => 6,
            'uk' => 10,
            default => 500,
        };
    }

    /**
     * Get index type display name
     */
    public function getIndexTypeName(): string
    {
        return match ($this->indexType) {
            'eea' => 'European (EEA)',
            'uk' => 'UK DAQI',
            default => 'US EPA',
        };
    }
}
