<?php

namespace App\Services\OpenData;

interface ProviderInterface
{
    /**
     * Get the display name of the provider
     */
    public function getName(): string;

    /**
     * Get the country/region code (ISO 3166-1 alpha-2 or region identifier)
     */
    public function getCountry(): string;

    /**
     * Get a description of the provider and its data
     */
    public function getDescription(): string;

    /**
     * Get available features (e.g., ['wms', 'nowcast', 'solar'])
     */
    public function getFeatures(): array;

    /**
     * Check if the provider is enabled in settings
     */
    public function isEnabled(): bool;

    /**
     * Get the settings key prefix (e.g., 'knmi')
     */
    public function getSettingsKey(): string;

    /**
     * Whether the provider is fully implemented (false for placeholders)
     */
    public function isImplemented(): bool;

    /**
     * Get status text ('available', 'coming_soon', 'planned')
     */
    public function getStatus(): string;

    /**
     * Get link to provider's API documentation (optional)
     */
    public function getApiUrl(): ?string;

    /**
     * Get geographic coverage area description
     */
    public function getCoverageArea(): string;
}
