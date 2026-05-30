<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove unused legacy display settings and clarify display.theme semantics.
     */
    public function up(): void
    {
        DB::table('settings')->whereIn('key', [
            'display.language_selector',
            'display.theme_selector',
            'display.date_format',
            'display.time_format',
            'display.time_format_short',
            'display.refresh_interval',
            'display.show_indoor',
            'display.show_feels_like',
            'display.use_rounded_values',
            'display.show_borders',
            'display.show_extra_links',
            'display.country_flag',
            'display.kiss_mode',
        ])->delete();

        DB::table('settings')
            ->where('key', 'display.theme')
            ->update([
                'description' => 'Default admin interface theme',
                'updated_at' => now(),
            ]);
    }

    /**
     * Restore removed display settings with legacy defaults.
     */
    public function down(): void
    {
        $now = now();
        $defaults = [
            [
                'key' => 'display.language_selector',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'display',
                'description' => 'Show language selector to visitors',
                'options' => null,
            ],
            [
                'key' => 'display.theme_selector',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'display',
                'description' => 'Show theme selector to visitors',
                'options' => null,
            ],
            [
                'key' => 'display.date_format',
                'value' => 'd-m-Y',
                'type' => 'select',
                'group' => 'display',
                'description' => 'Date format',
                'options' => 'd-m-Y:DD-MM-YYYY,m/d/Y:MM/DD/YYYY,Y-m-d:YYYY-MM-DD',
            ],
            [
                'key' => 'display.time_format',
                'value' => '24',
                'type' => 'select',
                'group' => 'display',
                'description' => 'Clock format',
                'options' => '24:24-hour (H:i:s),12:12-hour (h:i:s A)',
            ],
            [
                'key' => 'display.time_format_short',
                'value' => 'H:i',
                'type' => 'string',
                'group' => 'display',
                'description' => 'Short time format',
                'options' => null,
            ],
            [
                'key' => 'display.refresh_interval',
                'value' => '60',
                'type' => 'select',
                'group' => 'display',
                'description' => 'Auto-refresh interval (seconds)',
                'options' => '30:30s,60:1 min,120:2 min,300:5 min,600:10 min,0:Disabled',
            ],
            [
                'key' => 'display.show_indoor',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'display',
                'description' => 'Show indoor temperature/humidity',
                'options' => null,
            ],
            [
                'key' => 'display.show_feels_like',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'display',
                'description' => 'Always show feels-like temperature',
                'options' => null,
            ],
            [
                'key' => 'display.use_rounded_values',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'display',
                'description' => 'Use rounded values in display',
                'options' => null,
            ],
            [
                'key' => 'display.show_borders',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'display',
                'description' => 'Show borders around cards',
                'options' => null,
            ],
            [
                'key' => 'display.show_extra_links',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'display',
                'description' => 'Show external links section',
                'options' => null,
            ],
            [
                'key' => 'display.country_flag',
                'value' => 'nl.svg',
                'type' => 'string',
                'group' => 'display',
                'description' => 'Country flag image file',
                'options' => null,
            ],
            [
                'key' => 'display.kiss_mode',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'display',
                'description' => 'KISS mode (simplified interface)',
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

        DB::table('settings')
            ->where('key', 'display.theme')
            ->update([
                'description' => 'Default theme',
                'updated_at' => $now,
            ]);
    }
};
