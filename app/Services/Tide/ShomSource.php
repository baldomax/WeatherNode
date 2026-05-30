<?php

namespace App\Services\Tide;

/**
 * SHOM (Service Hydrographique et Océanographique de la Marine) — coming soon.
 *
 * Official French maritime authority. Registration required.
 * API docs: https://services.data.shom.fr/support/en/services/spm
 */
class ShomSource extends AbstractTideSource
{
    public function getName(): string        { return 'SHOM'; }
    public function getRegion(): string      { return 'FR'; }
    public function getSourceKey(): string   { return 'shom'; }
    public function isImplemented(): bool    { return false; }
    public function requiresApiKey(): bool   { return true; }
    public function getApiDocUrl(): ?string  { return 'https://services.data.shom.fr/support/en/services/spm'; }
    public function getCoverageArea(): string { return 'France (including overseas territories)'; }
    public function isStationBased(): bool   { return true; }
    public function getStations(): array     { return []; }

    public function fetchTideData(string $stationCode = ''): array
    {
        throw new \RuntimeException('SHOM tide source is not yet implemented.');
    }
}
