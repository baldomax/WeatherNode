<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A WeatherLink Live on the owner's own network as a live data source (#158),
 * listed right after the WeatherLink cloud sources.
 */
return new class extends Migration
{
    private const OPTION = 'wll_local:WeatherLink Live (local network)';

    private const AFTER = 'DWL_v2api_demo:WeatherLink Cloud v2 (Demo Mode),';

    public function up(): void
    {
        $options = (string) DB::table('settings')->where('key', 'livedata.format')->value('options');
        if ($options === '' || str_contains($options, self::OPTION)) {
            return;
        }

        $options = str_contains($options, self::AFTER)
            ? str_replace(self::AFTER, self::AFTER . self::OPTION . ',', $options)
            : $options . ',' . self::OPTION;

        DB::table('settings')->where('key', 'livedata.format')->update(['options' => $options, 'updated_at' => now()]);
        Cache::forget('setting.livedata.format');
    }

    public function down(): void
    {
        $options = (string) DB::table('settings')->where('key', 'livedata.format')->value('options');
        $options = str_replace([self::OPTION . ',', ',' . self::OPTION], '', $options);

        DB::table('settings')->where('key', 'livedata.format')->update(['options' => $options, 'updated_at' => now()]);
        Cache::forget('setting.livedata.format');
    }
};
