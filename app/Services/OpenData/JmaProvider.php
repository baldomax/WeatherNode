<?php

namespace App\Services\OpenData;

class JmaProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'JMA';
    }

    public function getCountry(): string
    {
        return 'JP';
    }

    public function getDescription(): string
    {
        return 'Japan Meteorological Agency - Open data for weather forecasts, satellite imagery, radar, typhoon tracking, and warnings covering Japan.';
    }

    public function getFeatures(): array
    {
        return ['satellite', 'radar', 'typhoon', 'forecast', 'warnings'];
    }

    public function getSettingsKey(): string
    {
        return 'jma';
    }

    public function isImplemented(): bool
    {
        return false;
    }

    public function getApiUrl(): ?string
    {
        return 'https://www.jma.go.jp/jma/indexe.html';
    }

    public function getCoverageArea(): string
    {
        return 'Japan';
    }
}
