<?php

declare(strict_types=1);

namespace Tests\Feature\Defaults;

use Illuminate\Support\Env;
use Tests\TestCase;

/**
 * Issue #105, seen in a container. The admin panel called itself Laravel.
 *
 * .env.example sets APP_NAME, so an install that copies it is fine. Docker
 * never writes a .env, and the framework's own default then wins, so every
 * containerised install has been sitting under the name of the framework it
 * happens to be built on.
 */
class AppNameDefaultTest extends TestCase
{
    /** @return list<string> */
    private function shippedViews(): array
    {
        $found = [];
        $dir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($dir as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    /** What an install with no APP_NAME ends up calling itself. */
    public function test_the_name_falls_back_to_this_app_and_not_the_framework(): void
    {
        $repository = Env::getRepository();
        $previous = $repository->get('APP_NAME');
        $repository->clear('APP_NAME');

        try {
            $config = require base_path('config/app.php');
            $this->assertSame('WeatherNode', $config['name']);
        } finally {
            if ($previous !== null) {
                $repository->set('APP_NAME', $previous);
            }
        }
    }

    public function test_no_page_falls_back_to_the_frameworks_name(): void
    {
        $offenders = [];

        foreach ($this->shippedViews() as $file) {
            foreach (explode("\n", (string) file_get_contents($file)) as $number => $line) {
                if (preg_match("/app\.name'\s*,\s*'Laravel'/", $line)) {
                    $offenders[] = basename($file) . ':' . ($number + 1);
                }
            }
        }

        $this->assertSame([], $offenders, "A page still falls back to Laravel:\n  " . implode("\n  ", $offenders));
    }
}
