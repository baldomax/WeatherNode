<?php

namespace App\Services\Tide;

interface TideSourceInterface
{
    /** Human-readable source name, e.g. "Rijkswaterstaat" */
    public function getName(): string;

    /** ISO 3166-1 alpha-2 country code or "GLOBAL", e.g. "NL", "US" */
    public function getRegion(): string;

    /** Key used in settings, e.g. "rws", "open_meteo", "noaa" */
    public function getSourceKey(): string;

    /** Whether this source is fully implemented (false = coming soon placeholder) */
    public function isImplemented(): bool;

    /** Whether an API key is required to use this source */
    public function requiresApiKey(): bool;

    /** Link to the API documentation */
    public function getApiDocUrl(): ?string;

    /** Human-readable coverage area description */
    public function getCoverageArea(): string;

    /**
     * Whether stations are predefined (true) or coordinate-based (false).
     * Station-based sources (RWS, EA, NOAA) require a station code.
     * Coordinate-based sources (Open-Meteo, Marea) use the site lat/lon.
     */
    public function isStationBased(): bool;

    /**
     * Return the list of predefined stations.
     * Format: ['station_code' => ['name' => 'Display Name'], ...]
     * Returns an empty array for coordinate-based sources.
     */
    public function getStations(): array;

    /**
     * Fetch tide data.
     *
     * For station-based sources pass the station code.
     * For coordinate-based sources the argument is ignored and the site
     * lat/lon from Settings is used automatically.
     *
     * Returns a standard array:
     *   station           – human-readable station / location name
     *   station_code      – station code or "lat,lon"
     *   current_level_cm  – most recent water level in cm (relative to local datum)
     *   current_timestamp – ISO-8601 timestamp of the current reading
     *   trend             – 'rising' | 'falling' | 'steady'
     *   tides             – array of {timestamp, timestamp_unix, type:'high'|'low', level_cm}
     *   series            – full time series [{timestamp, timestamp_unix, value}]
     *   source            – machine-readable source key
     *   updated_at        – ISO-8601 fetch time
     *
     * @throws \RuntimeException on API failure, empty response, or not implemented
     */
    public function fetchTideData(string $stationCode = ''): array;
}
