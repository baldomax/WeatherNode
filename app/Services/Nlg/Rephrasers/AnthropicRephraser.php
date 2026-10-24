<?php

namespace App\Services\Nlg\Rephrasers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\Message;
use App\Contracts\Nlg\BatchRephraser;
use App\Contracts\Nlg\Rephraser;
use App\Services\Nlg\Rephrasers\Concerns\BuildsWeatherRephrasePrompts;
use Illuminate\Support\Facades\Log;

/**
 * Rephraser backed by the native Claude API (official Anthropic PHP SDK).
 *
 * Differences from the OpenAI-compatible rephraser, all on purpose:
 *   - No `temperature`. Claude Opus 4.7 and every newer model reject any value below 1 with a 400,
 *     so the tone temperatures in config/nlg.php are not sent here.
 *   - Output-token headroom is always added. Claude 5 models think by default, and thinking tokens
 *     count against `max_tokens`, so the bare tone budget (60-120) would leave no room for the answer.
 *     Only tokens actually produced are billed, so a generous cap costs nothing when the model doesn't think.
 *   - The batch call uses structured outputs, so the reply is guaranteed to be a JSON object with
 *     exactly one string per id instead of free text we have to dig the JSON out of.
 *   - The admin "reasoning effort" maps to Claude's `output_config.effort`. Claude Opus 5.5 can't turn
 *     thinking off, so "disabled" maps to the lowest effort instead.
 */
class AnthropicRephraser implements BatchRephraser, Rephraser
{
    use BuildsWeatherRephrasePrompts;

    private bool $effortUnsupported = false;

    public function __construct(
        private Client $client,
        private string $model,
        private string $reasoningEffort = '',
    ) {}

