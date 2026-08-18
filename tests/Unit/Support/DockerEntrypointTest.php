<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Tests\TestCase;

class DockerEntrypointTest extends TestCase
{
    private function entrypoint(): string
    {
        return file_get_contents(base_path('docker/entrypoint.sh'));
    }

    /**
     * The entrypoint used to end with "exec supervisord", so any command passed
     * to docker run was silently discarded and the container booted normally
     * instead. That makes one-off commands impossible and hides mistakes.
     */
    public function test_the_entrypoint_runs_a_command_passed_to_the_container(): void
    {
        $this->assertMatchesRegularExpression(
            '/exec\s+"\$@"/',
            $this->entrypoint(),
            'docker/entrypoint.sh must exec its arguments so `docker run <image> php artisan ...` works.'
        );
    }

    public function test_the_image_still_defaults_to_supervisord(): void
    {
        $this->assertMatchesRegularExpression(
            '/^CMD\s+\[.*supervisord/m',
            file_get_contents(base_path('Dockerfile')),
            'With the entrypoint exec-ing "$@", the default process has to come from CMD.'
        );
    }
}
