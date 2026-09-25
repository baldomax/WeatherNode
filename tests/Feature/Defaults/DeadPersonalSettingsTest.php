<?php

declare(strict_types=1);

namespace Tests\Feature\Defaults;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #105. station.wu_id and yrno.location carried one person's Weather
 * Underground account and home village, and nothing in the app ever read
 * either of them. The real Weather Underground integration uses its own keys,
 * and YrNoService builds its request from coordinates.
 */
class DeadPersonalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_settings_nothing_reads_are_not_shipped(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        foreach (['station.wu_id', 'yrno.location'] as $key) {
            $this->assertNull(
                Setting::where('key', $key)->first(),
                "{$key} is still seeded, and nothing reads it"
            );
        }
    }

    /** If something starts reading them, that should be a decision, not a surprise. */
    public function test_nothing_in_the_app_reads_them(): void
    {
        $offenders = [];

        foreach ([app_path(), resource_path(), base_path('routes'), base_path('config')] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

            foreach ($files as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js'], true)) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                foreach (['station.wu_id', 'yrno.location'] as $key) {
                    if (str_contains($contents, $key)) {
                        $offenders[] = $file->getFilename().' reads '.$key;
                    }
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }

    public function test_no_command_defaults_to_a_personal_account(): void
    {
        $offenders = [];

        foreach (glob(app_path('Console/Commands/*.php')) as $file) {
            foreach (explode("\n", file_get_contents($file)) as $number => $line) {
                // A filename pattern in a comment is documentation, not a default.
                if (str_contains($line, 'IUITGE8') && !str_starts_with(trim($line), '*') && !str_starts_with(trim($line), '//')) {
                    $offenders[] = basename($file).':'.($number + 1);
                }
            }
        }

        $this->assertSame([], $offenders, "A command still defaults to one person's station:\n".implode("\n", $offenders));
    }
}
