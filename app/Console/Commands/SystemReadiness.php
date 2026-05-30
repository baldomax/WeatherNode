<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemReadiness extends Command
{
    protected $signature = 'system:readiness
                            {--json : Output readiness report as JSON}
                            {--strict : Exit with non-zero when readiness is not PASS}';

    protected $description = 'Validate production readiness checks (scheduler, polling freshness, security baseline)';

    public function handle(): int
    {
        $checks = $this->buildChecks();

        $summary = $this->summarize($checks);
        $report = [
            'generated_at' => now()->toIso8601String(),
            'overall' => $summary['overall'],
            'counts' => $summary['counts'],
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
        } else {
            $rows = [];
            foreach ($checks as $check) {
                $rows[] = [
                    $check['id'],
                    strtoupper($check['status']),
                    $check['summary'],
                ];
            }

            $this->table(['Check', 'Status', 'Summary'], $rows);
            $this->newLine();
            $this->line('Overall: ' . strtoupper($summary['overall']));
            $this->line('Counts: pass=' . $summary['counts']['pass'] . ', warn=' . $summary['counts']['warn'] . ', fail=' . $summary['counts']['fail'] . ', skip=' . $summary['counts']['skip']);
        }

        if ($this->option('strict') && $summary['overall'] !== 'pass') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildChecks(): array
    {
        $checks = [];
        $env = (string) config('app.env', 'production');
        $debug = (bool) config('app.debug', false);
        $appKey = (string) config('app.key', '');

        $checks[] = $this->makeCheck(
            'app_env',
            $env === 'production' ? 'pass' : 'warn',
            $env === 'production'
                ? 'APP_ENV is production.'
                : "APP_ENV is '{$env}' (expected 'production')."
        );

        $checks[] = $this->makeCheck(
            'app_debug',
            (!$debug || $env !== 'production') ? 'pass' : 'fail',
            (!$debug || $env !== 'production')
                ? 'APP_DEBUG is safe for current environment.'
                : 'APP_DEBUG is enabled in production.'
        );

        $checks[] = $this->makeCheck(
            'app_key',
            $appKey !== '' ? 'pass' : 'fail',
            $appKey !== '' ? 'APP_KEY is configured.' : 'APP_KEY is missing.'
        );

        $checks[] = $this->checkEcowittReceiverSecurity();

        $schedulerLastRun = $this->parseTimestamp(Cache::get('scheduler:last_run'));
        $schedulerAge = $this->minutesAgo($schedulerLastRun);
        $checks[] = $this->makeFreshnessCheck(
            id: 'scheduler_heartbeat',
            lastSeen: $schedulerLastRun,
            maxAgeMinutes: 5,
            missingMessage: 'Scheduler heartbeat missing. Check cron/supervisor for schedule:run.',
            staleMessage: "Scheduler heartbeat stale ({$schedulerAge} min ago).",
            okMessage: "Scheduler heartbeat OK ({$schedulerAge} min ago)."
        );

        $weatherLastUpdate = $this->parseTimestamp(Cache::get('weather:last_update'));
        $weatherAge = $this->minutesAgo($weatherLastUpdate);
        $checks[] = $this->makeFreshnessCheck(
            id: 'weather_current_data',
            lastSeen: $weatherLastUpdate,
            maxAgeMinutes: 5,
            missingMessage: 'Current weather update timestamp missing.',
            staleMessage: "Current weather data stale ({$weatherAge} min ago).",
            okMessage: "Current weather data fresh ({$weatherAge} min ago)."
        );

        $checks[] = $this->checkPublicApiKey();

        foreach ($this->pollChecks() as $pollCheck) {
            $checks[] = $this->checkPollFreshness(
                id: $pollCheck['id'],
                cacheKey: $pollCheck['cache_key'],
                logFile: $pollCheck['log_file'],
                maxAgeMinutes: $pollCheck['max_age_minutes'],
                scheduleIntervalMinutes: (int) ($pollCheck['schedule_interval_minutes'] ?? 0),
                enabled: (bool) $pollCheck['enabled']
            );
        }

        return $checks;
    }

    private function checkEcowittReceiverSecurity(): array
    {
        try {
            $enabled = $this->settingBool('ecowitt.secure_mode', false);
            if (!$enabled) {
                return $this->makeCheck('ecowitt_secure_receiver', 'skip', 'Ecowitt Secure Push Mode is disabled.');
            }

            $token = trim((string) Setting::getValue('ecowitt.secure_token', ''));
            $passkey = trim((string) Setting::getValue('ecowitt.passkey', ''));

            if ($token === '' || $passkey === '') {
                return $this->makeCheck(
                    'ecowitt_secure_receiver',
                    'fail',
                    'Ecowitt Secure Push Mode is enabled but token/passkey is missing.'
                );
            }

            return $this->makeCheck(
                'ecowitt_secure_receiver',
                'pass',
                'Ecowitt Secure Push Mode is configured (token + passkey).'
            );
        } catch (Throwable $e) {
            return $this->makeCheck('ecowitt_secure_receiver', 'fail', 'Could not validate Ecowitt receiver security: ' . $e->getMessage());
        }
    }

    private function checkPublicApiKey(): array
    {
        try {
            if (!Schema::hasTable('api_keys')) {
                return $this->makeCheck('api_public_key', 'fail', 'api_keys table is missing.');
            }

            $hasPublicKey = ApiKey::query()
                ->where('is_public', true)
                ->whereNull('revoked_at')
                ->exists();

            return $this->makeCheck(
                'api_public_key',
                $hasPublicKey ? 'pass' : 'fail',
                $hasPublicKey
                    ? 'Public API key is available.'
                    : 'No active public API key found.'
            );
        } catch (Throwable $e) {
            return $this->makeCheck('api_public_key', 'fail', 'Could not validate public API key: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pollChecks(): array
    {
        return [
            [
                'id' => 'poll_forecast',
                'cache_key' => 'poll_timestamp_forecast',
                'log_file' => 'poll-forecast.log',
                'max_age_minutes' => 45,
                'schedule_interval_minutes' => 30,
                'enabled' => $this->settingBool('yrno.enabled', false),
            ],
            [
                'id' => 'poll_alerts',
                'cache_key' => 'poll_timestamp_alerts',
                'log_file' => 'poll-alerts.log',
                'max_age_minutes' => 25,
                'schedule_interval_minutes' => 15,
                'enabled' => $this->settingBool('alerts.enabled', false),
            ],
            [
                'id' => 'poll_airquality',
                'cache_key' => 'poll_timestamp_airquality',
                'log_file' => 'poll-airquality.log',
                'max_age_minutes' => 45,
                'schedule_interval_minutes' => 30,
                'enabled' => $this->settingBool('waqi.enabled', false) || $this->settingBool('luftdaten.enabled', false) || $this->settingBool('luftdaten_noise.enabled', false),
            ],
            [
                'id' => 'poll_pollen',
                'cache_key' => 'poll_timestamp_pollen',
                'log_file' => 'poll-pollen.log',
                'max_age_minutes' => 80,
                'schedule_interval_minutes' => 60,
                'enabled' => $this->settingBool('pollen.openmeteo_enabled', true)
                    || $this->settingBool('pollen.google_enabled', false)
                    || $this->settingBool('pollen.ambee_enabled', false),
            ],
            [
                'id' => 'poll_metar',
                'cache_key' => 'poll_timestamp_metar',
                'log_file' => 'poll-metar.log',
                'max_age_minutes' => 45,
                'schedule_interval_minutes' => 30,
                'enabled' => $this->settingBool('metar.enabled', false),
            ],
            [
                'id' => 'poll_earthquake',
                'cache_key' => 'poll_timestamp_earthquake',
                'log_file' => 'poll-earthquake.log',
                'max_age_minutes' => 25,
                'schedule_interval_minutes' => 15,
                'enabled' => $this->settingBool('earthquakes.enabled', false),
            ],
            [
                'id' => 'poll_aurora',
                'cache_key' => 'poll_timestamp_aurora',
                'log_file' => 'poll-aurora.log',
                'max_age_minutes' => 45,
                'schedule_interval_minutes' => 30,
                'enabled' => true,
            ],
            [
                'id' => 'poll_iss',
                'cache_key' => 'poll_timestamp_iss',
                'log_file' => 'poll-iss.log',
                'max_age_minutes' => 80,
                'schedule_interval_minutes' => 60,
                'enabled' => true,
            ],
            [
                'id' => 'poll_astronomy',
                'cache_key' => 'poll_timestamp_astronomy',
                'log_file' => 'poll-astronomy.log',
                'max_age_minutes' => 80,
                'schedule_interval_minutes' => 60,
                'enabled' => true,
            ],
            [
                'id' => 'poll_knmi_nowcast',
                'cache_key' => 'poll_timestamp_knmi_nowcast',
                'log_file' => 'poll-knmi-nowcast.log',
                'max_age_minutes' => 20,
                'schedule_interval_minutes' => 10,
                'enabled' => $this->settingBool('radar.nowcast_enabled', false),
            ],
            [
                'id' => 'poll_solar_forecast',
                'cache_key' => 'poll_timestamp_solar_forecast',
                'log_file' => 'poll-solar-forecast.log',
                'max_age_minutes' => 45,
                'schedule_interval_minutes' => 30,
                'enabled' => $this->settingBool('solar_forecast.enabled', false),
            ],
            [
                'id' => 'poll_knmi_wms',
                'cache_key' => 'poll_timestamp_knmi_wms',
                'log_file' => 'poll-knmi-wms.log',
                'max_age_minutes' => 80,
                'schedule_interval_minutes' => 60,
                'enabled' => $this->settingBool('satellite.wms_enabled', false),
            ],
        ];
    }

    private function checkPollFreshness(
        string $id,
        string $cacheKey,
        string $logFile,
        int $maxAgeMinutes,
        int $scheduleIntervalMinutes,
        bool $enabled
    ): array {
        if (!$enabled) {
            return $this->makeCheck($id, 'skip', 'Disabled by settings.');
        }

        $lastSuccess = $this->parseTimestamp(Cache::get($cacheKey));
        $attemptCacheKey = str_starts_with($cacheKey, 'poll_timestamp_')
            ? str_replace('poll_timestamp_', 'poll_attempt_timestamp_', $cacheKey)
            : null;
        $lastAttempt = $attemptCacheKey ? $this->parseTimestamp(Cache::get($attemptCacheKey)) : null;

        if ($lastAttempt === null) {
            $logPath = storage_path('logs/' . $logFile);
            if (is_file($logPath)) {
                $lastAttempt = Carbon::createFromTimestamp((int) filemtime($logPath));
            }
        }

        $successAge = $this->minutesAgo($lastSuccess);
        $attemptAge = $this->minutesAgo($lastAttempt);
        $attemptFresh = $attemptAge !== null && $attemptAge <= $maxAgeMinutes;
        $staleWarnCeiling = $maxAgeMinutes + max(0, $scheduleIntervalMinutes);
        $withinGraceWindow = $successAge !== null && $successAge <= $staleWarnCeiling;

        if ($lastSuccess === null) {
            if ($attemptFresh) {
                return $this->makeCheck($id, 'warn', "No successful refresh yet, but scheduler attempted {$attemptAge} min ago.", [
                    'last_success' => null,
                    'last_attempt' => $lastAttempt?->toIso8601String(),
                    'attempt_age_minutes' => $attemptAge,
                    'max_age_minutes' => $maxAgeMinutes,
                    'warn_ceiling_minutes' => $staleWarnCeiling,
                ]);
            }

            return $this->makeCheck($id, 'fail', "No freshness signal found ({$cacheKey} or {$logFile}).");
        }

        if ($successAge === null || $successAge > $maxAgeMinutes) {
            if ($withinGraceWindow) {
                return $this->makeCheck(
                    $id,
                    'warn',
                    "Slightly stale ({$successAge} min ago, max {$maxAgeMinutes}) and within one schedule window ({$staleWarnCeiling} min).",
                    [
                        'last_success' => $lastSuccess->toIso8601String(),
                        'success_age_minutes' => $successAge,
                        'last_attempt' => $lastAttempt?->toIso8601String(),
                        'attempt_age_minutes' => $attemptAge,
                        'max_age_minutes' => $maxAgeMinutes,
                        'warn_ceiling_minutes' => $staleWarnCeiling,
                    ]
                );
            }

            if ($attemptFresh) {
                return $this->makeCheck(
                    $id,
                    'warn',
                    "Last successful refresh is stale ({$successAge} min ago, max {$maxAgeMinutes}), but attempts are still running ({$attemptAge} min ago).",
                    [
                        'last_success' => $lastSuccess->toIso8601String(),
                        'success_age_minutes' => $successAge,
                        'last_attempt' => $lastAttempt?->toIso8601String(),
                        'attempt_age_minutes' => $attemptAge,
                        'max_age_minutes' => $maxAgeMinutes,
                        'warn_ceiling_minutes' => $staleWarnCeiling,
                    ]
                );
            }

            return $this->makeCheck($id, 'fail', "Stale ({$successAge} min ago, max {$maxAgeMinutes}).", [
                'last_seen' => $lastSuccess->toIso8601String(),
                'age_minutes' => $successAge,
                'max_age_minutes' => $maxAgeMinutes,
                'last_attempt' => $lastAttempt?->toIso8601String(),
                'attempt_age_minutes' => $attemptAge,
                'warn_ceiling_minutes' => $staleWarnCeiling,
            ]);
        }

        return $this->makeCheck($id, 'pass', "Fresh ({$successAge} min ago).", [
            'last_seen' => $lastSuccess->toIso8601String(),
            'age_minutes' => $successAge,
            'max_age_minutes' => $maxAgeMinutes,
            'last_attempt' => $lastAttempt?->toIso8601String(),
            'attempt_age_minutes' => $attemptAge,
            'warn_ceiling_minutes' => $staleWarnCeiling,
        ]);
    }

    private function makeFreshnessCheck(
        string $id,
        ?Carbon $lastSeen,
        int $maxAgeMinutes,
        string $missingMessage,
        string $staleMessage,
        string $okMessage
    ): array {
        if ($lastSeen === null) {
            return $this->makeCheck($id, 'fail', $missingMessage);
        }

        $age = $this->minutesAgo($lastSeen);
        if ($age === null || $age > $maxAgeMinutes) {
            return $this->makeCheck($id, 'fail', $staleMessage, [
                'last_seen' => $lastSeen->toIso8601String(),
                'age_minutes' => $age,
                'max_age_minutes' => $maxAgeMinutes,
            ]);
        }

        return $this->makeCheck($id, 'pass', $okMessage, [
            'last_seen' => $lastSeen->toIso8601String(),
            'age_minutes' => $age,
            'max_age_minutes' => $maxAgeMinutes,
        ]);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function makeCheck(string $id, string $status, string $summary, array $meta = []): array
    {
        return [
            'id' => $id,
            'status' => $status,
            'summary' => $summary,
            'meta' => $meta,
        ];
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable $e) {
                return null;
            }
        }

        return null;
    }

    private function minutesAgo(?Carbon $timestamp): ?float
    {
        return $timestamp ? round($timestamp->diffInSeconds(now()) / 60, 2) : null;
    }

    private function settingBool(string $key, bool $default): bool
    {
        try {
            return (bool) Setting::getValue($key, $default);
        } catch (Throwable $e) {
            return $default;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @return array{overall: string, counts: array<string, int>}
     */
    private function summarize(array $checks): array
    {
        $counts = [
            'pass' => 0,
            'warn' => 0,
            'fail' => 0,
            'skip' => 0,
        ];

        foreach ($checks as $check) {
            $status = $check['status'] ?? 'warn';
            if (!array_key_exists($status, $counts)) {
                $status = 'warn';
            }
            $counts[$status]++;
        }

        $overall = 'pass';
        if ($counts['fail'] > 0) {
            $overall = 'fail';
        } elseif ($counts['warn'] > 0) {
            $overall = 'warn';
        }

        return [
            'overall' => $overall,
            'counts' => $counts,
        ];
    }
}
