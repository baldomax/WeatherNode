<?php

namespace App\Services\Nlg;

use Illuminate\Support\Facades\Http;

class NlgProviderModelDiscovery
{
    /**
     * @return array{supported: bool, models: array<int, string>, message: ?string}
     */
    public function discover(string $type, string $baseUrl, string $apiKey = ''): array
    {
        return $type === 'ollama'
            ? $this->discoverOllamaModels($baseUrl)
            : $this->discoverCompatibleModels($baseUrl, $apiKey);
    }

    public function modelExists(array $models, string $model, string $type = 'compatible'): bool
    {
        $needle = strtolower(trim($model));
        if ($needle === '') {
            return false;
        }

        $haystack = array_values(array_filter(array_map(static function ($value): ?string {
            return is_string($value) ? strtolower(trim($value)) : null;
        }, $models)));

        if (in_array($needle, $haystack, true)) {
            return true;
        }

        if ($type !== 'ollama') {
            return false;
        }

        return in_array($needle . ':latest', $haystack, true)
            || (str_ends_with($needle, ':latest') && in_array(substr($needle, 0, -7), $haystack, true));
    }

    /**
     * @return array{supported: bool, models: array<int, string>, message: ?string}
     */
    private function discoverCompatibleModels(string $baseUrl, string $apiKey): array
    {
        $endpoint = rtrim($baseUrl, '/') . '/models';
        $request = Http::timeout(10);

        if ($apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        $response = $request->get($endpoint);
        if (!$response->ok()) {
            return [
                'supported' => false,
                'models' => [],
                'message' => "Model listing unavailable (HTTP {$response->status()}).",
            ];
        }

        $data = $response->json();
        $models = [];

        foreach ((array) data_get($data, 'data', []) as $entry) {
            $id = data_get($entry, 'id');
            if (is_string($id) && trim($id) !== '') {
                $models[] = trim($id);
            }
        }

        if ($models === []) {
            foreach ((array) data_get($data, 'models', []) as $entry) {
                $name = data_get($entry, 'name');
                if (is_string($name) && trim($name) !== '') {
                    $models[] = trim($name);
                }
            }
        }

        return [
            'supported' => $models !== [],
            'models' => array_values(array_unique($models)),
            'message' => $models !== [] ? null : 'Model listing returned no usable model ids.',
        ];
    }

    /**
     * @return array{supported: bool, models: array<int, string>, message: ?string}
     */
    private function discoverOllamaModels(string $baseUrl): array
    {
        $response = Http::timeout(10)->get(rtrim($baseUrl, '/') . '/api/tags');

        if (!$response->ok()) {
            return [
                'supported' => false,
                'models' => [],
                'message' => "Cannot reach Ollama at {$baseUrl}. Is it running?",
            ];
        }

        $models = [];
        foreach ((array) data_get($response->json(), 'models', []) as $entry) {
            $name = data_get($entry, 'name');
            if (is_string($name) && trim($name) !== '') {
                $models[] = trim($name);
            }
        }

        return [
            'supported' => true,
            'models' => array_values(array_unique($models)),
            'message' => null,
        ];
    }
}
