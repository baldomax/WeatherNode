<?php

namespace App\Services\Alerts;

interface AlertServiceInterface
{
    /**
     * Fetch weather alerts from the service
     */
    public function fetchAlerts(): ?array;

    /**
     * Get active alerts (filtered by severity)
     */
    public function getActiveAlerts(): array;

    /**
     * Get the highest severity alert
     */
    public function getHighestSeverityAlert(): ?array;

    /**
     * Check if there are any active warnings
     */
    public function hasActiveWarnings(): bool;
}
