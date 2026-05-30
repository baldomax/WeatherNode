<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\GitHubTelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TelemetryController extends Controller
{
    /**
     * Get all community stations from GitHub
     */
    public function stations(GitHubTelemetryService $githubService): JsonResponse
    {
        $cacheKey = 'api_telemetry_stations';
        
        $data = Cache::remember($cacheKey, 1800, function () use ($githubService) {
            $stations = $githubService->readStations();
            return $stations ?? ['stations' => [], 'last_updated' => null];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'count' => count($data['stations'] ?? []),
        ]);
    }
}
