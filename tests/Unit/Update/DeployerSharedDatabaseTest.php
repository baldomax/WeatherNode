<?php

declare(strict_types=1);

namespace Tests\Unit\Update;

use App\Services\Update\DeployerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The deployer links shared state into each new release. For the SQLite
 * database that has to be done file by file: database/ also holds the
 * migrations the release ships, so symlinking the whole directory to shared/
 * deleted them and left `migrate --force` with nothing to apply.
 */
class DeployerSharedDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/wn-deploy-' . bin2hex(random_bytes(6));
        config([
            'updater.deploy_root' => $this->root,
            'updater.releases_path' => 'releases',
            'updater.shared_path' => 'shared',
            'updater.current_symlink' => 'current',
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->root) && File::exists($this->root)) {
            File::deleteDirectory($this->root);
        }

        parent::tearDown();
    }

    /**
     * Build a release directory the way extractZip() leaves it: the zip ships
     * database/migrations, and never a .sqlite file.
     */
    private function makeRelease(string $version): string
    {
        $releaseDir = $this->root . '/releases/' . $version;
        File::makeDirectory($releaseDir . '/database/migrations', 0755, true);
        File::put($releaseDir . '/database/migrations/2026_08_07_090000_add_quick_stats_tile_settings.php', '<?php');
        File::put($releaseDir . '/database/migrations/2026_07_29_220000_add_solar_hours.php', '<?php');

        return $releaseDir;
    }

    private function makeSharedSqlite(string $contents = 'live data'): string
    {
        $sharedDatabase = $this->root . '/shared/database';
        File::ensureDirectoryExists($sharedDatabase);
        File::put($sharedDatabase . '/database.sqlite', $contents);

        return $sharedDatabase . '/database.sqlite';
    }

    private function linkShared(string $releaseDir): void
    {
        $deployer = new DeployerService();

        (new ReflectionMethod($deployer, 'ensureDirectoriesExist'))->invoke($deployer);
        (new ReflectionMethod($deployer, 'linkSharedDirectories'))->invoke($deployer, $releaseDir);
    }

    private function migrationPath(string $releaseDir): string
    {
        return $releaseDir . '/database/migrations/2026_08_07_090000_add_quick_stats_tile_settings.php';
    }

    public function test_release_keeps_its_migrations_when_shared_holds_a_sqlite(): void
    {
        $releaseDir = $this->makeRelease('v2026.08.02');
        $this->makeSharedSqlite();

        $this->linkShared($releaseDir);

        $this->assertFalse(is_link($releaseDir . '/database'), 'database/ must stay a real directory');
        $this->assertFileExists(
            $this->migrationPath($releaseDir),
            'migrate --force runs against this path; without it every in-app update is a silent no-op'
        );
        $this->assertCount(2, File::glob($releaseDir . '/database/migrations/*.php'));
    }

    public function test_the_live_database_is_shared_not_copied(): void
    {
        $releaseDir = $this->makeRelease('v2026.08.02');
        $sharedSqlite = $this->makeSharedSqlite('live data');

        $this->linkShared($releaseDir);

        $releaseSqlite = $releaseDir . '/database/database.sqlite';
        $this->assertTrue(is_link($releaseSqlite), 'the release must point at the shared database, not its own copy');
        $this->assertSame('live data', File::get($releaseSqlite));

        // Writes through the release path must land in shared, or the next
        // deploy would strand them with the old release.
        File::put($releaseSqlite, 'written by the new release');
        $this->assertSame('written by the new release', File::get($sharedSqlite));
    }

    public function test_release_keeps_its_migrations_when_shared_holds_no_sqlite(): void
    {
        $releaseDir = $this->makeRelease('v2026.08.02');

        $this->linkShared($releaseDir);

        $this->assertFalse(is_link($releaseDir . '/database'));
        $this->assertFileExists($this->migrationPath($releaseDir));
    }

    /**
     * An install updated before this fix still has the whole-directory symlink
     * in its current release. Redeploying that same version must recover.
     */
    public function test_a_legacy_whole_directory_symlink_is_replaced_safely(): void
    {
        $releaseDir = $this->makeRelease('v2026.08.02');
        $sharedDatabase = dirname($this->makeSharedSqlite());

        File::deleteDirectory($releaseDir . '/database');
        symlink($sharedDatabase, $releaseDir . '/database');
        $this->assertTrue(is_link($releaseDir . '/database'));

        $this->linkShared($releaseDir);

        $this->assertFalse(is_link($releaseDir . '/database'), 'the legacy directory symlink must be replaced');
        $this->assertTrue(is_link($releaseDir . '/database/database.sqlite'));
        $this->assertFileExists($sharedDatabase . '/database.sqlite', 'the live database must survive the swap');
        $this->assertSame('live data', File::get($sharedDatabase . '/database.sqlite'));
    }

    /**
     * A retried deploy extracts into a release directory that already exists,
     * and one created by the old deployer still has database/ as a symlink to
     * shared/. File::copyDirectory follows directory symlinks, so without the
     * guard in extractZip the zip's migrations landed in shared/ and were then
     * orphaned when linkSharedDirectories replaced the symlink. This models
     * the true order: symlink first, then extraction.
     */
    public function test_extracting_over_a_legacy_symlink_keeps_migrations_in_the_release(): void
    {
        $releaseDir = $this->root . '/releases/v2026.08.02';
        File::makeDirectory($releaseDir, 0755, true);
        $sharedDatabase = dirname($this->makeSharedSqlite());
        symlink($sharedDatabase, $releaseDir . '/database');

        $zipPath = $this->root . '/release.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('database/migrations/2026_08_07_090000_add_quick_stats_tile_settings.php', '<?php');
        $zip->addFromString('artisan', '<?php');
        $zip->close();

        $deployer = new DeployerService();
        (new ReflectionMethod($deployer, 'extractZip'))->invoke($deployer, $zipPath, $releaseDir);

        $this->assertFalse(is_link($releaseDir . '/database'), 'the legacy symlink must be gone before the copy');
        $this->assertFileExists($this->migrationPath($releaseDir), 'migrations must land in the release, not shared');
        $this->assertFileDoesNotExist($sharedDatabase . '/migrations/2026_08_07_090000_add_quick_stats_tile_settings.php', 'shared must not be polluted with app files');
        $this->assertFileExists($sharedDatabase . '/database.sqlite', 'the live database must be untouched');
    }

    /**
     * cleanupOldReleases() calls File::deleteDirectory() straight on an old
     * release, unlike deleteRelease() which unlinks shared symlinks first.
     */
    public function test_pruning_an_old_release_cannot_delete_the_shared_database(): void
    {
        $old = $this->makeRelease('v2026.08.01');
        $sharedSqlite = $this->makeSharedSqlite('live data');

        $this->linkShared($old);
        $this->assertTrue(is_link($old . '/database/database.sqlite'));

        File::deleteDirectory($old);

        $this->assertDirectoryDoesNotExist($old);
        $this->assertFileExists($sharedSqlite, 'pruning a release must never follow the link into shared');
        $this->assertSame('live data', File::get($sharedSqlite));
    }

    public function test_shared_database_directory_is_created_empty_on_first_deploy(): void
    {
        $releaseDir = $this->makeRelease('v2026.08.02');

        $this->linkShared($releaseDir);

        $this->assertDirectoryExists($this->root . '/shared/database');
        $this->assertEmpty(
            File::glob($this->root . '/shared/database/*'),
            'nothing seeds shared/database, unlike shared/storage'
        );
    }
}
