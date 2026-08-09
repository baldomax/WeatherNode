<?php

namespace App\Services\Update;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\Update\HealthCheckService;
use App\Services\Update\BackupService;
use App\Models\UpdateLog;
use App\Models\Setting;

class DeployerService
{
    private string $deployRoot;
    private string $releasesPath;
    private string $sharedPath;
    private string $currentSymlink;
    private int $keepReleases;

    public function __construct()
    {
        $this->deployRoot = config('updater.deploy_root');
        $this->releasesPath = $this->deployRoot . '/' . config('updater.releases_path');
        $this->sharedPath = $this->deployRoot . '/' . config('updater.shared_path');
        $this->currentSymlink = $this->deployRoot . '/' . config('updater.current_symlink');
        // Admin-configurable (Updates page) with the env/config default as fallback.
        $this->keepReleases = max(1, (int) Setting::getValue('updater.keep_releases', config('updater.keep_releases', 5)));
    }

    /**
     * Deploy a release from a ZIP file
     */
    public function deploy(string $zipPath, string $version, ?int $userId = null, ?array $validationResults = null): array
    {
        $lockFile = storage_path('app/.updater_lock');
        
        // Acquire lock to prevent concurrent deployments
        $lockHandle = @fopen($lockFile, 'w');
        if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            return [
                'success' => false,
                'message' => 'Another deployment is already in progress',
            ];
        }

        $startTime = time();
        $updateLog = null;

        try {
            // Create audit log entry
            $updateLog = UpdateLog::create([
                'version' => $version,
                'status' => 'pending',
                'deployed_by' => $userId,
                'validation_results' => $validationResults,
            ]);
            // Create backup before update
            $backupResult = null;
            if (config('updater.backup_enabled', true)) {
                $backupService = app(BackupService::class);
                $backupResult = $backupService->createBackup();

                if (!$backupResult['success']) {
                    Log::warning('Backup failed but continuing with deployment', [
                        'backup_error' => $backupResult['message'],
                    ]);
                } else {
                    Log::info('Backup created successfully', [
                        'backups' => $backupResult['backups'] ?? [],
                    ]);
                }
            }

            // Put site in maintenance mode
            Artisan::call('down', ['--render' => 'errors::503', '--retry' => '60']);

            // Create directories if they don't exist
            $this->ensureDirectoriesExist();

            // Extract ZIP to new release directory
            $releaseDir = $this->releasesPath . '/' . $version;
            if (!File::exists($releaseDir)) {
                File::makeDirectory($releaseDir, 0755, true);
            }

            $this->extractZip($zipPath, $releaseDir);

            // Link shared directories
            $this->linkSharedDirectories($releaseDir);

            // Run post-deploy steps
            $this->runPostDeploySteps($releaseDir);

            // Store previous release for potential rollback
            $previousRelease = $this->getCurrentRelease();

            // Atomically switch symlink
            $this->switchSymlink($releaseDir);

            // Bust PHP's caches so the new release is served immediately. This
            // method runs inside the PHP-FPM request, so opcache_reset() clears
            // the pool's shared opcache and clearstatcache() drops this worker's
            // cached resolution of the `current` symlink. Without it FPM keeps
            // executing the previous release — and reporting its VERSION — until
            // the caches expire on their own. Done before the health check so it
            // probes the freshly activated code.
            $this->refreshPhpCaches();

            // Health check after symlink switch (before bringing site online)
            $healthCheckResults = null;
            if (config('updater.health_check_enabled', true)) {
                $healthCheck = app(HealthCheckService::class);
                $isHealthy = $healthCheck->verify($releaseDir);
                $healthCheckResults = $healthCheck->getDetailedResults($releaseDir);

                if (!$isHealthy) {
                    // Health check failed - rollback immediately
                    Log::error('Health check failed after deployment', [
                        'version' => $version,
                        'release_dir' => $releaseDir,
                        'health_results' => $healthCheckResults,
                    ]);

                    // Update audit log
                    if ($updateLog) {
                        $updateLog->update([
                            'status' => 'failed',
                            'error_message' => 'Health check failed',
                            'health_check_results' => $healthCheckResults,
                            'duration_seconds' => time() - $startTime,
                        ]);
                    }

                    // Rollback to previous release. Also restore the database to
                    // its pre-update state: runPostDeploySteps already ran
                    // `migrate --force`, so reverting only the code symlink would
                    // leave the old release running against a newer schema. The
                    // site was in maintenance during the deploy, so no data is
                    // lost by restoring the snapshot taken moments earlier.
                    if ($previousRelease) {
                        $this->rollback($previousRelease, $userId);
                        $dbRestored = $this->restorePreUpdateDatabase($backupResult);
                        throw new \Exception(
                            'Health check failed - automatically rolled back to version ' . $previousRelease
                            . ($dbRestored ? '. Database restored to pre-update state.' : '.')
                        );
                    } else {
                        // No previous release to rollback to - this is critical
                        throw new \Exception('Health check failed and no previous release available for rollback');
                    }
                }

                Log::info('Health check passed', [
                    'version' => $version,
                    'release_dir' => $releaseDir,
                ]);
            }

            // Clean up old releases
            $this->cleanupOldReleases();

            // Bring site back online
            // Note: Health check may have temporarily brought it online, but we ensure it's up here
            Artisan::call('up');

            // Update audit log with success
            if ($updateLog) {
                $updateLog->update([
                    'status' => 'success',
                    'deployed_at' => now(),
                    'release_dir' => $releaseDir,
                    'health_check_results' => $healthCheckResults,
                    'duration_seconds' => time() - $startTime,
                ]);
            }

            return [
                'success' => true,
                'message' => "Successfully deployed version {$version}",
                'release_dir' => $releaseDir,
                'health_check_passed' => config('updater.health_check_enabled', true),
            ];
        } catch (\Exception $e) {
            Log::error('Deployment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update audit log with failure
            if ($updateLog) {
                $updateLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'duration_seconds' => time() - $startTime,
                ]);
            }

