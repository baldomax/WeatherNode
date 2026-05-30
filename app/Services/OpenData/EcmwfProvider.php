<?php

namespace App\Services\OpenData;

class EcmwfProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'ECMWF';
    }

    public function getCountry(): string
    {
        return 'EU';
    }

    public function getDescription(): string
    {
        return 'European Centre for Medium-Range Weather Forecasts - Open data for global weather models, forecasts, and reanalysis data covering worldwide.';
    }

    public function getFeatures(): array
    {
        return ['models', 'forecast', 'reanalysis'];
    }

    public function getSettingsKey(): string
    {
        return 'ecmwf';
    }

    public function isImplemented(): bool
    {
        return false;
    }

    public function getApiUrl(): ?string
    {
        return 'https://www.ecmwf.int/en/forecasts';
    }

    public function getCoverageArea(): string
    {
        return 'Global';
    }
}
