<?php

namespace Tests\Unit\Nlg;

use App\Services\Nlg\AnthropicClientFactory;
use App\Services\Nlg\Rephrasers\AnthropicRephraser;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class AnthropicRephraserTest extends TestCase
{
    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container;
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
                        'style_notes' => 'Keep it compact and direct.',
                    ],
                    'friendly' => [
                        'max_sentences' => 4,
                        'max_tokens' => 120,
                        'temperature' => 0.55,
                        'style_notes' => 'Warm and upbeat.',
                    ],
                ],
                'rephrase' => [
                    'max_retry_attempts' => 2,
                    'retry_backoff_seconds' => [0, 0],
                    'anthropic_thinking_headroom_tokens' => 2000,
                ],
            ],
        ]));
        $app->instance('log', new class
        {
            public function info(string $message, array $context = []): void {}

            public function notice(string $message, array $context = []): void {}

            public function warning(string $message, array $context = []): void {}
        });

        $this->history = [];
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();

        parent::tearDown();
    }

    public function test_rewrite_sends_a_native_messages_request_without_temperature(): void
    {
        $rephraser = $this->rephraser([
            $this->message([
                ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
                ['type' => 'text', 'text' => 'Short rewrite.'],
            ]),
        ]);

        $text = $rephraser->rewrite('Draft text.', ['date' => '2026-10-17'], 'friendly', 'nl-nl');

        $this->assertSame('Short rewrite.', $text, 'the text block after a thinking block is used');
        $this->assertCount(1, $this->history);

        $request = $this->history[0]['request'];
        $this->assertSame('https://api.anthropic.com/v1/messages', (string) $request->getUri());
        $this->assertSame('test-key', $request->getHeaderLine('x-api-key'));

        $body = $this->body(0);
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame(120 + 2000, $body['max_tokens'], 'thinking headroom is added on top of the tone budget');
        $this->assertArrayNotHasKey('temperature', $body, 'newer Claude models reject temperature below 1');
        $this->assertArrayNotHasKey('output_config', $body, 'no effort is sent when the setting is empty');
        $this->assertStringContainsString('warm, upbeat local weather presenter', $body['system']);
        $this->assertSame('user', $body['messages'][0]['role']);
        $this->assertStringContainsString('Draft: Draft text.', $body['messages'][0]['content']);
    }

    public function test_reasoning_setting_maps_to_effort_and_disabled_means_low(): void
    {
        $rephraser = $this->rephraser([$this->message([['type' => 'text', 'text' => 'Ok.']])], reasoningEffort: 'disabled');
        $rephraser->rewrite('Draft.', [], 'brief');

        $this->assertSame(['effort' => 'low'], $this->body(0)['output_config']);
    }

    public function test_effort_is_dropped_for_models_that_reject_it(): void
    {
        $rephraser = $this->rephraser([
            $this->error(400, 'invalid_request_error', 'This model does not support the effort parameter.'),
            $this->message([['type' => 'text', 'text' => 'First.']]),
            $this->message([['type' => 'text', 'text' => 'Second.']]),
        ], reasoningEffort: 'low');

        $this->assertSame('First.', $rephraser->rewrite('Draft.', [], 'brief'));
        $this->assertSame('Second.', $rephraser->rewrite('Draft.', [], 'brief'));

        $this->assertSame(['effort' => 'low'], $this->body(0)['output_config']);
        $this->assertArrayNotHasKey('output_config', $this->body(1), 'the retry goes out without effort');
        $this->assertArrayNotHasKey('output_config', $this->body(2), 'later calls remember the model rejects effort');
    }

    public function test_rewrite_rejects_dry_rain_and_keeps_the_draft(): void
    {
        $rephraser = $this->rephraser([$this->message([['type' => 'text', 'text' => 'Dry rain is expected later.']])]);

        $draft = 'Mostly dry, with a slight chance of rain later.';
        $this->assertSame($draft, $rephraser->rewrite($draft, [], 'brief'));
    }

    public function test_refusal_and_truncated_answers_keep_the_draft(): void
    {
        $rephraser = $this->rephraser([
            $this->message([], stopReason: 'refusal'),
            $this->message([['type' => 'text', 'text' => 'Half a sen']], stopReason: 'max_tokens'),
        ]);

        $this->assertSame('Draft one.', $rephraser->rewrite('Draft one.', [], 'brief'));
        $this->assertSame('Draft two.', $rephraser->rewrite('Draft two.', [], 'brief'));
    }

    public function test_bad_request_keeps_the_draft_without_retrying(): void
    {
        $rephraser = $this->rephraser([
            $this->error(400, 'invalid_request_error', 'model: not found'),
        ]);

        $this->assertSame('Draft.', $rephraser->rewrite('Draft.', [], 'brief'));
        $this->assertCount(1, $this->history);
    }

    public function test_batch_uses_structured_output_and_maps_each_id(): void
    {
        $rephraser = $this->rephraser([
            $this->message([[
                'type' => 'text',
                'text' => json_encode([
                    '2026-10-17' => 'Sunny and mild.',
                    '2026-10-18' => 'Dry rain later.',
                    '2026-10-19' => '',
                ]),
            ]]),
        ], reasoningEffort: 'medium');

        $result = $rephraser->rewriteBatch([
            '2026-10-17' => ['draft' => 'Draft 17.', 'facts' => ['day' => 17]],
            '2026-10-18' => ['draft' => 'Draft 18.', 'facts' => ['day' => 18]],
            '2026-10-19' => ['draft' => 'Draft 19.', 'facts' => ['day' => 19]],
        ], 'brief');

        $this->assertSame([
            '2026-10-17' => 'Sunny and mild.',
            '2026-10-18' => 'Draft 18.',
            '2026-10-19' => 'Draft 19.',
        ], $result);

        $body = $this->body(0);
        $this->assertSame('medium', $body['output_config']['effort']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame(['2026-10-17', '2026-10-18', '2026-10-19'], $body['output_config']['format']['schema']['required']);
        $this->assertFalse($body['output_config']['format']['schema']['additionalProperties']);
        $this->assertSame((60 + 20) * 3 + 100 + 2000, $body['max_tokens']);
        $this->assertArrayNotHasKey('temperature', $body);
    }

    public function test_batch_retries_rate_limits_and_reserves_a_slot_per_attempt(): void
    {
        $rephraser = $this->rephraser([
            $this->error(429, 'rate_limit_error', 'slow down', ['retry-after' => '0']),
            $this->error(529, 'overloaded_error', 'overloaded'),
            $this->message([['type' => 'text', 'text' => json_encode(['a' => 'Rewritten.'])]]),
        ]);

        $reservations = 0;
        $result = $rephraser->rewriteBatch(
            ['a' => ['draft' => 'Draft.', 'facts' => []]],
            'brief',
            function () use (&$reservations): bool {
                $reservations++;

                return true;
            },
        );

        $this->assertSame(['a' => 'Rewritten.'], $result);
        $this->assertSame(3, $reservations);
        $this->assertCount(3, $this->history);
    }

    public function test_batch_sends_nothing_when_the_budget_is_spent(): void
    {
        $rephraser = $this->rephraser([]);

        $result = $rephraser->rewriteBatch(
            ['a' => ['draft' => 'Draft.', 'facts' => []]],
            'brief',
            fn (): bool => false,
        );

        $this->assertSame(['a' => 'Draft.'], $result);
        $this->assertCount(0, $this->history);
    }

    /**
     * @param  array<int, Response>  $responses
     */
    private function rephraser(array $responses, string $reasoningEffort = ''): AnthropicRephraser
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $client = (new AnthropicClientFactory(new GuzzleClient(['handler' => $stack])))->make('test-key');

        return new AnthropicRephraser($client, 'claude-opus-5-5', $reasoningEffort);
    }

    /**
     * @param  array<int, array<string, mixed>>  $content
     */
    private function message(array $content, string $stopReason = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => $content,
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'stop_details' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function error(int $status, string $type, string $message, array $headers = []): Response
    {
        return new Response($status, $headers + ['Content-Type' => 'application/json'], json_encode([
            'type' => 'error',
            'error' => ['type' => $type, 'message' => $message],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(int $index): array
    {
        return json_decode((string) $this->history[$index]['request']->getBody(), true);
    }
}
