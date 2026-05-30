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
     * Get update recommendation based on compatibility checks
     */
    private function getRecommendation(array $checks, bool $allPassed): string
    {
        if ($allPassed) {
            return 'Browser update is supported. You can use the in-app updater (Tier 1) for atomic updates with rollback.';
        }

        $failedChecks = collect($checks)->filter(fn($check) => !$check['passed']);
        
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
