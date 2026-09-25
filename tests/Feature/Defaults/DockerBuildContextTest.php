<?php

declare(strict_types=1);

namespace Tests\Feature\Defaults;

use Tests\TestCase;

/**
 * A local docker build copies the working directory, not the git tree, so a
 * gitignored database/database.sqlite was still baked into the image: 59MB of
 * the builder's own readings, settings and encrypted API keys.
 *
 * It was worse than dead weight. docker/entrypoint.sh:138 treats a file at
 * database/database.sqlite as a pre-2026.08 install and switches the app onto
 * it, ignoring the configured volume, so the container quietly ran on the
 * builder's data instead of the operator's.
 */
class DockerBuildContextTest extends TestCase
{
    public function test_no_database_can_be_copied_into_the_image(): void
    {
        $rules = file(base_path('.dockerignore'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $rules = array_filter($rules, fn ($line) => !str_starts_with(trim($line), '#'));

        $this->assertContains(
            '**/*.sqlite',
            $rules,
            'A docker build would copy database/database.sqlite into the image.'
        );
    }

    /**
     * A bare *.sqlite* only matches the context root, which is why the file in
     * database/ went in unnoticed. Verified against a real build.
     */
    public function test_the_rule_reaches_nested_directories(): void
    {
        $rules = file_get_contents(base_path('.dockerignore'));

        foreach (['**/*.sqlite', '**/*.sqlite-wal', '**/*.sqlite-shm'] as $pattern) {
            $this->assertStringContainsString($pattern, $rules, "{$pattern} is missing");
        }
    }

    /** The entrypoint creates a blank one, so the image needs no database. */
    public function test_the_entrypoint_still_creates_a_database_when_none_exists(): void
    {
        $this->assertStringContainsString(
            'touch "$SQLITE_PATH"',
            file_get_contents(base_path('docker/entrypoint.sh')),
            'Nothing creates the SQLite file, so excluding it from the image would break a fresh container.'
        );
    }
}
