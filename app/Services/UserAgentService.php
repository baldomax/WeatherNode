<?php

namespace App\Services;

final class UserAgentService
{
    public static function forExternalApi(bool $browserCompatible = false): string
    {
        $version = ltrim(VersionService::getAppVersion(), 'v');

        if ($browserCompatible) {
            return "Mozilla/5.0 (compatible; WeatherNode/{$version}; +https://weathernode.dev; mailto:info@weathernode.dev)";
        }

        return "WeatherNode/{$version} (+https://weathernode.dev; mailto:info@weathernode.dev)";
    }
}
