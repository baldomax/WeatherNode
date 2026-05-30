<?php

namespace App\Services\OpenData;

class MetOfficeProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'Met Office';
    }

    public function getCountry(): string
    {
        return 'GB';
    }

    public function getDescription(): string
    {
        return 'UK Met Office - Open data for weather forecasts, radar, satellite imagery, and warnings covering the United Kingdom.';
    }

    public function getFeatures(): array
    {
        return ['wms', 'radar', 'forecast', 'warnings'];
    }

    public function getSettingsKey(): string
    {
        return 'metoffice';
    }

    public function isImplemented(): bool
    {
        return false;
    }

    public function getApiUrl(): ?string
    {
        return 'https://www.metoffice.gov.uk/services/data';
    }

    public function getCoverageArea(): string
    {
        return 'United Kingdom';
    }
}
