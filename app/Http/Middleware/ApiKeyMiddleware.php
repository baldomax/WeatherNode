<?php

namespace App\Http\Middleware;

use App\Services\Security\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public const REQUEST_API_KEY_ATTRIBUTE = 'resolved_api_key';

    /** Default requests per minute for JSON API endpoints. */
    private const DEFAULT_RATE_LIMIT = 120;
    /** Radar tile/image requests are much more frequent than JSON API calls. */
    private const RADAR_VISUAL_RATE_MULTIPLIER = 5;

    public function __construct(private ApiKeyService $apiKeyService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveApiKey($request);
        if (!$key) {
            return response()->json(['error' => 'API key required'], 401);
        }

        $apiKey = $this->apiKeyService->findValidKey($key);
        if (!$apiKey) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        $radarVisual = $this->isRadarVisualRequest($request);
        $limit = max(1, (int) ($apiKey->rate_limit_per_minute ?? self::DEFAULT_RATE_LIMIT));
        if ($radarVisual) {
            $limit *= self::RADAR_VISUAL_RATE_MULTIPLIER;
        }

        $rateKey = $this->buildRateKey($request, $apiKey->key_hash, $radarVisual ? 'radar' : 'api');
        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            $retryAfter = RateLimiter::availableIn($rateKey);
            return response()
                ->json(['error' => 'Rate limit exceeded'], 429)
                ->header('Retry-After', (string) $retryAfter);
        }

        RateLimiter::hit($rateKey, 60);
        $this->apiKeyService->markUsed($apiKey);
        $request->attributes->set(self::REQUEST_API_KEY_ATTRIBUTE, $apiKey);

        return $next($request);
    }

    private function resolveApiKey(Request $request): ?string
    {
        $headerKey = trim((string) $request->header('X-API-Key', ''));
        if ($headerKey !== '') {
            return $headerKey;
        }

        if (!$this->isRadarVisualRequest($request)) {
            return null;
        }

        $queryKey = trim((string) $request->query('api_key', ''));
        return $queryKey !== '' ? $queryKey : null;
    }

    private function isRadarVisualRequest(Request $request): bool
    {
        return $request->is('api/radar/tile/*') || $request->is('api/radar/future-image');
    }

    private function buildRateKey(Request $request, string $keyHash, string $bucket): string
    {
        $clientIp = (string) ($request->ip() ?? 'unknown');
        return 'api-key:' . sha1($keyHash . '|' . $clientIp . '|' . $bucket);
    }
}
