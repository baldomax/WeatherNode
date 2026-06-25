<?php

namespace App\Services\Alerts;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MeteoalarmService implements AlertServiceInterface
{
    private string $regionCode;
    private int $cacheMaxAge = 900; // 15 minutes

    private string $atomNs = 'http://www.w3.org/2005/Atom';
    private string $capNs = 'urn:oasis:names:tc:emergency:cap:1.2';

    // CAP awareness_type id => internal warning-type slug
    private array $warningTypes = [
        1 => 'wind',
        2 => 'snow-ice',
        3 => 'thunderstorm',
        4 => 'fog',
        5 => 'high-temperature',
        6 => 'low-temperature',
        7 => 'coastal-event',
        8 => 'forest-fire',
        9 => 'avalanches',
        10 => 'rain',
        12 => 'flooding',
        13 => 'rain-flood',
        14 => 'marine-hazard',
        15 => 'drought',
    ];

    // CAP severity word => MeteoAlarm awareness level
    private array $severityLevels = [
        'minor' => 1,
        'moderate' => 2,
        'severe' => 3,
        'extreme' => 4,
    ];

    // Severity colors (level 2-4)
    private array $severityColors = [
        0 => 'transparent',
        1 => 'transparent',
        2 => '#FBEA55', // Yellow
        3 => '#F19E39', // Orange
        4 => '#BB2739', // Red
    ];

    // Country codes to feed names
    private array $countries = [
        'AT' => 'austria', 'BA' => 'bosnia-herzegovina', 'BE' => 'belgium', 'BG' => 'bulgaria',
        'CH' => 'switzerland', 'CY' => 'cyprus', 'CZ' => 'czechia', 'DE' => 'germany',
        'DK' => 'denmark', 'EE' => 'estonia', 'ES' => 'spain', 'FI' => 'finland',
        'FR' => 'france', 'GR' => 'greece', 'HR' => 'croatia', 'HU' => 'hungary',
        'IE' => 'ireland', 'IL' => 'israel', 'IS' => 'iceland', 'IT' => 'italy',
        'LT' => 'lithuania', 'LU' => 'luxembourg', 'LV' => 'latvia', 'MD' => 'moldova',
        'ME' => 'montenegro', 'MK' => 'north-macedonia', 'MT' => 'malta', 'NL' => 'netherlands',
        'NO' => 'norway', 'PL' => 'poland', 'PT' => 'portugal', 'RO' => 'romania',
        'RS' => 'serbia', 'SE' => 'sweden', 'SI' => 'slovenia', 'SK' => 'slovakia',
        'UK' => 'united-kingdom',
    ];

    // Region (country) primary language: language used in MeteoAlarm feed for that country
    private array $regionPrimaryLocale = [
        'AT' => 'de-DE', 'BA' => 'bs-BA', 'BE' => 'fr-FR', 'BG' => 'bg-BG', 'CH' => 'de-DE',
        'CY' => 'el-GR', 'CZ' => 'cs-CZ', 'DE' => 'de-DE', 'DK' => 'da-DK', 'EE' => 'et-EE',
        'ES' => 'es-ES', 'FI' => 'fi-FI', 'FR' => 'fr-FR', 'GR' => 'el-GR', 'HR' => 'hr-HR',
        'HU' => 'hu-HU', 'IE' => 'en-GB', 'IL' => 'he-IL', 'IS' => 'is-IS', 'IT' => 'it-IT',
        'LT' => 'lt-LT', 'LU' => 'fr-FR', 'LV' => 'lv-LV', 'MD' => 'ro-RO', 'ME' => 'sr-RS',
        'MK' => 'mk-MK', 'MT' => 'en-GB', 'NL' => 'nl-NL', 'NO' => 'nb-NO', 'PL' => 'pl-PL',
        'PT' => 'pt-PT', 'RO' => 'ro-RO', 'RS' => 'sr-RS', 'SE' => 'sv-SE', 'SI' => 'sl-SI',
        'SK' => 'sk-SK', 'UK' => 'en-GB',
    ];

