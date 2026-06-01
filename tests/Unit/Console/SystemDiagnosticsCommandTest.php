<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SystemDiagnosticsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_writes_snapshot_file_with_expected_sections(): void
    {
        Setting::setValue('advanced.log_level', 'warning', 'select', 'advanced');

        $relativeOutput = 'diagnostics/test-system-diagnostics.json';
        $absoluteOutput = storage_path('app/' . $relativeOutput);

        File::delete($absoluteOutput);

        $exitCode = Artisan::call('system:diagnostics', [
            '--output' => $relativeOutput,
            '--pretty' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($absoluteOutput);

        $data = json_decode((string) File::get($absoluteOutput), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('generated_at', $data);
        $this->assertArrayHasKey('app', $data);
        $this->assertArrayHasKey('logging', $data);
        $this->assertArrayHasKey('scheduler', $data);
        $this->assertArrayHasKey('weather', $data);
        $this->assertArrayHasKey('storage', $data);
        $this->assertArrayHasKey('recent_errors', $data);
        $this->assertSame('warning', $data['logging']['runtime_log_level'] ?? null);

        File::delete($absoluteOutput);
    }
}
