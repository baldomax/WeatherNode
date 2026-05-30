<?php

namespace App\Services\Alerts;

use App\Models\Setting;
use App\Services\UserAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Bureau of Meteorology (Australia) Weather Alert Service
 * 
 * NOTE: BOM blocks direct web scraping. This service uses the public
 * warnings RSS feeds when available.
 * 
 * FTP Data: ftp://ftp.bom.gov.au/anon/gen/fwo/
 * Web Warnings: http://www.bom.gov.au/{state}/warnings/
 */
class AustraliaAlertService implements AlertServiceInterface
{
    private string $state;
    private int $cacheMaxAge = 900; // 15 minutes

    // State warning page URLs (for scraping warning summaries)
    private array $stateWarningPages = [
        'nsw' => 'http://www.bom.gov.au/nsw/warnings/',
        'vic' => 'http://www.bom.gov.au/vic/warnings/',
        'qld' => 'http://www.bom.gov.au/qld/warnings/',
        'wa'  => 'http://www.bom.gov.au/wa/warnings/',
        'sa'  => 'http://www.bom.gov.au/sa/warnings/',
        'tas' => 'http://www.bom.gov.au/tas/warnings/',
        'nt'  => 'http://www.bom.gov.au/nt/warnings/',
        'act' => 'http://www.bom.gov.au/act/warnings/',
    ];

    // RSS feeds for warnings (some states)
    private array $rssFeeds = [
        'nsw' => 'http://www.bom.gov.au/fwo/IDN11060.warnings_nsw_rss.xml',
        'vic' => 'http://www.bom.gov.au/fwo/IDV10760.warnings_vic_rss.xml',
        'qld' => 'http://www.bom.gov.au/fwo/IDQ20006.warnings_qld_rss.xml',
    ];

    private array $severityColors = [
        4 => '#BB2739', // Red - Severe
        3 => '#F19E39', // Orange - Watch
        2 => '#FBEA55', // Yellow - Advice
        1 => '#90EE90', // Green - Normal
        0 => 'transparent',
    ];

    public function __construct()
    {
        $this->state = strtolower(Setting::getValue('alerts.au_state', 'nsw'));
    }

    /**
     * Fetch weather alerts from BOM
     * 
     * Note: BOM blocks automated web scraping. This method attempts to 
     * use available RSS feeds where supported.
     */
    public function fetchAlerts(): ?array
    {
        $cacheKey = "bom_alerts_{$this->state}";

        return Cache::remember($cacheKey, $this->cacheMaxAge, function () {
            // Try RSS feed first (if available for this state)
            if (isset($this->rssFeeds[$this->state])) {
                $alerts = $this->fetchFromRss($this->rssFeeds[$this->state]);
                if ($alerts !== null) {
                    return $alerts;
                }
            }

            // Return empty array with info message - BOM blocks automated access
            Log::info('BOM alerts: Direct web access blocked. For Australian users, please check warnings manually at ' . ($this->stateWarningPages[$this->state] ?? 'http://www.bom.gov.au/warnings/'));
            
            return [];
        });
    }

    /**
     * Try to fetch from RSS feed
     */
    private function fetchFromRss(string $url): ?array
    {
        try {
            $http = Http::timeout(15);
            if (!app()->environment('production') && env('HTTP_SKIP_TLS_VERIFY')) {
                $http = $http->withoutVerifying();
            }

            $response = $http
                ->withHeaders([
                    'User-Agent' => UserAgentService::forExternalApi(true),
                ])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            return $this->parseRss($response->body());

        } catch (\Exception $e) {
            Log::warning('BOM RSS fetch failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse BOM RSS feed
     */
    private function parseRss(string $xmlContent): array
    {
        $alerts = [];

        try {
            $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
            
            if (!$xml || !isset($xml->channel->item)) {
                return [];
            }

            foreach ($xml->channel->item as $item) {
                $title = (string) $item->title;
                $description = (string) $item->description;
                $link = (string) $item->link;

                // Skip non-warning items
                if (stripos($title, 'warning') === false && 
                    stripos($title, 'watch') === false && 
                    stripos($title, 'advice') === false) {
                    continue;
                }

                $severity = $this->extractSeverityFromTitle($title);

                $alerts[] = [
                    'title' => $title,
                    'description' => strip_tags($description),
                    'link' => $link,
                    'severity' => $severity,
                    'severity_name' => $this->getSeverityName($severity),
                    'severity_color' => $this->severityColors[$severity] ?? 'transparent',
                    'warning_type' => $this->mapWarningType($title),
                    'source' => 'Bureau of Meteorology',
                    'state' => strtoupper($this->state),
                ];
            }
        } catch (\Exception $e) {
            Log::error('BOM RSS parse error', ['error' => $e->getMessage()]);
        }

        return $alerts;
    }

    /**
     * Extract severity from title
     */
    private function extractSeverityFromTitle(string $title): int
    {
        $title = strtolower($title);
        
        if (str_contains($title, 'severe') || str_contains($title, 'emergency')) return 4;
        if (str_contains($title, 'warning')) return 3;
        if (str_contains($title, 'watch')) return 2;
        if (str_contains($title, 'advice') || str_contains($title, 'advisory')) return 1;
        
        return 2;
    }

    /**
     * Get severity name
     */
    private function getSeverityName(int $severity): string
    {
        return match($severity) {
            4 => 'Severe',
            3 => 'Warning',
            2 => 'Watch',
            1 => 'Advisory',
            default => 'Unknown',
        };
    }

    /**
     * Map warning type from title
     */
    private function mapWarningType(string $type): string
    {
        $type = strtolower($type);
        
        if (str_contains($type, 'cyclone') || str_contains($type, 'tropical')) return 'hurricane';
        if (str_contains($type, 'thunderstorm') || str_contains($type, 'storm')) return 'thunderstorm';
        if (str_contains($type, 'flood')) return 'flooding';
        if (str_contains($type, 'wind')) return 'wind';
        if (str_contains($type, 'fire') || str_contains($type, 'heat')) return 'high-temperature';
        if (str_contains($type, 'coastal') || str_contains($type, 'surf') || str_contains($type, 'marine')) return 'coastal-event';
        if (str_contains($type, 'fog')) return 'fog';
        if (str_contains($type, 'frost')) return 'low-temperature';
        
        return 'general';
    }

    /**
     * Get active alerts
     */
    public function getActiveAlerts(): array
    {
        $alerts = $this->fetchAlerts();
        return $alerts ?: [];
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
}
