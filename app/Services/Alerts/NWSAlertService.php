<?php

namespace App\Services\Alerts;

use App\Models\Setting;
use App\Services\UserAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * National Weather Service (USA) Alert Service
 * API Documentation: https://www.weather.gov/documentation/services-web-api
 */
class NWSAlertService implements AlertServiceInterface
{
    private float $latitude;
    private float $longitude;
    private string $state;
    private string $zone;
    private int $cacheMaxAge = 900; // 15 minutes

    // Alert severity mapping
    private array $severityMap = [
        'Extreme' => 4,
        'Severe' => 3,
        'Moderate' => 2,
        'Minor' => 1,
        'Unknown' => 0,
    ];

    // Severity colors
    private array $severityColors = [
        4 => '#BB2739', // Red - Extreme
        3 => '#F19E39', // Orange - Severe
        2 => '#FBEA55', // Yellow - Moderate
        1 => '#90EE90', // Light Green - Minor
        0 => 'transparent',
    ];

    public function __construct()
    {
        $this->latitude = (float) Setting::getValue('station.latitude', 52.52);
        $this->longitude = (float) Setting::getValue('station.longitude', 4.71);
        $this->state = Setting::getValue('alerts.us_state', 'NY');
        $this->zone = Setting::getValue('alerts.us_zone', '');
    }

    /**
     * Fetch weather alerts from NWS API
     */
    public function fetchAlerts(): ?array
    {
        $cacheKey = "nws_alerts_{$this->state}";

        return Cache::remember($cacheKey, $this->cacheMaxAge, function () {
            try {
                // Use point-based alerts if we have coordinates in USA
                $url = "https://api.weather.gov/alerts/active";
                $params = [];

                if (!empty($this->zone)) {
                    $params['zone'] = $this->zone;
                } elseif (!empty($this->state)) {
                    $params['area'] = $this->state;
                } else {
                    // Use point-based query
                    $params['point'] = "{$this->latitude},{$this->longitude}";
                }

                $http = Http::timeout(15);
                if (!app()->environment('production') && env('HTTP_SKIP_TLS_VERIFY')) {
                    $http = $http->withoutVerifying();
                }

                $response = $http
                    ->withHeaders([
                        'User-Agent' => UserAgentService::forExternalApi(),
                        'Accept' => 'application/geo+json',
                    ])
                    ->get($url, $params);

                if (!$response->successful()) {
                    Log::error('NWS API request failed', [
                        'status' => $response->status(),
                        'url' => $url,
                    ]);
                    return null;
                }

                return $this->parseResponse($response->json());

            } catch (\Exception $e) {
                Log::error('NWS alert exception', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Parse NWS GeoJSON response
     */
    private function parseResponse(array $data): array
    {
        $alerts = [];
        $features = $data['features'] ?? [];

        foreach ($features as $feature) {
            $props = $feature['properties'] ?? [];
            
            $severity = $this->severityMap[$props['severity'] ?? 'Unknown'] ?? 0;
            
            $alerts[] = [
                'id' => $props['id'] ?? null,
                'title' => $props['headline'] ?? $props['event'] ?? 'Weather Alert',
                'event' => $props['event'] ?? 'Unknown',
                'description' => $props['description'] ?? '',
                'instruction' => $props['instruction'] ?? '',
                'link' => $props['@id'] ?? null,
                'severity' => $severity,
                'severity_name' => $props['severity'] ?? 'Unknown',
                'severity_color' => $this->severityColors[$severity] ?? 'transparent',
                'urgency' => $props['urgency'] ?? 'Unknown',
                'certainty' => $props['certainty'] ?? 'Unknown',
                'effective' => $props['effective'] ?? null,
                'expires' => $props['expires'] ?? null,
                'areas' => $props['areaDesc'] ?? '',
                'warning_type' => $this->mapEventType($props['event'] ?? ''),
                'source' => 'NWS',
            ];
        }

        return $alerts;
    }

    /**
     * Map NWS event type to standard warning type
     */
    private function mapEventType(string $event): string
    {
        $event = strtolower($event);
        
        if (str_contains($event, 'tornado')) return 'tornado';
        if (str_contains($event, 'thunderstorm')) return 'thunderstorm';
        if (str_contains($event, 'hurricane') || str_contains($event, 'tropical')) return 'hurricane';
        if (str_contains($event, 'flood')) return 'flooding';
        if (str_contains($event, 'winter') || str_contains($event, 'snow') || str_contains($event, 'blizzard')) return 'snow-ice';
        if (str_contains($event, 'wind')) return 'wind';
        if (str_contains($event, 'heat')) return 'high-temperature';
        if (str_contains($event, 'cold') || str_contains($event, 'freeze')) return 'low-temperature';
        if (str_contains($event, 'fog')) return 'fog';
        if (str_contains($event, 'fire')) return 'forest-fire';
        
        return 'general';
    }

    /**
     * Get active alerts (severity >= Minor)
     */
    public function getActiveAlerts(): array
    {
        $alerts = $this->fetchAlerts();
        
        if (!$alerts) {
            return [];
        }

        return array_filter($alerts, fn($alert) => $alert['severity'] >= 1);
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
