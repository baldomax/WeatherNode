<?php

namespace App\Services\Tide;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class RijkswaterstaatSource extends AbstractTideSource
{
    private const API_BASE            = 'https://ddapi20-waterwebservices.rijkswaterstaat.nl';
    private const ENDPOINT_OBSERVATIONS = '/ONLINEWAARNEMINGENSERVICES/OphalenWaarnemingen';

    public const STATIONS = [
        'ijmuiden.buitenhaven' => ['name' => 'IJmuiden'],
        'hoekvanholland'       => ['name' => 'Hoek van Holland'],
        'vlissingen'           => ['name' => 'Vlissingen'],
        'denhelder.marsdiep'   => ['name' => 'Den Helder'],
        'harlingen.waddenzee'  => ['name' => 'Harlingen'],
        'scheveningen'         => ['name' => 'Scheveningen'],
    ];

    public const DEFAULT_STATION = 'ijmuiden.buitenhaven';

    // ── Interface metadata ────────────────────────────────────────────────────

    public function getName(): string        { return 'Rijkswaterstaat'; }
    public function getRegion(): string      { return 'NL'; }
    public function getSourceKey(): string   { return 'rws'; }
    public function isImplemented(): bool    { return true; }
    public function requiresApiKey(): bool   { return false; }
    public function getApiDocUrl(): ?string  { return 'https://waterinfo.rws.nl'; }
    public function getCoverageArea(): string { return 'Netherlands'; }
    public function isStationBased(): bool   { return true; }
    public function getStations(): array     { return self::STATIONS; }

    // ── Main fetch ────────────────────────────────────────────────────────────

    public function fetchTideData(string $stationCode = self::DEFAULT_STATION): array
    {
        $stationCode = $this->resolveStationCode($stationCode ?: self::DEFAULT_STATION);
        $stationName = self::STATIONS[$stationCode]['name'] ?? $stationCode;
        $now         = now();

        $measurements = $this->fetchRange($stationCode, $now->copy()->subHours(12), $now, false);
        $forecast     = $this->fetchRange($stationCode, $now, $now->copy()->addHours(72), true);

        $series = $this->mergeAndSort(array_merge($measurements, $forecast));

        if (empty($series)) {
            throw new \RuntimeException(
                "RWS API returned no tide data for station {$stationCode}"
            );
        }

        $nowMs        = $now->timestamp * 1000;
        $currentLevel = null;
        $currentTs    = null;

        foreach ($series as $point) {
            if ($point['timestamp_unix'] <= $nowMs) {
                $currentLevel = $point['value'];
                $currentTs    = $point['timestamp'];
            }
        }

        return [
            'station'           => $stationName,
            'station_code'      => $stationCode,
            'current_level_cm'  => $currentLevel,
            'current_timestamp' => $currentTs,
            'trend'             => $this->determineTrend($series, $nowMs),
            'tides'             => $this->detectTideEvents($series, $nowMs),
            'series'            => $series,
            'source'            => 'rijkswaterstaat',
            'updated_at'        => $now->toIso8601String(),
        ];
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function aquoMetadata(bool $isForecast = false): array
    {
        $meta = [
            'Compartiment' => ['Code' => 'OW'],
            'Eenheid'      => ['Code' => 'cm'],
            'Grootheid'    => ['Code' => 'WATHTE'],
        ];

        if ($isForecast) {
            $meta['ProcesType'] = 'verwachting';
        }

        return $meta;
    }

    private function fetchRange(
        string $stationCode,
        Carbon $from,
        Carbon $to,
        bool   $isForecast
    ): array {
        $body = [
            'AquoPlusWaarnemingMetadata' => [
                'AquoMetadata' => $this->aquoMetadata($isForecast),
            ],
            'Locatie' => ['Code' => $stationCode],
            'Periode' => [
                'Begindatumtijd' => $from->format('Y-m-d\\TH:i:s.000P'),
                'Einddatumtijd'  => $to->format('Y-m-d\\TH:i:s.000P'),
            ],
        ];

        $response = Http::timeout(15)
            ->post(self::API_BASE . self::ENDPOINT_OBSERVATIONS, $body);

        if ($response->status() === 204) {
            return [];
        }

        if (!$response->successful()) {
            throw new \RuntimeException(
                'RWS Waterinfo API returned HTTP ' . $response->status()
                . ($isForecast ? ' (forecast)' : ' (measurements)')
            );
        }

        $data = $response->json();

        if (empty($data['Succesvol']) || empty($data['WaarnemingenLijst'])) {
            return [];
        }

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

        return $points;
    }

    /**
     * Map legacy short station codes (e.g. IJMH) to new dot-notation codes.
     */
    private function resolveStationCode(string $code): string
    {
        $legacyMap = [
            'IJMH'     => 'ijmuiden.buitenhaven',
            'HOEKVHLD' => 'hoekvanholland',
            'VLISSGN'  => 'vlissingen',
            'DENHDR'   => 'denhelder.marsdiep',
            'HARVT10'  => 'harlingen.waddenzee',
            'SCHEVNGN' => 'scheveningen',
        ];

        return $legacyMap[$code] ?? $code;
    }
}
