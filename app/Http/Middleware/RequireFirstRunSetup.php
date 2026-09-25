<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\FirstRunSetup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While the first-run setup is owed, the admin area sends the owner to the
 * step that is owed.
 *
 * A notice is easy to scroll past, and an install that does not know where it
 * is publishes wrong sunrise times and forecasts in the meantime. The "I will
 * do this later" button moves the flag to skipped, which stops this
 * immediately and leaves the notice behind.
 *
 * Deliberately narrow. It never blocks a save, or the wizard itself, or
 * anything asking for JSON, and it is registered on the admin group alone, so
 * the public site is untouched.
 */
class RequireFirstRunSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!FirstRunSetup::pending()) {
            return $next($request);
        }

        if (!$request->isMethod('GET') || $request->routeIs('admin.setup.*')) {
            return $next($request);
        }

        // A redirect is not an answer to a request for data.
        if ($request->expectsJson() || !$request->acceptsHtml()) {
            return $next($request);
        }

        return redirect()->route(FirstRunSetup::nextRoute() ?? 'admin.setup.station');
    }
}
