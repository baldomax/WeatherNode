<?php

namespace App\Services\Tide;

/**
 * Environment Agency (UK) tide source — coming soon.
 *
 * Real-time tidal gauge data for England and Wales. No authentication required.
 * API docs: https://environment.data.gov.uk/flood-monitoring/doc/reference
 */
class EnvironmentAgencySource extends AbstractTideSource
{
    public function getName(): string        { return 'Environment Agency'; }
    public function getRegion(): string      { return 'GB'; }
    public function getSourceKey(): string   { return 'ea'; }
    public function isImplemented(): bool    { return false; }
    public function requiresApiKey(): bool   { return false; }
    public function getApiDocUrl(): ?string  { return 'https://environment.data.gov.uk/flood-monitoring/doc/reference'; }
    public function getCoverageArea(): string { return 'England & Wales'; }
    public function isStationBased(): bool   { return true; }
    public function getStations(): array     { return []; }

    public function fetchTideData(string $stationCode = ''): array
    {
        throw new \RuntimeException('Environment Agency tide source is not yet implemented.');
    }
}
