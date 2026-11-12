<?php

namespace App\Http\Controllers;

use App\Services\River\CfrToscanaStationCoordinatesService;
use App\Services\River\OsmRiverLinesService;
use App\Models\Setting;
use App\Support\WaterSections;
use App\Services\TideService;
use App\Services\Tide\TideServiceFactory;
use App\Services\Wave\OpenMeteoWaveService;
use App\Services\River\RijkswaterstaatRiverService;
use App\Services\River\RiverProviderRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WaterController extends Controller
{
    // ── Shared helpers ──────────────────────────────────────────────────────

    /** Load wave + SST data from cache; live-fetch on miss. */
    private function loadWaveData(): ?array
    {
        $lat  = round((float) Setting::latitude(), 2);
        $lon  = round((float) Setting::longitude(), 2);
        $data = Cache::get("waves_{$lat}_{$lon}");

        if (!$data) {
            try {
                $data = \App\Services\Wave\WaveServiceFactory::make()->fetch();
                if ($data && !empty($data['wave_series'])) {
                    Cache::put("waves_{$lat}_{$lon}", $data, now()->addHours(2));
                } else {
                    $data = null;
                }
            } catch (\Exception $e) {
                Log::warning('WaterController wave fetch failed', ['error' => $e->getMessage()]);
                $data = null;
            }
        }

        return $data;
    }

    /** Load merged river data from all enabled providers; live-fetch on cache miss. */
    private function loadRiverData(): ?array
    {
        $merged = [];

        foreach (RiverProviderRegistry::active() as $providerId => $providerMeta) {
            $enabled = (bool) RiverProviderRegistry::getSetting($providerId, 'enabled', false);
            if (!$enabled) {
                continue;
            }

            $cacheKey    = RiverProviderRegistry::cacheKey($providerId);
            $providerData = Cache::get($cacheKey);

            if (!$providerData) {
                try {
                    $providerDefaults = RiverProviderRegistry::defaultStations($providerId);
                    $stationsRaw = RiverProviderRegistry::getSetting(
                        $providerId, 'stations', $providerDefaults
                    );
                    $selected = is_string($stationsRaw)
                        ? (json_decode($stationsRaw, true) ?? $providerDefaults)
                        : ($stationsRaw ?? $providerDefaults);
                    $selected = array_values(array_filter(
                        (array) $selected,
                        fn ($v) => is_string($v) && !is_numeric($v) && $v !== ''
                    ));
                    if (empty($selected)) {
                        $selected = $providerDefaults;
                    }

                    $catalogMeta = [];
                    if (isset($providerMeta['catalog_service'])) {
                        $catalogMeta = app($providerMeta['catalog_service'])->getRiverStations();
                    }

                    $customRaw   = RiverProviderRegistry::getSetting($providerId, 'custom_stations', '[]');
                    $customList  = is_string($customRaw) ? (json_decode($customRaw, true) ?? []) : [];
                    $customMeta  = [];
                    $customCodes = [];
                    foreach ($customList as $entry) {
                        $code = $entry['code'] ?? null;
                        if (!$code) {
                            continue;
                        }
                        $customMeta[$code] = ['name' => $entry['name'] ?? $code, 'river' => $entry['river'] ?? '—'];
                        $customCodes[]     = $code;
                    }

                    $allCodes      = array_unique(array_merge($selected, $customCodes));
                    $extraMeta     = array_merge($catalogMeta, $customMeta);
                    $service       = app($providerMeta['service']);
                    $providerData  = $service->fetch($allCodes, $extraMeta);

                    if (!empty($providerData)) {
                        Cache::put($cacheKey, $providerData, now()->addMinutes(30));
                    } else {
                        $providerData = null;
                    }
                } catch (\Exception $e) {
                    Log::warning("WaterController river fetch failed [{$providerId}]", ['error' => $e->getMessage()]);
                    $providerData = null;
                }
            }

            if (!empty($providerData)) {
                $merged = array_merge($merged, $providerData);
            }
        }

        return empty($merged) ? null : $merged;
    }

    /**
     * A switched-off section sends visitors to the first one that is on, or
     * 404s when the whole Water section is off.
     */
    private function elsewhere(): \Illuminate\Http\RedirectResponse
    {
        $first = WaterSections::firstRoute();
        abort_if($first === null, 404);

        return redirect()->route($first);
    }

    // ── Tab controllers ─────────────────────────────────────────────────────

    /** GET /water — Tides (default tab) */
    public function tides()
    {
        if (!WaterSections::isEnabled('tides')) {
            return $this->elsewhere();
        }

        $enabled = true;
        $source  = Setting::getValue('tide.source', TideServiceFactory::DEFAULT_SOURCE);

        $stationCode = Setting::getValue("tide.{$source}_station_code",
                       Setting::getValue('tide.station_code', ''));
        $stationName = Setting::getValue('tide.station_name', '');

        $driver       = TideServiceFactory::make($source);
        $sourceLabel  = $driver->getName();
        $sourceDocUrl = $driver->getApiDocUrl();

        $tideData = null;
        if ($enabled) {
            $cacheKey = 'tide_' . $source . '_' . $stationCode;
            $tideData = Cache::get($cacheKey);
            if (!$tideData) {
                try {
                    $tideData = (new TideService())->fetchTideData($stationCode);
                    if ($tideData && !empty($tideData['series'])) {
                        Cache::put($cacheKey, $tideData, now()->addHours(2));
                    } else {
                        $tideData = null;
                    }
                } catch (\Exception $e) {
                    Log::warning('WaterController tide fetch failed', ['error' => $e->getMessage()]);
                }
            }
        }

        return view('weather.tide', [
            'activeTab'     => 'tides',
            'waterTabs'     => WaterSections::enabled(),
            // Tide
            'tideEnabled'   => $enabled,
            'tideData'      => $tideData,
            'stationCode'   => $stationCode,
            'stationName'   => $stationName,
            'stations'      => TideService::STATIONS,
            'source'        => $source,
            'sourceLabel'   => $sourceLabel,
            'sourceDocUrl'  => $sourceDocUrl,
            // Unused on this tab (nulls keep the view @php safe)
            'wavesEnabled'  => false,
            'waveData'      => null,
            'riverData'     => null,
        ]);
    }

    /** GET /water/waves — Waves */
    public function waves()
    {
        if (!WaterSections::isEnabled('waves')) {
            return $this->elsewhere();
        }

        return view('weather.tide', [
            'activeTab'     => 'waves',
            'waterTabs'     => WaterSections::enabled(),
            // Wave
            'wavesEnabled'  => true,
            'waveData'      => $this->loadWaveData(),
            // Unused on this tab
            'tideEnabled'   => false,
            'tideData'      => null,
            'stationCode'   => null,
            'stationName'   => null,
            'stations'      => [],
            'source'        => '',
            'sourceLabel'   => '',
            'sourceDocUrl'  => null,
            'riverData'     => null,
        ]);
    }

    /** GET /water/temp — Sea Temperature */
    public function temperature()
    {
        if (!WaterSections::isEnabled('temp')) {
            return $this->elsewhere();
        }

        return view('weather.tide', [
            'activeTab'     => 'temp',
            'waterTabs'     => WaterSections::enabled(),
            // SST data comes from wave service
            'wavesEnabled'  => true,
            'waveData'      => $this->loadWaveData(),
            // Unused on this tab
            'tideEnabled'   => false,
            'tideData'      => null,
            'stationCode'   => null,
            'stationName'   => null,
            'stations'      => [],
            'source'        => '',
            'sourceLabel'   => '',
            'sourceDocUrl'  => null,
            'riverData'     => null,
        ]);
    }

    /** GET /water/rivers — River Levels */
    public function rivers()
    {
        if (!WaterSections::isEnabled('rivers')) {
            return $this->elsewhere();
        }

        $riverData        = $this->loadRiverData();
        $riverMapStations = $this->buildRiverMapStations($riverData);

        return view('weather.tide', [
            'activeTab'         => 'rivers',
            'waterTabs'         => WaterSections::enabled(),
            // River
            'riverData'         => $riverData,
            'riverMapStations'  => $riverMapStations,
            'riverLinesGeoJson' => $this->buildRiverLinesGeoJson($riverMapStations),
            // Unused on this tab
            'tideEnabled'       => false,
            'tideData'          => null,
            'stationCode'       => null,
            'stationName'       => null,
            'stations'          => [],
            'source'            => '',
            'sourceLabel'       => '',
            'sourceDocUrl'      => null,
            'wavesEnabled'      => false,
            'waveData'          => null,
        ]);
    }

    /**
     * Join the live river data with CFR Toscana's official geographic
     * coordinates (from SIR Toscana's WFS service) to build the marker
     * list for the /water/rivers hydrometric map. Stations with no match
     * in the coordinates cache (RWS, KAUR -- outside Tuscany) are silently
     * skipped; only CFR Toscana stations have coordinates.
     *
     * Tuscany/CFR-Toscana-specific -- fork only, not proposed upstream.
     */
    private function buildRiverMapStations(?array $riverData): array
    {
        if (empty($riverData)) {
            return [];
        }

        try {
            $coordinates = app(CfrToscanaStationCoordinatesService::class)->getCoordinates();
        } catch (\Exception $e) {
            Log::warning('WaterController: failed to load CFR Toscana station coordinates', ['error' => $e->getMessage()]);
            return [];
        }

        $stations = [];
        foreach ($riverData as $code => $station) {
            $coord = $coordinates[$code] ?? null;
            if (!$coord) {
                continue;
            }

            $stations[] = [
                'code'             => $code,
                'lat'              => $coord['lat'],
                'lon'              => $coord['lon'],
                'name'             => $station['name'] ?? $coord['nome'],
                'river'            => $station['river'] ?? '--',
                'status'           => $station['status'] ?? 'normal',
                'level_cm'         => $station['level_cm'] ?? null,
                'threshold_1_m'    => $station['meta']['threshold_1_m'] ?? null,
                'threshold_2_m'    => $station['meta']['threshold_2_m'] ?? null,
                'zero_idrometrico' => $coord['zero_idrometrico'] ?? null,
                'updated_at'       => $station['updated_at'] ?? null,
            ];
        }

        return $stations;
    }

    /**
     * Fetch (and cache, see OsmRiverLinesService) the actual river-course
     * geometry from OpenStreetMap for every distinct river name among the
     * CFR Toscana map stations, so the hydrometric map can draw the river
     * lines themselves, not just the station markers.
     *
     * Tuscany/CFR-Toscana-specific -- fork only, not proposed upstream.
     */
    private function buildRiverLinesGeoJson(array $riverMapStations): array
    {
        if (empty($riverMapStations)) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        $riverNames = array_values(array_unique(array_column($riverMapStations, 'river')));

        try {
            return app(OsmRiverLinesService::class)->getRiverLines($riverNames);
        } catch (\Exception $e) {
            Log::warning('WaterController: failed to load OSM river lines', ['error' => $e->getMessage()]);
            return ['type' => 'FeatureCollection', 'features' => []];
        }
    }
}