    public function rewrite(string $draft, array $facts, string $tone = 'brief', ?string $locale = null): string
    {
        $toneCfg = $this->toneConfig($tone);

        $message = $this->send(
            params: [
                'maxTokens' => ((int) ($toneCfg['max_tokens'] ?? 60)) + $this->thinkingHeadroom(),
                'system' => $this->buildWeatherSystemPrompt($tone, $toneCfg, $locale),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Facts JSON: '.json_encode($facts, JSON_UNESCAPED_SLASHES).
                            "\n\nDraft: {$draft}\n\nRewrite:",
                    ],
                ],
            ],
            logContext: [],
        );

        if ($message === null || ! $this->finishedCleanly($message, [])) {
            return $draft;
        }

        $rewritten = trim($this->firstText($message));
        if ($rewritten === '') {
            return $draft;
        }

        if ($this->containsInvalidWeatherPhrase($rewritten)) {
            Log::warning('NLG rephrase produced invalid phrasing; falling back to draft', [
                'model' => $this->model,
                'provider' => 'anthropic',
                'text' => $rewritten,
            ]);

            return $draft;
        }

        return $rewritten;
    }

    public function rewriteBatch(array $items, string $tone = 'brief', ?callable $reserveSlot = null, ?string $locale = null): array
    {
        // Every id falls back to its own draft; the contract never drops a day.
        $drafts = array_map(static fn (array $item): string => $item['draft'], $items);
        if ($items === []) {
            return [];
        }

        $toneCfg = $this->toneConfig($tone);

        $payloadItems = [];
        $properties = [];
        foreach ($items as $id => $item) {
            $payloadItems[] = [
                'id' => (string) $id,
                'facts' => $item['facts'],
                'draft' => $item['draft'],
            ];
            $properties[(string) $id] = ['type' => 'string'];
        }

        // One shared response carries every day, so size the cap to the batch, plus thinking headroom.
        $perItem = (int) ($toneCfg['max_tokens'] ?? 60);
        $outputBudget = ($perItem + 20) * count($items) + 100;
        $maxTokens = min($outputBudget + $this->thinkingHeadroom(), 16000);

        $logContext = ['items' => count($items)];

        $message = $this->send(
            params: [
                'maxTokens' => $maxTokens,
                'system' => $this->buildWeatherSystemPrompt($tone, $toneCfg, $locale)
                    .' You will receive a JSON array of forecast items, each with an "id", "facts", and "draft".'
                    .' Rewrite EACH draft independently, following all the rules above and using only that item\'s own facts.'
                    .' Answer with a JSON object whose keys are the item ids and whose values are the rewritten plain-text forecast strings.',
                'messages' => [
                    ['role' => 'user', 'content' => json_encode($payloadItems, JSON_UNESCAPED_SLASHES)],
                ],
                'outputConfig' => [
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => [
                            'type' => 'object',
                            'properties' => $properties,
                            'required' => array_keys($properties),
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ],
            logContext: $logContext,
            reserveSlot: $reserveSlot,
        );

        if ($message === null || ! $this->finishedCleanly($message, $logContext)) {
            return $drafts;
        }

        $map = json_decode($this->firstText($message), true);
        if (! is_array($map)) {
            Log::warning('NLG batch rephrase returned unparseable JSON; keeping drafts', [
                'model' => $this->model,
                'provider' => 'anthropic',
                'items' => count($items),
                'stop_reason' => $message->stopReason,
                'content_snippet' => substr($this->firstText($message), 0, 500),
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
                    'provider' => 'anthropic',
                    'id' => (string) $id,
                    'text' => $rewritten,
                ]);
                $out[$id] = $item['draft'];

                continue;
            }

            $out[$id] = $rewritten;
        }

        return $out;
    }

    /**
     * Send one Messages API request, retrying on rate limits (429) and overload (529).
     * Returns null when the request failed for good; the caller then keeps its drafts.
     *
     * @param  array<string, mixed>  $params  Named arguments for MessagesService::create().
     * @param  array<string, mixed>  $logContext
     * @param  (callable(): bool)|null  $reserveSlot
     */
    private function send(array $params, array $logContext, ?callable $reserveSlot = null): ?Message
    {
        $params['model'] = $this->model;

        $effort = $this->effortUnsupported ? null : $this->effort();
        if ($effort !== null) {
            $params['outputConfig'] = array_merge($params['outputConfig'] ?? [], ['effort' => $effort]);
        }

        $maxRetries = max(0, (int) config('nlg.rephrase.max_retry_attempts', 2));
        $attempt = 0;

        while (true) {
            $attempt++;

            // Reserve before EVERY request (initial and each retry) so retries count against the budget.
            if ($reserveSlot !== null && ! $reserveSlot()) {
                return null;
            }

            try {
                return $this->client->messages->create(...$params);
            } catch (APIStatusException $e) {
                // Some models (Claude Haiku 4.5 and older) reject effort. Drop it and try again, and
                // remember that for the rest of this run so the next locale doesn't hit the same 400.
                if ($e->status === 400 && $effort !== null && str_contains(strtolower($this->errorMessage($e)), 'effort')) {
                    Log::notice('NLG rephrase model does not support effort; retrying without it', $logContext + [
                        'model' => $this->model,
                        'provider' => 'anthropic',
                        'effort' => $effort,
                    ]);
                    $this->effortUnsupported = true;
                    $effort = null;
                    unset($params['outputConfig']['effort']);
                    if ($params['outputConfig'] === []) {
                        unset($params['outputConfig']);
                    }

                    continue;
                }

                $retryable = in_array($e->status, [429, 529], true);
                if ($retryable && $attempt <= $maxRetries) {
                    $backoff = $this->resolveRetryBackoffSeconds($e, $attempt);
                    Log::notice('NLG rephrase hit provider throttle; retrying request', $logContext + [
                        'status' => $e->status,
                        'model' => $this->model,
                        'provider' => 'anthropic',
                        'attempt' => $attempt,
                        'backoff_seconds' => $backoff,
                        'message' => $this->errorMessage($e),
                    ]);
                    if ($backoff > 0) {
                        sleep($backoff);
                    }

                    continue;
                }

                Log::warning('NLG rephrase request failed', $logContext + [
                    'status' => $e->status,
                    'model' => $this->model,
                    'provider' => 'anthropic',
                    'message' => $this->errorMessage($e),
                ]);

                return null;
            } catch (\Exception $e) {
                Log::warning('NLG rephrase request exception', $logContext + [
                    'model' => $this->model,
                    'provider' => 'anthropic',
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        }
    }

    /**
     * A refusal or a cut-off answer is not usable text, so the caller keeps its drafts.
     *
     * @param  array<string, mixed>  $logContext
     */
    private function finishedCleanly(Message $message, array $logContext): bool
    {
        if ($message->stopReason === 'refusal' || $message->stopReason === 'max_tokens') {
            Log::warning('NLG rephrase stopped early; keeping drafts', $logContext + [
                'model' => $this->model,
                'provider' => 'anthropic',
                'stop_reason' => $message->stopReason,
                'refusal_category' => $message->stopDetails?->category,
                'output_tokens' => $message->usage->outputTokens,
            ]);

            return false;
        }

        return true;
    }

    private function firstText(Message $message): string
    {
        // Thinking blocks come before the text block, so never assume content[0] is text.
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return (string) $block->text;
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function toneConfig(string $tone): array
    {
        return config("nlg.tones.{$tone}")
            ?: config('nlg.tones.brief')
            ?: [
                'max_sentences' => 2,
                'max_tokens' => 60,
                'style_notes' => 'Concise, no filler, no emojis.',
            ];
    }

    /**
     * Map the admin reasoning setting to Claude's effort. Empty sends nothing, so the model uses its
     * own default; that matters for Claude Haiku 4.5, which rejects the effort parameter.
     */
    private function effort(): ?string
    {
        return match (trim($this->reasoningEffort)) {
            'disabled', 'low' => 'low',
            'medium' => 'medium',
            'high' => 'high',
            default => null,
        };
    }

    private function thinkingHeadroom(): int
    {
        return max(0, (int) config('nlg.rephrase.anthropic_thinking_headroom_tokens', 8000));
    }

    private function resolveRetryBackoffSeconds(APIStatusException $e, int $attempt): int
    {
        $retryAfter = trim((string) $e->response?->getHeaderLine('retry-after'));
        if ($retryAfter !== '' && is_numeric($retryAfter)) {
            return max(0, (int) ceil((float) $retryAfter));
        }

        $backoff = config('nlg.rephrase.retry_backoff_seconds', [2, 5]);
        if (! is_array($backoff) || $backoff === []) {
            return 0;
        }

        $value = $backoff[min($attempt - 1, count($backoff) - 1)] ?? 0;

        return max(0, is_numeric($value) ? (int) $value : 0);
    }

    private function errorMessage(APIStatusException $e): string
    {
        $message = is_array($e->body) ? data_get($e->body, 'error.message') : null;

        return is_string($message) ? $message : substr((string) json_encode($e->body), 0, 200);
    }
}
