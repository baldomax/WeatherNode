<?php

namespace App\Services\OpenData;

class NoaaProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'NOAA';
    }

    public function getCountry(): string
    {
        return 'US';
    }

    public function getDescription(): string
    {
        return 'National Oceanic and Atmospheric Administration - Open data for radar and severe weather products covering the United States. (Dashboard radar future frames is implemented; additional NOAA integrations can be added incrementally.)';
    }

    public function getFeatures(): array
    {
        return ['satellite', 'radar', 'alerts', 'forecast'];
    }

    public function getSettingsKey(): string
    {
        return 'noaa';
    }

    public function isImplemented(): bool
    {
        return true;
    }

    public function getApiUrl(): ?string
    {
        return 'https://www.noaa.gov/weather';
    }

    public function getCoverageArea(): string
    {
        return 'United States';
    }
}
