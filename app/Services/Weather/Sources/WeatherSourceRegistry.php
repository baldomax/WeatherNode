<?php

namespace App\Services\Weather\Sources;

class WeatherSourceRegistry
{
    /** @var array<string, WeatherSourceAdapter> */
    private array $adapters = [];

    /**
     * @param iterable<WeatherSourceAdapter> $adapters
     */
    public function __construct(iterable $adapters = [])
    {
        foreach ($adapters as $adapter) {
            $this->adapters[$adapter->key()] = $adapter;
        }
    }

    public function get(string $key): ?WeatherSourceAdapter
    {
        return $this->adapters[$key] ?? null;
    }

    /**
     * @return array<string, WeatherSourceAdapter>
     */
    public function all(): array
    {
        return $this->adapters;
    }
}
