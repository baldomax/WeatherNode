<?php

namespace App\Services\OpenData;

class MeteoFranceProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'Météo-France';
    }

    public function getCountry(): string
    {
        return 'FR';
    }

    public function getDescription(): string
    {
        return 'Météo-France - Open data for weather forecasts, satellite imagery, radar, and warnings covering France and overseas territories.';
    }

    public function getFeatures(): array
    {
        return ['satellite', 'radar', 'forecast', 'warnings'];
    }

    public function getSettingsKey(): string
    {
        return 'meteofrance';
    }

    public function isImplemented(): bool
    {
        return false;
    }

    public function getApiUrl(): ?string
    {
        return 'https://donneespubliques.meteofrance.fr/';
    }

    public function getCoverageArea(): string
    {
        return 'France';
    }
}
