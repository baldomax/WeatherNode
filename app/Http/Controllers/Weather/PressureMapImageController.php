<?php

declare(strict_types=1);

namespace App\Http\Controllers\Weather;

use App\Http\Controllers\Controller;
use App\Support\PressureMapRegistry;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * Serves the surface pressure charts through this app instead of hotlinking.
 *
 * DWD's European analysis is a 4.6MB scan at 4389x3114, around thirty times
 * the NOAA charts, and every visitor was fetching it from DWD. Downscaled once
 * and cached on disk, so the upstream sees one request per refresh window and
 * the browser gets something proportionate to the space it is drawn in.
 */
class PressureMapImageController extends Controller
{
    /** Charts refresh a few times a day upstream. */
    private const CACHE_TTL = 1800;

    /** Wide enough for the isobar labels to stay readable when zoomed. */
    private const MAX_WIDTH = 1600;

    public function show(string $map): Response
    {
        if (!PressureMapRegistry::exists($map)) {
            abort(404);
        }

        $path = "pressure-maps/{$map}.img";

        if (Storage::disk('local')->exists($path)) {
            $age = now()->timestamp - Storage::disk('local')->lastModified($path);
            if ($age < self::CACHE_TTL) {
                return $this->imageResponse(Storage::disk('local')->get($path));
            }
        }

        // The URL comes from the registry, never from the request.
        $url = (string) PressureMapRegistry::urlFor($map);

        try {
            $upstream = Http::timeout(20)->accept('image/*')->get($url);
            $contentType = strtolower((string) $upstream->header('Content-Type', ''));

            if ($upstream->successful() && str_contains($contentType, 'image/') && $upstream->body() !== '') {
                $body = $this->shrink($upstream->body());
                Storage::disk('local')->put($path, $body);

                return $this->imageResponse($body);
            }

            Log::warning('Pressure map upstream failed', [
                'map' => $map,
                'status' => $upstream->status(),
                'content_type' => $contentType,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Pressure map upstream exception', ['map' => $map, 'error' => $e->getMessage()]);
        }

        // Prefer a stale copy over nothing: these charts stay useful for hours.
        if (Storage::disk('local')->exists($path)) {
            return $this->imageResponse(Storage::disk('local')->get($path), true);
        }

        return response('Pressure map unavailable', 502);
    }

    /**
     * Shrink anything wider than MAX_WIDTH and re-encode.
     *
     * Format is whichever the runtime can produce, best first. These charts are
     * scans, so PNG stays large where a lossy format does not: the DWD chart at
     * 1600px is 0.27MB as WebP, 0.39MB as JPEG and 1.43MB as PNG, from 4.39MB.
     *
     * WebP needs GD built --with-webp, which the shipped Dockerfile does not
     * currently pass, so JPEG is the realistic outcome in the container. An
     * image returned unchanged is a size cost, never a broken page.
     */
    private function shrink(string $binary): string
    {
        $manager = $this->imageManager();

        if ($manager === null) {
            return $binary;
        }

        try {
            $image = $manager->read($binary);

            if ($image->width() > self::MAX_WIDTH) {
                $image = $image->scaleDown(width: self::MAX_WIDTH);
            }

            if (function_exists('imagewebp')) {
                return (string) $image->toWebp(90);
            }

            if (function_exists('imagejpeg')) {
                return (string) $image->toJpeg(90);
            }

            return (string) $image->toPng();
        } catch (\Throwable $e) {
            Log::warning('Pressure map re-encode failed, serving original', ['error' => $e->getMessage()]);

            return $binary;
        }
    }

    private function imageManager(): ?ImageManager
    {
        if (extension_loaded('imagick')) {
            return new ImageManager(new ImagickDriver());
        }

        if (extension_loaded('gd')) {
            return new ImageManager(new GdDriver());
        }

        return null;
    }

    private function imageResponse(string $body, bool $stale = false): Response
    {
        $mime = getimagesizefromstring($body)['mime'] ?? 'image/png';

        return response($body, 200)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'public, max-age=' . self::CACHE_TTL)
            ->header('X-Pressure-Map-Cache', $stale ? 'stale' : 'fresh');
    }
}
