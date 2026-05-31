<?php

namespace App\Services\Update;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

class BackupService
{
    private string $backupPath;
    private int $keepCount;

    public function __construct()
    {
        $sharedPath = config('updater.shared_path', 'shared');
        $deployRoot = config('updater.deploy_root', base_path());
        $this->backupPath = $deployRoot . '/' . $sharedPath . '/backups';
        // Admin-configurable (Updates page) with the env/config default as fallback.
        $this->keepCount = max(1, (int) Setting::getValue('updater.backup_keep_count', config('updater.backup_keep_count', 5)));
    }

    /**
     * Create backups before update
     */
    public function createBackup(): array
    {
        if (!config('updater.backup_enabled', true)) {
            return [
                'success' => true,
                'message' => 'Backups are disabled',
                'skipped' => true,
            ];
        }

        try {
            // Ensure backup directory exists
            if (!File::exists($this->backupPath)) {
                File::makeDirectory($this->backupPath, 0755, true);
            }

            $timestamp = date('Y-m-d_H-i-s');
            $backups = [];

            // Backup .env file
            $envBackup = $this->backupEnv($timestamp);
            if ($envBackup) {
                $backups['env'] = $envBackup;
            }

            // Backup database
            $dbBackup = $this->backupDatabase($timestamp);
            if ($dbBackup) {
                $backups['database'] = $dbBackup;
            }

            // Backup storage directory
            $storageBackup = $this->backupStorage($timestamp);
            if ($storageBackup) {
                $backups['storage'] = $storageBackup;
            }

            // Clean up old backups
            $this->cleanupOldBackups();

            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'backups' => $backups,
                'timestamp' => $timestamp,
            ];
        } catch (\Exception $e) {
            Log::error('Backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Backup .env file
     */
    private function backupEnv(string $timestamp): ?string
    {
        $envPath = base_path('.env');
        
        if (!file_exists($envPath)) {
            Log::warning('.env file not found for backup');
            return null;
        }

        $backupFile = $this->backupPath . "/env_backup_{$timestamp}.env";
        
        if (copy($envPath, $backupFile)) {
            return $backupFile;
        }

        throw new \Exception("Failed to backup .env file to {$backupFile}");
    }

    /**
     * Backup database
     */
    private function backupDatabase(string $timestamp): ?string
    {
        $connection = config('database.default');
        
        if ($connection === 'sqlite') {
            return $this->backupSqlite($timestamp);
        } elseif ($connection === 'mysql') {
            return $this->backupMysql($timestamp);
        }

        Log::warning("Database backup not supported for connection: {$connection}");
        return null;
    }

    /**
     * Backup SQLite database
     */
    private function backupSqlite(string $timestamp): ?string
    {
        $dbPath = config('database.connections.sqlite.database');
        
        if (!file_exists($dbPath)) {
            Log::warning('SQLite database file not found for backup');
            return null;
        }

        $backupFile = $this->backupPath . "/db_backup_{$timestamp}.sqlite";
        
        if (copy($dbPath, $backupFile)) {
            return $backupFile;
        }

        throw new \Exception("Failed to backup SQLite database to {$backupFile}");
    }

    /**
     * Backup MySQL database
     */
    private function backupMysql(string $timestamp): ?string
    {
        $config = config('database.connections.mysql');
        $backupFile = $this->backupPath . "/db_backup_{$timestamp}.sql";
        
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s %s > %s 2>&1',
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? 3306),
            escapeshellarg($config['database']),
            escapeshellarg($backupFile)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($backupFile)) {
            throw new \Exception("MySQL backup failed: " . implode("\n", $output));
        }

        return $backupFile;
    }

    /**
     * Backup storage directory
     */
    private function backupStorage(string $timestamp): ?string
    {
        $storagePath = storage_path();
        
        if (!File::exists($storagePath)) {
            Log::warning('Storage directory not found for backup');
            return null;
        }

        $backupFile = $this->backupPath . "/storage_backup_{$timestamp}.tar.gz";
        
        // Use tar to compress storage directory
        $command = sprintf(
            'cd %s && tar -czf %s storage/ 2>&1',
            escapeshellarg(dirname($storagePath)),
            escapeshellarg($backupFile)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($backupFile)) {
            // Fallback: try without compression
            $backupFile = $this->backupPath . "/storage_backup_{$timestamp}";
            File::copyDirectory($storagePath, $backupFile);
            
            if (!File::exists($backupFile)) {
                throw new \Exception("Storage backup failed: " . implode("\n", $output));
            }
        }

        return $backupFile;
    }

