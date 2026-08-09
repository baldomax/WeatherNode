<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEY = 'widgets.stats_enabled';

    /**
     * Every tile, so upgrading installs keep the ten-tile bar they have today.
     */
    private const DEFAULT_VALUE = '["today","max_wind","precipitation","aqi","uv","earthquakes","next_rain","advisory","vs_yesterday","best_time"]';

    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => self::KEY,
            'value' => self::DEFAULT_VALUE,
            'type' => 'json',
            'group' => 'widgets',
            'description' => 'Enabled Quick Stats tiles on the dashboard',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
    }
};
