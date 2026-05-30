<?php

namespace App\Contracts\Nlg;

interface Narrator
{
    /**
     * Generate human-readable text from structured payload data.
     *
     * @param array $payload Domain-specific structured data
     * @param array $options Optional configuration (tone, locale, etc.)
     * @return string Human-readable summary
     */
    public function narrate(array $payload, array $options = []): string;
}
