<?php

namespace App\Services\AirQuality;

use App\Models\Setting;

/**
 * Trait for calculating Air Quality Index values using different scales
 *
 * Supports:
 * - US EPA AQI (0-500)
 * - European EEA Index (1-6)
 * - UK DAQI (1-10)
 */
trait CalculatesAirQualityIndex
{
    /**
     * European EEA Index breakpoints (µg/m³)
     * 6 levels: Good (1), Fair (2), Moderate (3), Poor (4), Very Poor (5), Extremely Poor (6)
     */
    private static array $eeaBreakpoints = [
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
        'pm1' => [
            // PM1 not defined in EEA, use same as PM2.5 as approximation
            ['min' => 0, 'max' => 10, 'level' => 1],
            ['min' => 10.1, 'max' => 20, 'level' => 2],
            ['min' => 20.1, 'max' => 25, 'level' => 3],
            ['min' => 25.1, 'max' => 50, 'level' => 4],
            ['min' => 50.1, 'max' => 75, 'level' => 5],
            ['min' => 75.1, 'max' => 9999, 'level' => 6],
        ],
    ];

    /**
     * UK DAQI breakpoints (µg/m³)
     * 10 levels: Low (1-3), Moderate (4-6), High (7-9), Very High (10)
     */
    private static array $ukDaqiBreakpoints = [
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
        'pm1' => [
            // PM1 not defined in UK DAQI, use same as PM2.5 as approximation
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
    ];

    /**
     * US EPA AQI breakpoints for PM2.5 (µg/m³)
     */
    private static array $usEpaBreakpoints = [
        'pm25' => [
            ['pm_low' => 0, 'pm_high' => 12, 'aqi_low' => 0, 'aqi_high' => 50, 'level' => 'Good', 'color' => '#00e400'],
            ['pm_low' => 12.1, 'pm_high' => 35.4, 'aqi_low' => 51, 'aqi_high' => 100, 'level' => 'Moderate', 'color' => '#ffff00'],
            ['pm_low' => 35.5, 'pm_high' => 55.4, 'aqi_low' => 101, 'aqi_high' => 150, 'level' => 'Unhealthy for Sensitive', 'color' => '#ff7e00'],
            ['pm_low' => 55.5, 'pm_high' => 150.4, 'aqi_low' => 151, 'aqi_high' => 200, 'level' => 'Unhealthy', 'color' => '#ff0000'],
            ['pm_low' => 150.5, 'pm_high' => 250.4, 'aqi_low' => 201, 'aqi_high' => 300, 'level' => 'Very Unhealthy', 'color' => '#8f3f97'],
            ['pm_low' => 250.5, 'pm_high' => 500, 'aqi_low' => 301, 'aqi_high' => 500, 'level' => 'Hazardous', 'color' => '#7e0023'],
        ],
        'pm10' => [
            ['pm_low' => 0, 'pm_high' => 54, 'aqi_low' => 0, 'aqi_high' => 50, 'level' => 'Good', 'color' => '#00e400'],
            ['pm_low' => 55, 'pm_high' => 154, 'aqi_low' => 51, 'aqi_high' => 100, 'level' => 'Moderate', 'color' => '#ffff00'],
            ['pm_low' => 155, 'pm_high' => 254, 'aqi_low' => 101, 'aqi_high' => 150, 'level' => 'Unhealthy for Sensitive', 'color' => '#ff7e00'],
            ['pm_low' => 255, 'pm_high' => 354, 'aqi_low' => 151, 'aqi_high' => 200, 'level' => 'Unhealthy', 'color' => '#ff0000'],
            ['pm_low' => 355, 'pm_high' => 424, 'aqi_low' => 201, 'aqi_high' => 300, 'level' => 'Very Unhealthy', 'color' => '#8f3f97'],
            ['pm_low' => 425, 'pm_high' => 604, 'aqi_low' => 301, 'aqi_high' => 500, 'level' => 'Hazardous', 'color' => '#7e0023'],
        ],
        'pm1' => [
            // PM1 uses same breakpoints as PM2.5 (approximation)
            ['pm_low' => 0, 'pm_high' => 12, 'aqi_low' => 0, 'aqi_high' => 50, 'level' => 'Good', 'color' => '#00e400'],
            ['pm_low' => 12.1, 'pm_high' => 35.4, 'aqi_low' => 51, 'aqi_high' => 100, 'level' => 'Moderate', 'color' => '#ffff00'],
            ['pm_low' => 35.5, 'pm_high' => 55.4, 'aqi_low' => 101, 'aqi_high' => 150, 'level' => 'Unhealthy for Sensitive', 'color' => '#ff7e00'],
            ['pm_low' => 55.5, 'pm_high' => 150.4, 'aqi_low' => 151, 'aqi_high' => 200, 'level' => 'Unhealthy', 'color' => '#ff0000'],
            ['pm_low' => 150.5, 'pm_high' => 250.4, 'aqi_low' => 201, 'aqi_high' => 300, 'level' => 'Very Unhealthy', 'color' => '#8f3f97'],
            ['pm_low' => 250.5, 'pm_high' => 500, 'aqi_low' => 301, 'aqi_high' => 500, 'level' => 'Hazardous', 'color' => '#7e0023'],
        ],
    ];

    /**
     * EEA Index level categories
     */
    private static array $eeaCategories = [
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
    private static array $ukDaqiCategories = [
        1 => ['level' => 'Low', 'color' => '#9cff9c', 'description' => 'Enjoy your usual outdoor activities.'],
        2 => ['level' => 'Low', 'color' => '#31ff00', 'description' => 'Enjoy your usual outdoor activities.'],
        3 => ['level' => 'Low', 'color' => '#31cf00', 'description' => 'Enjoy your usual outdoor activities.'],
        4 => ['level' => 'Moderate', 'color' => '#ffff00', 'description' => 'Adults and children with lung or heart problems should reduce strenuous activity.'],
        5 => ['level' => 'Moderate', 'color' => '#ffcf00', 'description' => 'Adults and children with lung or heart problems should reduce strenuous activity.'],
        6 => ['level' => 'Moderate', 'color' => '#ff9a00', 'description' => 'Adults and children with lung or heart problems should reduce strenuous activity.'],
        7 => ['level' => 'High', 'color' => '#ff6464', 'description' => 'Anyone experiencing discomfort should reduce physical activity.'],
        8 => ['level' => 'High', 'color' => '#ff0000', 'description' => 'Anyone experiencing discomfort should reduce physical activity.'],
        9 => ['level' => 'High', 'color' => '#990000', 'description' => 'Anyone experiencing discomfort should reduce physical activity.'],
        10 => ['level' => 'Very High', 'color' => '#ce30ff', 'description' => 'Reduce physical exertion, particularly outdoors.'],
    ];

    /**
     * Get the configured index type from settings
     */
    protected function getConfiguredIndexType(): string
    {
        return Setting::getValue('airquality.index_type', 'us') ?? 'us';
    }

    /**
     * Calculate Air Quality Index from PM concentrations
     *
     * @param array $concentrations Array with keys: pm1, pm25, pm10 (values in µg/m³)
     * @param string|null $indexType Index type: 'us', 'eea', or 'uk'. Defaults to configured setting.
     * @return array ['value' => int, 'level' => string, 'color' => string, 'description' => string, 'dominant_pollutant' => string|null]
     */
    protected function calculateAqi(array $concentrations, ?string $indexType = null): array
    {
        $type = $indexType ?? $this->getConfiguredIndexType();

        $pm25 = $concentrations['pm25'] ?? $concentrations['pm2p5'] ?? null;
        $pm10 = $concentrations['pm10'] ?? null;
        $pm1 = $concentrations['pm1'] ?? null;

        if ($type === 'us') {
            return $this->calculateUsEpaAqi($pm25, $pm10, $pm1);
        }

        if ($type === 'eea') {
            return $this->calculateEeaIndex($pm25, $pm10, $pm1);
        }

        if ($type === 'uk') {
            return $this->calculateUkDaqi($pm25, $pm10, $pm1);
        }

        // Default to US EPA
        return $this->calculateUsEpaAqi($pm25, $pm10, $pm1);
    }

    /**
     * Calculate US EPA AQI from PM concentrations
     */
    private function calculateUsEpaAqi(?float $pm25, ?float $pm10, ?float $pm1): array
    {
        $maxAqi = 0;
        $maxCategory = null;
        $dominant = null;

        // Calculate AQI for each pollutant and take the highest
        foreach (['pm25' => $pm25, 'pm10' => $pm10, 'pm1' => $pm1] as $pollutant => $value) {
            if ($value === null) {
                continue;
            }

            $breakpoints = self::$usEpaBreakpoints[$pollutant] ?? self::$usEpaBreakpoints['pm25'];

            foreach ($breakpoints as $bp) {
                if ($value >= $bp['pm_low'] && $value <= $bp['pm_high']) {
                    $aqi = (($bp['aqi_high'] - $bp['aqi_low']) / ($bp['pm_high'] - $bp['pm_low']))
                         * ($value - $bp['pm_low']) + $bp['aqi_low'];
                    $aqi = round($aqi);

                    if ($aqi > $maxAqi) {
                        $maxAqi = $aqi;
                        $maxCategory = [
                            'level' => $bp['level'],
                            'color' => $bp['color'],
                        ];
                        $dominant = $pollutant;
                    }
                    break;
                }
            }
        }

        // If above 500, cap at hazardous
        if ($maxAqi >= 500) {
            $maxAqi = 500;
            $maxCategory = ['level' => 'Hazardous', 'color' => '#7e0023'];
        }

        if ($maxCategory === null) {
            $maxCategory = ['level' => 'Good', 'color' => '#00e400'];
        }

        return [
            'value' => $maxAqi,
            'level' => $maxCategory['level'],
            'color' => $maxCategory['color'],
            'description' => $this->getUsEpaDescription($maxAqi),
            'dominant_pollutant' => $dominant,
        ];
    }

    /**
     * Get US EPA AQI description based on value
     */
    private function getUsEpaDescription(int $aqi): string
    {
        return match (true) {
            $aqi <= 50 => 'Air quality is considered satisfactory.',
            $aqi <= 100 => 'Acceptable; some pollutants may be a concern for sensitive groups.',
            $aqi <= 150 => 'Sensitive groups may experience health effects.',
            $aqi <= 200 => 'Everyone may begin to experience health effects.',
            $aqi <= 300 => 'Health warnings of emergency conditions.',
            default => 'Health alert: everyone may experience serious effects.',
        };
    }

    /**
     * Calculate European EEA Index from PM concentrations
     */
    private function calculateEeaIndex(?float $pm25, ?float $pm10, ?float $pm1): array
    {
        $maxLevel = 0;
        $dominant = null;

        foreach (['pm25' => $pm25, 'pm10' => $pm10, 'pm1' => $pm1] as $pollutant => $value) {
            if ($value === null || !isset(self::$eeaBreakpoints[$pollutant])) {
                continue;
            }

            $level = $this->findLevelInBreakpoints((float) $value, self::$eeaBreakpoints[$pollutant]);
            if ($level > $maxLevel) {
                $maxLevel = $level;
                $dominant = $pollutant;
            }
        }

        $level = max($maxLevel, 1);
        $category = self::$eeaCategories[$level];

        return [
            'value' => $level,
            'level' => $category['level'],
            'color' => $category['color'],
            'description' => $category['description'],
            'dominant_pollutant' => $dominant,
        ];
    }

    /**
     * Calculate UK DAQI from PM concentrations
     */
    private function calculateUkDaqi(?float $pm25, ?float $pm10, ?float $pm1): array
    {
        $maxLevel = 0;
        $dominant = null;

        foreach (['pm25' => $pm25, 'pm10' => $pm10, 'pm1' => $pm1] as $pollutant => $value) {
            if ($value === null || !isset(self::$ukDaqiBreakpoints[$pollutant])) {
                continue;
            }

            $level = $this->findLevelInBreakpoints((float) $value, self::$ukDaqiBreakpoints[$pollutant]);
            if ($level > $maxLevel) {
                $maxLevel = $level;
                $dominant = $pollutant;
            }
        }

        $level = max($maxLevel, 1);
        $category = self::$ukDaqiCategories[$level];

        return [
            'value' => $level,
            'level' => $category['level'],
            'color' => $category['color'],
            'description' => $category['description'],
            'dominant_pollutant' => $dominant,
        ];
    }

    /**
     * Find the level for a given concentration value in breakpoints array
     */
    private function findLevelInBreakpoints(float $value, array $breakpoints): int
    {
        foreach ($breakpoints as $bp) {
            if ($value >= $bp['min'] && $value <= $bp['max']) {
                return $bp['level'];
            }
        }

        // If above max, return highest level
        return end($breakpoints)['level'];
    }

    /**
     * Get category info for a given AQI value and index type
     */
    protected function getAqiCategoryInfo(int $aqi, string $indexType): array
    {
        if ($indexType === 'eea') {
            $level = min(max($aqi, 1), 6);
            return self::$eeaCategories[$level];
        }

        if ($indexType === 'uk') {
            $level = min(max($aqi, 1), 10);
            return self::$ukDaqiCategories[$level];
        }

        // US EPA
        return match (true) {
            $aqi <= 50 => ['level' => 'Good', 'color' => '#00e400', 'description' => 'Air quality is considered satisfactory.'],
            $aqi <= 100 => ['level' => 'Moderate', 'color' => '#ffff00', 'description' => 'Acceptable; some pollutants may be a concern for sensitive groups.'],
            $aqi <= 150 => ['level' => 'Unhealthy for Sensitive Groups', 'color' => '#ff7e00', 'description' => 'Sensitive groups may experience health effects.'],
            $aqi <= 200 => ['level' => 'Unhealthy', 'color' => '#ff0000', 'description' => 'Everyone may begin to experience health effects.'],
            $aqi <= 300 => ['level' => 'Very Unhealthy', 'color' => '#8f3f97', 'description' => 'Health warnings of emergency conditions.'],
            default => ['level' => 'Hazardous', 'color' => '#7e0023', 'description' => 'Health alert: everyone may experience serious effects.'],
        };
    }

    /**
     * Get index type display name
     */
    protected function getIndexTypeName(string $indexType): string
    {
        return match ($indexType) {
            'eea' => 'European (EEA)',
            'uk' => 'UK DAQI',
            default => 'US EPA',
        };
    }
}
