<?php

namespace App\Services\River;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Rijkswaterstaat WaterWebservices — inland river gauge service.
 *
 * Uses the same RWS SOAP/JSON API as the coastal tide source but fetches
 * real-time gauge readings for configurable inland river stations.
 * No forecast data (river levels are observed, not predicted).
 * No API key required.
 *
 * API docs: https://waterinfo.rws.nl
 */
class RijkswaterstaatRiverService
{
    private const API_BASE             = 'https://ddapi20-waterwebservices.rijkswaterstaat.nl';
    private const ENDPOINT_OBSERVATIONS = '/ONLINEWAARNEMINGENSERVICES/OphalenWaarnemingen';

    /**
     * Preset stations sourced from the RWS locationslug catalog.
     * Codes use the same dot-notation format as the RWS WaterWebservices API.
     * All confirmed codes are from the live catalog; live data availability
     * depends on the station being actively measured at query time.
     */
    public const STATIONS = [
        // ── Rijn (Rhine) — entry point into NL ───────────────────────────────
        'lobith.bovenrijn.tolkamer'         => ['name' => 'Lobith',           'river' => 'Rijn'],
        'tolkamer'                          => ['name' => 'Tolkamer',         'river' => 'Rijn'],
        'pannerden.regelwerk.beneden'       => ['name' => 'Pannerden',        'river' => 'Rijn'],

        // ── Waal — largest Rhine branch ───────────────────────────────────────
        'nijmegen.waal'                     => ['name' => 'Nijmegen',         'river' => 'Waal'],
        'tiel.waal'                         => ['name' => 'Tiel',             'river' => 'Waal'],
        'zaltbommel'                        => ['name' => 'Zaltbommel',       'river' => 'Waal'],
        'herwijnen'                         => ['name' => 'Herwijnen',        'river' => 'Waal'],
        'andel.waal'                        => ['name' => 'Andel',            'river' => 'Waal'],
        'gorinchem'                         => ['name' => 'Gorinchem',        'river' => 'Waal'],

        // ── Neder-Rijn ────────────────────────────────────────────────────────
        'arnhem.nederrijn'                  => ['name' => 'Arnhem',           'river' => 'Neder-Rijn'],
        'dodewaard'                         => ['name' => 'Dodewaard',        'river' => 'Neder-Rijn'],
        'driel.beneden'                     => ['name' => 'Driel',            'river' => 'Neder-Rijn'],

        // ── Lek ───────────────────────────────────────────────────────────────
        'culemborg'                         => ['name' => 'Culemborg',        'river' => 'Lek'],
        'hagestein.beneden'                 => ['name' => 'Hagestein',        'river' => 'Lek'],

        // ── Benedenmerwede / delta ────────────────────────────────────────────
        'hardinxveld'                       => ['name' => 'Hardinxveld',      'river' => 'Benedenmerwede'],
        'sliedrecht'                        => ['name' => 'Sliedrecht',       'river' => 'Benedenmerwede'],
        'papendrecht'                       => ['name' => 'Papendrecht',      'river' => 'Benedenmerwede'],
        'dordrecht.oudemaas.benedenmerwede' => ['name' => 'Dordrecht',        'river' => 'Benedenmerwede'],

        // ── Nieuwe Maas ───────────────────────────────────────────────────────
        'rotterdam.brienenoordbrug'         => ['name' => 'Rotterdam',        'river' => 'Nieuwe Maas'],

        // ── Maas — Limburg to Noord-Brabant ──────────────────────────────────
        'eijsden.grens'                     => ['name' => 'Eijsden',          'river' => 'Maas'],
        'maastricht.borgharen.maas.beneden' => ['name' => 'Maastricht',       'river' => 'Maas'],
        'elsloo.maas'                       => ['name' => 'Elsloo',           'river' => 'Maas'],
        'roermond.maas'                     => ['name' => 'Roermond',         'river' => 'Maas'],
        'venlo.maas'                        => ['name' => 'Venlo',            'river' => 'Maas'],
        'heel.maas'                         => ['name' => 'Heel',             'river' => 'Maas'],
        'gennep'                            => ['name' => 'Gennep',           'river' => 'Maas'],
        'grave.beneden'                     => ['name' => 'Grave',            'river' => 'Maas'],
        'lith'                              => ['name' => 'Lith',             'river' => 'Maas'],
        'andel.maas'                        => ['name' => 'Andel',            'river' => 'Maas'],

        // ── IJssel — northeast branch ─────────────────────────────────────────
        'dieren.ijssel'                     => ['name' => 'Dieren',           'river' => 'IJssel'],
        'doesburg.ijssel'                   => ['name' => 'Doesburg',         'river' => 'IJssel'],
        'deventer'                          => ['name' => 'Deventer',         'river' => 'IJssel'],
        'zwolle'                            => ['name' => 'Zwolle',           'river' => 'IJssel'],
        'kampen.ijssel'                     => ['name' => 'Kampen',           'river' => 'IJssel'],
        'genemuiden'                        => ['name' => 'Genemuiden',       'river' => 'IJssel'],

        // ── Hollandsche IJssel ────────────────────────────────────────────────
        'gouda.hollandscheijssel'           => ['name' => 'Gouda',            'river' => 'Hollandsche IJssel'],

        // ── IJ / Amsterdam ────────────────────────────────────────────────────
        'amsterdam.schellingwouderbrug'     => ['name' => 'Amsterdam',        'river' => 'IJ'],
    ];

