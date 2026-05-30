<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateGeoIpDatabase extends Command
{
    protected $signature = 'geoip:update';
    protected $description = 'Download and update the GeoLite2 Country database.';

    public function handle(): int
    {
        $targetDir = dirname(config('visitorlog.geoip.database_path'));
        if (!$targetDir) {
            $this->error('GeoIP target directory is not configured.');
            return self::FAILURE;
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
            $this->error("Failed to create GeoIP directory at {$targetDir}.");
            return self::FAILURE;
        }

        $licenseKey = $this->resolveLicenseKey();
        if (!$licenseKey) {
            $this->error('MaxMind license key is missing. Set MAXMIND_LICENSE_KEY or provide GeoIP.conf.');
            return self::FAILURE;
        }

        $archivePath = $targetDir . '/GeoLite2-Country.tar.gz';
        $tarPath = $targetDir . '/GeoLite2-Country.tar';
        $extractDir = $targetDir . '/tmp';

        $downloadUrl = sprintf(
            'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=%s&suffix=tar.gz',
            urlencode($licenseKey)
        );

        $response = Http::timeout(60)->sink($archivePath)->get($downloadUrl);
        if (!$response->successful()) {
            $this->error('Failed to download GeoLite2 database. Check your license key.');
            Log::warning('GeoIP download failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return self::FAILURE;
        }

        try {
            if (!$this->isGzipArchive($archivePath)) {
                $preview = trim((string) @file_get_contents($archivePath, false, null, 0, 200));
                $this->error($preview !== '' ? $preview : 'Downloaded GeoIP archive is invalid.');
                return self::FAILURE;
            }

            if (file_exists($tarPath)) {
                unlink($tarPath);
            }

            $phar = new \PharData($archivePath);
            $phar->decompress();

            $tar = new \PharData($tarPath);
            $relativePath = $this->findDatabasePath($tar);
            if (!$relativePath) {
                $this->error('GeoLite2-Country.mmdb not found in the archive.');
                return self::FAILURE;
            }

            if (!is_dir($extractDir)) {
                mkdir($extractDir, 0750, true);
            }

            $tar->extractTo($extractDir, [$relativePath], true);

            $extractedPath = $extractDir . '/' . $relativePath;
            if (!is_file($extractedPath)) {
                $this->error('Extracted database file is missing.');
                return self::FAILURE;
            }

            $targetPath = config('visitorlog.geoip.database_path');
            if (!copy($extractedPath, $targetPath)) {
                $this->error('Failed to move GeoIP database into place.');
                return self::FAILURE;
            }
        } catch (\Exception $exception) {
            $this->error('GeoIP update failed. Check logs for details.');
            Log::warning('GeoIP update error', [
                'error' => $exception->getMessage(),
            ]);
            return self::FAILURE;
        } finally {
            $this->cleanupArtifacts([$archivePath, $tarPath], $extractDir);
        }

        $this->info('GeoLite2 Country database updated.');
        return self::SUCCESS;
    }

    private function resolveLicenseKey(): ?string
    {
        $licenseKey = config('visitorlog.geoip.license_key');
        if ($licenseKey) {
            return $licenseKey;
        }

        $configPath = config('visitorlog.geoip.config_path');
        if (!$configPath || !is_file($configPath)) {
            return null;
        }

        $contents = file($configPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$contents) {
            return null;
        }

        foreach ($contents as $line) {
            if (str_starts_with(trim($line), 'LicenseKey')) {
                $parts = preg_split('/\s+/', trim($line));
                return $parts[1] ?? null;
            }
        }

        return null;
    }

    private function findDatabasePath(\PharData $tar): ?string
    {
        $iterator = new \RecursiveIteratorIterator($tar);
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            $path = $iterator->getSubPathname();
            if (str_ends_with($path, 'GeoLite2-Country.mmdb')) {
                return $path;
            }
        }

        return null;
    }

    private function isGzipArchive(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return false;
        }
        $header = fread($handle, 2);
        fclose($handle);

        return $header === "\x1f\x8b";
    }

    private function cleanupArtifacts(array $files, string $extractDir): void
    {
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        if (is_dir($extractDir)) {
            $this->deleteDirectory($extractDir);
        }
    }

    private function deleteDirectory(string $directory): void
    {
        $items = scandir($directory);
        if (!$items) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }
            @unlink($path);
        }

        @rmdir($directory);
    }
}
