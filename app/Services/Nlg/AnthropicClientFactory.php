<?php

namespace App\Services\Nlg;

use Anthropic\Client;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Client\ClientInterface;

/**
 * Builds Anthropic SDK clients for the NLG rephraser, model discovery and the admin connection test.
 *
 * The SDK leaves timeouts to the HTTP transport, so a Guzzle client with a hard timeout is passed in
 * (a hung request would otherwise stall the scheduled rephrase run). SDK retries are turned off:
 * the rephraser runs its own retry loop so every attempt goes through the caller's request budget.
 * Tests pass their own PSR-18 transport to capture requests without touching the network.
 */
class AnthropicClientFactory
{
    public const DEFAULT_BASE_URL = 'https://api.anthropic.com';

    public function __construct(
        private ?ClientInterface $transporter = null,
    ) {}

    public function make(string $apiKey, string $baseUrl = '', float $timeoutSeconds = 30.0): Client
    {
        return new Client(
            apiKey: $apiKey,
            baseUrl: rtrim($baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL, '/'),
            requestOptions: [
                'maxRetries' => 0,
                'timeout' => $timeoutSeconds,
                'transporter' => $this->transporter ?? new GuzzleClient(['timeout' => $timeoutSeconds]),
            ],
            openTelemetry: false,
        );
    }
}
