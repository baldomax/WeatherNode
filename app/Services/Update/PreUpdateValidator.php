<?php

namespace App\Services\Update;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreUpdateValidator
{
    /**
     * Validate system requirements before deployment
     */
    public function validate(string $targetVersion, ?array $releaseInfo = null): array
    {
        $results = [
            'php_version' => $this->checkPhpVersion($releaseInfo),
            'php_extensions' => $this->checkPhpExtensions(),
            'disk_space' => $this->checkDiskSpace($releaseInfo),
            'database_schema' => $this->checkDatabaseSchema(),
            'breaking_changes' => $this->checkBreakingChanges($releaseInfo),
        ];

        $allPassed = collect($results)->every(function ($result) {
            return $result['passed'] ?? true;
        });

        return [
            'passed' => $allPassed,
            'results' => $results,
            'warnings' => collect($results)->filter(fn($r) => isset($r['warning']) && $r['warning'])->toArray(),
        ];
    }

    /**
     * Check PHP version compatibility
     */
    private function checkPhpVersion(?array $releaseInfo): array
    {
        $currentPhp = PHP_VERSION;
        $requiredPhp = '8.2.0'; // Minimum from composer.json
        
        // Try to get required PHP from release info if available
        if ($releaseInfo && isset($releaseInfo['php_version'])) {
            $requiredPhp = $releaseInfo['php_version'];
        }

        if (version_compare($currentPhp, $requiredPhp, '<')) {
            return [
                'passed' => false,
                'message' => "PHP version {$currentPhp} is below required {$requiredPhp}",
                'current' => $currentPhp,
                'required' => $requiredPhp,
            ];
        }

        return [
            'passed' => true,
            'message' => "PHP version {$currentPhp} meets requirements",
            'current' => $currentPhp,
            'required' => $requiredPhp,
        ];
    }

    /**
     * Check required PHP extensions
     */
    private function checkPhpExtensions(): array
    {
        $required = ['pdo', 'pdo_sqlite', 'mbstring', 'xml', 'curl', 'zip', 'gd', 'fileinfo'];
        $missing = [];

        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        if (!empty($missing)) {
            return [
                'passed' => false,
                'message' => 'Missing required PHP extensions: ' . implode(', ', $missing),
                'missing' => $missing,
            ];
        }

        return [
            'passed' => true,
            'message' => 'All required PHP extensions are installed',
            'extensions' => $required,
        ];
    }

    /**
     * Check available disk space
     */
    private function checkDiskSpace(?array $releaseInfo): array
    {
        $deployRoot = config('updater.deploy_root', base_path());
        $freeSpace = disk_free_space($deployRoot);
        
        // Estimate required space (ZIP size * 2 for extraction + safety margin)
        $estimatedNeeded = 100 * 1024 * 1024; // Default: 100MB
        
        if ($releaseInfo && isset($releaseInfo['zip_size'])) {
            $estimatedNeeded = $releaseInfo['zip_size'] * 2.5; // ZIP + extraction + margin
        }

        if ($freeSpace < $estimatedNeeded) {
            return [
                'passed' => false,
                'message' => sprintf(
                    'Insufficient disk space. Available: %s, Required: %s',
                    $this->formatBytes($freeSpace),
                    $this->formatBytes($estimatedNeeded)
                ),
                'available' => $freeSpace,
                'required' => $estimatedNeeded,
            ];
        }

        return [
            'passed' => true,
            'message' => sprintf(
                'Sufficient disk space available: %s',
                $this->formatBytes($freeSpace)
            ),
            'available' => $freeSpace,
            'required' => $estimatedNeeded,
        ];
    }

    /**
     * Check database schema compatibility
     */
    private function checkDatabaseSchema(): array
    {
        try {
            // Basic check: can we connect and query?
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            $driver = DB::getDriverName();
            $migrationsTableExists = Schema::hasTable('migrations');

            if (!$migrationsTableExists) {
                return [
                    'passed' => true,
                    'message' => "Database connection healthy ({$driver}); migrations table not found yet",
                    'warning' => true,
                ];
            }

            return [
                'passed' => true,
                'message' => 'Database schema appears compatible',
            ];
        } catch (\Exception $e) {
            return [
                'passed' => false,
                'message' => 'Database compatibility check failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check for breaking changes in release notes
     */
    private function checkBreakingChanges(?array $releaseInfo): array
    {
        if (!$releaseInfo || !isset($releaseInfo['body'])) {
            return [
                'passed' => true,
                'message' => 'No release notes available to check',
            ];
        }

        $body = $releaseInfo['body'];
        $hasBreaking = stripos($body, 'BREAKING') !== false ||
                      stripos($body, 'breaking change') !== false ||
                      stripos($body, '⚠️') !== false;

        if ($hasBreaking) {
            return [
                'passed' => true, // Not a blocker, just a warning
                'message' => 'Release contains breaking changes - review release notes carefully',
                'warning' => true,
                'has_breaking' => true,
            ];
        }

        return [
            'passed' => true,
            'message' => 'No breaking changes detected',
            'has_breaking' => false,
        ];
    }

    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
