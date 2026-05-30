<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\FireWeatherCalculator;
use Illuminate\Support\Facades\Cache;

class FireWeatherController extends Controller
{
    public function index(FireWeatherCalculator $calc)
    {
        // Fire weather is derived from today's DailySummary, which weather:fetch updates
        // every minute with the running daily max temperature / min humidity. Use a short
        // cache so the index tracks the day as it warms up (the Angström index is a
        // worst-case daily value) instead of freezing the cold overnight snapshot.
        // A scheduled job re-warms these keys every 15 minutes (see routes/console.php).
        $ttl = now()->addMinutes(15);

        $current = Cache::remember('fire_weather_current', $ttl, fn () => $calc->currentIndices());
        $history = Cache::remember('fire_weather_history_90', $ttl, fn () => $calc->historicalData(90));

        return view('weather.fire-weather', [
            'stationName'     => Setting::stationName(),
            'stationLocation' => Setting::stationLocation() ?: Setting::stationName(),
            'current'         => $current,
            'history'         => $history,
        ]);
    }
}
