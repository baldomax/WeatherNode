<?php

declare(strict_types=1);

namespace App\Services\Weather\Sources;

use App\Models\Setting;
use App\Services\Weather\Normalization\UnitConverter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WeatherLink v1 API Adapter
 * 
 * Provides integration with Davis WeatherLink v1 Cloud API.
 * Uses encrypted storage for password and API key.
 * 
 * @see https://www.weatherlink.com/static/docs/APIdocumentation.pdf
 */
class WeatherLinkV1Adapter implements WeatherSourceAdapter
{
    public function key(): string
    {
        return 'DWL';
    }

    public function fetch(): ?array
    {
        $deviceId = Setting::getValue('weatherlink.device_id', '');
        // Password and API key are stored encrypted - use getCastedValue for auto-decryption
        $password = $this->getDecryptedSetting('weatherlink.password');
        $apiKey = $this->getDecryptedSetting('weatherlink.api_key');

        if ($deviceId === '' || $password === '' || $apiKey === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)->get('https://api.weatherlink.com/v1/NoaaExt.json', [
                'user' => $deviceId,
                'pass' => $password,
                'apiToken' => $apiKey,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $payload = $response->json();
        } catch (\Exception $e) {
            return null;
        }

        if (!is_array($payload)) {
            return null;
        }

        $current = $payload;
        $obs = $payload['davis_current_observation'] ?? [];

        $recordedAt = null;
        if (!empty($current['observation_time_rfc822'])) {
            try {
                $recordedAt = Carbon::parse($current['observation_time_rfc822']);
            } catch (\Exception $e) {
                $recordedAt = null;
            }
        }

        $reading = [
            'recorded_at' => $recordedAt,
            'temperature' => UnitConverter::fahrenheitToCelsius($current['temp_f'] ?? null, 1),
            'humidity' => $current['relative_humidity'] ?? null,
            'dew_point' => UnitConverter::fahrenheitToCelsius($current['dewpoint_f'] ?? null, 1),
            'heat_index' => UnitConverter::fahrenheitToCelsius($current['heat_index_f'] ?? null, 1),
            'wind_chill' => UnitConverter::fahrenheitToCelsius($current['windchill_f'] ?? null, 1),
            'pressure_rel' => UnitConverter::inHgToHpa($current['pressure_in'] ?? null, 1),
            'wind_speed' => UnitConverter::mphToKmh($obs['wind_ten_min_avg_mph'] ?? ($current['wind_mph'] ?? null), 1),
            'wind_gust' => UnitConverter::mphToKmh($obs['wind_ten_min_gust_mph'] ?? null, 1),
            'wind_direction' => $current['wind_degrees'] ?? null,
            'rain_rate' => UnitConverter::inchesToMm($obs['rain_rate_in_per_hr'] ?? null, 2),
            'rain_daily' => UnitConverter::inchesToMm($obs['rain_day_in'] ?? null, 2),
            'uv_index' => $obs['uv_index'] ?? null,
            'solar_radiation' => $obs['solar_radiation'] ?? null,
            'temperature_indoor' => UnitConverter::fahrenheitToCelsius($obs['temp_in_f'] ?? null, 1),
            'humidity_indoor' => $obs['relative_humidity_in'] ?? null,
            'indoor_temperature' => UnitConverter::fahrenheitToCelsius($obs['temp_in_f'] ?? null, 1),
            'indoor_humidity' => $obs['relative_humidity_in'] ?? null,
        ];

        return array_filter($reading, static fn ($value) => $value !== null);
    }

    /**
     * Get decrypted setting value
     * 
     * Retrieves a setting and decrypts it if it's stored as encrypted type.
     * Uses the Setting model's built-in getCastedValue() which handles decryption.
     * 
     * @param string $key Setting key
     * @return string|null Decrypted value or null if not found/empty
     */
    private function getDecryptedSetting(string $key): ?string
    {
        $setting = Setting::where('key', $key)->first();
        
        if (!$setting) {
            return null;
        }

        $value = $setting->getCastedValue();
        
        return !empty($value) ? $value : null;
    }
}
