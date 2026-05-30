<?php

namespace App\Services\Nlg\Rephrasers;

use App\Contracts\Nlg\Rephraser;
use App\Services\Nlg\Rephrasers\Concerns\BuildsWeatherRephrasePrompts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class OpenAiCompatibleRephraser implements Rephraser
{
    use BuildsWeatherRephrasePrompts;

    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $model,
    ) {}

    public function rewrite(string $draft, array $facts, string $tone = 'brief'): string
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
                'content' => $this->buildWeatherSystemPrompt($tone, $toneCfg),
            ],
            [
                'role' => 'user',
                'content' =>
                    "Facts JSON: " . json_encode($facts, JSON_UNESCAPED_SLASHES) .
                    "\n\nDraft: {$draft}\n\nRewrite:",
            ],
        ];

        try {
            $maxRetries = max(0, (int) config('nlg.rephrase.max_retry_attempts', 2));
            $attempt = 0;

            while (true) {
                $attempt++;

                $res = Http::withToken($this->apiKey)
                    ->timeout(15)
                    ->post(rtrim($this->baseUrl, '/') . '/chat/completions', [
                        'model' => $this->model,
                        'messages' => $messages,
                        'max_tokens' => $toneCfg['max_tokens'] ?? 60,
                        'temperature' => 0.2,
                    ]);

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
            if (!is_string($text) || trim($text) === '') {
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

    private function resolveRetryBackoffSeconds(Response $response, int $attempt): int
    {
        $retryAfterHeader = $response->header('Retry-After');
        if (is_string($retryAfterHeader) && is_numeric(trim($retryAfterHeader))) {
            return max(0, (int) trim($retryAfterHeader));
        }

        $backoff = config('nlg.rephrase.retry_backoff_seconds', [2, 5]);
        if (!is_array($backoff) || $backoff === []) {
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
