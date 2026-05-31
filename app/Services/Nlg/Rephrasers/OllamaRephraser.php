<?php

namespace App\Services\Nlg\Rephrasers;

use App\Contracts\Nlg\Rephraser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaRephraser implements Rephraser
{
    use Concerns\BuildsWeatherRephrasePrompts;

    public function __construct(
        private string $hostUrl = 'http://localhost:11434',
        private string $model = 'llama3',
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

        $prompt = $this->buildOllamaPrompt($draft, $facts, $tone, $toneCfg, $locale);

        try {
            $res = Http::timeout(30)->post(rtrim($this->hostUrl, '/') . '/api/generate', [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => (float) ($toneCfg['temperature'] ?? 0.2),
                    'num_predict' => $toneCfg['max_tokens'] ?? 60,
                ],
            ]);

            if (!$res->ok()) {
                Log::warning('NLG rephrase request failed', [
                    'status' => $res->status(),
                    'model' => $this->model,
                    'provider' => parse_url($this->hostUrl, PHP_URL_HOST),
                    'message' => substr($res->body(), 0, 200),
                ]);
                return $draft;
            }

            $text = data_get($res->json(), 'response');
            if (!is_string($text) || trim($text) === '') {
                return $draft;
            }

            $rewritten = trim($text);
            if ($this->containsInvalidWeatherPhrase($rewritten)) {
                Log::warning('NLG rephrase produced invalid phrasing; falling back to draft', [
                    'model' => $this->model,
                    'provider' => parse_url($this->hostUrl, PHP_URL_HOST),
                    'text' => $rewritten,
                ]);

                return $draft;
            }

            return $rewritten;
        } catch (\Exception $e) {
            Log::warning('NLG rephrase request exception', [
                'model' => $this->model,
                'provider' => parse_url($this->hostUrl, PHP_URL_HOST),
                'message' => $e->getMessage(),
            ]);
            return $draft;
        }
    }
}
