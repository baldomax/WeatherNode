<?php

namespace App\Services\Update;

class CompatibilityChecker
{
    /**
     * Check if browser-based updates are supported on this server
     */
    public function checkCompatibility(): array
    {
        $checks = [
            'write_access' => $this->checkWriteAccess(),
            'symlinks' => $this->checkSymlinks(),
            'zip_extraction' => $this->checkZipExtraction(),
            'artisan_execution' => $this->checkArtisanExecution(),
            'deployment_paths' => $this->checkDeploymentPaths(),
        ];

        $allPassed = collect($checks)->every(fn($check) => $check['passed']);

        return [
            'supported' => $allPassed,
            'checks' => $checks,
            'recommendation' => $this->getRecommendation($checks, $allPassed),
        ];
    }

    /**
     * Check if PHP can write to the deploy root
     */
    private function checkWriteAccess(): array
    {
        $deployRoot = config('updater.deploy_root');
        $testFile = $deployRoot . '/.updater_test_' . time();
        
        try {
            $result = @file_put_contents($testFile, 'test');
            if ($result !== false) {
                @unlink($testFile);
                return [
                    'passed' => true,
                    'message' => 'Write access to deploy root is available',
                ];
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return [
            'passed' => false,
            'message' => 'Cannot write to deploy root directory',
            'advice' => 'Use manual ZIP upload (Tier 0) or SSH update',
        ];
    }

    /**
     * Check if symlinks are supported
     */
    private function checkSymlinks(): array
    {
        $deployRoot = config('updater.deploy_root');
        $testLink = $deployRoot . '/.updater_test_link_' . time();
        $testTarget = $deployRoot . '/.updater_test_target_' . time();
        
        try {
            // Create a test file
            @file_put_contents($testTarget, 'test');
            
            // Try to create a symlink
            if (@symlink($testTarget, $testLink)) {
                @unlink($testLink);
                @unlink($testTarget);
                return [
                    'passed' => true,
                    'message' => 'Symlinks are supported',
                ];
            }
            
            @unlink($testTarget);
        } catch (\Exception $e) {
            // Ignore
        }

        return [
            'passed' => false,
            'message' => 'Symlinks are not available',
            'advice' => 'Use SSH update or manual ZIP upload (Tier 0). Note: updates will be in-place (no atomic rollback).',
        ];
    }

    /**
     * Check if ZIP extraction is available
     */
    private function checkZipExtraction(): array
    {
        if (!extension_loaded('zip')) {
            return [
                'passed' => false,
                'message' => 'PHP ZIP extension is not installed',
                'advice' => 'Install PHP ZIP extension or use SSH/manual ZIP upload',
            ];
        }

        return [
            'passed' => true,
            'message' => 'ZIP extraction is available',
        ];
    }

    /**
     * Check if Artisan commands can be executed
     */
    private function checkArtisanExecution(): array
    {
        // Check if we can at least access the artisan file
        $artisanPath = base_path('artisan');
        
        if (!file_exists($artisanPath)) {
            return [
                'passed' => false,
                'message' => 'Artisan file not found',
                'advice' => 'This may indicate an installation issue',
            ];
        }

        // Check if we can execute PHP (basic check)
        if (!function_exists('exec') && !function_exists('shell_exec') && !function_exists('system')) {
            return [
                'passed' => false,
                'message' => 'Cannot execute system commands (exec/shell_exec disabled)',
                'advice' => 'Migrations may need to be run manually via SSH',
            ];
        }

        return [
            'passed' => true,
            'message' => 'Artisan execution appears possible',
        ];
    }

    /**
     * Check that the live site is actually served from the release that the
     * `current` symlink points to.
     *
     * The browser updater extracts each release into releases/<version> and
     * flips the `current` symlink. That only changes the live site if the web
     * server's document root follows `current/public`. On a "static" install
     * (the app served from a fixed public/ folder) the updater would extract,
     * migrate, and flip the symlink, but visitors would keep seeing the old
     * code — the classic "update said OK but nothing changed". This check
     * detects that mismatch up front instead of letting a no-op deploy run.
     */
    private function checkDeploymentPaths(): array
    {
        $deployRoot = config('updater.deploy_root');
        $releasesPath = rtrim($deployRoot, '/') . '/' . trim(config('updater.releases_path', 'releases'), '/');
        $currentLink = rtrim($deployRoot, '/') . '/' . trim(config('updater.current_symlink', 'current'), '/');

        $base = realpath(base_path()) ?: rtrim(base_path(), '/');
        $releasesReal = realpath($releasesPath);

        // The running code's base path must live under the releases directory
        // (i.e. the docroot is serving a release, not a fixed copy).
        $servedFromRelease = $releasesReal !== false
            && ($base === $releasesReal || str_starts_with($base . '/', $releasesReal . '/'));

        if ($servedFromRelease) {
            $currentTarget = is_link($currentLink) ? (realpath($currentLink) ?: null) : null;
            $isCurrentRelease = $currentTarget !== null && $currentTarget === $base;

            return [
                'passed' => true,
                'message' => $isCurrentRelease
                    ? 'The live site is served from the current release symlink. Updates take effect immediately.'
                    : 'The live site is served from a release directory. Updates will take effect on the next request.',
            ];
        }

        return [
            'passed' => false,
            'message' => 'The live site is not served from the release symlink (current/public).',
            'advice' => 'Browser updates deploy to releases/ and flip the "current" symlink, but your web root '
                . 'serves a fixed directory, so the live site would not change. Point your document root at '
                . '"' . $currentLink . '/public" (see HOSTING.md), or update via Git / file sync instead and set '
                . 'UPDATER_ENABLED=false.',
        ];
    }

    /**
     * Get update recommendation based on compatibility checks
     */
    private function getRecommendation(array $checks, bool $allPassed): string
    {
        if ($allPassed) {
            return 'Browser update is supported. You can use the in-app updater (Tier 1) for atomic updates with rollback.';
        }

        $failedChecks = collect($checks)->filter(fn($check) => !$check['passed']);

        if ($failedChecks->has('deployment_paths')) {
            return 'Browser update will not change your live site: your web root is not served from the '
                . '"current/public" release symlink, so updates would deploy to releases/ without taking effect. '
                . 'Point your document root at <deploy_root>/current/public (see HOSTING.md), or update via '
                . 'Git / file sync instead.';
        }

        if ($failedChecks->has('write_access')) {
            return 'Browser update is not supported. Use manual GitHub Release ZIP upload (Tier 0) via FTP/file manager.';
        }

        if ($failedChecks->has('symlinks')) {
            return 'Browser update is partially supported but symlinks are not available. Updates will be in-place (no atomic rollback). Consider using SSH update or manual ZIP upload.';
        }

        if ($failedChecks->has('zip_extraction')) {
            return 'Browser update is not supported. Install PHP ZIP extension, or use SSH update, or use manual ZIP upload (Tier 0).';
        }

        return 'Browser update may have limitations. Use SSH update or manual ZIP upload (Tier 0) for best compatibility.';
    }
}
