<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\DockerDatabaseLayout;
use Tests\TestCase;

class DockerDatabaseLayoutTest extends TestCase
{
    private function useSqliteAt(string $path): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $path,
        ]);
    }

    /**
     * The container markers cannot be created in a test run, so anything that
     * depends on them is asserted against the host, where they are absent.
     */
    private function runningInContainer(): bool
    {
        return file_exists('/.dockerenv') || file_exists('/run/.containerenv');
    }

    public function test_a_database_inside_the_app_directory_is_flagged_only_in_a_container(): void
    {
        $this->useSqliteAt(base_path('database/database.sqlite'));

        $this->assertSame($this->runningInContainer(), DockerDatabaseLayout::isLegacy());
    }

    public function test_the_recommended_path_is_never_flagged(): void
    {
        $this->useSqliteAt(DockerDatabaseLayout::RECOMMENDED_PATH);

        $this->assertFalse(DockerDatabaseLayout::isLegacy());
    }

    public function test_a_path_outside_the_app_directory_is_never_flagged(): void
    {
        $this->useSqliteAt('/srv/weathernode/data/database.sqlite');

        $this->assertFalse(DockerDatabaseLayout::isLegacy());
    }

    public function test_a_path_that_merely_shares_a_prefix_is_not_flagged(): void
    {
        // base_path('database') is a prefix of this string, but it is a sibling
        // directory rather than something inside it.
        $this->useSqliteAt(base_path('database-backups/database.sqlite'));

        $this->assertFalse(DockerDatabaseLayout::isLegacy());
    }

    public function test_mysql_installs_are_never_flagged(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.sqlite.database' => base_path('database/database.sqlite'),
        ]);

        $this->assertFalse(DockerDatabaseLayout::isLegacy());
    }

    public function test_an_empty_database_path_is_not_flagged(): void
    {
        $this->useSqliteAt('');

        $this->assertFalse(DockerDatabaseLayout::isLegacy());
    }

    public function test_the_admin_layout_does_not_show_the_notice_outside_a_container(): void
    {
        if ($this->runningInContainer()) {
            $this->markTestSkipped('Test host is a container, so the notice is expected.');
        }

        $this->useSqliteAt(base_path('database/database.sqlite'));

        $this->assertFalse(DockerDatabaseLayout::isLegacy());
    }
}
