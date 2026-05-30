<?php

namespace App\Services\OpenData;

class KnmiProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'KNMI';
    }

    public function getCountry(): string
    {
        return 'NL';
    }

    public function getDescription(): string
    {
        return 'Royal Netherlands Meteorological Institute - Open data for weather, radar, satellite, and forecasts covering the Netherlands region.';
    }

    public function getFeatures(): array
    {
        return ['wms', 'radar_nowcast', 'solar_nowcast'];
    }

    public function getSettingsKey(): string
    {
        return 'knmi';
    }

    public function isImplemented(): bool
    {
        return true;
    }

    public function getApiUrl(): ?string
    {
        return 'https://dataplatform.knmi.nl/organization/knmi';
    }

    public function getCoverageArea(): string
    {
        return 'Netherlands';
    }
}
