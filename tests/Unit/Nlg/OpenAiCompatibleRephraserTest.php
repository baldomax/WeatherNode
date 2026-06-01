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
                        'temperature' => 0.2,
                        'style_notes' => 'Keep it compact and direct. Prefer 1-2 short sentences with only the main forecast takeaway. No filler, no emojis.',
                    ],
                    'friendly' => [
                        'max_sentences' => 4,
                        'max_tokens' => 120,
                        'temperature' => 0.55,
                        'style_notes' => 'Use a warm, natural weather-presenter tone. It may be slightly longer than brief, with a clearer day flow and more natural transitions. Avoid stiff or literal wording. No filler, no emojis.',
                    ],
                    'formal' => [
                        'max_sentences' => 4,
                        'max_tokens' => 120,
                        'temperature' => 0.3,
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
        $this->assertStringContainsString('warm, upbeat local weather presenter', $payload['messages'][0]['content']);
        $this->assertSame(0.55, $payload['temperature'], 'friendly tone should run a touch hotter than the default');
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

    public function test_rewrite_batch_maps_results_in_a_single_request(): void
    {
        $calls = 0;
        $payload = null;

        Http::fake(function (Request $request) use (&$calls, &$payload) {
            $calls++;
            $payload = $request->data();

            return Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        '2026-03-13' => 'Sunny and dry on Friday.',
                        '2026-03-14' => 'Cloudier on Saturday with a chance of rain later.',
                    ])]],
                ],
            ]);
        });

        $rephraser = new OpenAiCompatibleRephraser(
            baseUrl: 'https://example.test/v1',
            apiKey: 'test-key',
            model: 'test-model',
        );

        $result = $rephraser->rewriteBatch([
            '2026-03-13' => ['draft' => 'Draft one.', 'facts' => ['date' => '2026-03-13']],
            '2026-03-14' => ['draft' => 'Draft two.', 'facts' => ['date' => '2026-03-14']],
        ], 'brief');

        $this->assertSame(1, $calls, 'a whole locale must cost a single provider request');
        $this->assertSame([
            '2026-03-13' => 'Sunny and dry on Friday.',
            '2026-03-14' => 'Cloudier on Saturday with a chance of rain later.',
        ], $result);
        $this->assertGreaterThan(60, $payload['max_tokens'], 'token cap should scale with the number of items');
    }

    public function test_rewrite_batch_instructs_the_model_to_keep_the_target_language(): void
    {
        config()->set('localization.locales', ['nl-nl' => ['label' => 'Nederlands', 'short' => 'NL']]);

        $payload = null;
        Http::fake(function (Request $request) use (&$payload) {
            $payload = $request->data();

            return Http::response([
                'choices' => [['message' => ['content' => json_encode(['2026-03-13' => 'Zonnig en droog.'])]]],
            ]);
        });

        $rephraser = new OpenAiCompatibleRephraser('https://example.test/v1', 'test-key', 'test-model');
        $rephraser->rewriteBatch(
            ['2026-03-13' => ['draft' => 'Zonnig.', 'facts' => ['date' => '2026-03-13']]],
            'brief',
            null,
            'nl-nl',
        );

        $system = $payload['messages'][0]['content'];
        $this->assertStringContainsString('Nederlands', $system, 'the prompt must name the target language');
        $this->assertStringContainsString('never translate it to English', $system);
    }

    public function test_rewrite_omits_language_directive_when_no_locale_given(): void
    {
        $payload = null;
        Http::fake(function (Request $request) use (&$payload) {
            $payload = $request->data();

            return Http::response([
                'choices' => [['message' => ['content' => 'Sunny and dry.']]],
            ]);
        });

        $rephraser = new OpenAiCompatibleRephraser('https://example.test/v1', 'test-key', 'test-model');
        $rephraser->rewrite('Draft.', ['date' => '2026-03-13'], 'brief');

        $this->assertStringStartsWith('Rewrite the forecast', $payload['messages'][0]['content']);
    }

    public function test_rewrite_batch_omits_reasoning_effort_and_headroom_by_default(): void
    {
        $payload = null;
        Http::fake(function (Request $request) use (&$payload) {
            $payload = $request->data();

            return Http::response([
                'choices' => [['message' => ['content' => json_encode(['2026-03-13' => 'Sunny and dry.'])]]],
            ]);
        });

        $rephraser = new OpenAiCompatibleRephraser('https://example.test/v1', 'test-key', 'test-model');
        $rephraser->rewriteBatch(['2026-03-13' => ['draft' => 'Draft.', 'facts' => ['date' => '2026-03-13']]], 'brief');

        $this->assertArrayNotHasKey('reasoning_effort', $payload, 'param must not be sent to non-reasoning providers');
        // 1 item: (60 + 20) * 1 + 100 = 180, no reasoning headroom.
        $this->assertSame(180, $payload['max_tokens']);
    }

    public function test_rewrite_batch_sends_reasoning_effort_and_headroom_when_configured(): void
    {
        config()->set('nlg.rephrase.reasoning_headroom_tokens', 2000);

        $payload = null;
        Http::fake(function (Request $request) use (&$payload) {
            $payload = $request->data();

            return Http::response([
                'choices' => [['message' => ['content' => json_encode(['2026-03-13' => 'Sunny and dry.'])]]],
            ]);
        });

        $rephraser = new OpenAiCompatibleRephraser('https://example.test/v1', 'test-key', 'test-model', 'low');
        $rephraser->rewriteBatch(['2026-03-13' => ['draft' => 'Draft.', 'facts' => ['date' => '2026-03-13']]], 'brief');

        $this->assertSame('low', $payload['reasoning_effort']);
        $this->assertArrayNotHasKey('disable_reasoning', $payload);
        // 180 output budget + 2000 reasoning headroom.
        $this->assertSame(2180, $payload['max_tokens']);
    }

    public function test_rewrite_batch_disables_reasoning_without_headroom_when_set_to_disabled(): void
    {
        $payload = null;
        Http::fake(function (Request $request) use (&$payload) {
            $payload = $request->data();

            return Http::response([
                'choices' => [['message' => ['content' => json_encode(['2026-03-13' => 'Sunny and dry.'])]]],
            ]);
        });

        $rephraser = new OpenAiCompatibleRephraser('https://example.test/v1', 'test-key', 'test-model', 'disabled');
        $rephraser->rewriteBatch(['2026-03-13' => ['draft' => 'Draft.', 'facts' => ['date' => '2026-03-13']]], 'brief');

        $this->assertTrue($payload['disable_reasoning'], 'disabled must send the Cerebras disable_reasoning flag');
        $this->assertArrayNotHasKey('reasoning_effort', $payload, 'disabled must not also send reasoning_effort');
        // No reasoning means no headroom — just the output budget: (60 + 20) * 1 + 100 = 180.
        $this->assertSame(180, $payload['max_tokens']);
    }

    public function test_rewrite_batch_falls_back_per_item_for_missing_or_invalid_entries(): void
    {
        Http::fake(fn () => Http::response([
            'choices' => [
                ['message' => ['content' => json_encode([
                    '2026-03-13' => 'Dry rain later.', // invalid phrasing → keep draft
                    // '2026-03-14' omitted → keep draft
                    '2026-03-15' => 'Bright and breezy.',
                ])]],
            ],
        ]));

        $rephraser = new OpenAiCompatibleRephraser(
            baseUrl: 'https://example.test/v1',
            apiKey: 'test-key',
            model: 'test-model',
        );

        $result = $rephraser->rewriteBatch([
            '2026-03-13' => ['draft' => 'Draft one.', 'facts' => []],
            '2026-03-14' => ['draft' => 'Draft two.', 'facts' => []],
            '2026-03-15' => ['draft' => 'Draft three.', 'facts' => []],
        ], 'brief');

        $this->assertSame([
            '2026-03-13' => 'Draft one.',
            '2026-03-14' => 'Draft two.',
            '2026-03-15' => 'Bright and breezy.',
        ], $result);
    }

    public function test_rewrite_batch_reserves_a_slot_before_each_request_and_aborts_when_denied(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response(['choices' => [['message' => ['content' => '{}']]]]);
        });

        $rephraser = new OpenAiCompatibleRephraser(
            baseUrl: 'https://example.test/v1',
            apiKey: 'test-key',
            model: 'test-model',
        );

        $reserveCalls = 0;
        $result = $rephraser->rewriteBatch(
            ['2026-03-13' => ['draft' => 'Draft one.', 'facts' => []]],
            'brief',
            function () use (&$reserveCalls): bool {
                $reserveCalls++;

                return false; // budget exhausted
            },
        );

        $this->assertSame(1, $reserveCalls, 'the slot must be reserved before the request fires');
        $this->assertSame(0, $calls, 'no provider request may fire once the budget denies a slot');
        $this->assertSame(['2026-03-13' => 'Draft one.'], $result);
    }

    public function test_rewrite_batch_counts_each_retry_against_the_reserve_callback(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            if ($calls === 1) {
                return Http::response(['error' => ['message' => 'rate limited']], 429, ['Retry-After' => '0']);
            }

            return Http::response(['choices' => [['message' => ['content' => json_encode([
                '2026-03-13' => 'Recovered batch rewrite.',
            ])]]]]);
        });

        $rephraser = new OpenAiCompatibleRephraser(
            baseUrl: 'https://example.test/v1',
            apiKey: 'test-key',
            model: 'test-model',
        );

        $reserveCalls = 0;
        $result = $rephraser->rewriteBatch(
            ['2026-03-13' => ['draft' => 'Draft one.', 'facts' => []]],
            'brief',
            function () use (&$reserveCalls): bool {
                $reserveCalls++;

                return true;
            },
        );

        $this->assertSame(2, $calls);
        $this->assertSame(2, $reserveCalls, 'the retry must reserve its own budget slot');
        $this->assertSame(['2026-03-13' => 'Recovered batch rewrite.'], $result);
    }
}
