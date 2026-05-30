<?php

namespace App\Services\Tide;

/**
 * Maritime Safety Queensland (MSQ) — coming soon.
 *
 * Official Australian tide data for Queensland coastal stations.
 * Not a REST API — data is typically file-based / ingestion pipeline.
 * Data: https://www.msq.qld.gov.au/tides/open-data
 */
class MsqSource extends AbstractTideSource
{
    public function getName(): string        { return 'Maritime Safety Queensland'; }
    public function getRegion(): string      { return 'AU'; }
    public function getSourceKey(): string   { return 'msq'; }
    public function isImplemented(): bool    { return false; }
    public function requiresApiKey(): bool   { return false; }
    public function getApiDocUrl(): ?string  { return 'https://www.msq.qld.gov.au/tides/open-data'; }
    public function getCoverageArea(): string { return 'Australia (Queensland)'; }
    public function isStationBased(): bool   { return true; }
    public function getStations(): array     { return []; }

    public function fetchTideData(string $stationCode = ''): array
    {
        throw new \RuntimeException('MSQ tide source is not yet implemented.');
    }
}
