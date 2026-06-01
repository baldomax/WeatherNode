<?php

namespace Tests\Unit\Nlg;

use App\Services\Nlg\NlgProviderModelDiscovery;
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
}
