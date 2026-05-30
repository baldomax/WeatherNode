<?php

namespace App\Console\Commands;

use App\Services\Security\ApiKeyService;
use Illuminate\Console\Command;

class CreateApiKey extends Command
{
    protected $signature = 'api:key:create
                            {name : Display name for the API key}
                            {--public : Store this key for browser use}
                            {--rate= : Requests per minute limit}';

    protected $description = 'Create a new API key';

    public function handle(ApiKeyService $service): int
    {
        $name = $this->argument('name');
        $isPublic = (bool) $this->option('public');
        $rateLimit = $this->option('rate') !== null ? (int) $this->option('rate') : null;

        try {
            $plain = $service->createKey($name, $isPublic, $rateLimit);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $this->info('API key created.');
        $this->line("Name: {$name}");
        $this->line('Key: ' . $plain);
        if ($rateLimit !== null) {
            $this->line("Rate limit: {$rateLimit} requests/min");
        }
        if ($isPublic) {
            $this->warn('Public keys are exposed to the browser. Use for site-only calls.');
        }

        return 0;
    }
}
