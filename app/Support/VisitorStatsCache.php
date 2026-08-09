<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for the assembled admin visitor analytics payload.
 *
 * Invalidation is by version counter rather than by tracking keys, so it works
 * on the file and database drivers too, where cache tags are unavailable.
 * Bumping the version orphans every previous entry and they expire on their
 * own TTL.
 */
class VisitorStatsCache
{
    private const VERSION_KEY = 'visitor_stats:version';

    /** Short, because the payload includes today so far. */
    private const TTL_SECONDS = 300;

    public static function remember(string $signature, Closure $callback): mixed
    {
        return Cache::remember(self::key($signature), self::TTL_SECONDS, $callback);
    }

    public static function flush(): void
    {
        $current = (int) Cache::get(self::VERSION_KEY, 0);
        Cache::forever(self::VERSION_KEY, $current + 1);
    }

    public static function key(string $signature): string
    {
        $version = (int) Cache::get(self::VERSION_KEY, 0);

        return 'visitor_stats:' . $version . ':' . $signature;
    }
}
