<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if old key exists
        $oldSetting = DB::table('settings')->where('key', 'waqi.index_type')->first();

        if ($oldSetting) {
            // Delete any existing new key first (in case of re-run)
            DB::table('settings')->where('key', 'airquality.index_type')->delete();

            // Rename the key and update description
            DB::table('settings')
                ->where('key', 'waqi.index_type')
                ->update([
                    'key' => 'airquality.index_type',
                    'description' => 'Index Type',
                ]);

            // Clear cache
            Cache::forget('setting.waqi.index_type');
            Cache::forget('setting.airquality.index_type');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $newSetting = DB::table('settings')->where('key', 'airquality.index_type')->first();

        if ($newSetting) {
            DB::table('settings')
                ->where('key', 'airquality.index_type')
                ->update([
                    'key' => 'waqi.index_type',
                    'description' => 'Air quality index type',
                ]);

            Cache::forget('setting.waqi.index_type');
            Cache::forget('setting.airquality.index_type');
        }
    }
};
