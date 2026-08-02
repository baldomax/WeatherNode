<?php

namespace App\Services\OpenData;

class AemetProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'AEMET';
    }

    public function getCountry(): string
    {
        return 'ES';
    }

    public function getDescription(): string
    {
        return 'Agencia Estatal de Meteorología - OpenData API for daily and hourly weather forecasts, observations, and warnings in Spain.';
    }

    public function getFeatures(): array
    {
        return ['forecast', 'warnings', 'observations'];
    }

    public function getSettingsKey(): string
    {
        return 'aemet';
    }

    public function isImplemented(): bool
    {
        return true;
    }

    public function getApiUrl(): ?string
    {
        return 'https://opendata.aemet.es/';
    }

    public function getCoverageArea(): string
    {
        return 'Spain';
    }
}
