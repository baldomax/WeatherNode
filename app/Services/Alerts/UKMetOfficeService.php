<?php

namespace App\Services\Alerts;

use App\Models\Setting;
use App\Services\UserAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * UK Met Office Weather Warning Service
 * RSS Feed: https://www.metoffice.gov.uk/public/data/PWSCache/WarningsRSS/Region/
 */
class UKMetOfficeService implements AlertServiceInterface
{
    private string $region;
    private int $cacheMaxAge = 900; // 15 minutes

    private array $severityColors = [
        4 => '#BB2739', // Red
        3 => '#F19E39', // Amber/Orange
        2 => '#FBEA55', // Yellow
        1 => '#90EE90', // Green
        0 => 'transparent',
    ];

    public function __construct()
    {
        $this->region = Setting::getValue('alerts.uk_region', 'se'); // South East default
    }

    /**
     * Fetch weather alerts from Met Office RSS
     */
    public function fetchAlerts(): ?array
    {
        $cacheKey = "uk_alerts_{$this->region}";

        return Cache::remember($cacheKey, $this->cacheMaxAge, function () {
            try {
                $url = "https://www.metoffice.gov.uk/public/data/PWSCache/WarningsRSS/Region/{$this->region}";
                
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
                    Log::error('UK Met Office request failed', [
                        'status' => $response->status(),
                        'url' => $url,
                    ]);
                    return null;
                }

                return $this->parseRss($response->body());

            } catch (\Exception $e) {
                Log::error('UK Met Office alert exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Parse Met Office RSS response
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

                // Extract severity from title (usually "Yellow", "Amber", "Red")
                $severity = $this->extractSeverity($title);

                $alerts[] = [
                    'title' => $title,
                    'description' => strip_tags($description),
                    'description_html' => $description,
                    'link' => $link,
                    'severity' => $severity,
                    'severity_name' => $this->getSeverityName($severity),
                    'severity_color' => $this->severityColors[$severity] ?? 'transparent',
                    'warning_type' => $this->extractWarningType($title),
                    'source' => 'Met Office',
                ];
            }
        } catch (\Exception $e) {
            Log::error('UK Met Office XML parse error', ['error' => $e->getMessage()]);
        }

        return $alerts;
    }

    /**
     * Extract severity level from title
     */
    private function extractSeverity(string $title): int
    {
        $title = strtolower($title);
        
        if (str_contains($title, 'red')) return 4;
        if (str_contains($title, 'amber')) return 3;
        if (str_contains($title, 'yellow')) return 2;
        
        return 1;
    }

    /**
     * Get severity name
     */
    private function getSeverityName(int $severity): string
    {
        return match($severity) {
            4 => 'Red',
            3 => 'Amber',
            2 => 'Yellow',
            default => 'Green',
        };
    }

    /**
     * Extract warning type from title
     */
    private function extractWarningType(string $title): string
    {
        $title = strtolower($title);
        
        if (str_contains($title, 'wind')) return 'wind';
        if (str_contains($title, 'rain')) return 'rain';
        if (str_contains($title, 'snow') || str_contains($title, 'ice')) return 'snow-ice';
        if (str_contains($title, 'thunder')) return 'thunderstorm';
        if (str_contains($title, 'fog')) return 'fog';
        if (str_contains($title, 'heat')) return 'high-temperature';
        if (str_contains($title, 'cold') || str_contains($title, 'frost')) return 'low-temperature';
        if (str_contains($title, 'flood')) return 'flooding';
        
        return 'general';
    }

    /**
     * Get active alerts
     */
    public function getActiveAlerts(): array
    {
        $alerts = $this->fetchAlerts();
        
        if (!$alerts) {
            return [];
        }

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
}
