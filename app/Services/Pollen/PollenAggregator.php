<?php

namespace App\Services\Pollen;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Merges pollen data from all enabled sources into a single normalised structure.
 *
 * Priority for risk levels (highest quality first):
 *   Ambee (count + risk + species) > Google (risk + plant names) > Open-Meteo (count → calculated risk)
 *
 * Priority for raw counts:
 *   Ambee > Open-Meteo
 *
 * Priority for 5-day forecast risk levels:
 *   Google > Open-Meteo (calculated)
 *
 * Output shape:
 * [
 *   'today'   => ['grass' => [...], 'tree' => [...], 'weed' => [...], 'overall_risk' => ..., 'overall_risk_index' => ..., 'overall_color' => ...],
 *   'forecast' => [ ['date'=>..., 'date_label'=>..., 'grass'=>[risk_index,color], ...], ... ],  // 5 days
 *   'species'  => ['tree'=>[...], 'grass'=>[...], 'weed'=>[...]] | null,
 *   'health_recommendations' => [...] | [],
 *   'sources'  => ['openmeteo'],
 *   'updated_at' => '...',
 * ]
 */
class PollenAggregator
{
    private const RISK_COLORS = [
        0 => '#22c55e',  // None  — green
        1 => '#84cc16',  // Low   — lime
        2 => '#eab308',  // Moderate — yellow
        3 => '#f97316',  // High  — orange
        4 => '#ef4444',  // Very High — red
    ];

    private const RISK_LABELS = [
        0 => 'None',
        1 => 'Low',
        2 => 'Moderate',
        3 => 'High',
        4 => 'Very High',
    ];

    public function __construct(
        private readonly OpenMeteoPollenService $openMeteo,
        private readonly GooglePollenService    $google,
        private readonly AmbeePollenService     $ambee,
    ) {}

    public function getData(): ?array
    {
        $lat = Setting::latitude();
        $lon = Setting::longitude();

        $openMeteoData = null;
        $googleData    = null;
        $ambeeData     = null;
        $ambeeLatest   = null;

        // Open-Meteo: always attempt (free, no key)
        if ((bool) Setting::getValue('pollen.openmeteo_enabled', true)) {
            $openMeteoData = $this->openMeteo->fetch($lat, $lon, 5);
        }

        // Google Pollen API (optional)
        if ((bool) Setting::getValue('pollen.google_enabled', false)) {
            $key = $this->decryptKey('pollen.google_api_key');
            if ($key) {
                $googleData = $this->google->fetch($lat, $lon, $key, 5);
            }
        }

        // Ambee (optional, paid)
        if ((bool) Setting::getValue('pollen.ambee_enabled', false)) {
            $key = $this->decryptKey('pollen.ambee_api_key');
            if ($key) {
                $ambeeLatest = $this->ambee->fetchLatest($lat, $lon, $key);
                $ambeeData   = $this->ambee->fetchForecast($lat, $lon, $key);
            }
        }

        // At least one source must have returned data
        if (!$openMeteoData && !$googleData && !$ambeeData) {
            Log::warning('PollenAggregator: no data from any source');
            return null;
        }

        return $this->merge($openMeteoData, $googleData, $ambeeData, $ambeeLatest);
    }

    private function merge(?array $om, ?array $google, ?array $ambee, ?array $ambeeLatest): array
    {
        $sources = [];
        if ($om)          $sources[] = 'openmeteo';
        if ($google)      $sources[] = 'google';
        if ($ambeeLatest || $ambee) $sources[] = 'ambee';

        // ── Today ──────────────────────────────────────────────────────────
        // Source priority for today's risk: Ambee latest > Google day[0] > Open-Meteo day[0]
        $todayRaw = [
            'grass' => $this->todayCategory('grass', $om, $google, $ambeeLatest),
            'tree'  => $this->todayCategory('tree',  $om, $google, $ambeeLatest),
            'weed'  => $this->todayCategory('weed',  $om, $google, $ambeeLatest),
        ];

        $overallIndex = max(
            $todayRaw['grass']['risk_index'],
            $todayRaw['tree']['risk_index'],
            $todayRaw['weed']['risk_index']
        );

        $today = array_merge($todayRaw, [
            'overall_risk'       => self::RISK_LABELS[$overallIndex] ?? 'Low',
            'overall_risk_index' => $overallIndex,
            'overall_color'      => self::RISK_COLORS[$overallIndex] ?? '#22c55e',
        ]);

        // ── 5-day forecast ─────────────────────────────────────────────────
        // Source priority for forecast risk: Google > Ambee forecast > Open-Meteo
        $forecast = $this->buildForecast($om, $google, $ambee);

        // ── Species breakdown ──────────────────────────────────────────────
        // Ambee latest (best, has counts) > Google day[0]
        $species = null;
        if ($ambeeLatest && !empty($ambeeLatest['species'])) {
            $species = $ambeeLatest['species'];
        } elseif ($google && !empty($google['forecast'][0]['species'])) {
            $species = $this->normaliseGoogleSpecies($google['forecast'][0]['species']);
        }

        // ── Health recommendations ─────────────────────────────────────────
        $healthRecs = [];
        if ($google && !empty($google['forecast'][0]['health_recs'])) {
            $healthRecs = $google['forecast'][0]['health_recs'];
        }

        return [
            'today'                  => $today,
            'forecast'               => $forecast,
            'species'                => $species,
            'health_recommendations' => $healthRecs,
            'sources'                => $sources,
            'updated_at'             => now()->utc()->toIso8601String(),
        ];
    }

