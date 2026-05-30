<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove legacy cloudbase display setting.
     */
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'display.cloudbase_unit')
            ->delete();
    }

    /**
     * Restore cloudbase display setting (legacy behavior).
     */
    public function down(): void
    {
        $exists = DB::table('settings')->where('key', 'display.cloudbase_unit')->exists();
        if ($exists) {
            return;
        }

        $now = now();
        DB::table('settings')->insert([
            'key' => 'display.cloudbase_unit',
            'value' => 'metres',
            'type' => 'select',
            'group' => 'display',
            'description' => 'Cloud base unit',
            'options' => 'metres:Metres,feet:Feet',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
