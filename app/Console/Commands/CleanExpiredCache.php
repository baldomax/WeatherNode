<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class CleanExpiredCache extends Command
{
    protected $signature = 'cache:clean-expired {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Remove expired cache entries (works for both database and file cache drivers)';

    public function handle()
    {
        $driver = config('cache.default');
        $isDryRun = $this->option('dry-run');
        
        $this->info("Cache driver: {$driver}");
        
        if ($driver === 'database') {
            return $this->cleanDatabaseCache($isDryRun);
        } elseif ($driver === 'file') {
            return $this->cleanFileCache($isDryRun);
        } else {
            // For other drivers (redis, memcached, etc.), use Laravel's built-in prune
            $this->info("Using Laravel's built-in cache pruning for {$driver} driver.");
            if (!$isDryRun) {
                Artisan::call('cache:prune-stale-tags');
                $this->info('Cache pruned successfully.');
            } else {
                $this->info('Would prune cache (dry-run mode).');
            }
            return 0;
        }
    }
    
    private function cleanDatabaseCache(bool $isDryRun): int
    {
        // Count expired entries
        $expiredCount = DB::table('cache')
            ->where('expiration', '<', time())
            ->count();
        
        if ($expiredCount === 0) {
            $this->info('No expired cache entries found.');
            return 0;
        }
        
        if ($isDryRun) {
            $this->info("Would delete {$expiredCount} expired cache entries.");
            $this->line('Run without --dry-run to actually delete them.');
            return 0;
        }
        
        // Delete expired entries
        $deleted = DB::table('cache')
            ->where('expiration', '<', time())
            ->delete();
        
        $this->info("Deleted {$deleted} expired cache entries.");
        
        // Show remaining cache size
        $remaining = DB::table('cache')->count();
        $this->line("Remaining cache entries: {$remaining}");
        
        return 0;
    }
    
    private function cleanFileCache(bool $isDryRun): int
    {
        // Expired file cache entries are ignored on read, but they are only
        // unlinked if that same key is read again (FileStore::getPayload calls
        // forget()). A key that is written, expires, and is never requested
        // again stays on disk forever, so the cache directory grows without
        // limit. Sweep it here instead.
        $path = $this->fileCachePath();

        if (!is_dir($path)) {
            $this->info("File cache directory does not exist yet: {$path}");
            return 0;
        }

        $now = time();
        $expiredCount = 0;
        $expiredBytes = 0;
        $liveCount = 0;

        foreach ($this->cacheFiles($path) as $file) {
            $entry = $this->readEntry($file->getPathname());

            // Not a cache payload (e.g. a .gitignore), or gone already - leave it alone.
            if ($entry === null) {
                continue;
            }

            if ($entry['expires'] > $now) {
                $liveCount++;
                continue;
            }

            $expiredCount++;
            $expiredBytes += $entry['size'];

            if (!$isDryRun) {
                @unlink($file->getPathname());
            }
        }

        if ($expiredCount === 0) {
            $this->info('No expired cache entries found.');
            $this->line("Remaining cache entries: {$liveCount}");
            return 0;
        }

        $size = $this->formatBytes($expiredBytes);

        if ($isDryRun) {
            $this->info("Would delete {$expiredCount} expired cache entries ({$size}).");
            $this->line('Run without --dry-run to actually delete them.');
            return 0;
        }

        $this->info("Deleted {$expiredCount} expired cache entries ({$size}).");

        // Keys are hashed into two levels of directories that are never removed
        // once empty. Each one costs an inode and a block, and in Docker they
        // are walked by the entrypoint on every container start.
        $removedDirectories = $this->removeEmptyDirectories($path);

        if ($removedDirectories > 0) {
            $this->line("Removed {$removedDirectories} empty cache directories.");
        }

        $this->line("Remaining cache entries: {$liveCount}");

        return 0;
    }

    private function fileCachePath(): string
    {
        $store = config('cache.default');

        return (string) config("cache.stores.{$store}.path", storage_path('framework/cache/data'));
    }

    /**
     * @return Generator<SplFileInfo>
     */
    private function cacheFiles(string $path): Generator
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($entries as $entry) {
            if ($entry->isFile()) {
                yield $entry;
            }
        }
    }

    /**
     * The file store writes a 10 digit UNIX timestamp as the first 10 bytes of
     * every entry (9999999999 for forever). Anything else is not one of ours.
     *
     * Expiry and size come from one open handle on purpose. A second look at
     * the path would be a second chance for the file to have gone, and the
     * app deletes these same expired entries itself whenever one is read.
     *
     * @return array{expires: int, size: int}|null
     */
    private function readEntry(string $file): ?array
    {
        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            return null;
        }

        $header = fread($handle, 10);
        $stat = fstat($handle);
        fclose($handle);

        if (!is_string($header) || strlen($header) !== 10 || !ctype_digit($header)) {
            return null;
        }

        return [
            'expires' => (int) $header,
            'size' => (int) ($stat['size'] ?? 0),
        ];
    }

    private function removeEmptyDirectories(string $path): int
    {
        $removed = 0;

        // CHILD_FIRST so a directory whose only contents were empty
        // subdirectories is collapsed in the same pass. rmdir() refuses
        // non-empty directories, which is the guard against deleting live data.
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink() && @rmdir($entry->getPathname())) {
                $removed++;
            }
        }

        return $removed;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $value = $bytes / 1024;

        foreach (['KB', 'MB', 'GB'] as $unit) {
            if ($value < 1024 || $unit === 'GB') {
                return sprintf('%.1f %s', $value, $unit);
            }

            $value /= 1024;
        }

        return "{$bytes} B";
    }
}
