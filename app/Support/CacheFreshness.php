<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Cache writes that record when they happened.
 *
 * The cache stores no write time of its own, so freshness checks need a
 * companion entry. Writing it here keeps the payload and its timestamp on the
 * same TTL, rather than relying on every call site to remember both.
 */
class CacheFreshness
{
    public static function stampKey(string $key): string
    {
        return $key . '_updated_at';
    }

    public static function put(string $key, mixed $value, DateTimeInterface|int $ttl): mixed
    {
        Cache::put($key, $value, $ttl);
        Cache::put(self::stampKey($key), now()->toIso8601String(), $ttl);

        return $value;
    }

    public static function remember(string $key, DateTimeInterface|int $ttl, Closure $callback): mixed
    {
        $cached = Cache::get($key);
        if (!is_null($cached)) {
            return $cached;
        }

        return self::put($key, $callback(), $ttl);
    }

    public static function forget(string $key): void
    {
        Cache::forget($key);
        Cache::forget(self::stampKey($key));
    }

    /**
     * Accepts whatever the configured store hands back: an ISO string from
     * put(), or a Carbon left by an older writer.
     */
    public static function updatedAt(string $key): ?Carbon
    {
        $raw = Cache::get(self::stampKey($key));

        if ($raw instanceof DateTimeInterface) {
            return Carbon::instance($raw);
        }

        if (is_int($raw)) {
            return Carbon::createFromTimestamp($raw);
        }

        if (is_string($raw) && $raw !== '') {
            try {
                return Carbon::parse($raw);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
