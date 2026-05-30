<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Throwable;

class SystemDiagnostics extends Command
{
    protected $signature = 'system:diagnostics
                            {--output= : Output path (relative to storage/app or absolute)}
                            {--pretty : Pretty-print JSON output}';

    protected $description = 'Generate a diagnostics snapshot JSON for troubleshooting';

    private const VALID_LOG_LEVELS = [
        'debug',
        'info',
        'notice',
        'warning',
        'error',
        'critical',
        'alert',
        'emergency',
    ];

    public function handle(): int
    {
        $outputPath = $this->resolveOutputPath((string) $this->option('output'));
        File::ensureDirectoryExists(dirname($outputPath));

        $snapshot = [
            'generated_at' => now()->toIso8601String(),
            'app' => $this->collectAppInfo(),
            'logging' => $this->collectLoggingInfo(),
            'scheduler' => $this->collectSchedulerInfo(),
            'weather' => $this->collectWeatherInfo(),
            'storage' => $this->collectStorageInfo(),
            'recent_errors' => $this->collectRecentErrors(),
        ];

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($snapshot, $flags);
        if ($json === false) {
            $this->error('Failed to encode diagnostics snapshot to JSON.');
            return self::FAILURE;
        }

        File::put($outputPath, $json . PHP_EOL);
        $this->info("Diagnostics snapshot written to: {$outputPath}");

        return self::SUCCESS;
    }

    private function resolveOutputPath(string $outputOption): string
    {
        if ($outputOption !== '') {
            if (str_starts_with($outputOption, '/')) {
                return $outputOption;
            }

            return storage_path('app/' . ltrim($outputOption, '/'));
        }

        $filename = 'system-diagnostics-' . now()->format('Ymd_His') . '.json';

        return storage_path('app/diagnostics/' . $filename);
    }

