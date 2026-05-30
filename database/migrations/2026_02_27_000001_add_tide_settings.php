<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['key' => 'tide.enabled',      'value' => '0',         'type' => 'boolean', 'group' => 'tide'],
            ['key' => 'tide.station_code', 'value' => 'IJMH',      'type' => 'string',  'group' => 'tide'],
            ['key' => 'tide.station_name', 'value' => 'IJmuiden',  'type' => 'string',  'group' => 'tide'],
        ];

        foreach ($defaults as $row) {
            Setting::firstOrCreate(
                ['key' => $row['key']],
                ['value' => $row['value'], 'type' => $row['type'], 'group' => $row['group']]
            );
        }
    }

    public function down(): void
    {
        Setting::whereIn('key', ['tide.enabled', 'tide.station_code', 'tide.station_name'])->delete();
    }
};
