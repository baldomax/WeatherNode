<?php

namespace App\Services\Alerts;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class AlertAggregatorService
{
    /**
     * Return all active warnings — official (Meteoalarm etc.) + internal threshold-based —
     * merged into a single list and sorted by severity descending.
     */
    public function getAll(): array
    {
        $enabled = (bool) Setting::getValue('alerts.enabled', true);

        // External official alerts (Meteoalarm, NWS, etc.)
        $external = [];
        if ($enabled) {
            $raw      = Cache::get('weather_alerts', []);
            $external = array_map(fn ($a) => array_merge($a, [
                'source'       => 'meteoalarm',
                'source_label' => 'Meteoalarm',
            ]), is_array($raw) ? $raw : []);
        }

        // Internal threshold-based warnings (cached 15 min)
        $internal = app(LocalWarningService::class)->getWarnings();

        // Merge and sort by severity descending
        $all = array_merge($external, $internal);
        usort($all, fn ($a, $b) => ($b['severity'] ?? 0) <=> ($a['severity'] ?? 0));

        return $all;
    }
}
