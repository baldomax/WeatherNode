<?php

namespace Tests\Unit\Nlg;

use App\Services\Nlg\AnthropicClientFactory;
use App\Services\Nlg\NlgProviderModelDiscovery;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;

class NlgProviderModelDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container();
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        Http::swap(new HttpFactory());
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();

        parent::tearDown();
    }

    public function test_it_discovers_openai_compatible_models_from_models_endpoint(): void
    {
        $calledUrl = null;

        Http::fake(function (Request $request) use (&$calledUrl) {
            $calledUrl = $request->url();

            return Http::response([
                'data' => [
                    ['id' => 'gemini-2.5-flash'],
                    ['id' => 'gemini-2.5-pro'],
                ],
            ]);
        });

        $discovery = new NlgProviderModelDiscovery();
        $result = $discovery->discover('compatible', 'https://generativelanguage.googleapis.com/v1beta/openai', 'test-key');

        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/openai/models', $calledUrl);
        $this->assertTrue($result['supported']);
        $this->assertSame(['gemini-2.5-flash', 'gemini-2.5-pro'], $result['models']);
        $this->assertTrue($discovery->modelExists($result['models'], 'gemini-2.5-flash'));
    }

    public function test_it_discovers_ollama_models_and_matches_latest_alias(): void
    {
        Http::fake([
            'http://localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'llama3:latest'],
                    ['name' => 'qwen2.5:latest'],
                ],
            ]),
        ]);

        $discovery = new NlgProviderModelDiscovery();
        $result = $discovery->discover('ollama', 'http://localhost:11434');

        $this->assertTrue($result['supported']);
        $this->assertSame(['llama3:latest', 'qwen2.5:latest'], $result['models']);
        $this->assertTrue($discovery->modelExists($result['models'], 'llama3', 'ollama'));
    }

    public function test_it_reports_when_model_listing_is_unavailable(): void
    {
        Http::fake([
            'https://api.cerebras.ai/v1/models' => Http::response(['error' => ['message' => 'not found']], 404),
        ]);

        $discovery = new NlgProviderModelDiscovery();
        $result = $discovery->discover('compatible', 'https://api.cerebras.ai/v1', 'test-key');

        $this->assertFalse($result['supported']);
        $this->assertSame([], $result['models']);
        $this->assertStringContainsString('HTTP 404', (string) $result['message']);
    }

    public function test_it_discovers_claude_models_with_an_api_key_header(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    ['type' => 'model', 'id' => 'claude-opus-5-5', 'display_name' => 'Claude Opus 5.5', 'created_at' => '2026-09-01T00:00:00Z'],
                    ['type' => 'model', 'id' => 'claude-haiku-4-5', 'display_name' => 'Claude Haiku 4.5', 'created_at' => '2025-10-01T00:00:00Z'],
                ],
                'has_more' => false,
                'first_id' => 'claude-opus-5-5',
                'last_id' => 'claude-haiku-4-5',
            ])),
        ]));
        $stack->push(Middleware::history($history));

        $discovery = new NlgProviderModelDiscovery(
            new AnthropicClientFactory(new GuzzleClient(['handler' => $stack])),
        );
        $result = $discovery->discover('anthropic', 'https://api.anthropic.com', 'test-key');

        $this->assertTrue($result['supported']);
        $this->assertSame(['claude-opus-5-5', 'claude-haiku-4-5'], $result['models']);
        $this->assertStringStartsWith('https://api.anthropic.com/v1/models', (string) $history[0]['request']->getUri());
        $this->assertSame('test-key', $history[0]['request']->getHeaderLine('x-api-key'));
        $this->assertSame('', $history[0]['request']->getHeaderLine('authorization'));
    }

    public function test_it_reports_claude_model_listing_errors(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(401, ['Content-Type' => 'application/json'], json_encode([
                'type' => 'error',
                'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key'],
            ])),
        ]));

        $discovery = new NlgProviderModelDiscovery(
            new AnthropicClientFactory(new GuzzleClient(['handler' => $stack])),
        );
        $result = $discovery->discover('anthropic', 'https://api.anthropic.com', 'bad-key');

        $this->assertFalse($result['supported']);
        $this->assertStringContainsString('HTTP 401', (string) $result['message']);
        $this->assertStringContainsString('invalid x-api-key', (string) $result['message']);
    }
}
