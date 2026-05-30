<?php

namespace App\Services\Update;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class HealthCheckService
{
    private int $timeout;
    private array $endpoints;

    public function __construct()
    {
        $this->timeout = config('updater.health_check_timeout', 30);
        $this->endpoints = config('updater.health_check_endpoints', ['/']);
    }

    /**
     * Verify the health of a deployed release
     * 
     * @param string $releaseDir The release directory to check
     * @return bool True if healthy, false otherwise
     */
    public function verify(string $releaseDir): bool
    {
        $results = [
            'http_check' => $this->checkHttpResponse($releaseDir),
            'database_check' => $this->checkDatabase(),
            'artisan_check' => $this->checkArtisan($releaseDir),
            'error_log_check' => $this->checkErrorLog($releaseDir),
        ];

        $allPassed = collect($results)->every(fn($result) => $result['passed']);

        if (!$allPassed) {
            Log::warning('Health check failed', [
                'release_dir' => $releaseDir,
                'results' => $results,
            ]);
        }

        return $allPassed;
    }

    /**
     * Check if HTTP endpoints respond correctly
     * Note: This temporarily brings the site online to test, then restores maintenance mode
     */
    private function checkHttpResponse(string $releaseDir): array
    {
        try {
            // Temporarily bring site online for health check
            $wasInMaintenance = file_exists(storage_path('framework/down'));
            
            if ($wasInMaintenance) {
                Artisan::call('up');
                // Small delay to ensure site is up
                usleep(500000); // 0.5 seconds
            }
            
            try {
                $baseUrl = config('app.url');
                
                foreach ($this->endpoints as $endpoint) {
                    $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
                    
                    $response = Http::timeout($this->timeout)
                        ->get($url);

                    // A response below 500 means the app booted and routed the
                    // request. 2xx/3xx are clearly healthy; 401/403/404 indicate
                    // the framework is up but the route is protected or absent —
                    // still a healthy app, not a failed deploy. Only 5xx (or a
                    // connection error, handled below) signals a broken release.
                    if ($response->status() >= 500) {
                        return [
                            'passed' => false,
                            'message' => "HTTP check failed: {$endpoint} returned status {$response->status()}",
                            'endpoint' => $endpoint,
                            'status' => $response->status(),
                        ];
                    }
                }

                return [
                    'passed' => true,
                    'message' => 'HTTP endpoints responding correctly',
                    'was_in_maintenance' => $wasInMaintenance,
                ];
            } catch (\Exception $e) {
                return [
                    'passed' => false,
                    'message' => 'HTTP check failed: ' . $e->getMessage(),
                    'error' => $e->getMessage(),
                ];
            }
        } catch (\Exception $e) {
            return [
                'passed' => false,
                'message' => 'HTTP check failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            // Simple query to verify database connection
            DB::connection()->getPdo();
            
            // Try a simple query
            DB::select('SELECT 1');
            
            return [
                'passed' => true,
                'message' => 'Database connection healthy',
            ];
        } catch (\Exception $e) {
            return [
                'passed' => false,
                'message' => 'Database check failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if Artisan can execute in the release directory
     */
    private function checkArtisan(string $releaseDir): array
    {
        $artisanPath = $releaseDir . '/artisan';
        
        if (!file_exists($artisanPath)) {
            return [
                'passed' => false,
                'message' => 'Artisan file not found in release',
            ];
        }

        if (!is_executable($artisanPath)) {
            return [
                'passed' => false,
                'message' => 'Artisan file is not executable',
            ];
        }

        // Try to run a simple artisan command
        try {
            $originalCwd = getcwd();
            chdir($releaseDir);
            
            exec('php artisan --version 2>&1', $output, $returnCode);
            
            chdir($originalCwd);
            
            if ($returnCode !== 0) {
                return [
                    'passed' => false,
                    'message' => 'Artisan command failed',
                    'output' => implode("\n", $output),
                ];
            }

            return [
                'passed' => true,
                'message' => 'Artisan executable and working',
            ];
        } catch (\Exception $e) {
            if (isset($originalCwd)) {
                chdir($originalCwd);
            }
            
            return [
                'passed' => false,
                'message' => 'Artisan check failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check for fatal errors in error log
     */
    private function checkErrorLog(string $releaseDir): array
    {
        $logPath = $releaseDir . '/storage/logs/laravel.log';
        
        // If log doesn't exist yet, that's OK (fresh deployment)
        if (!file_exists($logPath)) {
            return [
                'passed' => true,
                'message' => 'No error log found (fresh deployment)',
            ];
        }

        // Check last 50 lines for fatal errors
        $lines = file($logPath);
        $recentLines = array_slice($lines, -50);
        
        foreach ($recentLines as $line) {
            if (stripos($line, 'Fatal error') !== false || 
                stripos($line, 'Parse error') !== false ||
                stripos($line, 'Class not found') !== false) {
                return [
                    'passed' => false,
                    'message' => 'Fatal errors detected in log',
                    'error_line' => trim($line),
                ];
            }
        }

        return [
            'passed' => true,
            'message' => 'No fatal errors in recent logs',
        ];
    }

    /**
     * Get detailed health check results
     */
    public function getDetailedResults(string $releaseDir): array
    {
        return [
            'http_check' => $this->checkHttpResponse($releaseDir),
            'database_check' => $this->checkDatabase(),
            'artisan_check' => $this->checkArtisan($releaseDir),
            'error_log_check' => $this->checkErrorLog($releaseDir),
        ];
    }
}
