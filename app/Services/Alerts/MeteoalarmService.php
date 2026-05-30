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
    
    // Warning types mapping
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
     * Fetch weather alerts from Meteoalarm RSS feed
     */
    public function fetchAlerts(): ?array
    {
        $country = substr($this->regionCode, 0, 2);
        
        if (!isset($this->countries[$country])) {
            Log::warning("Meteoalarm: Unknown country code: {$country}");
            return null;
        }

        // Include locale in cache key so each locale gets its own cached alerts
        $locale = app()->getLocale();
        $cacheKey = "meteoalarm_{$country}_{$locale}";

        return Cache::remember($cacheKey, $this->cacheMaxAge, function () use ($country) {
            try {
                $feedUrl = "https://feeds.meteoalarm.org/feeds/meteoalarm-legacy-rss-{$this->countries[$country]}";
                
                $response = Http::timeout(10)->get($feedUrl);
                
                if (!$response->successful()) {
                    Log::error('Meteoalarm RSS request failed', [
                        'status' => $response->status(),
                        'url' => $feedUrl,
                    ]);
                    return null;
                }

                return $this->parseRssFeed($response->body());

            } catch (\Exception $e) {
                Log::error('Meteoalarm exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Parse Meteoalarm RSS feed
     */
    private function parseRssFeed(string $xmlContent): array
    {
        $alerts = [];
        $regionAreas = explode(',', $this->regionCode);

        try {
            $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
            
            if (!$xml || !isset($xml->channel->item)) {
                return [];
            }

            foreach ($xml->channel->item as $item) {
                $link = (string) $item->link;
                
                // Check if this alert applies to our region
                $matchesRegion = false;
                foreach ($regionAreas as $area) {
                    if (strpos($link, trim($area)) !== false) {
                        $matchesRegion = true;
                        break;
                    }
                }
                
                if (!$matchesRegion) {
                    continue;
                }

                $title = (string) $item->title;
                $description = (string) $item->description;
                
                // Extract severity level and warning type from description
                $severity = $this->extractSeverity($description);
                $warningType = $this->extractWarningType($description);
                $warningTypeLabel = $warningType
                    ? __(ucfirst(str_replace('-', ' ', $warningType)))
                    : null;
                
                // Extract locale-specific description
                $localeDescription = $this->extractLocaleDescription($description);
                $plainDescription = strip_tags($description);

                $alerts[] = [
                    'title' => $title,
                    'description' => $localeDescription ?: $plainDescription,
                    'description_html' => $description,
                    'link' => $link,
                    'severity' => $severity,
                    'severity_color' => $this->severityColors[$severity] ?? 'transparent',
                    'warning_type' => $warningType,
                    'warning_type_label' => $warningTypeLabel,
                    'region' => $this->regionCode,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Meteoalarm XML parse error', ['error' => $e->getMessage()]);
        }

        return $alerts;
    }

    /**
     * Extract severity level from description HTML
     */
    private function extractSeverity(string $html): int
    {
        // First, try to extract from data-awareness-level attributes (new RSS format)
        preg_match_all('/data-awareness-level="(\d)"/', $html, $dataMatches);
        
        if (!empty($dataMatches[1])) {
            return (int) max($dataMatches[1]);
        }
        
        // Fall back to old format: severity flag images (wflag-l1, wflag-l2, wflag-l3, wflag-l4)
        preg_match_all('/wflag-l(\d)/', $html, $matches);
        
        if (empty($matches[1])) {
            return 0;
        }

        return (int) max($matches[1]);
    }

    /**
     * Extract warning type from description HTML
     */
    private function extractWarningType(string $html): ?string
    {
        // First, try to extract from data-awareness-type attributes (new RSS format)
        preg_match_all('/data-awareness-type="(\d)"/', $html, $dataMatches);
        
        if (!empty($dataMatches[1])) {
            $typeId = (int) max($dataMatches[1]);
            if (isset($this->warningTypes[$typeId])) {
                return $this->warningTypes[$typeId];
            }
        }
        
        // Fall back to old format: Look for warning type in image paths
        foreach ($this->warningTypes as $id => $type) {
            if (strpos($html, "wtype-l{$id}") !== false || strpos($html, "/{$id}.") !== false) {
                return $type;
            }
        }
        
        return null;
    }

    /**
     * Extract locale-specific description from RSS feed HTML.
     * Preference order: (1) user's app locale, (2) English, (3) region's local language.
     * Uses first language for which text is present in the feed.
     */
    private function extractLocaleDescription(string $html): ?string
    {
        $appLocale = $this->normalizeLocaleKey(app()->getLocale());
        $country = substr($this->regionCode, 0, 2);
        $regionLocale = $this->normalizeLocaleKey($this->regionPrimaryLocale[$country] ?? 'en-GB');
        $appBase = explode('-', $appLocale)[0] ?? $appLocale;
        $regionBase = explode('-', $regionLocale)[0] ?? $regionLocale;

        // Preference order: (1) user locale, (2) English, (3) region's local language
        $preferred = [$appLocale];
        if ($appBase !== 'en') {
            $preferred[] = 'en-GB';
        }
        if ($regionBase !== $appBase && $regionBase !== 'en' && !in_array($regionLocale, $preferred, true)) {
            $preferred[] = $regionLocale;
        }

        foreach ($preferred as $locale) {
            $text = $this->extractDescriptionForLocale($html, $locale);
            if ($text !== null && $text !== '') {
                return $text;
            }
        }

        // Last resort: first language found in the feed
        return $this->extractDescriptionForLocale($html, null);
    }

    /**
     * Extract description for a specific locale from feed HTML, or first available if locale is null.
     */
    private function extractDescriptionForLocale(string $html, ?string $locale): ?string
    {
        $locale = $locale ? strtolower($this->normalizeLocaleKey($locale)) : null;
        $localeConfig = [
            'nl' => ['codes' => ['nl-NL', 'nl'], 'name' => 'Dutch'],
            'nl-nl' => ['codes' => ['nl-NL', 'nl'], 'name' => 'Dutch'],
            'en' => ['codes' => ['en-GB', 'en'], 'name' => 'English'],
            'en-gb' => ['codes' => ['en-GB', 'en'], 'name' => 'English'],
            'en-us' => ['codes' => ['en-GB', 'en'], 'name' => 'English'],
            'de' => ['codes' => ['de-DE', 'de'], 'name' => 'German'],
            'de-de' => ['codes' => ['de-DE', 'de'], 'name' => 'German'],
            'fr' => ['codes' => ['fr-FR', 'fr'], 'name' => 'French'],
            'fr-fr' => ['codes' => ['fr-FR', 'fr'], 'name' => 'French'],
            'es' => ['codes' => ['es-ES', 'es'], 'name' => 'Spanish'],
            'es-es' => ['codes' => ['es-ES', 'es'], 'name' => 'Spanish'],
            'it' => ['codes' => ['it-IT', 'it'], 'name' => 'Italian'],
            'it-it' => ['codes' => ['it-IT', 'it'], 'name' => 'Italian'],
            'pl' => ['codes' => ['pl-PL', 'pl'], 'name' => 'Polish'],
            'pl-pl' => ['codes' => ['pl-PL', 'pl'], 'name' => 'Polish'],
            'pt' => ['codes' => ['pt-PT', 'pt'], 'name' => 'Portuguese'],
            'pt-pt' => ['codes' => ['pt-PT', 'pt'], 'name' => 'Portuguese'],
            'cs' => ['codes' => ['cs-CZ', 'cs'], 'name' => 'Czech'],
            'cs-cz' => ['codes' => ['cs-CZ', 'cs'], 'name' => 'Czech'],
            'sk' => ['codes' => ['sk-SK', 'sk'], 'name' => 'Slovak'],
            'sk-sk' => ['codes' => ['sk-SK', 'sk'], 'name' => 'Slovak'],
            'da' => ['codes' => ['da-DK', 'da'], 'name' => 'Danish'],
            'da-dk' => ['codes' => ['da-DK', 'da'], 'name' => 'Danish'],
            'sv' => ['codes' => ['sv-SE', 'sv'], 'name' => 'Swedish'],
            'sv-se' => ['codes' => ['sv-SE', 'sv'], 'name' => 'Swedish'],
            'nb' => ['codes' => ['nb-NO', 'nb'], 'name' => 'Norwegian'],
            'nb-no' => ['codes' => ['nb-NO', 'nb'], 'name' => 'Norwegian'],
            'el' => ['codes' => ['el-GR', 'el'], 'name' => 'Greek'],
            'el-gr' => ['codes' => ['el-GR', 'el'], 'name' => 'Greek'],
            'bg' => ['codes' => ['bg-BG', 'bg'], 'name' => 'Bulgarian'],
            'bg-bg' => ['codes' => ['bg-BG', 'bg'], 'name' => 'Bulgarian'],
            'hr' => ['codes' => ['hr-HR', 'hr'], 'name' => 'Croatian'],
            'hr-hr' => ['codes' => ['hr-HR', 'hr'], 'name' => 'Croatian'],
            'hu' => ['codes' => ['hu-HU', 'hu'], 'name' => 'Hungarian'],
            'hu-hu' => ['codes' => ['hu-HU', 'hu'], 'name' => 'Hungarian'],
            'ro' => ['codes' => ['ro-RO', 'ro'], 'name' => 'Romanian'],
            'ro-ro' => ['codes' => ['ro-RO', 'ro'], 'name' => 'Romanian'],
            'sr' => ['codes' => ['sr-RS', 'sr'], 'name' => 'Serbian'],
            'sr-rs' => ['codes' => ['sr-RS', 'sr'], 'name' => 'Serbian'],
            'sl' => ['codes' => ['sl-SI', 'sl'], 'name' => 'Slovenian'],
            'sl-si' => ['codes' => ['sl-SI', 'sl'], 'name' => 'Slovenian'],
            'fi' => ['codes' => ['fi-FI', 'fi'], 'name' => 'Finnish'],
            'fi-fi' => ['codes' => ['fi-FI', 'fi'], 'name' => 'Finnish'],
            'et' => ['codes' => ['et-EE', 'et'], 'name' => 'Estonian'],
            'et-ee' => ['codes' => ['et-EE', 'et'], 'name' => 'Estonian'],
            'lt' => ['codes' => ['lt-LT', 'lt'], 'name' => 'Lithuanian'],
            'lt-lt' => ['codes' => ['lt-LT', 'lt'], 'name' => 'Lithuanian'],
            'lv' => ['codes' => ['lv-LV', 'lv'], 'name' => 'Latvian'],
            'lv-lv' => ['codes' => ['lv-LV', 'lv'], 'name' => 'Latvian'],
            'mk' => ['codes' => ['mk-MK', 'mk'], 'name' => 'Macedonian'],
            'mk-mk' => ['codes' => ['mk-MK', 'mk'], 'name' => 'Macedonian'],
        ];

        $allLangNames = ['Dutch', 'English', 'German', 'French', 'Spanish', 'Italian', 'Polish', 'Portuguese', 'Czech', 'Slovak', 'Hungarian', 'Romanian', 'Bulgarian', 'Croatian', 'Serbian', 'Slovenian', 'Greek', 'Finnish', 'Swedish', 'Norwegian', 'Danish', 'Estonian', 'Lithuanian', 'Latvian', 'Macedonian'];
        $langNamesPattern = implode('|', $allLangNames);

        if ($locale !== null) {
            $config = $localeConfig[$locale] ?? $localeConfig['en'] ?? null;
            if ($config === null) {
                return null;
            }
            $langName = $config['name'];
            $langCodes = $config['codes'];

            foreach ($langCodes as $langCode) {
                $pattern = "/{$langName}\({$langCode}\):\s*((?:(?!{$langNamesPattern}|<\/)[^<])+)/is";
                if (preg_match($pattern, $html, $matches)) {
                    $text = trim($matches[1] ?? '');
                    if ($text !== '') {
                        return preg_replace('/\s+/', ' ', $text);
                    }
                }
                $shortCode = explode('-', $langCode)[0];
                if ($shortCode !== $langCode) {
                    $pattern = "/{$langName}\({$shortCode}\):\s*((?:(?!{$langNamesPattern}|<\/)[^<])+)/is";
                    if (preg_match($pattern, $html, $matches)) {
                        $text = trim($matches[1] ?? '');
                        if ($text !== '') {
                            return preg_replace('/\s+/', ' ', $text);
                        }
                    }
                }
            }
            $pattern = "/{$langName}\([^)]+\):\s*((?:(?!{$langNamesPattern}|<\/)[^<])+)/is";
            if (preg_match($pattern, $html, $matches)) {
                $text = trim($matches[1] ?? '');
                if ($text !== '') {
                    return preg_replace('/\s+/', ' ', $text);
                }
            }
            return null;
        }

        // First available language in feed
        $pattern = "/(?:{$langNamesPattern})\([^)]+\):\s*((?:(?!{$langNamesPattern}|<\/)[^<])+)/is";
        if (preg_match($pattern, $html, $matches)) {
            $text = trim($matches[1] ?? '');
            if ($text !== '') {
                return preg_replace('/\s+/', ' ', $text);
            }
        }
        return null;
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

        if ($region) {
            return $language . '-' . strtoupper($region);
        }

        return $language;
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
        return array_filter($alerts, fn($alert) => $alert['severity'] >= 2);
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

        usort($alerts, fn($a, $b) => $b['severity'] <=> $a['severity']);
        
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