    private function todayCategory(string $cat, ?array $om, ?array $google, ?array $ambeeLatest): array
    {
        // Ambee latest is the highest quality source for today
        if ($ambeeLatest && isset($ambeeLatest[$cat])) {
            $src = $ambeeLatest[$cat];
            return [
                'risk_index' => $src['risk_index'],
                'risk'       => $src['risk'],
                'count'      => $src['count'] ?? null,
                'color'      => self::RISK_COLORS[$src['risk_index']] ?? '#22c55e',
            ];
        }

        // Google day[0]
        if ($google && isset($google['forecast'][0][$cat])) {
            $src = $google['forecast'][0][$cat];
            // Try to get count from Open-Meteo for the same day
            $count = $this->omCountForDay($om, 0, $cat);
            return [
                'risk_index' => $src['risk_index'],
                'risk'       => $src['risk'],
                'count'      => $count,
                'color'      => self::RISK_COLORS[$src['risk_index']] ?? '#22c55e',
            ];
        }

        // Open-Meteo calculated
        if ($om && isset($om['forecast'][0][$cat])) {
            $src = $om['forecast'][0][$cat];
            return [
                'risk_index' => $src['risk_index'],
                'risk'       => $src['risk'],
                'count'      => $src['count'] ?? null,
                'color'      => self::RISK_COLORS[$src['risk_index']] ?? '#22c55e',
            ];
        }

        return ['risk_index' => 0, 'risk' => 'None', 'count' => null, 'color' => self::RISK_COLORS[0]];
    }

    private function buildForecast(?array $om, ?array $google, ?array $ambee): array
    {
        // Determine the date range from whatever source has data
        $dates = [];
        if ($om) {
            foreach ($om['forecast'] as $d) {
                $dates[] = $d['date'];
            }
        } elseif ($google) {
            foreach ($google['forecast'] as $d) {
                $dates[] = $d['date'];
            }
        } elseif ($ambee) {
            foreach ($ambee['forecast'] as $d) {
                $dates[] = $d['date'];
            }
        }

        $dates = array_slice(array_unique($dates), 0, 5);
        $forecast = [];

        foreach ($dates as $i => $date) {
            $day = ['date' => $date, 'date_label' => $this->dateLabel($date, $i)];

            foreach (['grass', 'tree', 'weed'] as $cat) {
                $riskIndex = 0;
                $count     = null;

                // Priority for forecast risk: Google > Ambee > Open-Meteo
                if ($google) {
                    $gDay = $this->findByDate($google['forecast'], $date);
                    if ($gDay && isset($gDay[$cat])) {
                        $riskIndex = $gDay[$cat]['risk_index'];
                    }
                }

                if ($riskIndex === 0 && $ambee) {
                    $aDay = $this->findByDate($ambee['forecast'], $date);
                    if ($aDay && isset($aDay[$cat])) {
                        $riskIndex = $aDay[$cat]['risk_index'];
                        $count     = $aDay[$cat]['count'] ?? null;
                    }
                }

                if ($om) {
                    $omDay = $om['forecast'][$i] ?? null;
                    if ($omDay) {
                        if ($riskIndex === 0 && isset($omDay[$cat])) {
                            $riskIndex = $omDay[$cat]['risk_index'];
                        }
                        if ($count === null && isset($omDay[$cat]['count'])) {
                            $count = $omDay[$cat]['count'];
                        }
                    }
                }

                $day[$cat] = [
                    'risk_index' => $riskIndex,
                    'risk'       => self::RISK_LABELS[$riskIndex] ?? 'None',
                    'count'      => $count,
                    'color'      => self::RISK_COLORS[$riskIndex] ?? '#22c55e',
                ];
            }

            $forecast[] = $day;
        }

        return $forecast;
    }

    private function omCountForDay(?array $om, int $dayIndex, string $cat): ?float
    {
        if (!$om || !isset($om['forecast'][$dayIndex][$cat]['count'])) {
            return null;
        }
        return $om['forecast'][$dayIndex][$cat]['count'];
    }

    private function findByDate(array $forecast, string $date): ?array
    {
        foreach ($forecast as $day) {
            if ($day['date'] === $date) {
                return $day;
            }
        }
        return null;
    }

    private function normaliseGoogleSpecies(array $googleSpecies): array
    {
        // Google species are already keyed by plant name with risk_index
        return $googleSpecies;
    }

    private function dateLabel(string $date, int $index): string
    {
        if ($index === 0) {
            return 'Today';
        }
        if ($index === 1) {
            return 'Tomorrow';
        }
        return date('D', strtotime($date));
    }

    private function decryptKey(string $settingKey): ?string
    {
        $raw = Setting::getValue($settingKey, '');
        if (empty($raw)) {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Exception $e) {
            // Key may be stored as plain text in dev
            return $raw;
        }
    }

    public static function riskColor(int $index): string
    {
        return self::RISK_COLORS[$index] ?? '#22c55e';
    }

    public static function riskLabel(int $index): string
    {
        return self::RISK_LABELS[$index] ?? 'None';
    }
}