    /**
     * Clean up old backups, keeping only the last N
     */
    private function cleanupOldBackups(): void
    {
        if (!File::exists($this->backupPath)) {
            return;
        }

        $backups = collect(File::files($this->backupPath))
            ->map(function ($file) {
                return [
                    'path' => $file->getPathname(),
                    'modified' => $file->getMTime(),
                ];
            })
            ->sortByDesc('modified')
            ->values();

        // Remove backups beyond keep count
        if ($backups->count() > $this->keepCount) {
            $toRemove = $backups->slice($this->keepCount);
            
            foreach ($toRemove as $backup) {
                @unlink($backup['path']);
            }
        }
    }

    /**
     * Get list of available backups
     */
    public function getBackups(): array
    {
        if (!File::exists($this->backupPath)) {
            return [];
        }

        return collect(File::files($this->backupPath))
            ->map(function ($file) {
                return [
                    'filename' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'created_at' => $file->getMTime(),
                    'type' => $this->getBackupType($file->getFilename()),
                ];
            })
            ->sortByDesc('created_at')
            ->values()
            ->toArray();
    }

    /**
     * Restore the database from a backup file (used by automatic rollback).
     */
    public function restoreDatabase(string $backupFile): array
    {
        try {
            if (!file_exists($backupFile)) {
                return ['success' => false, 'message' => "Backup file not found: {$backupFile}"];
            }

            $connection = config('database.default');

            if ($connection === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                if (!@copy($backupFile, $dbPath)) {
                    throw new \Exception("Failed to copy SQLite backup over {$dbPath}");
                }
            } elseif ($connection === 'mysql') {
                $config = config('database.connections.mysql');
                $command = sprintf(
                    'mysql --user=%s --password=%s --host=%s --port=%s %s < %s 2>&1',
                    escapeshellarg($config['username']),
                    escapeshellarg($config['password']),
                    escapeshellarg($config['host']),
                    escapeshellarg($config['port'] ?? 3306),
                    escapeshellarg($config['database']),
                    escapeshellarg($backupFile)
                );

                exec($command, $output, $returnCode);
                if ($returnCode !== 0) {
                    throw new \Exception('mysql restore failed: ' . implode("\n", $output));
                }
            } else {
                return ['success' => false, 'message' => "Restore not supported for connection: {$connection}"];
            }

            return ['success' => true, 'message' => 'Database restored from backup'];
        } catch (\Exception $e) {
            Log::error('Database restore failed', ['error' => $e->getMessage(), 'backup' => $backupFile]);
            return ['success' => false, 'message' => 'Database restore failed: ' . $e->getMessage()];
        }
    }

    /**
     * Delete a single backup file (or uncompressed storage backup directory) by
     * its filename. The name is validated to stay inside the backup directory.
     */
    public function deleteBackup(string $filename): array
    {
        // Reject path traversal — only a bare filename is allowed.
        if ($filename === '' || basename($filename) !== $filename) {
            return ['success' => false, 'message' => 'Invalid backup filename'];
        }

        $target = $this->backupPath . '/' . $filename;
        $realBase = realpath($this->backupPath);
        $realTarget = realpath($target);

        if ($realBase === false || $realTarget === false || !str_starts_with($realTarget, $realBase . DIRECTORY_SEPARATOR)) {
            return ['success' => false, 'message' => 'Backup not found'];
        }

        $deleted = File::isDirectory($realTarget)
            ? File::deleteDirectory($realTarget)
            : @unlink($realTarget);

        if (!$deleted) {
            return ['success' => false, 'message' => "Failed to delete backup {$filename}"];
        }

        Log::info('Backup deleted', ['filename' => $filename]);
        return ['success' => true, 'message' => "Deleted backup {$filename}"];
    }

    /**
     * Determine backup type from filename
     */
    private function getBackupType(string $filename): string
    {
        if (strpos($filename, 'env_backup') !== false) {
            return 'env';
        } elseif (strpos($filename, 'db_backup') !== false) {
            return 'database';
        } elseif (strpos($filename, 'storage_backup') !== false) {
            return 'storage';
        }
        
        return 'unknown';
    }
}
