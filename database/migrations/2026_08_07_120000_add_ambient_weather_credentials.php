<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('settings')->insertOrIgnore([
            'key' => 'ambient.application_key',
            'value' => '',
            'type' => 'encrypted',
            'group' => 'ambient',
            'description' => 'Ambient Weather Application Key',
            'options' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $legacyMac = DB::table('settings')->where('key', 'ambient.device_id')->first();
        $currentMac = DB::table('settings')->where('key', 'ambient.mac_address')->first();

        if ($legacyMac && ! $currentMac) {
            DB::table('settings')
                ->where('key', 'ambient.device_id')
                ->update([
                    'key' => 'ambient.mac_address',
                    'type' => 'string',
                    'group' => 'ambient',
                    'description' => 'Ambient Weather device MAC',
                    'updated_at' => $now,
                ]);
        } elseif ($legacyMac) {
            DB::table('settings')->where('key', 'ambient.device_id')->delete();
        } elseif (! $currentMac) {
            DB::table('settings')->insert([
                'key' => 'ambient.mac_address',
                'value' => '',
                'type' => 'string',
                'group' => 'ambient',
                'description' => 'Ambient Weather device MAC',
                'options' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('setting.ambient.application_key');
        Cache::forget('setting.ambient.device_id');
        Cache::forget('setting.ambient.mac_address');
    }

    public function down(): void
    {
        $mac = DB::table('settings')->where('key', 'ambient.mac_address')->first();
        $legacyMac = DB::table('settings')->where('key', 'ambient.device_id')->first();

        if ($mac && ! $legacyMac) {
            DB::table('settings')
                ->where('key', 'ambient.mac_address')
                ->update([
                    'key' => 'ambient.device_id',
                    'description' => 'Ambient Weather device MAC',
                    'updated_at' => now(),
                ]);
        }

        DB::table('settings')->where('key', 'ambient.application_key')->delete();

        Cache::forget('setting.ambient.application_key');
        Cache::forget('setting.ambient.device_id');
        Cache::forget('setting.ambient.mac_address');
    }
};
