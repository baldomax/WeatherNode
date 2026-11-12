<?php

namespace App\Console\Commands;

use App\Services\River\CfrToscanaStationCatalogService;
use App\Services\River\OsmRiverLinesService;
use App\Services\River\RiverProviderRegistry;
use Illuminate\Console\Command;

/**
 * Tuscany/CFR-Toscana-specific -- fork only, not proposed upstream.
 *
 * Pre-warms the OSM river-line cache (see OsmRiverLinesService) for
 * whatever CFR Toscana stations are currently selected in admin, so the
 * /water/rivers map never makes a visitor wait on a live Overpass query.
 * Safe/idempotent to re-run: if the current station selection's river set
 * is already cached, this does nothing but confirm it.
 *
 * Run this after changing the CFR Toscana station selection in admin.
 */
class SyncCfrToscanaRiverLines extends Command
{
    protected $signature = 'rivers:sync-cfr-toscana-river-lines';

    protected $description = 'Pre-fetch and cache OpenStreetMap river-line geometry for the currently selected CFR Toscana stations';

    public function handle(CfrToscanaStationCatalogService $catalog, OsmRiverLinesService $lines): int
    {
        $providerId = 'cfr_toscana';
        $defaults   = RiverProviderRegistry::defaultStations($providerId);
        $raw        = RiverProviderRegistry::getSetting($providerId, 'stations', $defaults);
        $selected   = is_string($raw) ? (json_decode($raw, true) ?? $defaults) : ($raw ?? $defaults);
        $selected   = array_values(array_filter((array) $selected, fn ($v) => is_string($v) && $v !== ''));
        if (empty($selected)) {
            $selected = $defaults;
        }

        $catalogData = $catalog->getRiverStations();

        $riverNames = [];
        foreach ($selected as $code) {
            $river = $catalogData[$code]['river'] ?? null;
            if ($river) {
                $riverNames[] = $river;
            }
        }
        $riverNames = array_values(array_unique($riverNames));

        if (empty($riverNames)) {
            $this->warn('No river names found for the selected CFR Toscana stations -- nothing to fetch.');
            return self::SUCCESS;
        }

        $this->info('Rivers: ' . implode(', ', $riverNames));
        $this->info('Fetching line geometry from OpenStreetMap (Overpass) -- cached runs return instantly...');

        $geojson = $lines->getRiverLines($riverNames);

        $this->info('Cached ' . count($geojson['features']) . ' line segments.');
        return self::SUCCESS;
    }
}