    public function __construct()
    {
        $this->regionCode = Setting::getValue('alerts.region_code', 'NL011');
    }

    /**
     * Fetch weather alerts from the MeteoAlarm Atom feed.
     *
     * The legacy RSS feed (meteoalarm-legacy-rss-*) was deprecated on 2026-01-14;
     * this uses the maintained Atom feed (meteoalarm-legacy-atom-*), which carries
     * one CAP entry per warned area. Per-warning localized text is read from each
     * entry's linked CAP document.
     */
    public function fetchAlerts(): ?array
    {
        $country = strtoupper(substr($this->regionCode, 0, 2));

        if (!isset($this->countries[$country])) {
            Log::warning("Meteoalarm: Unknown country code: {$country}");
            return null;
        }

        // Include locale in cache key so each locale gets its own cached alerts
        $locale = app()->getLocale();
        $cacheKey = "meteoalarm_atom_{$this->regionCode}_{$locale}";

        return Cache::remember($cacheKey, $this->cacheMaxAge, function () use ($country) {
            try {
                $feedUrl = "https://feeds.meteoalarm.org/feeds/meteoalarm-legacy-atom-{$this->countries[$country]}";

                $response = Http::timeout(10)->get($feedUrl);

                if (!$response->successful()) {
                    Log::error('Meteoalarm Atom request failed', [
                        'status' => $response->status(),
                        'url' => $feedUrl,
                    ]);
                    return null;
                }

                return $this->parseAtomFeed($response->body());

            } catch (\Exception $e) {
                Log::error('Meteoalarm exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Parse the MeteoAlarm Atom feed.
     *
     * Keeps only non-expired warnings for the configured region, collapses
     * multiple time-window warnings of the same hazard type down to the highest
     * severity, then enriches each survivor with localized text from its CAP doc.
     */
    private function parseAtomFeed(string $xmlContent): array
    {
        $regionAreas = array_filter(array_map('trim', explode(',', $this->regionCode)));

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        if ($xml === false) {
            Log::error('Meteoalarm XML parse error', ['error' => 'Invalid Atom feed']);
            return [];
        }

        // Collapse to one candidate per hazard type, keeping the highest severity.
        $candidates = [];
        foreach ($xml->children($this->atomNs)->entry as $entry) {
            $cap = $entry->children($this->capNs);
            $atom = $entry->children($this->atomNs);

            $emmaId = $this->extractEmmaId($cap);
            if ($emmaId === '' || !$this->matchesRegion($emmaId, $regionAreas)) {
                continue;
            }

            // Skip warnings that have already expired.
            $expires = (string) $cap->expires;
            if ($expires !== '' && ($ts = strtotime($expires)) !== false && $ts < time()) {
                continue;
            }

            $level = $this->severityLevels[strtolower(trim((string) $cap->severity))] ?? 0;
            if ($level < 2) {
                continue; // only yellow (2) and above are surfaced
            }

            $event = trim((string) $cap->event);
            $type = $this->deriveTypeFromEvent($event);

            [$capUrl, $infoLink] = $this->extractLinks($atom);

            $key = $type ?? $event;
            if (!isset($candidates[$key]) || $level > $candidates[$key]['level']) {
                $candidates[$key] = [
                    'level' => $level,
                    'type' => $type,
                    'event' => $event,
                    'title' => trim((string) $atom->title),
                    'cap_url' => $capUrl,
                    'link' => $infoLink,
                ];
            }
        }

        // Enrich survivors with localized headline/description from their CAP docs.
        $alerts = [];
        foreach ($candidates as $candidate) {
            $details = $this->fetchCapDetails($candidate['cap_url']);
            $type = $details['type'] ?? $candidate['type'];

            $alerts[] = [
                'title' => $details['headline'] ?: ($candidate['title'] ?: $candidate['event']),
                'description' => $details['description'] ?: $candidate['event'],
                'link' => $candidate['link'],
                'severity' => $candidate['level'],
                'severity_color' => $this->severityColors[$candidate['level']] ?? 'transparent',
                'warning_type' => $type,
                'warning_type_label' => $type
                    ? __(ucfirst(str_replace('-', ' ', $type)))
                    : null,
                'region' => $this->regionCode,
            ];
        }

        return $alerts;
    }

    /**
     * Read the EMMA_ID geocode value from an entry's CAP children.
     */
    private function extractEmmaId(\SimpleXMLElement $cap): string
    {
        foreach ($cap->geocode as $geocode) {
            if (strcasecmp($this->childString($geocode, 'valueName'), 'EMMA_ID') === 0) {
                return $this->childString($geocode, 'value');
            }
        }

        return '';
    }

    /**
     * Pull the CAP document link and the human "more info" link from an entry.
     *
     * @return array{0: ?string, 1: ?string} [cap document url, info url]
     */
    private function extractLinks(\SimpleXMLElement $atom): array
    {
        $capUrl = null;
        $infoLink = null;

        foreach ($atom->link as $link) {
            $attrs = $link->attributes();
            $type = (string) ($attrs['type'] ?? '');
            $rel = (string) ($attrs['rel'] ?? '');
            $href = (string) ($attrs['href'] ?? '');

            if ($type === 'application/cap+xml') {
                $capUrl = $href;
            } elseif ($infoLink === null && $type === '' && $rel === '') {
                $infoLink = $href;
            }
        }

        return [$capUrl, $infoLink];
    }

    /**
     * Match an EMMA_ID against the configured region(s).
     * Supports exact area codes (NL011) and country prefixes (NL).
     */
    private function matchesRegion(string $emmaId, array $regionAreas): bool
    {
        $emmaId = strtoupper($emmaId);

        foreach ($regionAreas as $area) {
            $area = strtoupper($area);
            if ($area !== '' && ($emmaId === $area || str_starts_with($emmaId, $area))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Derive an internal hazard-type slug from the CAP event text,
     * e.g. "Severe high-temperature warning" => "high-temperature".
     */
    private function deriveTypeFromEvent(string $event): ?string
    {
        $haystack = strtolower(str_replace(' ', '-', $event));

        // Match longest slugs first so "rain-flood" wins over "rain".
        $slugs = array_values($this->warningTypes);
        usort($slugs, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($slugs as $slug) {
            if (str_contains($haystack, $slug)) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * Fetch a single warning's CAP document and extract localized details.
     *
     * @return array{headline: ?string, description: ?string, type: ?string}
     */
    private function fetchCapDetails(?string $capUrl): array
    {
        $empty = ['headline' => null, 'description' => null, 'type' => null];

        if (!$capUrl) {
            return $empty;
        }

        try {
            $response = Http::timeout(10)->get($capUrl);
            if (!$response->successful()) {
                return $empty;
            }

            libxml_use_internal_errors(true);
            $alert = simplexml_load_string($response->body());
            if ($alert === false) {
                return $empty;
            }

            $infos = [];
            foreach ($alert->children($this->capNs)->info as $info) {
                $infos[] = [
                    'lang' => $this->childString($info, 'language'),
                    'node' => $info,
                ];
            }

            $info = $this->pickCapInfo($infos);
            if ($info === null) {
                return $empty;
            }

            return [
                'headline' => $this->childString($info, 'headline') ?: null,
                'description' => $this->childString($info, 'description') ?: null,
                'type' => $this->extractAwarenessType($info),
            ];

        } catch (\Exception $e) {
            Log::warning('Meteoalarm CAP fetch failed', ['url' => $capUrl, 'error' => $e->getMessage()]);
            return $empty;
        }
    }

    /**
     * Choose the CAP <info> block for the best-matching language.
     * Preference: (1) app locale, (2) English, (3) region's local language, (4) first available.
     *
     * @param array<int, array{lang: string, node: \SimpleXMLElement}> $infos
     */
    private function pickCapInfo(array $infos): ?\SimpleXMLElement
    {
        if (empty($infos)) {
            return null;
        }

        $appLocale = $this->normalizeLocaleKey(app()->getLocale());
        $appBase = explode('-', $appLocale)[0];
        $country = strtoupper(substr($this->regionCode, 0, 2));
        $regionLocale = $this->normalizeLocaleKey($this->regionPrimaryLocale[$country] ?? 'en-GB');

        $preferred = [$appLocale];
        if ($appBase !== 'en') {
            $preferred[] = 'en-GB';
        }
        $preferred[] = $regionLocale;

        foreach ($preferred as $pref) {
            $prefBase = explode('-', $pref)[0];
            foreach ($infos as $info) {
                $lang = $this->normalizeLocaleKey($info['lang']);
                if (strcasecmp($lang, $pref) === 0
                    || strcasecmp(explode('-', $lang)[0], $prefBase) === 0) {
                    return $info['node'];
                }
            }
        }

        return $infos[0]['node'];
    }

    /**
     * Read the canonical hazard slug from a CAP info block's awareness_type parameter,
     * e.g. "5; high-temperature" => "high-temperature".
     */
    private function extractAwarenessType(\SimpleXMLElement $info): ?string
    {
        foreach ($info->children($this->capNs)->parameter as $param) {
            if (strcasecmp($this->childString($param, 'valueName'), 'awareness_type') !== 0) {
                continue;
            }

            $value = $this->childString($param, 'value');
            if (preg_match('/^\s*(\d+)/', $value, $m) && isset($this->warningTypes[(int) $m[1]])) {
                return $this->warningTypes[(int) $m[1]];
            }

            $parts = explode(';', $value);
            if (count($parts) > 1) {
                return strtolower(trim($parts[1]));
            }
        }

        return null;
    }

    /**
     * Read a named child element as a string, tolerating namespace placement
     * (MeteoAlarm puts geocode value/valueName in the Atom default namespace,
     * while CAP documents use the CAP namespace throughout).
     */
    private function childString(\SimpleXMLElement $node, string $name): string
    {
        foreach (['', $this->atomNs, $this->capNs] as $ns) {
            $children = $ns === '' ? $node->children() : $node->children($ns);
            if (isset($children->$name)) {
                $value = trim((string) $children->$name);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * Normalize locale strings to a consistent key format (ll-CC).
     */
    private function normalizeLocaleKey(string $locale): string
    {
        $locale = str_replace('_', '-', trim($locale));
        $parts = explode('-', $locale);
        $language = strtolower($parts[0] ?? '');
        $region = $parts[1] ?? null;

        if ($language === '') {
            return 'en-GB';
        }

        return $region ? $language . '-' . strtoupper($region) : $language;
    }

    /**
     * Get active alerts for current region
     */
    public function getActiveAlerts(): array
    {
        $alerts = $this->fetchAlerts();

        if (!$alerts) {
            return [];
        }

        // Filter to only include alerts with severity >= 2
        return array_values(array_filter($alerts, fn ($alert) => $alert['severity'] >= 2));
    }

    /**
     * Get highest severity alert
     */
    public function getHighestSeverityAlert(): ?array
    {
        $alerts = $this->getActiveAlerts();

        if (empty($alerts)) {
            return null;
        }

        usort($alerts, fn ($a, $b) => $b['severity'] <=> $a['severity']);

        return $alerts[0];
    }

    /**
     * Check if there are any active warnings
     */
    public function hasActiveWarnings(): bool
    {
        return !empty($this->getActiveAlerts());
    }

    /**
     * Get severity description
     */
    public function getSeverityDescription(int $level): string
    {
        return match($level) {
            0, 1 => 'No warnings',
            2 => 'Yellow - Be aware',
            3 => 'Orange - Be prepared',
            4 => 'Red - Take action',
            default => 'Unknown',
        };
    }
}
