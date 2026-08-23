<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use FilesystemIterator;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class CleanExpiredCacheCommandTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cachePath = storage_path('framework/testing/cache-'.uniqid());
        mkdir($this->cachePath, 0775, true);

        config([
            'cache.default' => 'file',
            'cache.stores.file.driver' => 'file',
            'cache.stores.file.path' => $this->cachePath,
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->cachePath);

        parent::tearDown();
    }

    public function test_it_deletes_expired_entries_and_keeps_live_ones(): void
    {
        $this->writeEntry('aa/bb/expired-one', time() - 60);
        $this->writeEntry('aa/bb/expired-two', time() - 3600);
        $this->writeEntry('cc/dd/live', time() + 300);

        $exitCode = Artisan::call('cache:clean-expired');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Deleted 2 expired cache entries', $output);
        $this->assertStringContainsString('Remaining cache entries: 1', $output);

        $this->assertFileDoesNotExist($this->cachePath.'/aa/bb/expired-one');
        $this->assertFileDoesNotExist($this->cachePath.'/aa/bb/expired-two');
        $this->assertFileExists($this->cachePath.'/cc/dd/live');
    }

    public function test_it_keeps_entries_stored_forever(): void
    {
        // Illuminate\Cache\FileStore::forever() writes 9999999999.
        $this->writeEntry('aa/bb/forever', 9999999999);

        Artisan::call('cache:clean-expired');

        $this->assertStringContainsString('No expired cache entries found.', Artisan::output());
        $this->assertFileExists($this->cachePath.'/aa/bb/forever');
    }

    public function test_dry_run_reports_without_deleting(): void
    {
        $this->writeEntry('aa/bb/expired', time() - 60);

        Artisan::call('cache:clean-expired', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('Would delete 1 expired cache entries', $output);
        $this->assertFileExists($this->cachePath.'/aa/bb/expired');
    }

    public function test_it_leaves_files_that_are_not_cache_payloads_alone(): void
    {
        file_put_contents($this->cachePath.'/.gitignore', "*\n!.gitignore\n");
        $this->writeEntry('aa/bb/expired', time() - 60);

        Artisan::call('cache:clean-expired');

        $this->assertFileExists($this->cachePath.'/.gitignore');
        $this->assertFileDoesNotExist($this->cachePath.'/aa/bb/expired');
    }

    public function test_it_removes_directories_left_empty(): void
    {
        $this->writeEntry('aa/bb/expired', time() - 60);
        $this->writeEntry('cc/dd/live', time() + 300);

        Artisan::call('cache:clean-expired');
        $output = Artisan::output();

        $this->assertStringContainsString('Removed 2 empty cache directories.', $output);
        $this->assertDirectoryDoesNotExist($this->cachePath.'/aa');
        $this->assertDirectoryExists($this->cachePath.'/cc/dd');
    }

    public function test_it_handles_a_cache_directory_that_does_not_exist_yet(): void
    {
        $missing = $this->cachePath.'/not-created-yet';
        config(['cache.stores.file.path' => $missing]);

        $exitCode = Artisan::call('cache:clean-expired');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('File cache directory does not exist yet', Artisan::output());
    }

    /**
     * Mirrors the on-disk format of Illuminate\Cache\FileStore: a 10 digit
     * expiry timestamp followed by the serialized value.
     */
    /** The reported size comes from the same handle the expiry is read from. */
    public function test_it_reports_the_size_of_what_it_deleted(): void
    {
        $this->writeEntry('aa/bb/expired-one', time() - 60);
        $this->writeEntry('aa/bb/expired-two', time() - 60);

        $expectedBytes = filesize($this->cachePath.'/aa/bb/expired-one')
            + filesize($this->cachePath.'/aa/bb/expired-two');

        Artisan::call('cache:clean-expired');

        $this->assertStringContainsString("({$expectedBytes} B)", Artisan::output());
    }

    /**
     * The app unlinks an expired entry itself whenever one is read, so a sweep
     * of expired entries can meet a file that has just gone. Skip it and carry
     * on rather than aborting the run partway through.
     */
    public function test_an_entry_it_cannot_read_does_not_stop_the_sweep(): void
    {
        $this->writeEntry('aa/bb/expired', time() - 60);
        $this->writeEntry('aa/bb/unreadable', time() - 60);
        chmod($this->cachePath.'/aa/bb/unreadable', 0000);

        if (is_readable($this->cachePath.'/aa/bb/unreadable')) {
            $this->markTestSkipped('Running as root, so file permissions do not apply.');
        }

        $exitCode = Artisan::call('cache:clean-expired');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Deleted 1 expired cache entries', Artisan::output());
        $this->assertFileDoesNotExist($this->cachePath.'/aa/bb/expired');
    }

    private function writeEntry(string $relativePath, int $expiration): void
    {
        $fullPath = $this->cachePath.'/'.$relativePath;
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($fullPath, $expiration.serialize('cached value'));
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($entries as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }

        @rmdir($path);
    }
}
