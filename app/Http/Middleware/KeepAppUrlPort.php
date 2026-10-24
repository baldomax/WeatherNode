<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Behind a port mapping, such as Unraid's 10130 to the container's 80, the
 * app only learns the port from the Host header. Debian's nginx now passes
 * HTTP_HOST as $host, without the port, as a security workaround, so links
 * built from the request pointed at port 80. On Unraid that is Unraid's own
 * login page.
 *
 * When APP_URL names this same host with a port, and the request came in
 * without one, give the request APP_URL's port. Only the configured host is
 * touched, so a Host header cannot steer anything.
 */
class KeepAppUrlPort
{
    public function handle(Request $request, Closure $next): Response
    {
        $appUrl = trim((string) config('app.url', ''));
        $host = parse_url($appUrl, PHP_URL_HOST);
        $port = parse_url($appUrl, PHP_URL_PORT);

        if (is_string($host) && is_int($port)
            && strcasecmp($host, $request->getHost()) === 0
            && ! str_contains((string) $request->headers->get('host', ''), ':')
            && $request->getPort() !== $port) {
            $hostWithPort = $request->getHost() . ':' . $port;
            $request->headers->set('host', $hostWithPort);
            $request->server->set('HTTP_HOST', $hostWithPort);
            $request->server->set('SERVER_PORT', $port);
        }

        return $next($request);
    }
}
