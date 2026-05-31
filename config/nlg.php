<?php

return [
    // Tone presets: short, friendly, formal, etc.
    'tones' => [
        'brief' => [
            'max_sentences' => 2,
            'max_tokens' => 60,
            'temperature' => 0.2,
            'style_notes' => 'Keep it compact and direct. Prefer 1-2 short sentences with only the main forecast takeaway. No filler, no emojis.',
        ],
        'friendly' => [
            'max_sentences' => 4,
            'max_tokens' => 120,
            // Warmer tone needs room to vary from the neutral draft, so run a touch hotter.
            'temperature' => 0.55,
            'style_notes' => 'Sound genuinely warm and upbeat, like a local presenter chatting to one person. Address the reader directly in the informal second person of the target language. Vary the sentence openings — do NOT start like the draft. Natural, human, spoken rhythm. No filler, no emojis.',
        ],
        'formal' => [
            'max_sentences' => 4,
            'max_tokens' => 120,
            'temperature' => 0.3,
            'style_notes' => 'Use a professional forecast tone with complete, polished sentences. It may be slightly longer than brief, with explicit timing, measured phrasing, and clear forecast ordering. No filler, no emojis.',
        ],
    ],

    // Provider presets (reference data — user settings are stored in DB)
    'providers' => [
        'openai' => [
            'label' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o-mini',
            'requires_key' => true,
            'type' => 'compatible',
            'hint' => 'Paid API. Requires an OpenAI API key.',
        ],
        'groq' => [
            'label' => 'Groq (Free)',
            'base_url' => 'https://api.groq.com/openai/v1',
            'default_model' => 'llama-3.3-70b-versatile',
            'requires_key' => true,
            'type' => 'compatible',
            'hint' => 'Free tier: 30 RPM, 1 000 requests/day. No credit card required.',
        ],
        'openrouter' => [
            'label' => 'OpenRouter (Free)',
            'base_url' => 'https://openrouter.ai/api/v1',
            'default_model' => 'meta-llama/llama-4-scout:free',
            'requires_key' => true,
            'type' => 'compatible',
            'hint' => 'Free tier: 20 RPM, 50 requests/day. Free models end with :free.',
        ],
        'cerebras' => [
            'label' => 'Cerebras (Free)',
            'base_url' => 'https://api.cerebras.ai/v1',
            'default_model' => 'llama-3.3-70b',
            'requires_key' => true,
            'type' => 'compatible',
            'hint' => 'Free tier: 30 RPM, 1M tokens/day. No credit card required.',
        ],
        'google' => [
            'label' => 'Google AI Studio (Free)',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'default_model' => 'gemini-2.5-flash',
            'requires_key' => true,
            'type' => 'compatible',
            'hint' => 'Free tier available. Requires a Google AI Studio API key.',
        ],
        'compatible' => [
            'label' => 'Custom OpenAI-Compatible',
            'base_url' => '',
            'default_model' => '',
            'requires_key' => true,
            'type' => 'compatible',
            'hint' => 'Any server implementing the OpenAI chat/completions API.',
        ],
        'ollama' => [
            'label' => 'Ollama (Local)',
            'base_url' => 'http://localhost:11434',
            'default_model' => 'llama3',
            'requires_key' => false,
            'type' => 'ollama',
            'hint' => 'Local Ollama instance. No API key needed.',
        ],
    ],

    // Rephrase pacing defaults to avoid provider 429 spikes.
    // Keep this below strict provider quotas (for example 5 RPM).
    'rephrase' => [
        'max_requests_per_minute' => 4,
        'max_retry_attempts' => 2,
        'retry_backoff_seconds' => [2, 5],

        // Extra output-token headroom added on top of the tone budget when a reasoning effort is
        // selected for the active model (Admin > Settings > NLG). Reasoning models spend tokens on
        // internal "thinking" before the answer, so without headroom the JSON is truncated
        // (finish_reason: length) before any content is produced. Sane internal default; the
        // operator never needs to touch it.
        'reasoning_headroom_tokens' => 2000,
    ],

    // Per-provider request budget defaults (rpm / rph / rpd = per minute / hour / day).
    // null = unlimited for that tier. These are only defaults — the operator can override
    // them for the active provider via Admin > Settings > NLG (stored as nlg.limits.*).
    // Enforced by App\Services\Nlg\RephraseBudget: the minute tier is paced (the run waits),
    // the hour/day tiers are skipped (entries keep their deterministic text until reset).
    'limits' => [
        // Cerebras free-tier limits are PER MODEL. The large models (gpt-oss-120b, zai-glm-4.7)
        // are capped at 5 RPM — much lower than the older llama models. Default to the strict
        // tier so a model swap can't silently outrun the quota; operators raise it per their plan.
        'cerebras' => ['rpm' => 5, 'rph' => 150, 'rpd' => 2400],
        'groq' => ['rpm' => 30, 'rph' => null, 'rpd' => 1000],
        'openrouter' => ['rpm' => 20, 'rph' => null, 'rpd' => 50],
        'openai' => ['rpm' => null, 'rph' => null, 'rpd' => null],
        'google' => ['rpm' => null, 'rph' => null, 'rpd' => null],
    ],
];
