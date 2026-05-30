<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove unused duplicate settings from the advanced group.
     */
    public function up(): void
    {
        DB::table('settings')->whereIn('key', [
            'cron.enabled',
            'cron.check_status',
            'cron.non_cron_interval',
            'advanced.cache_duration',
            'advanced.data_retention_days',
            'advanced.close_popup_auto',
            'advanced.metar_refresh',
            'advanced.forecast_refresh',
            'advanced.earthquake_refresh',
            'advanced.airquality_refresh',
        ])->delete();
    }

    /**
     * Restore removed advanced keys with legacy default values.
     */
    public function down(): void
    {
        $now = now();
        $defaults = [
            [
                'key' => 'cron.enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'advanced',
                'description' => 'Use cron jobs for data fetching',
                'options' => null,
            ],
            [
                'key' => 'cron.check_status',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'advanced',
                'description' => 'Monitor cron job status',
                'options' => null,
            ],
            [
                'key' => 'cron.non_cron_interval',
                'value' => '120',
                'type' => 'integer',
                'group' => 'advanced',
                'description' => 'Fallback refresh interval without cron (seconds)',
                'options' => null,
            ],
            [
                'key' => 'advanced.cache_duration',
                'value' => '300',
                'type' => 'integer',
                'group' => 'advanced',
                'description' => 'API cache duration (seconds)',
                'options' => null,
            ],
            [
                'key' => 'advanced.data_retention_days',
                'value' => '0',
                'type' => 'integer',
                'group' => 'advanced',
                'description' => 'Historical data retention (days, 0=forever)',
                'options' => null,
            ],
            [
                'key' => 'advanced.close_popup_auto',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'advanced',
                'description' => 'Auto-close popups',
                'options' => null,
            ],
            [
                'key' => 'advanced.metar_refresh',
                'value' => '600',
                'type' => 'integer',
                'group' => 'advanced',
                'description' => 'METAR data refresh interval (seconds)',
                'options' => null,
            ],
            [
                'key' => 'advanced.forecast_refresh',
                'value' => '900',
                'type' => 'integer',
                'group' => 'advanced',
                'description' => 'Forecast data refresh interval (seconds)',
                'options' => null,
            ],
            [
                'key' => 'advanced.earthquake_refresh',
                'value' => '600',
                'type' => 'integer',
                'group' => 'advanced',
                'description' => 'Earthquake data refresh interval (seconds)',
                'options' => null,
            ],
            [
                'key' => 'advanced.airquality_refresh',
                'value' => '120',
                'type' => 'integer',
                'group' => 'advanced',
                'description' => 'Air quality data refresh interval (seconds)',
                'options' => null,
            ],
        ];

        foreach ($defaults as $default) {
            $exists = DB::table('settings')->where('key', $default['key'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('settings')->insert(array_merge($default, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }
};
