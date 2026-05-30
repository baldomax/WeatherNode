<?php

namespace App\Services\OpenData;

class OpenDataProviderRegistry
{
    /**
     * @var ProviderInterface[]
     */
    private static array $providers = [];

    /**
     * Register a provider
     */
    public static function register(ProviderInterface $provider): void
    {
        self::$providers[$provider->getSettingsKey()] = $provider;
    }

    /**
     * Get all registered providers
     *
     * @return ProviderInterface[]
     */
    public static function getAll(): array
    {
        return array_values(self::$providers);
    }

    /**
     * Get only enabled providers
     *
     * @return ProviderInterface[]
     */
    public static function getEnabled(): array
    {
        return array_filter(self::$providers, fn($provider) => $provider->isEnabled());
    }

    /**
     * Get provider by key
     */
    public static function getByKey(string $key): ?ProviderInterface
    {
        return self::$providers[$key] ?? null;
    }

    /**
     * Get implemented providers only
     *
     * @return ProviderInterface[]
     */
    public static function getImplemented(): array
    {
        return array_filter(self::$providers, fn($provider) => $provider->isImplemented());
    }

    /**
     * Get placeholder providers only
     *
     * @return ProviderInterface[]
     */
    public static function getPlaceholders(): array
    {
        return array_filter(self::$providers, fn($provider) => !$provider->isImplemented());
    }
}
