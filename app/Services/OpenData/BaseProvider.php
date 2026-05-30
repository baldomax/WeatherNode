<?php

namespace App\Services\OpenData;

use App\Models\Setting;

abstract class BaseProvider implements ProviderInterface
{
    /**
     * Check if the provider is enabled in settings
     */
    public function isEnabled(): bool
    {
        return (bool) Setting::getValue("opendata.{$this->getSettingsKey()}.enabled", false);
    }

    /**
     * Get status text based on implementation status
     */
    public function getStatus(): string
    {
        if ($this->isImplemented()) {
            return 'available';
        }
        return 'coming_soon';
    }

    /**
     * Default implementation - can be overridden
     */
    public function getApiUrl(): ?string
    {
        return null;
    }
}
