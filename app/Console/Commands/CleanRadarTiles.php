<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\TileProxyController;
use Illuminate\Console\Command;

class CleanRadarTiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'radar:clean-tiles 
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old cached radar tiles (older than 2 hours)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('Dry run mode - no files will be deleted');
            $this->newLine();
        }
        
        $this->info('Cleaning up old radar tiles...');
        
        if ($dryRun) {
            // Count files that would be deleted
            $basePath = storage_path('app/radar-tiles');
            
            if (!is_dir($basePath)) {
                $this->info('No radar tiles directory found.');
                return Command::SUCCESS;
            }
            
            $count = 0;
            $cutoffTime = now()->subHours(2)->timestamp;
            
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getMTime() < $cutoffTime) {
                    $count++;
                    if ($this->getOutput()->isVerbose()) {
                        $this->line("  Would delete: " . $file->getPathname());
                    }
                }
            }
            
            $this->info("Would delete {$count} old tile(s)");
        } else {
            $deleted = TileProxyController::cleanupOldTiles();
            $this->info("Deleted {$deleted} old tile(s)");
        }
        
        return Command::SUCCESS;
    }
}
