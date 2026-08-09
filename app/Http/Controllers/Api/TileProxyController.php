<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Radar\RainViewerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TileProxyController extends Controller
{
    /**
     * Cache TTL for radar tiles (in seconds)
     * RainViewer updates every 10 minutes, so 15 minutes is safe
     */
    private const TILE_CACHE_TTL = 900; // 15 minutes
    
    /**
     * Cache TTL for frame metadata (in seconds)
     */
    private const FRAME_CACHE_TTL = 1800; // 30 minutes

    /**
     * Consider frame metadata stale after this age (seconds)
     */
    private const FRAME_STALE_AFTER = 720; // 12 minutes

    /**
     * Avoid concurrent/rapid revalidation bursts on frame metadata
     */
    private const FRAME_REFRESH_LOCK_TTL = 60; // 60 seconds
    
    /**
     * Maximum age of cached tiles before cleanup (in hours)
     */
    private const MAX_TILE_AGE_HOURS = 2;

    /**
     * Cache TTL for proxied future-frame images (seconds).
     */
    private const FUTURE_IMAGE_CACHE_TTL = 900;

    /**
     * Allowed upstream hosts for generic future-frame image proxy.
     *
     * Keep this list strict to avoid open-proxy abuse.
     */
    private const FUTURE_IMAGE_ALLOWED_HOSTS = [
        'digital.weather.gov',
        'anonymous.api.dataplatform.knmi.nl',
    ];
    
    /**
     * Proxy a radar tile from RainViewer with caching
     * 
     * URL format: /api/radar/tile/{path}/{size}/{z}/{x}/{y}/{color}/{options}.png
     * Example: /api/radar/tile/v2/radar/1737561600/256/5/16/10/1/1_0.png
     */
    public function tile(Request $request, string $path)
    {
        // Validate path format to prevent abuse
        if (!$this->isValidTilePath($path)) {
            return response('Invalid tile path', 400);
        }
        
        // Tiles are cached on disk only, never in the cache store.
        //
        // This used to also Cache::put the raw PNG, on the assumption the cache
        // was in-memory. Laravel defaults CACHE_STORE to `database`, and the
        // cache table's value column is mediumtext/utf8mb4, so on MySQL the
        // bytes are either rejected outright (strict mode: "Incorrect string
        // value: '\x89PNG...'") or silently truncated to nothing (non-strict,
        // where the read then fails to unserialize). Either way it was never a
        // working cache, just a per-tile write into the database that could
        // never be read back.
        $filePath = 'radar-tiles/' . $path;

        if (Storage::disk('local')->exists($filePath)) {
            return $this->tileResponse(Storage::disk('local')->get($filePath), true);
        }

        // Miss: fetch upstream and store it on disk.
        $frames = $this->resolveLatestFrames(refreshIfStale: true);
        $host = $this->resolveRainViewerHost($frames);
        $upstreamUrl = "{$host}/" . ltrim($path, '/');

        try {
            $response = Http::timeout(8)->accept('image/png')->get($upstreamUrl);
            $contentType = strtolower((string) $response->header('Content-Type', ''));

            if ($response->successful() && str_contains($contentType, 'image/')) {
                $tileData = $response->body();
                if ($tileData !== '') {
                    Storage::disk('local')->put($filePath, $tileData);
                    return $this->tileResponse($tileData, false);
                }
            }

            Log::warning('Radar tile upstream fetch failed', [
                'path' => $path,
                'status' => $response->status(),
                'content_type' => $contentType,
                'url' => $upstreamUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Radar tile upstream fetch exception', [
                'path' => $path,
                'url' => $upstreamUrl,
                'error' => $e->getMessage(),
            ]);
        }

        // Upstream miss/error fallback: reuse the latest cached tile for the same map cell.
        $fallbackTileData = $this->findCachedFallbackTileData($path);
        if ($fallbackTileData !== null) {
            return $this->tileResponse($fallbackTileData, true);
        }

        return $this->transparentPixelResponse();
    }

    /**
     * Proxy future-frame image URLs (e.g. NOAA WMS) to avoid browser CORS blocking.
     */
    public function futureImage(Request $request): Response
    {
        $rawUrl = trim((string) $request->query('url', ''));
        if ($rawUrl === '') {
            return response('Missing url parameter', 400);
        }

        if (!$this->isAllowedFutureImageUrl($rawUrl)) {
            return response('Unsupported upstream URL', 400);
        }

        // On disk for the same reason as tiles: an image in the cache store is
        // rejected or silently truncated on MySQL. Kept under radar-tiles/ so
        // the existing hourly cleanup prunes these too.
        $filePath = 'radar-tiles/future/' . md5($rawUrl) . '.img';

        if (Storage::disk('local')->exists($filePath)) {
            $age = now()->timestamp - Storage::disk('local')->lastModified($filePath);
            if ($age < self::FUTURE_IMAGE_CACHE_TTL) {
                return $this->tileResponse(Storage::disk('local')->get($filePath), true);
            }
        }

        try {
            $upstream = Http::timeout(12)->accept('image/*')->get($rawUrl);
            $contentType = strtolower((string) $upstream->header('Content-Type', ''));
            if ($upstream->successful() && str_contains($contentType, 'image/')) {
                $body = $upstream->body();
                if ($body !== '') {
                    Storage::disk('local')->put($filePath, $body);
                    return $this->tileResponse($body, false);
                }
            }

            Log::warning('Future radar image proxy upstream failed', [
                'url' => $rawUrl,
                'status' => $upstream->status(),
                'content_type' => $contentType,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Future radar image proxy exception', [
                'url' => $rawUrl,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->transparentPixelResponse();
    }

    /**
     * Validate tile path to prevent abuse
     */
    private function isValidTilePath(string $path): bool
    {
        // Must end with .png
        if (!str_ends_with($path, '.png')) {
            return false;
        }
        
        // Must start with v2/radar/ (RainViewer format)
        if (!str_starts_with($path, 'v2/radar/')) {
            return false;
        }
        
        // Must contain only safe characters
        if (!preg_match('#^v2/radar/\d+/\d+/\d+/\d+/\d+/\d+/[\d_]+\.png$#', $path)) {
            return false;
        }
        
        return true;
    }

    private function isAllowedFutureImageUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        foreach (self::FUTURE_IMAGE_ALLOWED_HOSTS as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return tile as PNG response with appropriate headers
     */
    private function tileResponse(string $data, bool $fromCache): Response
    {
        return response($data, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=900') // Browser can cache for 15 min
            ->header('X-Cache', $fromCache ? 'HIT' : 'MISS')
            ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Return a transparent 1x1 PNG pixel
     */
    private function transparentPixelResponse(): Response
    {
        // 1x1 transparent PNG
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        
        return response($pixel, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=60')
            ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Get radar frames (proxied API call with caching)
     */
    public function frames(): \Illuminate\Http\JsonResponse
    {
        $frames = $this->resolveLatestFrames(refreshIfStale: true);

        if ($this->hasValidFrames($frames)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'version' => $frames['version'] ?? '2.0',
                    'generated' => $frames['generated'] ?? time(),
                    // In proxy mode the frontend must request tiles through this endpoint.
                    'host' => '/api/radar/tile',
                    'radar' => [
                        'past' => $frames['radar']['past'] ?? [],
                    ],
                ],
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Radar data not available',
            'data' => null,
        ]);
    }

    private function resolveLatestFrames(bool $refreshIfStale): ?array
    {
        $frames = $this->readFramesFromCache();

        if (!$refreshIfStale || !$this->shouldRefreshFrames($frames)) {
            return $frames;
        }

        // Use a short lock to avoid hammering RainViewer when many requests hit at once.
        $lockAcquired = Cache::add(
            'rainviewer_frames_refresh_lock',
            1,
            now()->addSeconds(self::FRAME_REFRESH_LOCK_TTL)
        );

        if (!$lockAcquired) {
            return $frames;
        }

        $freshFrames = $this->refreshFramesFromUpstream();
        return $freshFrames ?? $frames;
    }

    private function readFramesFromCache(): ?array
    {
        $frames = Cache::get('rainviewer_frames_proxy');
        if (is_array($frames) && isset($frames['data']) && is_array($frames['data'])) {
            $frames = $frames['data'];
        } elseif (!is_array($frames)) {
            $frames = Cache::get('rainviewer_frames');
        }

        return is_array($frames) ? $frames : null;
    }

    private function shouldRefreshFrames(?array $frames): bool
    {
        if (!$this->hasValidFrames($frames)) {
            return true;
        }

        $generated = (int) ($frames['generated'] ?? 0);
        if ($generated <= 0) {
            return true;
        }

        return (time() - $generated) > self::FRAME_STALE_AFTER;
    }

    private function hasValidFrames(?array $frames): bool
    {
        if (!is_array($frames)) {
            return false;
        }

        $pastFrames = data_get($frames, 'radar.past');
        return is_array($pastFrames) && count($pastFrames) > 0;
    }

    private function refreshFramesFromUpstream(): ?array
    {
        try {
            $service = app(RainViewerService::class);
            $frames = $service->getRadarFrames(bypassCache: true);

            if (!$this->hasValidFrames($frames)) {
                return null;
            }

            Cache::put('rainviewer_frames', $frames, now()->addSeconds(self::FRAME_CACHE_TTL));
            Cache::put('rainviewer_frames_proxy', [
                'success' => true,
                'data' => [
                    'version' => $frames['version'] ?? '2.0',
                    'generated' => $frames['generated'] ?? time(),
                    'host' => $this->resolveRainViewerHost($frames),
                    'radar' => [
                        'past' => $frames['radar']['past'] ?? [],
                    ],
                ],
            ], now()->addSeconds(self::FRAME_CACHE_TTL));

            return $frames;
        } catch (\Throwable $e) {
            Log::warning('RainViewer metadata refresh failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveRainViewerHost(?array $frames): string
    {
        $host = is_array($frames) ? ($frames['host'] ?? null) : null;
        if (!is_string($host) || $host === '') {
            $host = 'https://tilecache.rainviewer.com';
        }

        return rtrim($host, '/');
    }

    private function findCachedFallbackTileData(string $path): ?string
    {
        $normalizedPath = ltrim($path, '/');
        if (!preg_match('#^v2/radar/\d+(/.+)$#', $normalizedPath, $matches)) {
            return null;
        }

        $suffix = $matches[1];
        $frames = $this->readFramesFromCache();
        if (!$this->hasValidFrames($frames)) {
            return null;
        }

        $pastFrames = data_get($frames, 'radar.past', []);
        if (!is_array($pastFrames) || empty($pastFrames)) {
            return null;
        }

        for ($i = count($pastFrames) - 1; $i >= 0; $i--) {
            $framePath = ltrim((string) data_get($pastFrames, "{$i}.path", ''), '/');
            if ($framePath === '') {
                continue;
            }

            $candidatePath = $framePath . $suffix;
            if ($candidatePath === $normalizedPath) {
                continue;
            }

            $candidateFilePath = 'radar-tiles/' . $candidatePath;
            if (!Storage::disk('local')->exists($candidateFilePath)) {
                continue;
            }

            return Storage::disk('local')->get($candidateFilePath);
        }

        return null;
    }

    /**
     * Cleanup old cached tiles
     * Called by scheduled task or manually
     */
    public static function cleanupOldTiles(): int
    {
        $deleted = 0;
        $basePath = 'radar-tiles';
        
        if (!Storage::disk('local')->exists($basePath)) {
            return 0;
        }
        
        $cutoffTime = now()->subHours(self::MAX_TILE_AGE_HOURS)->timestamp;
        
        // Get all files recursively
        $files = Storage::disk('local')->allFiles($basePath);
        
        foreach ($files as $file) {
            try {
                $lastModified = Storage::disk('local')->lastModified($file);
                
                if ($lastModified < $cutoffTime) {
                    Storage::disk('local')->delete($file);
                    $deleted++;
                }
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }
        
        // Clean up empty directories
        self::cleanupEmptyDirectories($basePath);
        
        Log::info('Radar tile cleanup completed', [
            'deleted' => $deleted,
        ]);
        
        return $deleted;
    }

    /**
     * Remove empty directories recursively
     */
    private static function cleanupEmptyDirectories(string $path): void
    {
        $directories = Storage::disk('local')->directories($path);
        
        foreach ($directories as $dir) {
            self::cleanupEmptyDirectories($dir);
            
            // Check if directory is now empty
            $files = Storage::disk('local')->files($dir);
            $subdirs = Storage::disk('local')->directories($dir);
            
            if (empty($files) && empty($subdirs)) {
                Storage::disk('local')->deleteDirectory($dir);
            }
        }
    }
}
