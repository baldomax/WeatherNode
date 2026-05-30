<?php

namespace Tests\Unit\Nlg;

use App\Services\Nlg\Rephrasers\OpenAiCompatibleRephraser;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;

class OpenAiCompatibleRephraserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container();
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $app->instance('config', new ConfigRepository([
            'nlg' => [
                'tones' => [
                    'brief' => [
                        'max_sentences' => 2,
                        'max_tokens' => 60,
                        'style_notes' => 'Keep it compact and direct. Prefer 1-2 short sentences with only the main forecast takeaway. No filler, no emojis.',
                    ],
                    'friendly' => [
                        'max_sentences' => 4,
                        'max_tokens' => 120,
                        'style_notes' => 'Use a warm, natural weather-presenter tone. It may be slightly longer than brief, with a clearer day flow and more natural transitions. Avoid stiff or literal wording. No filler, no emojis.',
                    ],
                    'formal' => [
                        'max_sentences' => 4,
                        'max_tokens' => 120,
                        'style_notes' => 'Use a professional forecast tone with complete, polished sentences. It may be slightly longer than brief, with explicit timing, measured phrasing, and clear forecast ordering. No filler, no emojis.',
                    ],
                ],
                'rephrase' => [
                    'max_requests_per_minute' => 60,
                    'max_retry_attempts' => 2,
                    'retry_backoff_seconds' => [0, 0],
                ],
            ],
        ]));
        $app->instance('log', new class
        {
            public function info(string $message, array $context = []): void
            {
            }

            public function notice(string $message, array $context = []): void
            {
            }

            public function warning(string $message, array $context = []): void
            {
            }
        });

        Http::swap(new HttpFactory());
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();

        parent::tearDown();
    }

    public function test_it_sends_tone_token_cap_to_chat_completion_request(): void
    {
        $payload = null;

        Http::fake(function (Request $request) use (&$payload) {
            $payload = $request->data();

            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'Short rewrite.']],
                ],
            ]);
        });

        $rephraser = new OpenAiCompatibleRephraser(
            baseUrl: 'https://example.test/v1',
            apiKey: 'test-key',
            model: 'test-model',
        );

        $text = $rephraser->rewrite('Draft text.', ['date' => '2026-03-13'], 'brief');

        $this->assertSame('Short rewrite.', $text);
        $this->assertIsArray($payload);
        $this->assertSame(60, $payload['max_tokens']);
        $this->assertSame('test-model', $payload['model']);
    }

    public function test_it_adds_weather_specific_guardrails_and_rejects_dry_rain_output(): void
    {
        $payload = null;

        Http::fake(function (Request $request) use (&$payload) {
            $payload = $request->data();

            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'Dry rain is expected later.']],
                ],
            ]);
        });

        $rephraser = new OpenAiCompatibleRephraser(
            baseUrl: 'https://example.test/v1',
            apiKey: 'test-key',
            model: 'test-model',
        );

        $draft = 'Mostly dry, with a slight chance of rain later.';
        $text = $rephraser->rewrite($draft, ['date' => '2026-03-13'], 'friendly');

        $this->assertSame($draft, $text);
        $this->assertIsArray($payload);
        $this->assertSame(120, $payload['max_tokens']);
        $this->assertStringContainsString('Never write contradictory precipitation phrases', $payload['messages'][0]['content']);
        $this->assertStringContainsString('Use idiomatic forecast language', $payload['messages'][0]['content']);
        $this->assertStringContainsString('Sound like a clear local weather presenter', $payload['messages'][0]['content']);
    }

    public function test_brief_and_formal_tones_keep_distinct_prompt_guidance(): void
    {
        $payloads = [];

        Http::fake(function (Request $request) use (&$payloads) {
            $payloads[] = $request->data();

            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'Forecast rewrite.']],
                ],
            ]);
        });

        $rephraser = new OpenAiCompatibleRephraser(
            baseUrl: 'https://example.test/v1',
            apiKey: 'test-key',
            model: 'test-model',
        );

        $rephraser->rewrite('Draft text.', ['date' => '2026-03-13'], 'brief');
        $rephraser->rewrite('Draft text.', ['date' => '2026-03-13'], 'formal');

        $this->assertCount(2, $payloads);
        $this->assertSame(60, $payloads[0]['max_tokens']);
        $this->assertStringContainsString('Keep it very short', $payloads[0]['messages'][0]['content']);
        $this->assertSame(120, $payloads[1]['max_tokens']);
        $this->assertStringContainsString('Sound like a concise professional forecast', $payloads[1]['messages'][0]['content']);
    }

    public function test_it_retries_once_after_429_response(): void
    {
        $calls = 0;

        Http::fake(function () use (&$calls) {
            $calls++;

            if ($calls === 1) {
                return Http::response([
                    'error' => [
                        'message' => 'Requests per minute limit exceeded - too many requests sent.',
                        'type' => 'too_many_requests_error',
                        'param' => 'quota',
                        'code' => 'request_quota_exceeded',
                    ],
                ], 429, ['Retry-After' => '0']);
            }

            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'Recovered rewrite.']],
                ],
            ]);
        });

        $rephraser = new OpenAiCompatibleRephraser(
            baseUrl: 'https://example.test/v1',
            apiKey: 'test-key',
            model: 'test-model',
        );

        $text = $rephraser->rewrite('Draft text.', ['date' => '2026-03-13'], 'brief');

        $this->assertSame('Recovered rewrite.', $text);
        $this->assertSame(2, $calls);
    }
}
