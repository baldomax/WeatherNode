<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\Security\ApiKeyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    public function index(ApiKeyService $service): View
    {
        $tableReady = $this->tableReady();
        $publicKey = $tableReady ? $service->getOrCreatePublicKey() : null;
        $keys = $tableReady ? ApiKey::orderByDesc('created_at')->get() : collect();

        return view('admin.api-keys.index', [
            'tableReady' => $tableReady,
            'publicKey' => $publicKey,
            'keys' => $keys,
        ]);
    }

    public function store(Request $request, ApiKeyService $service): RedirectResponse
    {
        if (!$this->tableReady()) {
            return redirect()
                ->route('admin.api-keys.index')
                ->with('error', __('API key table is missing. Run migrations first.'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:6000'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $plain = $service->createKey(
            $data['name'],
            (bool) ($data['is_public'] ?? false),
            $data['rate_limit_per_minute'] ?? null
        );

        return redirect()
            ->route('admin.api-keys.index')
            ->with('created_key', $plain)
            ->with('created_name', $data['name']);
    }

    public function revoke(ApiKey $apiKey, ApiKeyService $service): RedirectResponse
    {
        if ($apiKey->revoked_at) {
            return redirect()
                ->route('admin.api-keys.index')
                ->with('success', __('API key already revoked.'));
        }

        $apiKey->forceFill(['revoked_at' => now()])->save();
        $service->forgetPublicKeyCache();

        return redirect()
            ->route('admin.api-keys.index')
            ->with('success', __('API key revoked.'));
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('api_keys');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
