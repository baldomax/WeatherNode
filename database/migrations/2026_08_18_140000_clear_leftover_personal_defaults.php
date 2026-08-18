<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Two settings shipped with values pointing at the install this project grew
 * out of: its webcam image and its public URL. Anyone who never changed them
 * has been showing someone else's webcam, and reporting someone else's address
 * as their own site.
 *
 * Only rows still holding the shipped value are cleared. Nobody else would
 * have typed these, so an exact match means the setting was never touched.
 */
return new class extends Migration
{
    private const LEFTOVERS = [
        'webcam.url' => 'https://www.meteouitgeest.nl/thumbnail/image.jpg',
        'station.server_url' => 'https://meteouitgeest.nl/',
    ];

    public function up(): void
    {
        foreach (self::LEFTOVERS as $key => $shippedValue) {
            DB::table('settings')
                ->where('key', $key)
                ->where('value', $shippedValue)
                ->update(['value' => '', 'updated_at' => now()]);

            Cache::forget("setting.{$key}");
        }
    }

    public function down(): void
    {
        // Restoring someone else's URLs would be the bug, not the fix.
    }
};
