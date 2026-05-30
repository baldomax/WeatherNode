<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace legacy advanced.debug_mode with advanced.log_level.
     */
    public function up(): void
    {
        $legacy = DB::table('settings')->where('key', 'advanced.debug_mode')->first();
        $existing = DB::table('settings')->where('key', 'advanced.log_level')->first();

        if (!$existing) {
            $logLevel = (($legacy->value ?? '0') === '1') ? 'debug' : 'info';
            $now = now();

            DB::table('settings')->insert([
                'key' => 'advanced.log_level',
                'value' => $logLevel,
                'type' => 'select',
                'group' => 'advanced',
                'description' => 'Application log verbosity threshold',
                'options' => 'debug:Debug,info:Info,notice:Notice,warning:Warning,error:Error,critical:Critical,alert:Alert,emergency:Emergency',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('settings')->where('key', 'advanced.debug_mode')->delete();
    }

    /**
     * Restore legacy key from advanced.log_level.
     */
    public function down(): void
    {
        $legacy = DB::table('settings')->where('key', 'advanced.debug_mode')->first();
        $logLevel = DB::table('settings')->where('key', 'advanced.log_level')->first();

        if (!$legacy && $logLevel) {
            $debugEnabled = strtolower((string) $logLevel->value) === 'debug' ? '1' : '0';
            $now = now();

            DB::table('settings')->insert([
                'key' => 'advanced.debug_mode',
                'value' => $debugEnabled,
                'type' => 'boolean',
                'group' => 'advanced',
                'description' => 'Enable debug mode',
                'options' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('settings')->where('key', 'advanced.log_level')->delete();
    }
};