    private function collectAppInfo(): array
    {
        return [
            'name' => config('app.name'),
            'env' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'locale' => app()->getLocale(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'version_file' => $this->readVersionFile(),
            'cache_driver' => config('cache.default'),
            'database_connection' => config('database.default'),
            'queue_connection' => config('queue.default'),
        ];
    }

    private function collectLoggingInfo(): array
    {
        $channels = config('logging.channels', []);
        $channelInfo = [];

        foreach ($channels as $name => $channel) {
            if (!is_array($channel)) {
                continue;
            }

            $channelInfo[$name] = array_filter([
                'driver' => $channel['driver'] ?? null,
                'level' => $channel['level'] ?? null,
                'path' => $channel['path'] ?? null,
                'days' => $channel['days'] ?? null,
                'uses_tap' => !empty($channel['tap']),
            ], static fn ($value) => $value !== null && $value !== []);
        }

        return [
            'default_channel' => config('logging.default'),
            'stack_channels' => $this->normalizeStackChannels(config('logging.channels.stack.channels', [])),
            'env_log_level' => env('LOG_LEVEL', 'info'),
            'runtime_log_level' => $this->resolveRuntimeLogLevel(),
            'channels' => $channelInfo,
        ];
    }

    private function collectSchedulerInfo(): array
    {
        $lastRun = $this->parseTimestamp(Cache::get('scheduler:last_run'));
        $minutesSinceLastRun = $this->minutesAgo($lastRun);

        $status = 'never';
        if ($minutesSinceLastRun !== null) {
            $status = $minutesSinceLastRun <= 5 ? 'running' : 'stale';
        }

        return [
            'status' => $status,
            'last_run' => $lastRun?->toIso8601String(),
            'minutes_since_last_run' => $minutesSinceLastRun,
            'expected_cron' => '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1',
            'task_logs' => $this->collectTaskLogStats(),
        ];
    }

    private function collectWeatherInfo(): array
    {
        $lastUpdate = $this->parseTimestamp(Cache::get('weather:last_update'));

        return [
            'last_weather_update' => $lastUpdate?->toIso8601String(),
            'minutes_since_last_weather_update' => $this->minutesAgo($lastUpdate),
            'poll_timestamps' => $this->collectPollTimestamps(),
            'data_source_health' => Cache::get('data_source_health', []),
        ];
    }

    private function collectStorageInfo(): array
    {
        $logsPath = storage_path('logs');
        $files = File::exists($logsPath) ? File::files($logsPath) : [];

        $totalBytes = 0;
        $entries = [];

        foreach ($files as $file) {
            $size = $file->getSize();
            $totalBytes += $size;

            $entries[] = [
                'file' => $file->getFilename(),
                'size_bytes' => $size,
                'modified_at' => Carbon::createFromTimestamp($file->getMTime())->toIso8601String(),
            ];
        }

        usort($entries, static fn (array $a, array $b) => $b['size_bytes'] <=> $a['size_bytes']);

        return [
            'logs_directory' => $logsPath,
            'log_file_count' => count($entries),
            'total_log_size_bytes' => $totalBytes,
            'largest_log_files' => array_slice($entries, 0, 10),
        ];
    }

    private function collectTaskLogStats(): array
    {
        $taskLogs = [
            'weather-fetch.log',
            'poll-forecast.log',
            'poll-alerts.log',
            'poll-airquality.log',
            'poll-pollen.log',
            'poll-metar.log',
            'poll-earthquake.log',
            'poll-aurora.log',
            'poll-iss.log',
            'poll-astronomy.log',
            'poll-knmi-nowcast.log',
            'poll-solar-forecast.log',
            'poll-knmi-wms.log',
            'generate-nlg.log',
            'weather-summary.log',
            'wu-history-sync.log',
            'cache-cleanup.log',
            'radar-cleanup.log',
            'visitor-rollup.log',
            'geoip-update.log',
        ];

        $result = [];

        foreach ($taskLogs as $logFile) {
            $path = storage_path('logs/' . $logFile);
            if (!File::exists($path)) {
                $result[$logFile] = ['exists' => false];
                continue;
            }

            $modifiedAt = Carbon::createFromTimestamp((int) filemtime($path));
            $result[$logFile] = [
                'exists' => true,
                'size_bytes' => filesize($path) ?: 0,
                'modified_at' => $modifiedAt->toIso8601String(),
                'minutes_since_update' => $this->minutesAgo($modifiedAt),
            ];
        }

        return $result;
    }

    private function collectPollTimestamps(): array
    {
        $keys = [
            'poll_timestamp_forecast',
            'poll_timestamp_alerts',
            'poll_timestamp_airquality',
            'poll_timestamp_pollen',
            'poll_timestamp_metar',
            'poll_timestamp_earthquake',
            'poll_timestamp_aurora',
            'poll_timestamp_iss',
            'poll_timestamp_astronomy',
            'poll_timestamp_knmi_nowcast',
            'poll_timestamp_solar_forecast',
            'poll_timestamp_knmi_wms',
        ];

        $timestamps = [];

        foreach ($keys as $key) {
            $parsed = $this->parseTimestamp(Cache::get($key));
            $timestamps[$key] = $parsed ? [
                'timestamp' => $parsed->toIso8601String(),
                'minutes_ago' => $this->minutesAgo($parsed),
            ] : null;
        }

        return $timestamps;
    }

    private function collectRecentErrors(int $limit = 20): array
    {
        $logFiles = glob(storage_path('logs/laravel*.log')) ?: [];
        if ($logFiles === []) {
            return [];
        }

        usort($logFiles, static fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
        $latestLog = $logFiles[0];

        if (!is_readable($latestLog)) {
            return [];
        }

        $errors = [];
        $file = new \SplFileObject($latestLog, 'r');

        while (!$file->eof()) {
            $line = trim((string) $file->fgets());
            if ($line !== '' && (stripos($line, '.ERROR:') !== false || stripos($line, 'error') !== false)) {
                $errors[] = $line;
                if (count($errors) > $limit) {
                    array_shift($errors);
                }
            }
        }

        return $errors;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function minutesAgo(?Carbon $timestamp): ?float
    {
        if ($timestamp === null) {
            return null;
        }

        return round(abs($timestamp->diffInSeconds(now(), false)) / 60, 2);
    }

    private function resolveRuntimeLogLevel(): string
    {
        try {
            $level = strtolower((string) Setting::getValue('advanced.log_level', env('LOG_LEVEL', 'info')));
        } catch (Throwable) {
            $level = strtolower((string) env('LOG_LEVEL', 'info'));
        }

        return in_array($level, self::VALID_LOG_LEVELS, true) ? $level : 'info';
    }

    private function normalizeStackChannels(mixed $channels): array
    {
        if (is_string($channels)) {
            return array_values(array_filter(array_map('trim', explode(',', $channels))));
        }

        if (is_array($channels)) {
            return array_values(array_filter(array_map(static fn ($channel) => (string) $channel, $channels)));
        }

        return [];
    }

    private function readVersionFile(): ?string
    {
        $path = base_path('VERSION');
        if (!File::exists($path)) {
            return null;
        }

        $content = trim((string) File::get($path));

        return $content !== '' ? $content : null;
    }
}
