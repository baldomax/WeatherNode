<?php

declare(strict_types=1);

namespace Tests\Unit\Migrations;

use Tests\TestCase;

/**
 * A migration that adds a column to an existing table can meet that column
 * already present: an interrupted run, a restored database, or the volume that
 * used to shadow database/migrations with an older copy. Laravel aborts the
 * whole migrate run on the error, so every later migration and the seeder are
 * skipped too, which surfaces as an app that boots with blank settings pages
 * rather than as a migration failure.
 */
class MigrationsAreRerunnableTest extends TestCase
{
    /** Column operations that are safe without a guard. */
    private const NON_ADDITIVE = ['dropColumn', 'dropIndex', 'dropForeign', 'dropUnique', 'renameColumn', 'index', 'unique', 'foreign', 'primary'];

    /** @return array<string, string> file => up() body */
    private function migrationUpBodies(): array
    {
        $bodies = [];
        foreach (glob(database_path('migrations/*.php')) as $path) {
            $source = file_get_contents($path);
            if (!str_contains($source, 'function up')) {
                continue;
            }
            $up = substr($source, strpos($source, 'function up'));
            $up = explode('function down', $up)[0];
            $bodies[basename($path)] = $up;
        }

        return $bodies;
    }

    public function test_every_column_addition_checks_whether_the_column_is_already_there(): void
    {
        $unguarded = [];

        foreach ($this->migrationUpBodies() as $file => $up) {
            if (!str_contains($up, 'Schema::table(')) {
                continue;
            }

            preg_match_all('/\$table->(\w+)\(/', $up, $matches);
            $additive = array_diff($matches[1], self::NON_ADDITIVE);

            if ($additive !== [] && !str_contains($up, 'hasColumn')) {
                $unguarded[] = $file;
            }
        }

        $this->assertSame(
            [],
            $unguarded,
            "These migrations add a column without a Schema::hasColumn() guard, so re-running them aborts the whole migrate run:\n  " . implode("\n  ", $unguarded)
        );
    }
}
