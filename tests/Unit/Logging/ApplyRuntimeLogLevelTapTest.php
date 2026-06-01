<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\ApplyRuntimeLogLevelTap;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use RuntimeException;
use Tests\TestCase;

class ApplyRuntimeLogLevelTapTest extends TestCase
{
    use RefreshDatabase;

    private ?string $originalLogLevel = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLogLevel = getenv('LOG_LEVEL') === false ? null : (string) getenv('LOG_LEVEL');
    }

    protected function tearDown(): void
    {
        if ($this->originalLogLevel === null) {
            putenv('LOG_LEVEL');
            unset($_ENV['LOG_LEVEL'], $_SERVER['LOG_LEVEL']);
        } else {
            putenv("LOG_LEVEL={$this->originalLogLevel}");
            $_ENV['LOG_LEVEL'] = $this->originalLogLevel;
            $_SERVER['LOG_LEVEL'] = $this->originalLogLevel;
        }

        parent::tearDown();
    }

    public function test_valid_setting_level_is_applied_to_handlers(): void
    {
        Setting::setValue('advanced.log_level', 'error', 'select', 'advanced');
        $this->setLogLevelEnv('debug');

        $handler = new StreamHandler('php://memory', Level::Debug);
        $logger = $this->makeLogger($handler);

        (new ApplyRuntimeLogLevelTap())($logger);

        $this->assertSame(Level::Error, $handler->getLevel());
    }

    public function test_invalid_setting_level_falls_back_to_info(): void
    {
        Setting::setValue('advanced.log_level', 'invalid_level', 'select', 'advanced');
        $this->setLogLevelEnv('debug');

        $handler = new StreamHandler('php://memory', Level::Debug);
        $logger = $this->makeLogger($handler);

        (new ApplyRuntimeLogLevelTap())($logger);

        $this->assertSame(Level::Info, $handler->getLevel());
    }

    public function test_setting_lookup_failure_falls_back_to_env_level(): void
    {
        $this->setLogLevelEnv('warning');

        $handler = new StreamHandler('php://memory', Level::Debug);
        $logger = $this->makeLogger($handler);

        $tap = new class extends ApplyRuntimeLogLevelTap
        {
            protected function fetchSettingLevel(): mixed
            {
                throw new RuntimeException('Simulated settings lookup failure');
            }
        };

        $tap($logger);

        $this->assertSame(Level::Warning, $handler->getLevel());
    }

    private function makeLogger(StreamHandler $handler): IlluminateLogger
    {
        $monolog = new MonologLogger('test');
        $monolog->pushHandler($handler);

        return new IlluminateLogger($monolog);
    }

    private function setLogLevelEnv(string $level): void
    {
        putenv("LOG_LEVEL={$level}");
        $_ENV['LOG_LEVEL'] = $level;
        $_SERVER['LOG_LEVEL'] = $level;
    }
}
