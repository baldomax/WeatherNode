<?php

namespace App\Services;

use App\Services\Tide\RijkswaterstaatSource;
use App\Services\Tide\TideServiceFactory;

/**
 * TideService — backward-compatible façade over TideServiceFactory.
 *
 * New code should use TideServiceFactory::make() directly.
 * This class is kept so existing references (controller, poller) continue
 * to work without changes and can be migrated gradually.
 */
class TideService
{
    /**
     * Legacy constant — RWS station list (kept for blade templates).
     * @deprecated Use TideServiceFactory::make()->getStations()
     */
    public const STATIONS = RijkswaterstaatSource::STATIONS;

    /**
     * Legacy constant — default RWS station code.
     * @deprecated Use RijkswaterstaatSource::DEFAULT_STATION
     */
    public const DEFAULT_STATION = RijkswaterstaatSource::DEFAULT_STATION;

    /**
     * Fetch tide data using the currently configured source.
     *
     * @throws \RuntimeException on API failure or no data
     */
    public function fetchTideData(string $stationCode = self::DEFAULT_STATION): array
    {
        return TideServiceFactory::make()->fetchTideData($stationCode);
    }
}
