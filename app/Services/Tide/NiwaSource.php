<?php

namespace App\Services\Tide;

/**
 * NIWA Tide API (New Zealand) — coming soon.
 *
 * Best New Zealand tide source. Provides historical and future predictions.
 * Free API key required via developer portal.
 * Docs: https://developer.niwa.co.nz/docs/tide-api/latest/overview
 */
class NiwaSource extends AbstractTideSource
{
    public function getName(): string        { return 'NIWA'; }
    public function getRegion(): string      { return 'NZ'; }
    public function getSourceKey(): string   { return 'niwa'; }
    public function isImplemented(): bool    { return false; }
    public function requiresApiKey(): bool   { return true; }
    public function getApiDocUrl(): ?string  { return 'https://developer.niwa.co.nz/docs/tide-api/latest/overview'; }
    public function getCoverageArea(): string { return 'New Zealand'; }
    public function isStationBased(): bool   { return true; }
    public function getStations(): array     { return []; }

    public function fetchTideData(string $stationCode = ''): array
    {
        throw new \RuntimeException('NIWA tide source is not yet implemented.');
    }
}
