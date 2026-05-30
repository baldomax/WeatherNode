<?php

namespace App\Contracts\Nlg;

interface Rephraser
{
    /**
     * Rewrite a deterministic draft text using LLM, maintaining facts.
     *
     * @param string $draft The deterministic draft text
     * @param array $facts Structured facts JSON to ensure accuracy
     * @param string $tone Tone preset (brief, friendly, formal)
     * @return string Rewritten text, or original draft if rewrite fails
     */
    public function rewrite(string $draft, array $facts, string $tone = 'brief'): string;
}
