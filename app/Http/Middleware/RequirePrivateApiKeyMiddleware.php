<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePrivateApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->attributes->get(ApiKeyMiddleware::REQUEST_API_KEY_ATTRIBUTE);

        if (!$apiKey) {
            return response()->json(['error' => 'API key required'], 401);
        }

        if ((bool) ($apiKey->is_public ?? false)) {
            return response()->json(['error' => 'Private API key required'], 403);
        }

        return $next($request);
    }
}
