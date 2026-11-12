<?php

namespace App\Console\Commands;

use App\Services\River\CfrToscanaStationCoordinatesService;
use Illuminate\Console\Command;

/**
 * Tuscany/CFR-Toscana-specific — fork only, not proposed upstream.
 */
class SyncCfrToscanaStationCoordinates extends Command
{
    protected $signature = 'rivers:sync-cfr-toscana-coordinates';

    protected $description = 'Fetch and cache CFR Toscana hydrometric station coordinates from the SIR Toscana WFS service';

    public function handle(CfrToscanaStationCoordinatesService $service): int
    {
        $this->info('Fetching station coordinates from SIR Toscana WFS…');

        $coordinates = $service->refresh();

        if (empty($coordinates)) {
            $this->error('No coordinates fetched — check connectivity to geo.sir.toscana.it or the WFS response format.');
            return self::FAILURE;
        }

        $this->info('Cached coordinates for ' . count($coordinates) . ' stations.');
        return self::SUCCESS;
    }
}
