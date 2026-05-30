<?php

namespace App\Services\Nlg;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Multi-tier, per-provider request budget for LLM NLG rephrasing.
 *
 * The per-minute tier is *paced* (sleep to the next minute) because it frees up
 * quickly within a single scheduler run. The per-hour/per-day tiers are *skipped*
 * (return false) when exhausted, so callers keep the deterministic draft instead of
 * burning quota on 429s. Counters are cache-backed so the budget is shared across
 * processes and survives between scheduled runs.
 */
class RephraseBudget
{
    private Closure $limitsResolver;
    private Closure $clock;
    private Closure $sleeper;

    /** Tier that caused the most recent skip ('hour'|'day'), or null. */
    private ?string $lastSkipReason = null;

    public function __construct(
        private Repository $cache,
        ?Closure $limitsResolver = null,
        ?Closure $clock = null,
        ?Closure $sleeper = null,
    ) {
        $this->limitsResolver = $limitsResolver ?? fn (string $provider): array => $this->effectiveLimits($provider);
        $this->clock = $clock ?? fn (): CarbonImmutable => CarbonImmutable::now();
        $this->sleeper = $sleeper ?? fn (int $seconds): mixed => sleep($seconds);
    }

    /**
     * Reserve one request for the given provider.
     *
     * Returns true if the request may be spent now (after pacing the minute window),
     * or false if an hour/day budget is exhausted (caller should skip and keep the draft).
     */
    public function tryReserve(string $providerId): bool
    {
        $this->lastSkipReason = null;

        try {
            $limits = ($this->limitsResolver)($providerId);
            $rpm = $limits['rpm'] ?? null;
            $rph = $limits['rph'] ?? null;
            $rpd = $limits['rpd'] ?? null;

            $minuteSleeps = 0;
            while (true) {
                $now = ($this->clock)();

                // Long windows: skip (caller keeps the deterministic draft).
                if ($rpd !== null && $this->count($providerId, 'day', $now) >= $rpd) {
                    $this->lastSkipReason = 'day';

                    return false;
                }
                if ($rph !== null && $this->count($providerId, 'hour', $now) >= $rph) {
                    $this->lastSkipReason = 'hour';

                    return false;
                }

                // Short window: pace by sleeping to the next minute boundary, then re-check.
                if ($rpm !== null && $this->count($providerId, 'min', $now) >= $rpm && $minuteSleeps < 2) {
                    ($this->sleeper)(max(1, 60 - (int) $now->second));
                    $minuteSleeps++;
                    continue;
                }

                $this->increment($providerId, 'day', $now);
                $this->increment($providerId, 'hour', $now);
                $this->increment($providerId, 'min', $now);

                return true;
            }
        } catch (Throwable $e) {
            // Fail open: the budget is best-effort cost protection. A cache-backend
            // hiccup must not block weather-text generation. Log and proceed.
            $this->logSafely('NLG rephrase budget unavailable; proceeding without throttle', [
                'provider' => $providerId,
                'message' => $e->getMessage(),
            ]);

            return true;
        }
    }

    /** Tier ('hour'|'day') that caused the most recent skip, or null if the last reserve succeeded. */
    public function lastSkipReason(): ?string
    {
        return $this->lastSkipReason;
    }

    /**
     * Resolve the effective request limits for a provider.
     *
     * Precedence per tier: admin DB setting → provider config default → fallback.
     * A value of 0 / blank means "unlimited" (null) for that tier.
     *
     * @return array{rpm: ?int, rph: ?int, rpd: ?int}
     */
    public function effectiveLimits(string $providerId): array
    {
        return [
            'rpm' => $this->resolveTier('rpm', $providerId, (int) config('nlg.rephrase.max_requests_per_minute', 4)),
            'rph' => $this->resolveTier('rph', $providerId, null),
            'rpd' => $this->resolveTier('rpd', $providerId, null),
        ];
    }

    private function resolveTier(string $tier, string $providerId, ?int $fallback): ?int
    {
        $dbValue = Setting::getValue("nlg.limits.{$tier}", null);
        if ($dbValue !== null && $dbValue !== '') {
            return $this->normalizeLimit((int) $dbValue);
        }

        $configValue = config("nlg.limits.{$providerId}.{$tier}");
        if ($configValue !== null) {
            return $this->normalizeLimit((int) $configValue);
        }

        return $fallback;
    }

    /** A non-positive limit means "unlimited". */
    private function normalizeLimit(int $value): ?int
    {
        return $value > 0 ? $value : null;
    }

    private function logSafely(string $message, array $context): void
    {
        try {
            Log::warning($message, $context);
        } catch (Throwable) {
            // No logging backend available (e.g. a bare unit test); fail open silently.
        }
    }

    private function bucketKey(string $providerId, string $tier, CarbonImmutable $now): string
    {
        $stamp = match ($tier) {
            'min' => $now->format('Y-m-d-H-i'),
            'hour' => $now->format('Y-m-d-H'),
            'day' => $now->format('Y-m-d'),
        };

        return "nlg-budget:{$providerId}:{$tier}:{$stamp}";
    }

    private function count(string $providerId, string $tier, CarbonImmutable $now): int
    {
        return (int) $this->cache->get($this->bucketKey($providerId, $tier, $now), 0);
    }

    private function increment(string $providerId, string $tier, CarbonImmutable $now): void
    {
        $key = $this->bucketKey($providerId, $tier, $now);
        $ttl = match ($tier) {
            'min' => 90,
            'hour' => 3700,
            'day' => 90000,
        };
        $this->cache->put($key, $this->count($providerId, $tier, $now) + 1, $ttl);
    }
}
