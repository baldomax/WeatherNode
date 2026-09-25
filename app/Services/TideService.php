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
     * A gauge network cannot be asked about "the usual station". Without one
     * the drivers fall back to their own first entry, which is how a fresh
     * install ended up publishing the tide at IJmuiden, so stop here instead.
     *
     * @throws \RuntimeException on API failure or no data
     */
    public function fetchTideData(string $stationCode = ''): array
    {
        $driver = TideServiceFactory::make();

        if ($driver->isStationBased() && trim($stationCode) === '') {
            throw new \RuntimeException('No tide station is configured for ' . $driver->getName());
        }

        return $driver->fetchTideData($stationCode);
    }
}
