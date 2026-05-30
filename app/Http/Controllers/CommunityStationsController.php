<?php

namespace App\Http\Controllers;

use App\Services\Telemetry\GitHubTelemetryService;
use Illuminate\Http\Request;

class CommunityStationsController extends Controller
{
    /**
     * Display the community stations map
     */
    public function index(GitHubTelemetryService $githubService)
    {
        $stations = $githubService->readStations();
        $stationList = $stations['stations'] ?? [];

        $hardwareTypes = collect($stationList)
            ->pluck('hardware')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $countries = collect($stationList)
            ->pluck('country_code')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return view('weather.community-stations', [
            'stations' => $stationList,
            'lastUpdated' => $stations['last_updated'] ?? null,
            'hardwareTypes' => $hardwareTypes,
            'countries' => $countries,
        ]);
    }
}
