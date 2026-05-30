<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;

class SyncWeatherUnderground extends Command
{
    protected $signature = 'weather:sync-wu
                            {--days= : Number of days back to sync (default from settings)}
                            {--force : Run even if WU sync is disabled}
                            {--skip-existing : Skip days that already have data}';

    protected $description = 'Sync recent Weather Underground history into daily summaries';

    public function handle(): int
    {
        $enabled = (bool) Setting::getValue('history.wu_sync_enabled', false);
        if (!$enabled && !$this->option('force')) {
            $this->info('WU history sync is disabled.');
            return Command::SUCCESS;
        }

        $stationId = Setting::getValue('wunderground.station_id', '');
        $apiKeyEncrypted = Setting::getValue('wunderground.api_key', '');
        $apiKey = $this->decryptSetting($apiKeyEncrypted);

        if (empty($stationId) || empty($apiKey)) {
            $this->error('WU API key or station ID not configured.');
            return Command::FAILURE;
        }

        $days = $this->resolveDays();
        $endDate = now()->toDateString();
        $startDate = now()->subDays($days - 1)->toDateString();

        $wuStart = Setting::getValue('wunderground.start_date', '');
        if (!empty($wuStart)) {
            try {
                $wuStartDate = Carbon::parse($wuStart)->toDateString();
                if ($startDate < $wuStartDate) {
                    $startDate = $wuStartDate;
                }
            } catch (\Exception $e) {
                // Ignore invalid setting.
            }
        }

        $params = [
            '--api-key' => $apiKey,
            '--station' => $stationId,
            '--start-date' => $startDate,
            '--end-date' => $endDate,
        ];

        $skipExisting = $this->option('skip-existing') || (bool) Setting::getValue('history.wu_sync_skip_existing', true);
        if ($skipExisting) {
            $params['--skip-existing'] = true;
        }

        $this->info("Syncing WU history {$startDate} to {$endDate}...");
        $exitCode = Artisan::call('weather:import-wu', $params);
        $this->output->write(Artisan::output());

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function resolveDays(): int
    {
        $value = $this->option('days');
        if ($value === null || $value === '') {
            $value = Setting::getValue('history.wu_sync_days', 7);
        }

        $days = (int) $value;
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 365) {
            $days = 365;
        }

        return $days;
    }

    private function decryptSetting(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }
}
