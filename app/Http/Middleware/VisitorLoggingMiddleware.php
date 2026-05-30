<?php

namespace App\Http\Middleware;

use App\Models\VisitorLog;
use App\Services\GeoIp\GeoIpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VisitorLoggingMiddleware
{
    public function __construct(private GeoIpService $geoIpService)
    {
    }

    /**
     * Capture timing before the request is handled.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('visitorlog.start', microtime(true));

        return $next($request);
    }

    /**
     * Log after the response is sent to avoid slowing down the request.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (!$this->shouldLog($request, $response)) {
            return;
        }

        $start = $request->attributes->get('visitorlog.start', microtime(true));
        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $path = '/' . ltrim($request->path(), '/');
        $ip = $request->ip();

        if (!$ip) {
            return;
        }

        [$referrerHost, $searchEngine, $searchTerms] = $this->parseReferrer(
            $request->headers->get('referer'),
            $request->getHost()
        );

        $userAgentInfo = $this->parseUserAgent($request->userAgent());
        $countryCode = $this->geoIpService->lookupCountryCode($ip);

        try {
            VisitorLog::create([
                'occurred_at' => now(),
                'path' => $path,
                'method' => $request->getMethod(),
                'status_code' => $response->getStatusCode(),
                'response_ms' => $durationMs,
                'referrer_host' => $referrerHost,
                'search_engine' => $searchEngine,
                'search_terms' => $searchTerms,
                'country_code' => $countryCode,
                'device_type' => $userAgentInfo['device_type'],
                'browser_family' => $userAgentInfo['browser_family'],
                'os_family' => $userAgentInfo['os_family'],
                'is_bot' => $userAgentInfo['is_bot'],
                'ip_hash' => $this->hashIp($ip),
                'ip_encrypted' => $ip,
            ]);
        } catch (\Exception $exception) {
            Log::warning('Failed to log visitor request', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (!config('visitorlog.enabled')) {
            return false;
        }

        $method = strtoupper($request->getMethod());
        $allowedMethods = config('visitorlog.log_methods', ['GET', 'HEAD']);
        if (!in_array($method, $allowedMethods, true)) {
            return false;
        }

        $path = '/' . ltrim($request->path(), '/');
        $ignorePaths = config('visitorlog.ignore_paths', []);
        foreach ($ignorePaths as $ignorePath) {
            $ignorePath = '/' . trim($ignorePath, '/');
            if ($ignorePath === '/') {
                continue;
            }
            if ($ignorePath === $path || str_starts_with($path, $ignorePath . '/')) {
                return false;
            }
        }

        return true;
    }

    private function parseReferrer(?string $referrer, string $requestHost): array
    {
        if (!$referrer) {
            return [null, null, null];
        }

        $parts = parse_url($referrer);
        if (!$parts || empty($parts['host'])) {
            return [null, null, null];
        }

        $referrerHost = strtolower($parts['host']);
        $referrerHost = preg_replace('/^www\./', '', $referrerHost);
        $requestHost = strtolower($requestHost);
        $requestHost = preg_replace('/^www\./', '', $requestHost);

        if ($referrerHost === $requestHost) {
            return [null, null, null];
        }

        $searchEngine = null;
        $searchTerms = null;
        $engineConfig = $this->matchSearchEngine($referrerHost);

        if ($engineConfig) {
            $searchEngine = $engineConfig['name'];
            if (config('visitorlog.store_search_terms') && !empty($parts['query'])) {
                parse_str($parts['query'], $queryParams);
                $paramNames = (array) ($engineConfig['param'] ?? []);
                foreach ($paramNames as $paramName) {
                    $rawTerms = $queryParams[$paramName] ?? null;
                    if (is_string($rawTerms)) {
                        $rawTerms = trim($rawTerms);
                        if ($rawTerms !== '') {
                            $searchTerms = substr($rawTerms, 0, 255);
                            break;
                        }
                    }
                }
            }
        }

        return [$referrerHost, $searchEngine, $searchTerms];
    }

    private function matchSearchEngine(string $host): ?array
    {
        $searchEngines = config('visitorlog.search_engines', []);
        foreach ($searchEngines as $pattern => $engineConfig) {
            if (str_contains($host, $pattern)) {
                return $engineConfig;
            }
        }

        return null;
    }

    private function parseUserAgent(?string $userAgent): array
    {
        $agent = strtolower($userAgent ?? '');
        $isBot = str_contains($agent, 'bot')
            || str_contains($agent, 'crawl')
            || str_contains($agent, 'spider')
            || str_contains($agent, 'slurp');

        $deviceType = 'desktop';
        if ($isBot) {
            $deviceType = 'bot';
        } elseif (str_contains($agent, 'tablet') || str_contains($agent, 'ipad')) {
            $deviceType = 'tablet';
        } elseif (str_contains($agent, 'mobi') || str_contains($agent, 'iphone') || str_contains($agent, 'android')) {
            $deviceType = 'mobile';
        }

        $browserFamily = 'Other';
        if (str_contains($agent, 'edg/')) {
            $browserFamily = 'Edge';
        } elseif (str_contains($agent, 'opr/') || str_contains($agent, 'opera')) {
            $browserFamily = 'Opera';
        } elseif (str_contains($agent, 'chrome/') && !str_contains($agent, 'chromium')) {
            $browserFamily = 'Chrome';
        } elseif (str_contains($agent, 'safari/') && !str_contains($agent, 'chrome')) {
            $browserFamily = 'Safari';
        } elseif (str_contains($agent, 'firefox/')) {
            $browserFamily = 'Firefox';
        } elseif (str_contains($agent, 'msie') || str_contains($agent, 'trident/')) {
            $browserFamily = 'Internet Explorer';
        }

        $osFamily = 'Other';
        if (str_contains($agent, 'iphone') || str_contains($agent, 'ipad') || str_contains($agent, 'ios')) {
            $osFamily = 'iOS';
        } elseif (str_contains($agent, 'android')) {
            $osFamily = 'Android';
        } elseif (str_contains($agent, 'windows')) {
            $osFamily = 'Windows';
        } elseif (str_contains($agent, 'mac os x') || str_contains($agent, 'macintosh')) {
            $osFamily = 'MacOS';
        } elseif (str_contains($agent, 'linux')) {
            $osFamily = 'Linux';
        }

        return [
            'device_type' => $deviceType,
            'browser_family' => $browserFamily,
            'os_family' => $osFamily,
            'is_bot' => $isBot,
        ];
    }

    private function hashIp(string $ip): string
    {
        $salt = config('visitorlog.ip_hash_salt', '');
        $key = config('app.key', '');

        return hash_hmac('sha256', $ip, $key . $salt);
    }
}
