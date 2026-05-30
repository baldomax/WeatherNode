<?php

namespace App\Services\Weather\Sources;

interface WeatherSourceAdapter
{
    public function key(): string;

    /**
     * Return normalized reading data, or null if unavailable.
     */
    public function fetch(): ?array;
}
