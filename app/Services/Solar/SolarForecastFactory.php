<?php

namespace App\Services\Solar;

use App\Models\Setting;
use Illuminate\Contracts\Container\Container;

class SolarForecastFactory
{
    public function __construct(
        private Container $app
    ) {
    }

    /**
     * Resolve the configured solar forecast provider and return its forecast data.
     * Used by the poller; returns null if disabled or provider fails.
     */
    public function getSolarForecast(int $hours = 24): ?array
    {
        $provider = $this->make();
        if ($provider === null) {
            return null;
        }

        return $provider->getSolarForecast($hours);
    }

    /**
     * Get the configured solar forecast service instance, or null if disabled/unconfigured.
     */
    public function make(): ?SolarForecastContract
    {
        $provider = Setting::getValue('solar_forecast.provider', 'open_meteo');
        $enabled = Setting::getValue('solar_forecast.enabled', false);

        if (!$enabled) {
            return null;
        }

        if ($provider === 'solcast') {
            $apiKey = Setting::getValue('solar_forecast.solcast_api_key', '');
            if ($apiKey === '') {
                return null;
            }
        }

        return match ($provider) {
            'open_meteo' => $this->app->make(OpenMeteoSolarService::class),
            'forecast_solar' => $this->app->make(ForecastSolarService::class),
            'open_quartz' => $this->app->make(OpenQuartzSolarService::class),
            'solcast' => $this->app->make(SolcastSolarService::class),
            default => $this->app->make(OpenMeteoSolarService::class),
        };
    }
}
