<?php

namespace App\Services\Nlg\Rephrasers;

use App\Contracts\Nlg\BatchRephraser;
use App\Contracts\Nlg\Rephraser;
use App\Services\Nlg\Rephrasers\Concerns\BuildsWeatherRephrasePrompts;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiCompatibleRephraser implements BatchRephraser, Rephraser
{
    use BuildsWeatherRephrasePrompts;

    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $model,
        private string $reasoningEffort = '',
    ) {}

    public function rewrite(string $draft, array $facts, string $tone = 'brief', ?string $locale = null): string
    {
        $toneCfg = config("nlg.tones.{$tone}")
            ?: config('nlg.tones.brief')
            ?: [
                'max_sentences' => 2,
                'max_tokens' => 60,
                'style_notes' => 'Concise, no filler, no emojis.',
            ];

        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildWeatherSystemPrompt($tone, $toneCfg, $locale),
            ],
            [
                'role' => 'user',
                'content' => 'Facts JSON: '.json_encode($facts, JSON_UNESCAPED_SLASHES).
                    "\n\nDraft: {$draft}\n\nRewrite:",
            ],
        ];

        try {
            $maxRetries = max(0, (int) config('nlg.rephrase.max_retry_attempts', 2));
            $attempt = 0;

            while (true) {
                $attempt++;

                $headroom = $this->reasoningHeadroom();

                $res = Http::withToken($this->apiKey)
                    ->timeout(15)
                    ->post(rtrim($this->baseUrl, '/').'/chat/completions', $this->applyReasoningControls([
                        'model' => $this->model,
                        'messages' => $messages,
                        'max_tokens' => ((int) ($toneCfg['max_tokens'] ?? 60)) + $headroom,
                        'temperature' => (float) ($toneCfg['temperature'] ?? 0.2),
                    ]));

                if ($res->ok()) {
                    break;
                }

                if ($res->status() === 429 && $attempt <= $maxRetries) {
                    $backoff = $this->resolveRetryBackoffSeconds($res, $attempt);
                    Log::notice('NLG rephrase hit provider throttle; retrying request', [
                        'status' => $res->status(),
                        'model' => $this->model,
                        'provider' => $this->providerHost(),
                        'attempt' => $attempt,
                        'backoff_seconds' => $backoff,
                        'message' => data_get($res->json(), 'error.message', substr($res->body(), 0, 200)),
                    ]);
                    if ($backoff > 0) {
                        sleep($backoff);
                    }

                    continue;
                }

                Log::warning('NLG rephrase request failed', [
                    'status' => $res->status(),
                    'model' => $this->model,
                    'provider' => $this->providerHost(),
                    'message' => data_get($res->json(), 'error.message', substr($res->body(), 0, 200)),
                ]);

                return $draft;
            }

            $text = data_get($res->json(), 'choices.0.message.content');
            if (! is_string($text) || trim($text) === '') {
                return $draft;
            }

            $rewritten = trim($text);
            if ($this->containsInvalidWeatherPhrase($rewritten)) {
                Log::warning('NLG rephrase produced invalid phrasing; falling back to draft', [
                    'model' => $this->model,
                    'provider' => $this->providerHost(),
                    'text' => $rewritten,
                ]);

                return $draft;
            }

            return $rewritten;
        } catch (\Exception $e) {
            Log::warning('NLG rephrase request exception', [
                'model' => $this->model,
                'provider' => $this->providerHost(),
                'message' => $e->getMessage(),
            ]);

            return $draft;
        }
    }

    public function rewriteBatch(array $items, string $tone = 'brief', ?callable $reserveSlot = null, ?string $locale = null): array
    {
        // Map every id to its draft up front; this is the fallback the contract guarantees
        // for ids the model omits, phrases invalidly, or when the request fails entirely.
        $drafts = array_map(static fn (array $item): string => $item['draft'], $items);
        if ($items === []) {
            return [];
        }

        $toneCfg = config("nlg.tones.{$tone}")
            ?: config('nlg.tones.brief')
            ?: [
                'max_sentences' => 2,
                'max_tokens' => 60,
                'style_notes' => 'Concise, no filler, no emojis.',
            ];

        $payloadItems = [];
        foreach ($items as $id => $item) {
            $payloadItems[] = [
                'id' => (string) $id,
                'facts' => $item['facts'],
                'draft' => $item['draft'],
            ];
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildWeatherSystemPrompt($tone, $toneCfg, $locale)
                    .' You will receive a JSON array of forecast items, each with an "id", "facts", and "draft".'
                    .' Rewrite EACH draft independently, following all the rules above and using only that item\'s own facts.'
                    .' Return ONLY a JSON object whose keys are the item ids and whose values are the rewritten plain-text'
                    .' forecast strings. Include every id exactly once. No markdown, no code fences, no commentary.',
            ],
            [
                'role' => 'user',
                'content' => json_encode($payloadItems, JSON_UNESCAPED_SLASHES),
            ],
        ];

        // One shared response carries every day, so size the cap to the batch. Reasoning models
        // also burn tokens on internal thinking that never reaches `content`, so add headroom on
        // top of the output budget — otherwise the JSON is truncated (finish_reason: length) before
        // any answer is emitted. Clamped to stay within the provider's per-minute token budget.
        $perItem = (int) ($toneCfg['max_tokens'] ?? 60);
        $outputBudget = ($perItem + 20) * count($items) + 100;
        $headroom = $this->reasoningHeadroom();
        $maxTokens = min($outputBudget + $headroom, 8000);

        try {
            $maxRetries = max(0, (int) config('nlg.rephrase.max_retry_attempts', 2));
            $attempt = 0;

            while (true) {
                $attempt++;

                // Reserve a slot before EVERY request (initial and each retry) so retries are paced
                // and counted by the caller's budget instead of silently bypassing the quota.
                if ($reserveSlot !== null && ! $reserveSlot()) {
                    return $drafts;
                }

                $res = Http::withToken($this->apiKey)
                    ->timeout(30)
                    ->post(rtrim($this->baseUrl, '/').'/chat/completions', $this->applyReasoningControls([
                        'model' => $this->model,
                        'messages' => $messages,
                        'max_tokens' => $maxTokens,
                        'temperature' => (float) ($toneCfg['temperature'] ?? 0.2),
                    ]));

                if ($res->ok()) {
                    break;
                }

                if ($res->status() === 429 && $attempt <= $maxRetries) {
                    $backoff = $this->resolveRetryBackoffSeconds($res, $attempt);
                    Log::notice('NLG batch rephrase hit provider throttle; retrying request', [
                        'status' => $res->status(),
                        'model' => $this->model,
                        'provider' => $this->providerHost(),
                        'items' => count($items),
                        'attempt' => $attempt,
                        'backoff_seconds' => $backoff,
                        'message' => data_get($res->json(), 'error.message', substr($res->body(), 0, 200)),
                    ]);
                    if ($backoff > 0) {
                        sleep($backoff);
                    }

                    continue;
                }

                Log::warning('NLG batch rephrase request failed', [
                    'status' => $res->status(),
                    'model' => $this->model,
                    'provider' => $this->providerHost(),
                    'items' => count($items),
                    'message' => data_get($res->json(), 'error.message', substr($res->body(), 0, 200)),
                ]);

                return $drafts;
            }

            $content = data_get($res->json(), 'choices.0.message.content');
            $map = $this->decodeBatchJson(is_string($content) ? $content : '');
            if ($map === null) {
                Log::warning('NLG batch rephrase returned unparseable JSON; keeping drafts', [
                    'model' => $this->model,
                    'provider' => $this->providerHost(),
                    'items' => count($items),
                    'finish_reason' => data_get($res->json(), 'choices.0.finish_reason'),
                    'content_length' => is_string($content) ? strlen($content) : 0,
                    'content_snippet' => is_string($content) ? substr($content, 0, 500) : '(non-string content)',
                    // Request-side facts to confirm the reasoning controls actually took effect.
                    'requested_max_tokens' => $maxTokens,
                    'reasoning_effort' => $this->reasoningEffort !== '' ? $this->reasoningEffort : '(off)',
                    'usage' => data_get($res->json(), 'usage'),
                ]);

                return $drafts;
            }

            $out = [];
            foreach ($items as $id => $item) {
                $value = $map[(string) $id] ?? null;
                $rewritten = is_string($value) ? trim($value) : '';

                if ($rewritten === '') {
                    $out[$id] = $item['draft'];

                    continue;
                }

                if ($this->containsInvalidWeatherPhrase($rewritten)) {
                    Log::warning('NLG batch rephrase produced invalid phrasing; falling back to draft', [
                        'model' => $this->model,
                        'provider' => $this->providerHost(),
                        'id' => (string) $id,
                        'text' => $rewritten,
                    ]);
                    $out[$id] = $item['draft'];

                    continue;
                }

                $out[$id] = $rewritten;
            }

            return $out;
        } catch (\Exception $e) {
            Log::warning('NLG batch rephrase request exception', [
                'model' => $this->model,
                'provider' => $this->providerHost(),
                'items' => count($items),
                'message' => $e->getMessage(),
            ]);

            return $drafts;
        }
    }

    /**
     * Add the operator-selected reasoning control to a chat/completions payload.
     *
     * Reasoning models (OpenAI o-series, and several Groq / OpenRouter / Cerebras models) otherwise
     * spend the whole token budget on internal thinking and return empty content. The controls are
     * only sent when a value is selected, so plain chat models keep working exactly as before:
     *   - 'disabled'        → `disable_reasoning: true` (Cerebras GLM / gpt-oss — turns thinking off)
     *   - 'low'/'medium'/'high' → standard OpenAI-compatible `reasoning_effort`
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyReasoningControls(array $payload): array
    {
        $value = trim($this->reasoningEffort);
        if ($value === '') {
            return $payload;
        }

        if ($value === 'disabled') {
            $payload['disable_reasoning'] = true;

            return $payload;
        }

        $payload['reasoning_effort'] = $value;

        return $payload;
    }

    /**
     * Extra output-token headroom for reasoning models. Only applied when a reasoning effort level
     * is selected (low/medium/high) — that selection is how the operator declares the active model
     * is a reasoning model that thinks before answering. Not needed when reasoning is disabled or
     * when the param is off, so non-reasoning providers keep their exact tone budget.
     */
    private function reasoningHeadroom(): int
    {
        $value = trim($this->reasoningEffort);
        if ($value === '' || $value === 'disabled') {
            return 0;
        }

        return max(0, (int) config('nlg.rephrase.reasoning_headroom_tokens', 2000));
    }

    /**
     * Decode the model's batch response into an id => text map, tolerating code fences
     * or stray prose around the JSON object. Returns null when no object can be parsed.
     *
     * @return array<string, mixed>|null
     */
    private function decodeBatchJson(string $content): ?array
    {
        $text = trim($content);
        if ($text === '') {
            return null;
        }

        if (str_starts_with($text, '```')) {
            $text = trim((string) preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $text));
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // The model wrapped the object in prose — extract the first {...} block.
        if (preg_match('/\{.*\}/s', $text, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function resolveRetryBackoffSeconds(Response $response, int $attempt): int
    {
        $retryAfterHeader = $response->header('Retry-After');
        if (is_string($retryAfterHeader) && is_numeric(trim($retryAfterHeader))) {
            return max(0, (int) trim($retryAfterHeader));
        }

        $backoff = config('nlg.rephrase.retry_backoff_seconds', [2, 5]);
        if (! is_array($backoff) || $backoff === []) {
            return 0;
        }

        $index = min($attempt - 1, count($backoff) - 1);
        $value = $backoff[$index] ?? 0;

        return max(0, is_numeric($value) ? (int) $value : 0);
    }

    private function providerHost(): string
    {
        return (string) parse_url($this->baseUrl, PHP_URL_HOST);
    }
}
