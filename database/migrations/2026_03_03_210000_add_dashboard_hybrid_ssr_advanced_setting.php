<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEY = 'dashboard.hybrid_ssr_enabled';

    public function up(): void
    {
        $existing = DB::table('settings')->where('key', self::KEY)->first();

        if ($existing) {
            DB::table('settings')->where('key', self::KEY)->update([
                'type' => 'boolean',
                'group' => 'advanced',
                'description' => 'Enable hybrid SSR for dashboard first render (server HTML + JS hydration)',
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('settings')->insert([
            'key' => self::KEY,
            'value' => '0',
            'type' => 'boolean',
            'group' => 'advanced',
            'description' => 'Enable hybrid SSR for dashboard first render (server HTML + JS hydration)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
    }
};
