<?php

namespace App\Services\Alerts;

use App\Models\Setting;
use App\Services\UserAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Environment Canada Weather Alert Service
 * RSS Feed: https://weather.gc.ca/rss/warning/
 */
class CanadaAlertService implements AlertServiceInterface
{
    private string $province;
    private string $regionCode;
    private string $language;
    private int $cacheMaxAge = 900; // 15 minutes

    private array $severityColors = [
        4 => '#BB2739', // Red - Warning
        3 => '#F19E39', // Orange - Watch
        2 => '#FBEA55', // Yellow - Advisory/Statement
        1 => '#90EE90', // Green - Ended
        0 => 'transparent',
    ];

    // Province region codes (area codes on weather.gc.ca)
    private array $defaultRegionCodes = [
        'AB' => 'ab-52', // Calgary
        'BC' => 'bc-74', // Vancouver
        'MB' => 'mb-38', // Winnipeg
        'NB' => 'nb-36', // Fredericton
        'NL' => 'nl-24', // St. John's
        'NS' => 'ns-19', // Halifax
        'NT' => 'nt-24', // Yellowknife
        'NU' => 'nu-20', // Iqaluit
        'ON' => 'on-143', // Toronto
        'PE' => 'pe-5',  // Charlottetown
        'QC' => 'qc-147', // Montreal
        'SK' => 'sk-32', // Regina
        'YT' => 'yt-16', // Whitehorse
    ];

    public function __construct()
    {
        $this->province = strtoupper(Setting::getValue('alerts.province', 'ON'));
        $this->regionCode = Setting::getValue('alerts.ca_region_code', $this->defaultRegionCodes[$this->province] ?? 'on-143');
        $this->language = Setting::getValue('locale', 'en') === 'fr' ? 'f' : 'e';
    }

    /**
     * Fetch weather alerts from Environment Canada RSS
     */
    public function fetchAlerts(): ?array
    {
        $cacheKey = "ec_alerts_{$this->regionCode}";

        return Cache::remember($cacheKey, $this->cacheMaxAge, function () {
            try {
                $url = "https://weather.gc.ca/rss/warning/{$this->regionCode}_{$this->language}.xml";
                
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
                    Log::error('Environment Canada RSS request failed', [
                        'status' => $response->status(),
                        'url' => $url,
                    ]);
                    return null;
                }

                return $this->parseRss($response->body());

            } catch (\Exception $e) {
                Log::error('Environment Canada alert exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Parse Environment Canada RSS response
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
                $pubDate = (string) $item->pubDate;

                // Skip "no watches or warnings" items
                if (stripos($title, 'no watches or warnings') !== false || 
                    stripos($title, 'aucune veille ni avertissement') !== false) {
                    continue;
                }

                $severity = $this->extractSeverity($title);

                $alerts[] = [
                    'title' => $title,
                    'description' => strip_tags($description),
                    'link' => $link,
                    'published' => $pubDate,
                    'severity' => $severity,
                    'severity_name' => $this->getSeverityName($severity),
                    'severity_color' => $this->severityColors[$severity] ?? 'transparent',
                    'warning_type' => $this->extractWarningType($title),
                    'source' => 'Environment Canada',
                ];
            }
        } catch (\Exception $e) {
            Log::error('EC RSS parse error', ['error' => $e->getMessage()]);
        }

        return $alerts;
    }

    /**
     * Extract severity from title
     */
    private function extractSeverity(string $title): int
    {
        $title = strtolower($title);
        
        // Warning = highest
        if (str_contains($title, 'warning') || str_contains($title, 'avertissement')) return 4;
        // Watch = high
        if (str_contains($title, 'watch') || str_contains($title, 'veille')) return 3;
        // Advisory/Statement = moderate
        if (str_contains($title, 'advisory') || str_contains($title, 'statement') || 
            str_contains($title, 'bulletin') || str_contains($title, 'avis')) return 2;
        // Ended = low
        if (str_contains($title, 'ended') || str_contains($title, 'terminé')) return 1;
        
        return 2; // Default to advisory level
    }

    /**
     * Get severity name
     */
    private function getSeverityName(int $severity): string
    {
        return match($severity) {
            4 => 'Warning',
            3 => 'Watch',
            2 => 'Advisory',
            1 => 'Ended',
            default => 'Unknown',
        };
    }

    /**
     * Map EC priority to severity level
     */
    private function mapPriority(string $priority): int
    {
        return match(strtolower($priority)) {
            'high' => 4,
            'medium' => 3,
            'low' => 2,
            default => 1,
        };
    }

    /**
     * Map event type to standard warning type
     */
    private function mapEventType(string $event): string
    {
        $event = strtolower($event);
        
        if (str_contains($event, 'wind')) return 'wind';
        if (str_contains($event, 'snow') || str_contains($event, 'blizzard') || str_contains($event, 'winter')) return 'snow-ice';
        if (str_contains($event, 'thunder')) return 'thunderstorm';
        if (str_contains($event, 'rain') || str_contains($event, 'flood')) return 'rain-flood';
        if (str_contains($event, 'heat')) return 'high-temperature';
        if (str_contains($event, 'cold') || str_contains($event, 'frost') || str_contains($event, 'freeze')) return 'low-temperature';
        if (str_contains($event, 'fog')) return 'fog';
        if (str_contains($event, 'tornado')) return 'tornado';
        
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
