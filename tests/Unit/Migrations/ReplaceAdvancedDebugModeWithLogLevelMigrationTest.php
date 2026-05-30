<?php

declare(strict_types=1);

namespace Tests\Unit\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReplaceAdvancedDebugModeWithLogLevelMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_converts_legacy_debug_mode_to_debug_level(): void
    {
        DB::table('settings')->whereIn('key', ['advanced.log_level', 'advanced.debug_mode'])->delete();
        DB::table('settings')->insert([
            'key' => 'advanced.debug_mode',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'advanced',
            'description' => 'Enable debug mode',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertDatabaseHas('settings', [
            'key' => 'advanced.log_level',
            'value' => 'debug',
            'type' => 'select',
            'group' => 'advanced',
        ]);
        $this->assertDatabaseMissing('settings', ['key' => 'advanced.debug_mode']);
    }

    public function test_up_creates_info_level_when_legacy_setting_is_missing(): void
    {
        DB::table('settings')->whereIn('key', ['advanced.log_level', 'advanced.debug_mode'])->delete();

        $this->migration()->up();

        $this->assertDatabaseHas('settings', [
            'key' => 'advanced.log_level',
            'value' => 'info',
            'type' => 'select',
            'group' => 'advanced',
        ]);
        $this->assertDatabaseMissing('settings', ['key' => 'advanced.debug_mode']);
    }

    public function test_up_preserves_existing_log_level_value(): void
    {
        DB::table('settings')->whereIn('key', ['advanced.log_level', 'advanced.debug_mode'])->delete();
        DB::table('settings')->insert([
            [
                'key' => 'advanced.log_level',
                'value' => 'warning',
                'type' => 'select',
                'group' => 'advanced',
                'description' => 'Application log verbosity threshold',
                'options' => 'debug:Debug,info:Info,notice:Notice,warning:Warning,error:Error,critical:Critical,alert:Alert,emergency:Emergency',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'advanced.debug_mode',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'advanced',
                'description' => 'Enable debug mode',
                'options' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->migration()->up();

        $this->assertDatabaseHas('settings', [
            'key' => 'advanced.log_level',
            'value' => 'warning',
        ]);
        $this->assertDatabaseMissing('settings', ['key' => 'advanced.debug_mode']);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_02_07_170000_replace_advanced_debug_mode_with_log_level.php');
    }
}
