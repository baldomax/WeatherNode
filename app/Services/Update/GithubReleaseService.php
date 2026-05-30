<?php

namespace App\Services\Update;

use App\Support\Versioning;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GithubReleaseService
{
    private const LATEST_RELEASE_CACHE_KEY = 'github_latest_release';
    private const LATEST_RELEASE_CACHE_TTL = 300;

    private string $repo;
    private ?string $token;

    public function __construct()
    {
        $this->repo = config('updater.github_repo');
        $this->token = config('updater.github_token');
    }

    /**
     * Get the latest release from GitHub
     */
    public function getLatestRelease(): ?array
    {
        return Cache::remember(self::LATEST_RELEASE_CACHE_KEY, self::LATEST_RELEASE_CACHE_TTL, function () {
            try {
                $url = "https://api.github.com/repos/{$this->repo}/releases/latest";

                $response = Http::withHeaders($this->getHeaders())
                    ->connectTimeout(3)
                    ->timeout(6)
                    ->get($url);

                if (!$response->successful()) {
                    return null;
                }

                $data = $response->json();

                return [
                    'tag' => $data['tag_name'] ?? null,
                    'name' => $data['name'] ?? null,
                    'body' => $data['body'] ?? null,
                    'published_at' => $data['published_at'] ?? null,
                    'sha256' => $this->extractReleaseChecksum($data['body'] ?? null),
                    'assets' => $this->parseAssets($data['assets'] ?? []),
                ];
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * Get all releases
     */
    public function getReleases(int $limit = 10): array
    {
        try {
            $url = "https://api.github.com/repos/{$this->repo}/releases";

            $response = Http::withHeaders($this->getHeaders())
                ->connectTimeout(3)
                ->timeout(8)
                ->get($url, ['per_page' => $limit]);

            if (!$response->successful()) {
                return [];
            }

            $releases = $response->json();

            return collect($releases)->map(function ($release) {
                return [
                    'tag' => $release['tag_name'] ?? null,
                    'name' => $release['name'] ?? null,
                    'body' => $release['body'] ?? null,
                    'published_at' => $release['published_at'] ?? null,
                    'sha256' => $this->extractReleaseChecksum($release['body'] ?? null),
                    'assets' => $this->parseAssets($release['assets'] ?? []),
                ];
            })->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Download a release asset
     */
    public function downloadAsset(string $assetUrl, string $destinationPath): bool
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->connectTimeout(5)
                ->timeout(300)
                ->sink($destinationPath)
                ->get($assetUrl);

            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Parse release assets to find the deploy ZIP
     */
    private function parseAssets(array $assets): array
    {
        $assetName = config('updater.release_asset_name');
        
        return collect($assets)->map(function ($asset) use ($assetName) {
            return [
                'name' => $asset['name'] ?? null,
                'url' => $asset['browser_download_url'] ?? null,
                'size' => $asset['size'] ?? null,
                'sha256' => $this->extractAssetChecksum($asset),
                'is_deploy_zip' => ($asset['name'] ?? '') === $assetName,
            ];
        })->toArray();
    }

    private function extractAssetChecksum(array $asset): ?string
    {
        $digest = $asset['digest'] ?? null;
        if (!is_string($digest) || trim($digest) === '') {
            return null;
        }

        $digest = trim($digest);
        if (preg_match('/^sha256:([a-f0-9]{64})$/i', $digest, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }

    private function extractReleaseChecksum(mixed $body): ?string
    {
        if (!is_string($body) || trim($body) === '') {
            return null;
        }

        // Accept either "sha256: <hash>" or "<hash>  filename" style snippets in release notes.
        if (preg_match('/sha256[^a-f0-9]*([a-f0-9]{64})/i', $body, $matches) === 1) {
            return strtolower($matches[1]);
        }

        if (preg_match('/\b([a-f0-9]{64})\b/i', $body, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    /**
     * Get HTTP headers for GitHub API requests
     */
    private function getHeaders(): array
    {
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
        ];

        if ($this->token) {
            $headers['Authorization'] = "token {$this->token}";
        }

        return $headers;
    }

    /**
     * Check if an update is available
     */
    public function isUpdateAvailable(): bool
    {
        $latest = $this->getLatestRelease();
        if (!$latest) {
            return false;
        }

        $currentVersion = \App\Services\VersionService::getAppVersion();
        $latestVersion = $latest['tag'] ?? '';

        if (!is_string($latestVersion) || trim($latestVersion) === '') {
            return false;
        }

        // True only when GitHub tag is actually newer than current version.
        return Versioning::compare($currentVersion, $latestVersion) < 0;
    }

}
