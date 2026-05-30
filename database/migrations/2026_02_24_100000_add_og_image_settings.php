<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('settings')->upsert([
            [
                'key'         => 'og.enabled',
                'value'       => '0',
                'type'        => 'boolean',
                'group'       => 'og',
                'description' => 'Generate dynamic Open Graph images for social sharing (requires GD or Imagick)',
                'options'     => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'og.driver',
                'value'       => 'auto',
                'type'        => 'string',
                'group'       => 'og',
                'description' => 'Image processing driver to use for OG image generation',
                'options'     => 'auto:Auto-detect (recommended),gd:GD (PHP extension),imagick:Imagick (ImageMagick)',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ], ['key'], ['value', 'type', 'group', 'description', 'options', 'updated_at']);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['og.enabled', 'og.driver'])->delete();
    }
};
