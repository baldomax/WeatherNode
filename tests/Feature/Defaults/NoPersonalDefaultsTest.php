<?php

declare(strict_types=1);

namespace Tests\Feature\Defaults;

use Tests\TestCase;

/**
 * WeatherNode ships from what began as one person's weather site. #12 cleaned
 * up the visible branding, but several defaults still point at that install:
 * its webcam, its public URL, and its name as the identifier sent to a
 * third-party API. A fresh install should reference nobody in particular.
 */
class NoPersonalDefaultsTest extends TestCase
{
    /** @return list<string> */
    private function shippedFiles(): array
    {
        return array_merge(
            glob(database_path('seeders/*.php')),
            glob(app_path('Services/*/*.php')),
            glob(app_path('Console/Commands/*.php')),
            [resource_path('views/weather/dashboard.blade.php')],
        );
    }

    public function test_no_shipped_default_points_at_the_original_install(): void
    {
        $offenders = [];

        foreach ($this->shippedFiles() as $file) {
            $contents = file_get_contents($file);
            foreach (explode("\n", $contents) as $number => $line) {
                // The seeder's own docblock explains where the values came from.
                if (str_contains($line, 'These values are taken from')) {
                    continue;
                }
                if (stripos($line, 'meteouitgeest') !== false) {
                    $offenders[] = basename($file) . ':' . ($number + 1);
                }
            }
        }

        $this->assertSame([], $offenders, "Shipped defaults still reference the original install:\n  " . implode("\n  ", $offenders));
    }

    public function test_no_command_defaults_to_a_developer_machine_path(): void
    {
        $offenders = [];

        foreach (glob(app_path('Console/Commands/*.php')) as $file) {
            if (preg_match('#/Users/[a-z]+/#i', file_get_contents($file))) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'A command defaults to a path on someone\'s laptop: ' . implode(', ', $offenders));
    }
}
