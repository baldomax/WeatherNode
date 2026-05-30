<?php

namespace App\Services\Tide;

/**
 * NTU TPXO Tide API (Asia / Pacific) — coming soon.
 *
 * Based on the TPXO9 global ocean tidal model. Fully open, covers Asia and Pacific.
 * GitHub: https://github.com/cywhale/tide
 * API: https://eco.odb.ntu.edu.tw/api/tide
 */
class NtuTpxoSource extends AbstractTideSource
{
    public function getName(): string        { return 'NTU TPXO'; }
    public function getRegion(): string      { return 'ASIA'; }
    public function getSourceKey(): string   { return 'ntu_tpxo'; }
    public function isImplemented(): bool    { return false; }
    public function requiresApiKey(): bool   { return false; }
    public function getApiDocUrl(): ?string  { return 'https://eco.odb.ntu.edu.tw/api/tide'; }
    public function getCoverageArea(): string { return 'Asia & Pacific (TPXO9 model)'; }
    public function isStationBased(): bool   { return false; }
    public function getStations(): array     { return []; }

    public function fetchTideData(string $stationCode = ''): array
    {
        throw new \RuntimeException('NTU TPXO tide source is not yet implemented.');
    }
}
