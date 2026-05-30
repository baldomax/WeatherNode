<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Telemetry\TelemetryService;
use App\Services\Telemetry\TelemetryAggregatorService;
use Illuminate\Console\Command;

class SendTelemetry extends Command
{
    protected $signature = 'telemetry:send';
    protected $description = 'Send station telemetry data to the community aggregator';

    public function handle(TelemetryService $telemetryService, TelemetryAggregatorService $aggregatorService)
    {
        if (!Setting::getValue('telemetry.enabled', false)) {
            $this->info('Telemetry is disabled, skipping.');
            return 0;
        }

        $stationData = $telemetryService->collectStationData();
        if (!$stationData) {
            $this->error('Failed to collect station data.');
            return 1;
        }

        $this->info('Sending telemetry for: ' . $stationData['name']);

        if ($aggregatorService->sendStationData($stationData)) {
            $telemetryService->markAsUpdated($stationData);
            $this->info('Telemetry sent successfully.');
            return 0;
        }

        $this->error('Failed to send telemetry data.');
        return 1;
    }
}
