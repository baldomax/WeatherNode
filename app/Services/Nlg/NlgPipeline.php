<?php

namespace App\Services\Nlg;

use App\Contracts\Nlg\Narrator;
use App\Contracts\Nlg\Rephraser;

class NlgPipeline
{
    public function __construct(
        private Narrator $narrator,
        private ?Rephraser $rephraser = null,
    ) {}

    /**
     * Render text from payload, optionally using LLM rephraser.
     *
     * @param array $payload Domain-specific structured data
     * @param array $facts Structured facts JSON for LLM guardrails
     * @param string $tone Tone preset (brief, friendly, formal)
     * @return string Final text (deterministic or LLM-enhanced)
     */
    public function render(array $payload, array $facts = [], string $tone = 'brief'): string
    {
        $draft = $this->narrator->narrate($payload);

        if (!$this->rephraser || !\App\Models\Setting::getValue('nlg.llm_enabled', false)) {
            return $draft;
        }

        // If no facts provided, use payload as facts
        if (empty($facts)) {
            $facts = $payload;
        }

        return $this->rephraser->rewrite($draft, $facts, $tone);
    }
}
