<?php

namespace App\Services\Security;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class ApiKeyService
{
    private const PUBLIC_CACHE_KEY = 'api_keys.public_key';
    private const PUBLIC_CACHE_TTL = 3600;

    public function getOrCreatePublicKey(): ?string
    {
        if (!$this->isTableReady()) {
            return null;
        }

        try {
            return Cache::remember(self::PUBLIC_CACHE_KEY, self::PUBLIC_CACHE_TTL, function () {
                return $this->resolvePublicKey();
            });
        } catch (\Throwable $e) {
            return $this->resolvePublicKey();
        }
    }

    public function createKey(string $name, bool $isPublic = false, ?int $rateLimit = null): string
    {
        if (!$this->isTableReady()) {
            throw new \RuntimeException('API key table is not available.');
        }

        $plain = $this->generateKey();

        ApiKey::create([
            'name' => $name,
            'key_hash' => $this->hashKey($plain),
            'key_prefix' => substr($plain, 0, 8),
            'key_encrypted' => $isPublic ? Crypt::encryptString($plain) : null,
            'is_public' => $isPublic,
            'rate_limit_per_minute' => $rateLimit,
        ]);

        Cache::forget(self::PUBLIC_CACHE_KEY);

        return $plain;
    }

    public function findValidKey(string $plain): ?ApiKey
    {
        if (!$this->isTableReady()) {
            return null;
        }

        return ApiKey::query()
            ->where('key_hash', $this->hashKey($plain))
            ->whereNull('revoked_at')
            ->first();
    }

    public function markUsed(ApiKey $apiKey): void
    {
        $cacheKey = "api_keys.last_used.{$apiKey->id}";
        if (Cache::has($cacheKey)) {
            return;
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();
        Cache::put($cacheKey, true, 300);
    }

    public function hashKey(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function forgetPublicKeyCache(): void
    {
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }

    private function generateKey(): string
    {
        return bin2hex(random_bytes(24));
    }

    private function resolvePublicKey(): ?string
    {
        $existing = ApiKey::query()
            ->where('is_public', true)
            ->whereNull('revoked_at')
            ->whereNotNull('key_encrypted')
            ->latest()
            ->first();

        if ($existing?->key_encrypted) {
            return Crypt::decryptString($existing->key_encrypted);
        }

        $plain = $this->generateKey();

        ApiKey::create([
            'name' => 'Site',
            'key_hash' => $this->hashKey($plain),
            'key_prefix' => substr($plain, 0, 8),
            'key_encrypted' => Crypt::encryptString($plain),
            'is_public' => true,
        ]);

        return $plain;
    }

    private function isTableReady(): bool
    {
        try {
            return Schema::hasTable('api_keys');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
