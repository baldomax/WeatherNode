<?php

namespace App\Services\Tide;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class TideServiceFactory
{
    /**
     * All registered tide source drivers, in display order.
     * Keys are the setting values stored in tide.source.
     */
    public const SOURCES = [
        // ── Implemented ──────────────────────────────────────────────────────
        'rws'        => RijkswaterstaatSource::class,
        'open_meteo' => OpenMeteoMarineSource::class,
        'marea'      => MareaSource::class,

        // ── Placeholders (coming soon) ────────────────────────────────────────
        'ea'         => EnvironmentAgencySource::class,
        'noaa'       => NoaaSource::class,
        'bsh'        => BshSource::class,
        'shom'       => ShomSource::class,
        'copernicus' => CopernicusMarineSource::class,
        'niwa'       => NiwaSource::class,
        'msq'        => MsqSource::class,
        'ntu_tpxo'   => NtuTpxoSource::class,
    ];

    /**
     * Instantiate the configured (or specified) tide source driver.
     * Falls back to Rijkswaterstaat if the configured source is unknown or fails.
     */
    public static function make(?string $source = null): TideSourceInterface
    {
        $source = $source ?? Setting::getValue('tide.source', 'rws');
        $class  = self::SOURCES[$source] ?? RijkswaterstaatSource::class;

        try {
            return app($class);
        } catch (\Exception $e) {
            Log::error('Failed to instantiate tide source', [
                'source' => $source,
                'class'  => $class,
                'error'  => $e->getMessage(),
            ]);
            return app(RijkswaterstaatSource::class);
        }
    }

    /**
     * Return metadata for all registered sources (for admin UI display).
     */
    public static function all(): array
    {
        $sources = [];
        foreach (self::SOURCES as $key => $class) {
            /** @var TideSourceInterface $instance */
            $instance  = app($class);
            $sources[$key] = [
                'key'           => $key,
                'name'          => $instance->getName(),
                'region'        => $instance->getRegion(),
                'coverage_area' => $instance->getCoverageArea(),
                'implemented'   => $instance->isImplemented(),
                'requires_key'  => $instance->requiresApiKey(),
                'station_based' => $instance->isStationBased(),
                'api_doc_url'   => $instance->getApiDocUrl(),
            ];
        }
        return $sources;
    }
}
