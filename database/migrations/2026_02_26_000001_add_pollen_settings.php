<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $settings = [
        // Open-Meteo: always on by default (free, no key)
        ['key' => 'pollen.openmeteo_enabled', 'value' => '1', 'type' => 'boolean'],

        // Google Pollen API (optional, paid)
        ['key' => 'pollen.google_enabled',  'value' => '0',  'type' => 'boolean'],
        ['key' => 'pollen.google_api_key',  'value' => '',   'type' => 'encrypted'],

        // Ambee Pollen API (optional, paid)
        ['key' => 'pollen.ambee_enabled',   'value' => '0',  'type' => 'boolean'],
        ['key' => 'pollen.ambee_api_key',   'value' => '',   'type' => 'encrypted'],

        // Cache duration in minutes
        ['key' => 'pollen.cache_minutes',   'value' => '60', 'type' => 'integer'],
    ];

    public function up(): void
    {
        foreach ($this->settings as $setting) {
            DB::table('settings')->insertOrIgnore([
                'key'        => $setting['key'],
                'value'      => $setting['value'],
                'type'       => $setting['type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $keys = array_column($this->settings, 'key');
        DB::table('settings')->whereIn('key', $keys)->delete();
    }
};
