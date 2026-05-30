<?php

namespace App\Services\OpenData;

class BomProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'BOM';
    }

    public function getCountry(): string
    {
        return 'AU';
    }

    public function getDescription(): string
    {
        return 'Bureau of Meteorology - Open data for weather forecasts, radar, satellite imagery, and warnings covering Australia.';
    }

    public function getFeatures(): array
    {
        return ['radar', 'satellite', 'forecast', 'warnings'];
    }

    public function getSettingsKey(): string
    {
        return 'bom';
    }

    public function isImplemented(): bool
    {
        return false;
    }

    public function getApiUrl(): ?string
    {
        return 'http://www.bom.gov.au/';
    }

    public function getCoverageArea(): string
    {
        return 'Australia';
    }
}
