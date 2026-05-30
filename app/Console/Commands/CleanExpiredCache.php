<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

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
        $this->info('File cache driver: expired files are automatically ignored.');
        $this->info('No manual cleanup needed - Laravel handles this automatically.');
        return 0;
    }
}
