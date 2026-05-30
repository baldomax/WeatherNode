<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'auto' option to display.language and display.unit_system so
     * visitors can follow browser locale. Does not change existing values.
     */
    public function up(): void
    {
        $lang = DB::table('settings')->where('key', 'display.language')->first();
        if ($lang && $lang->options !== null && !str_contains($lang->options, 'auto:')) {
            DB::table('settings')
                ->where('key', 'display.language')
                ->update(['options' => $lang->options . ',auto:Auto (browser)', 'updated_at' => now()]);
        }

        $units = DB::table('settings')->where('key', 'display.unit_system')->first();
        if ($units && $units->options !== null && !str_contains($units->options, 'auto:')) {
            DB::table('settings')
                ->where('key', 'display.unit_system')
                ->update(['options' => $units->options . ',auto:Auto (browser locale)', 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $lang = DB::table('settings')->where('key', 'display.language')->first();
        if ($lang && $lang->options !== null && str_contains($lang->options, 'auto:')) {
            $options = preg_replace('/,?auto:[^,]*/', '', $lang->options);
            DB::table('settings')->where('key', 'display.language')->update(['options' => $options, 'updated_at' => now()]);
        }

        $units = DB::table('settings')->where('key', 'display.unit_system')->first();
        if ($units && $units->options !== null && str_contains($units->options, 'auto:')) {
            $options = preg_replace('/,?auto:[^,]*/', '', $units->options);
            DB::table('settings')->where('key', 'display.unit_system')->update(['options' => $options, 'updated_at' => now()]);
        }
    }
};
