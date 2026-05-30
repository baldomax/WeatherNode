<?php

namespace App\Services\Telemetry;

use App\Models\Setting;
use App\Services\UserAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending telemetry data to a central aggregator
 * The aggregator handles GitHub updates, so sites don't need GitHub tokens
 */
class TelemetryAggregatorService
{
    private ?string $aggregatorUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->aggregatorUrl = Setting::getValue('telemetry.aggregator_url', 'https://weathernode.dev/telemetry-aggregator/api/telemetry');
        $this->apiKey = Setting::getValue('telemetry.api_key', '');
    }

    /**
     * Send station data to central aggregator
     */
    public function sendStationData(array $stationData): bool
    {
        if (empty($this->aggregatorUrl)) {
            Log::warning('Telemetry aggregator URL not configured');
            return false;
        }

        try {
            $payload = [
                'station' => $stationData,
                'timestamp' => now()->toIso8601String(),
            ];

            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => UserAgentService::forExternalApi(),
            ];

            // Add API key if configured (for authentication)
            if (!empty($this->apiKey)) {
                $headers['X-API-Key'] = $this->apiKey;
            }

            $http = Http::timeout(10);
            if (!app()->environment('production') && env('HTTP_SKIP_TLS_VERIFY')) {
                $http = $http->withoutVerifying();
            }

            $response = $http
                ->withHeaders($headers)
                ->post($this->aggregatorUrl, $payload);

            if ($response->successful()) {
                Log::info('Telemetry data sent successfully', [
                    'station' => $stationData['name'],
                ]);
                return true;
            }

            Log::error('Failed to send telemetry data', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Exception sending telemetry data', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Remove station from aggregator (when telemetry disabled)
     */
    public function removeStation(string $stationId): bool
    {
        if (empty($this->aggregatorUrl)) {
            return false;
        }

        try {
            $payload = [
                'action' => 'remove',
                'station_id' => $stationId,
            ];

            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => UserAgentService::forExternalApi(),
            ];

            if (!empty($this->apiKey)) {
                $headers['X-API-Key'] = $this->apiKey;
            }

            $http = Http::timeout(10);
            if (!app()->environment('production') && env('HTTP_SKIP_TLS_VERIFY')) {
                $http = $http->withoutVerifying();
            }

            $response = $http
                ->withHeaders($headers)
                ->post($this->aggregatorUrl, $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Exception removing station from aggregator', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
