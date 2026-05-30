<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\MenuFeatureMap;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureMenuEnabledMiddleware
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (MenuFeatureMap::enabled($feature)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Feature is disabled',
                'feature' => $feature,
            ], 404);
        }

        return redirect()
            ->route('home')
            ->with('feature_disabled', __('This page is currently disabled.'));
    }
}