    public const DEFAULT_STATIONS = ['lobith.bovenrijn.tolkamer', 'nijmegen.waal'];

    /**
     * Fetch current gauge readings for the given station codes.
     *
     * @param  string[]  $stationCodes  Keys from STATIONS (or custom codes)
     * @param  array     $extraMeta     Metadata for codes not in STATIONS:
     *                                 ['custom-code' => ['name' => '...', 'river' => '...']]
     * @return array<string, array>    Keyed by station code
     */
    public function fetch(array $stationCodes, array $extraMeta = []): array
    {
        $results = [];
        $allMeta = array_merge(self::STATIONS, $extraMeta);

        foreach ($stationCodes as $code) {
            if (!isset($allMeta[$code])) {
                continue;
            }

            try {
                $stationMeta = $allMeta[$code];
                $series      = $this->fetchStationSeries($code);
                $level       = $this->currentLevel($series);

                if (empty($series)) {
                    Log::debug("RWS River: no observations for station [{$code}] — RWS may not publish WATHTE/OW at this location.");
                }

                $results[$code] = [
                    'name'         => $stationMeta['name'],
                    'river'        => $stationMeta['river'],
                    'station_code' => $code,
                    'level_cm'     => $level,
                    'trend'        => $this->determineTrend($series),
                    'status'       => $this->determineStatus($series),
                    'series'       => $series,
                    'updated_at'   => now()->toIso8601String(),
                ];
            } catch (\Exception $e) {
                Log::warning("RWS River: failed to fetch station {$code}", [
                    'error' => $e->getMessage(),
                ]);

                // Return null-state entry so the view can show "unavailable"
                $stationMeta    = $allMeta[$code];
                $results[$code] = [
                    'name'         => $stationMeta['name'],
                    'river'        => $stationMeta['river'],
                    'station_code' => $code,
                    'level_cm'     => null,
                    'trend'        => 'steady',
                    'status'       => 'normal',
                    'series'       => [],
                    'updated_at'   => now()->toIso8601String(),
                ];
            }
        }

        return $results;
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    private function fetchStationSeries(string $stationCode): array
    {
        $now  = now();
        $from = $now->copy()->subHours(6);

        $body = [
            'AquoPlusWaarnemingMetadata' => [
                'AquoMetadata' => [
                    'Compartiment' => ['Code' => 'OW'],
                    'Eenheid'      => ['Code' => 'cm'],
                    'Grootheid'    => ['Code' => 'WATHTE'],
                ],
            ],
            'Locatie' => ['Code' => $stationCode],
            'Periode' => [
                'Begindatumtijd' => $from->format('Y-m-d\\TH:i:s.000P'),
                'Einddatumtijd'  => $now->format('Y-m-d\\TH:i:s.000P'),
            ],
        ];

        $response = Http::timeout(15)
            ->post(self::API_BASE . self::ENDPOINT_OBSERVATIONS, $body);

        if ($response->status() === 204) {
            return [];
        }

        if (!$response->successful()) {
            throw new \RuntimeException(
                "RWS River API HTTP {$response->status()} for station {$stationCode}"
            );
        }

        $data = $response->json();

        if (empty($data['Succesvol']) || empty($data['WaarnemingenLijst'])) {
            return [];
        }

        // Pick the entry with the most readings
        $bestList = [];
        foreach ($data['WaarnemingenLijst'] as $entry) {
            $metingen = $entry['MetingenLijst'] ?? [];
            if (count($metingen) > count($bestList)) {
                $bestList = $metingen;
            }
        }

        $points = [];
        foreach ($bestList as $item) {
            $val = $item['Meetwaarde']['Waarde_Numeriek'] ?? null;
            if ($val === null) {
                continue;
            }
            $ts       = Carbon::parse($item['Tijdstip']);
            $points[] = [
                'timestamp'      => $ts->toIso8601String(),
                'timestamp_unix' => $ts->timestamp * 1000,
                'value'          => round((float) $val, 1),
            ];
        }

        usort($points, fn ($a, $b) => $a['timestamp_unix'] <=> $b['timestamp_unix']);

        return $points;
    }

    private function currentLevel(array $series): ?float
    {
        if (empty($series)) {
            return null;
        }

        return end($series)['value'];
    }

    private function determineTrend(array $series): string
    {
        if (count($series) < 4) {
            return 'steady';
        }

        $recent = array_slice($series, -4);
        $first  = $recent[0]['value'];
        $last   = end($recent)['value'];
        $diff   = $last - $first;

        if ($diff > 5) {
            return 'rising';
        }

        if ($diff < -5) {
            return 'falling';
        }

        return 'steady';
    }

    private function determineStatus(array $series): string
    {
        if (count($series) < 4) {
            return 'normal';
        }

        $recent = array_slice($series, -4);
        $diff   = end($recent)['value'] - $recent[0]['value'];

        if ($diff > 30) return 'warning';
        if ($diff > 5)  return 'watch';

        return 'normal';
    }
}
