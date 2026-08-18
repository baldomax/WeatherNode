<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Which radar sources appear on /radar becomes an explicit choice.
 *
 * These cards used to render for everyone, so installs outside the Netherlands
 * could not remove them. Deriving them from the main provider fixed that but
 * took them away from Dutch installs running RainViewer as their provider,
 * which is a normal setup. Neither is derivable, so it is a setting.
 *
 * The default comes from where the station is, so neither group has to go and
 * repair their settings after upgrading.
 */
return new class extends Migration
{
    private const KEY = 'radar.card_sources';

    /** Rough bounding box of the Netherlands, including the Wadden islands. */
    private const NL_LAT = [50.7, 53.7];

    private const NL_LON = [3.2, 7.3];

    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => self::KEY,
            'value' => $this->stationIsInTheNetherlands() ? 'knmi,buienradar' : '',
            'type' => 'string',
            'group' => 'radar',
            'description' => 'Additional radar sources to show on the radar page, besides the main provider',
            'options' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
    }

    private function stationIsInTheNetherlands(): bool
    {
        $latitude = DB::table('settings')->where('key', 'station.latitude')->value('value');
        $longitude = DB::table('settings')->where('key', 'station.longitude')->value('value');

        if ($latitude === null || $longitude === null) {
            return false;
        }

        return (float) $latitude >= self::NL_LAT[0] && (float) $latitude <= self::NL_LAT[1]
            && (float) $longitude >= self::NL_LON[0] && (float) $longitude <= self::NL_LON[1];
    }
};
