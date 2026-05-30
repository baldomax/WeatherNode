<?php

namespace App\Logging;

use App\Models\Setting;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Level;
use Throwable;

class ApplyRuntimeLogLevelTap
{
    private const DEFAULT_LEVEL = 'info';

    /**
     * @var array<string, bool>
     */
    private const ALLOWED_LEVELS = [
        'debug' => true,
        'info' => true,
        'notice' => true,
        'warning' => true,
        'error' => true,
        'critical' => true,
        'alert' => true,
        'emergency' => true,
    ];

    public function __invoke(IlluminateLogger $logger): void
    {
        $level = $this->resolveLevel();
        $monologLevel = Level::fromName($level);

        foreach ($logger->getLogger()->getHandlers() as $handler) {
            if (method_exists($handler, 'setLevel')) {
                $handler->setLevel($monologLevel);
            }
        }
    }

    private function resolveLevel(): string
    {
        $envFallback = $this->normalizeLevel(env('LOG_LEVEL', self::DEFAULT_LEVEL)) ?? self::DEFAULT_LEVEL;

        try {
            $settingLevel = $this->normalizeLevel($this->fetchSettingLevel());
        } catch (Throwable $e) {
            return $envFallback;
        }

        return $settingLevel ?? self::DEFAULT_LEVEL;
    }

    protected function fetchSettingLevel(): mixed
    {
        return Setting::getValue('advanced.log_level', null);
    }

    private function normalizeLevel(mixed $level): ?string
    {
        if (!is_string($level)) {
            return null;
        }

        $normalized = strtolower(trim($level));

        return isset(self::ALLOWED_LEVELS[$normalized]) ? $normalized : null;
    }
}
