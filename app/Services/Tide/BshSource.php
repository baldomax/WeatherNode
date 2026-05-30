<?php

namespace App\Services\Tide;

/**
 * BSH (Bundesamt für Seeschifffahrt und Hydrographie) — coming soon.
 *
 * Official German maritime authority tide predictions.
 * Data: https://www.bsh.de/EN/DATA/Predictions/Tides
 */
class BshSource extends AbstractTideSource
{
    public function getName(): string        { return 'BSH'; }
    public function getRegion(): string      { return 'DE'; }
    public function getSourceKey(): string   { return 'bsh'; }
    public function isImplemented(): bool    { return false; }
    public function requiresApiKey(): bool   { return false; }
    public function getApiDocUrl(): ?string  { return 'https://www.bsh.de/EN/DATA/Predictions/Tides'; }
    public function getCoverageArea(): string { return 'Germany (North Sea & Baltic)'; }
    public function isStationBased(): bool   { return true; }
    public function getStations(): array     { return []; }

    public function fetchTideData(string $stationCode = ''): array
    {
        throw new \RuntimeException('BSH tide source is not yet implemented.');
    }
}