            // Ensure site is brought back online even on failure
            try {
                Artisan::call('up');
            } catch (\Exception $upException) {
                Log::error('Failed to bring site back online', [
                    'error' => $upException->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'message' => 'Deployment failed: ' . $e->getMessage(),
            ];
        } finally {
            // Release lock
            if (isset($lockHandle)) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                @unlink($lockFile);
            }
        }
    }

    /**
     * Rollback to a previous release
     */
    public function rollback(string $version, ?int $userId = null): array
    {
        $version = trim($version);
        if (!$this->isValidGitRef($version)) {
            return [
                'success' => false,
                'message' => 'Invalid version/tag format',
            ];
        }

        try {
            $releaseDir = $this->releasesPath . '/' . $version;
            
            if (!File::exists($releaseDir)) {
                return [
                    'success' => false,
                    'message' => "Release {$version} not found",
                ];
            }

            // Find the failed update log entry
            $failedLog = UpdateLog::where('status', 'failed')
                ->orWhere('status', 'success')
                ->latest('deployed_at')
                ->first();

            // Switch symlink to previous release
            $this->switchSymlink($releaseDir);

            // Update audit log if we have a failed deployment
            if ($failedLog) {
                $failedLog->update([
                    'status' => 'rolled_back',
                    'rollback_at' => now(),
                    'rollback_by' => $userId,
                ]);
            }

            return [
                'success' => true,
                'message' => "Rolled back to version {$version}",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Rollback failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get list of available releases
     */
    public function getReleases(): array
    {
        if (!File::exists($this->releasesPath)) {
            return [];
        }

        $releases = File::directories($this->releasesPath);
        
        return collect($releases)
            ->map(function ($path) {
                return [
                    'version' => basename($path),
                    'path' => $path,
                    'created_at' => filemtime($path),
                    'size' => $this->directorySize($path),
                ];
            })
            ->sortByDesc('created_at')
            ->values()
            ->toArray();
    }

    /**
     * Delete a release directory to reclaim disk space.
     *
     * Refuses to delete the currently active release. Shared symlinks
     * (storage, .env, database) are unlinked first so the shared targets are
     * never touched.
     */
    public function deleteRelease(string $version): array
    {
        $version = trim($version);

        if (!$this->isValidGitRef($version)) {
            return ['success' => false, 'message' => 'Invalid version/tag format'];
        }

        $current = $this->getCurrentRelease();
        if ($current !== null && $version === $current) {
            return ['success' => false, 'message' => 'Cannot delete the currently active release'];
        }

        $releaseDir = $this->releasesPath . '/' . $version;
        if (!File::isDirectory($releaseDir)) {
            return ['success' => false, 'message' => "Release {$version} not found"];
        }

        $freed = $this->directorySize($releaseDir);

        // Remove symlinks into shared resources first so deleteDirectory can
        // never recurse into the shared storage/database/.env targets.
        foreach (['storage', '.env', 'database'] as $link) {
            $linkPath = $releaseDir . '/' . $link;
            if (is_link($linkPath)) {
                @unlink($linkPath);
            }
        }

        if (!File::deleteDirectory($releaseDir)) {
            return ['success' => false, 'message' => "Failed to delete release {$version}"];
        }

        Log::info('Release deleted', ['version' => $version, 'bytes_freed' => $freed]);

        return [
            'success' => true,
            'message' => "Deleted release {$version}",
            'bytes_freed' => $freed,
        ];
    }

    /**
     * Total size of a directory's own files, in bytes. Symlinks are skipped so
     * shared storage (linked into each release) isn't counted or followed.
     */
    private function directorySize(string $path): int
    {
        if (!is_dir($path) || is_link($path)) {
            return 0;
        }

        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                    fn (\SplFileInfo $current) => !$current->isLink()
                ),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && !$file->isLink()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return $size;
    }

    /**
     * Restore the database from the pre-update backup taken at the start of the
     * deploy. Returns true on success. Used by the automatic rollback path so a
     * failed deploy reverts schema changes, not just the code symlink.
     */
    private function restorePreUpdateDatabase(?array $backupResult): bool
    {
        $dbBackup = $backupResult['backups']['database'] ?? null;
        if (!$dbBackup) {
            Log::warning('No pre-update database backup available; rollback reverted code only.');
            return false;
        }

        $result = app(BackupService::class)->restoreDatabase($dbBackup);
        if (!empty($result['success'])) {
            Log::info('Database restored to pre-update state during rollback', ['backup' => $dbBackup]);
            return true;
        }

        Log::error('Database restore failed during rollback', [
            'error' => $result['message'] ?? 'unknown',
            'backup' => $dbBackup,
        ]);
        return false;
    }

    /**
     * Get current release version
     */
    public function getCurrentRelease(): ?string
    {
        if (!is_link($this->currentSymlink)) {
            return null;
        }

        $target = readlink($this->currentSymlink);
        return basename($target);
    }

    /**
     * Update via Git (if enabled and available)
     */
    public function updateViaGit(string $version): array
    {
        if (!config('updater.allow_git')) {
            return [
                'success' => false,
                'message' => 'Git updates are disabled. Set UPDATER_ALLOW_GIT=true in .env',
            ];
        }

        $version = trim($version);
        if (!$this->isValidGitRef($version)) {
            return [
                'success' => false,
                'message' => 'Invalid version/tag format',
            ];
        }

        $repoPath = config('updater.deploy_root');
        
        // Verify we're in a git repository
        if (!is_dir($repoPath . '/.git')) {
            return [
                'success' => false,
                'message' => 'Not a Git repository',
            ];
        }

        try {
            // Fetch tags
            $this->runGitCommand($repoPath, ['fetch', '--tags']);

            // Checkout the specified version/tag
            $this->runGitCommand($repoPath, ['checkout', $version]);

            // Run post-deploy steps
            $this->runPostDeploySteps($repoPath);

            return [
                'success' => true,
                'message' => "Successfully updated to {$version} via Git",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Git update failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Run a Git command safely
     */
    private function runGitCommand(string $repoPath, array $args): string
    {
        $command = implode(' ', array_map('escapeshellarg', $args));
        $fullCommand = "cd " . escapeshellarg($repoPath) . " && git " . $command . " 2>&1";
        
        if (!function_exists('exec')) {
            throw new \Exception('exec() function is not available');
        }

        $output = [];
        $returnVar = 0;
        exec($fullCommand, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \Exception('Git command failed: ' . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    /**
     * Validate git version/tag input to avoid option injection and invalid refs.
     */
    private function isValidGitRef(string $ref): bool
    {
        if ($ref === '' || strlen($ref) > 120) {
            return false;
        }

        if (!preg_match('/\A[A-Za-z0-9._\/-]+\z/', $ref)) {
            return false;
        }

        if (
            str_starts_with($ref, '-')
            || str_contains($ref, '..')
            || str_contains($ref, '@{')
            || str_contains($ref, '\\')
            || str_ends_with($ref, '/')
            || str_ends_with($ref, '.lock')
        ) {
            return false;
        }

        return true;
    }

    /**
     * Ensure required directories exist
     */
    private function ensureDirectoriesExist(): void
    {
        if (!File::exists($this->releasesPath)) {
            File::makeDirectory($this->releasesPath, 0755, true);
        }

        if (!File::exists($this->sharedPath)) {
            File::makeDirectory($this->sharedPath, 0755, true);
        }

        // Create shared subdirectories
        $sharedDirs = ['storage', 'database'];
        foreach ($sharedDirs as $dir) {
            $path = $this->sharedPath . '/' . $dir;
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }
    }

    /**
     * Extract ZIP file to release directory
     */
    private function extractZip(string $zipPath, string $destination): void
    {
        $zip = new \ZipArchive();
        
        if ($zip->open($zipPath) !== true) {
            throw new \Exception("Failed to open ZIP file: {$zipPath}");
        }

        // Extract to a temporary directory first
        $tempDir = $destination . '_temp';
        if (File::exists($tempDir)) {
            File::deleteDirectory($tempDir);
        }
        File::makeDirectory($tempDir, 0755, true);

        $this->extractZipSafely($zip, $tempDir);
        $zip->close();

        // A retried deploy reuses the release directory, and one deployed by
        // the old code still has database/ as a symlink into shared/. The copy
        // below follows directory symlinks, so without this the zip's
        // database/migrations would land in shared/ and then be orphaned when
        // linkSharedDirectories() replaces the symlink with a real directory.
        $legacyDatabaseLink = $destination . '/database';
        if (is_link($legacyDatabaseLink)) {
            unlink($legacyDatabaseLink);
        }

        // Move contents from temp to final destination
        // Handle case where ZIP contains a single root folder
        $contents = File::directories($tempDir);
        if (count($contents) === 1 && count(File::files($tempDir)) === 0) {
            // ZIP has a single root folder, move its contents
            $rootFolder = $contents[0];
            File::copyDirectory($rootFolder, $destination);
        } else {
            // ZIP contents are at root level
            File::copyDirectory($tempDir, $destination);
        }

        // Clean up temp directory
        File::deleteDirectory($tempDir);
    }

    private function extractZipSafely(\ZipArchive $zip, string $destination): void
    {
        $destination = rtrim($destination, '/');

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);
            if (!is_string($entry) || $entry === '') {
                continue;
            }

            $normalizedEntry = str_replace('\\', '/', $entry);
            if (!$this->isSafeZipEntryPath($normalizedEntry)) {
                throw new \Exception("Unsafe ZIP entry path detected: {$entry}");
            }

            if ($this->isZipSymlinkEntry($zip, $index)) {
                throw new \Exception("ZIP contains unsupported symlink entry: {$entry}");
            }

            // Directory entries are represented by a trailing slash.
            if (str_ends_with($normalizedEntry, '/')) {
                $dirPath = $destination . '/' . rtrim($normalizedEntry, '/');
                if (!File::exists($dirPath)) {
                    File::makeDirectory($dirPath, 0755, true);
                }
                continue;
            }

            $targetPath = $destination . '/' . $normalizedEntry;
            $targetDir = dirname($targetPath);
            if (!File::exists($targetDir)) {
                File::makeDirectory($targetDir, 0755, true);
            }

            $content = $zip->getFromIndex($index);
            if ($content === false) {
                throw new \Exception("Failed to read ZIP entry: {$entry}");
            }

            if (file_put_contents($targetPath, $content, LOCK_EX) === false) {
                throw new \Exception("Failed to write extracted entry: {$entry}");
            }

            // Preserve the executable bit from the ZIP's stored Unix mode.
            // file_put_contents() writes with the default umask (typically
            // 0644), which strips +x from files like `artisan`. The post-deploy
            // health check (is_executable) and any CLI use then fail.
            $this->applyZipEntryPermissions($zip, $index, $targetPath);
        }
    }

    /**
     * Restore a file's executable bit from the ZIP entry's stored Unix mode.
     */
    private function applyZipEntryPermissions(\ZipArchive $zip, int $index, string $targetPath): void
    {
        $opsys = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            return;
        }

        // Unix permission bits live in the high 16 bits of the external attrs.
        $mode = ($attributes >> 16) & 0777;

        // Only propagate execute bits (e.g. for `artisan`); keep writes owner-only.
        if ($mode & 0111) {
            @chmod($targetPath, 0755);
        }
    }

    private function isSafeZipEntryPath(string $entry): bool
    {
        if ($entry === '' || str_starts_with($entry, '/')) {
            return false;
        }

        if (preg_match('/^[A-Za-z]:\//', $entry) === 1) {
            return false;
        }

        $segments = explode('/', $entry);
        foreach ($segments as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function isZipSymlinkEntry(\ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            return false;
        }

        // Unix file type bits are stored in the high 16 bits.
        $mode = ($attributes >> 16) & 0xF000;
        return $mode === 0xA000;
    }

    /**
     * Link shared directories (.env, storage, database) into release
     */
    private function linkSharedDirectories(string $releaseDir): void
    {
        // Link .env if it exists in shared
        $sharedEnv = $this->sharedPath . '/.env';
        $releaseEnv = $releaseDir . '/.env';
        
        if (File::exists($sharedEnv) && !File::exists($releaseEnv)) {
            symlink($sharedEnv, $releaseEnv);
        }

        // Link storage
        $sharedStorage = $this->sharedPath . '/storage';
        $releaseStorage = $releaseDir . '/storage';
        
        if (File::exists($sharedStorage)) {
            // Seed shared storage with the framework skeleton (and any default
            // files) shipped in the release before discarding it. On a first
            // deploy shared/storage is freshly created and empty; symlinking the
            // release to it as-is would drop storage/framework/{cache,sessions,
            // views}, breaking compiled views, sessions, cache, and
            // `artisan down`. Existing runtime data (logs, sessions) is never
            // overwritten.
            if (File::exists($releaseStorage)) {
                $this->seedDirectory($releaseStorage, $sharedStorage);
                File::deleteDirectory($releaseStorage);
            }
            symlink($sharedStorage, $releaseStorage);
        }

        // Link the SQLite database file(s) into the release.
        //
        // Only the .sqlite files, never the whole database/ directory: that
        // directory also holds database/migrations, so replacing it with a
        // symlink to shared/ deleted every migration the release shipped.
        // `migrate --force` in runPostDeploySteps then ran against shared/,
        // which nothing ever seeds, and silently applied nothing — so schema
        // changes never reached SQLite installs and the drift only surfaced
        // later, as a missing column or setting.
        $sharedDatabase = $this->sharedPath . '/database';
        $releaseDatabase = $releaseDir . '/database';

        if (File::exists($sharedDatabase)) {
            $sharedSqliteFiles = File::glob($sharedDatabase . '/*.sqlite*');

            if (!empty($sharedSqliteFiles)) {
                // A same-version redeploy can find the legacy whole-directory
                // symlink still in place. Drop it (the shared target is left
                // untouched) so the release gets a real directory again.
                if (is_link($releaseDatabase)) {
                    unlink($releaseDatabase);
                }
                if (!File::isDirectory($releaseDatabase)) {
                    File::makeDirectory($releaseDatabase, 0755, true);
                }

                foreach ($sharedSqliteFiles as $sharedSqliteFile) {
                    $releaseSqliteFile = $releaseDatabase . '/' . basename($sharedSqliteFile);

                    // The zip never ships a .sqlite, but a stale link from an
                    // earlier deploy of this same version can be here.
                    if (is_link($releaseSqliteFile) || File::exists($releaseSqliteFile)) {
                        File::delete($releaseSqliteFile);
                    }

                    symlink($sharedSqliteFile, $releaseSqliteFile);
                }
            }
        }
    }

    /**
     * Run post-deploy steps (migrations, cache clear, etc.)
     */
    private function runPostDeploySteps(string $releaseDir): void
    {
        // Remove compiled bootstrap artifacts before any artisan command.
        // These files may reference dev-only providers (e.g. Collision) and
        // can crash migrations when deploying --no-dev builds.
        $this->purgeBootstrapCacheFiles($releaseDir);

        // Note: We can't use chdir() with Artisan::call because it uses base_path()
        // Instead, we need to run artisan from the release directory using exec
        // But for now, we'll assume the release is extracted to a path we can reference
        
        // Run migrations - use the artisan file in the release directory
        $artisanPath = $releaseDir . '/artisan';
        if (file_exists($artisanPath)) {
            // Use exec to run artisan from the release directory
            $originalCwd = getcwd();
            chdir($releaseDir);
            
            try {
                // Run migrations
                exec('php artisan migrate --force 2>&1', $migrateOutput, $migrateReturn);
                if ($migrateReturn !== 0) {
                    throw new \Exception('Migration failed: ' . implode("\n", $migrateOutput));
                }

                // Clear caches
                exec('php artisan config:clear 2>&1', $configOutput, $configReturn);
                exec('php artisan cache:clear 2>&1', $cacheOutput, $cacheReturn);
                exec('php artisan view:clear 2>&1', $viewOutput, $viewReturn);
                exec('php artisan route:clear 2>&1', $routeOutput, $routeReturn);
            } finally {
                chdir($originalCwd);
            }
        } else {
            // Fallback: try with Artisan::call (may not work if base_path differs)
            try {
                $this->purgeBootstrapCacheFiles($releaseDir);
                Artisan::call('migrate', ['--force' => true]);
                Artisan::call('config:clear');
                Artisan::call('cache:clear');
                Artisan::call('view:clear');
                Artisan::call('route:clear');
            } catch (\Exception $e) {
                Log::warning('Artisan commands may have failed due to path mismatch', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function purgeBootstrapCacheFiles(string $releaseDir): void
    {
        $cacheDir = rtrim($releaseDir, '/') . '/bootstrap/cache';
        if (!File::exists($cacheDir) || !File::isDirectory($cacheDir)) {
            return;
        }

        foreach (File::files($cacheDir) as $file) {
            $path = $file->getPathname();
            if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }
            @unlink($path);
        }
    }

    /**
     * Recursively copy entries from $source into $target that don't already
     * exist there. Bootstraps shared storage from a release without clobbering
     * existing runtime data (logs, sessions, cache, uploads).
     */
    private function seedDirectory(string $source, string $target): void
    {
        if (!File::isDirectory($target)) {
            File::makeDirectory($target, 0775, true);
        }

        foreach (File::directories($source) as $dir) {
            $dest = $target . '/' . basename($dir);
            if (File::exists($dest)) {
                $this->seedDirectory($dir, $dest);
            } else {
                File::copyDirectory($dir, $dest);
            }
        }

        // Include hidden files so .gitignore keepers (which preserve empty
        // framework dirs) are carried over too.
        foreach (File::files($source, true) as $file) {
            $dest = $target . '/' . $file->getFilename();
            if (!File::exists($dest)) {
                File::copy($file->getPathname(), $dest);
            }
        }
    }

    /**
     * Invalidate PHP's opcode and realpath caches after a symlink switch so the
     * newly activated release is served right away. Best-effort: opcache may be
     * disabled or its API restricted, in which case the caches expire on their
     * own (realpath_cache_ttl, typically 120s).
     */
    private function refreshPhpCaches(): void
    {
        // Drop this worker's cached stat/symlink resolution of `current`.
        clearstatcache(true);

        if (function_exists('opcache_reset') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOL)) {
            @opcache_reset();
        }
    }

    /**
     * Atomically switch the current symlink
     */
    private function switchSymlink(string $releaseDir): void
    {
        // Create a temporary symlink first
        $tempSymlink = $this->currentSymlink . '_temp';
        
        if (File::exists($tempSymlink)) {
            @unlink($tempSymlink);
        }
        
        symlink($releaseDir, $tempSymlink);

        // Atomically replace the current symlink
        if (File::exists($this->currentSymlink)) {
            @unlink($this->currentSymlink);
        }
        
        rename($tempSymlink, $this->currentSymlink);
    }

    /**
     * Clean up old releases, keeping only the last N
     */
    private function cleanupOldReleases(): void
    {
        $releases = $this->getReleases();
        
        if (count($releases) <= $this->keepReleases) {
            return;
        }

        // Sort by creation time, oldest first
        $sorted = collect($releases)
            ->sortBy('created_at')
            ->values()
            ->toArray();

        // Remove oldest releases beyond keep limit
        $toRemove = array_slice($sorted, 0, count($sorted) - $this->keepReleases);
        
        foreach ($toRemove as $release) {
            $path = $release['path'];
            if (File::exists($path)) {
                File::deleteDirectory($path);
            }
        }
    }
}
