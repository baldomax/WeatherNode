<?php

namespace App\Services\Radar;

use App\Models\Setting;
use App\Services\OpenData\KnmiNowcastService;
use App\Services\OpenData\OpenDataProviderRegistry;
use Illuminate\Support\Facades\Cache;

class RadarFutureFramesService
{
    public const PROVIDER_AUTO = 'auto';
    public const PROVIDER_NONE = 'none';

    /** Cache TTL for future frames (any provider). 10 minutes. */
    private const FUTURE_FRAMES_CACHE_TTL_MINUTES = 10;

    /**
     * Providers that have a concrete implementation today.
     *
     * Add new providers here and implement a corresponding load*Frames() method.
     */
    private const IMPLEMENTED_PROVIDER_KEYS = ['knmi', 'noaa'];

    public function __construct(
        private readonly KnmiNowcastService $knmiNowcastService,
        private readonly NoaaFutureFramesService $noaaFutureFramesService
    ) {
    }

    /**
     * Return provider options for admin UI.
     */
    public function getProviderOptions(): array
    {
        $options = [
            [
                'key' => self::PROVIDER_AUTO,
                'label' => 'Auto',
                'description' => 'Use best available provider for station location',
                'implemented' => true,
            ],
            [
                'key' => self::PROVIDER_NONE,
                'label' => 'Disabled',
                'description' => 'Do not append future frames',
                'implemented' => true,
            ],
        ];

        foreach (OpenDataProviderRegistry::getAll() as $provider) {
            $features = array_map('strtolower', $provider->getFeatures());
            $supportsRadar = in_array('radar', $features, true) || in_array('radar_nowcast', $features, true);
            if (!$supportsRadar) {
                continue;
            }

            $providerKey = strtolower($provider->getSettingsKey());
            $options[] = [
                'key' => $providerKey,
                'label' => sprintf('%s (%s)', $provider->getName(), $provider->getCoverageArea()),
                'description' => $provider->isImplemented() ? 'Implemented' : 'Planned',
                'implemented' => $this->isProviderImplemented($providerKey),
            ];
        }

        return $options;
    }

    public function normalizeProviderKey(?string $providerKey): string
    {
        $normalized = strtolower(trim((string) $providerKey));
        if ($normalized === '' || $normalized === self::PROVIDER_AUTO) {
            return self::PROVIDER_AUTO;
        }

        if ($normalized === self::PROVIDER_NONE || $normalized === 'off' || $normalized === 'disabled') {
            return self::PROVIDER_NONE;
        }

        return $normalized;
    }

    /**
     * Resolve provider key using explicit config or auto logic.
     */
    public function resolveProviderKey(?string $providerKey = null): string
    {
        $normalized = $this->normalizeProviderKey($providerKey);
        if ($normalized !== self::PROVIDER_AUTO) {
            return $normalized;
        }

        // Auto strategy can be expanded with more region/provider checks.
        if ((bool) Setting::getValue('radar.nowcast_enabled', false) && Setting::isStationInNetherlands()) {
            return 'knmi';
        }

        if ((bool) Setting::getValue('opendata.noaa.enabled', false)
            && $this->noaaFutureFramesService->isStationInCoverage(Setting::latitude(), Setting::longitude())) {
            return 'noaa';
        }

        return self::PROVIDER_NONE;
    }

    /**
     * Get normalized future frames payload for the dashboard.
     */
    public function getFutureFrames(?string $providerKey = null): array
    {
        $resolvedProvider = $this->resolveProviderKey($providerKey);

        if ($resolvedProvider === self::PROVIDER_NONE) {
            return [
                'success' => true,
                'provider' => self::PROVIDER_NONE,
                'frames' => [],
                'message' => null,
            ];
        }

        if (!$this->isProviderImplemented($resolvedProvider)) {
            return [
                'success' => false,
                'provider' => $resolvedProvider,
                'frames' => [],
                'message' => 'Future frame provider is not implemented yet',
            ];
        }

        $cacheKey = $this->futureFramesCacheKey($resolvedProvider);
        return Cache::remember($cacheKey, now()->addMinutes(self::FUTURE_FRAMES_CACHE_TTL_MINUTES), function () use ($resolvedProvider) {
            return match ($resolvedProvider) {
                'knmi' => $this->loadKnmiFrames(),
                'noaa' => $this->loadNoaaFrames(),
                default => [
                    'success' => false,
                    'provider' => $resolvedProvider,
                    'frames' => [],
                    'message' => 'Unknown future frame provider',
                ],
            };
        });
    }

    /**
     * Cache key for future frames by provider (and location for providers that vary by station).
     */
    private function futureFramesCacheKey(string $resolvedProvider): string
    {
        if ($resolvedProvider === 'noaa') {
            $lat = round(Setting::latitude(), 1);
            $lon = round(Setting::longitude(), 1);
            return "radar_future_frames_noaa_{$lat}_{$lon}";
        }
        return "radar_future_frames_{$resolvedProvider}";
    }

    private function isProviderImplemented(string $providerKey): bool
    {
        return in_array($providerKey, self::IMPLEMENTED_PROVIDER_KEYS, true);
    }

    private function loadKnmiFrames(): array
    {
        if (!(bool) Setting::getValue('radar.nowcast_enabled', false)) {
            return [
                'success' => false,
                'provider' => 'knmi',
                'frames' => [],
                'message' => 'KNMI nowcast is not enabled',
            ];
        }

        $metadata = Cache::get('knmi_nowcast_metadata');
        if (!$metadata) {
            $metadata = $this->knmiNowcastService->getNowcastMetadata();
            if (!empty($metadata['times'])) {
                Cache::put('knmi_nowcast_metadata', $metadata, now()->addMinutes(30));
            }
        }

        if (!$metadata || empty($metadata['times']) || !is_array($metadata['times'])) {
            return [
                'success' => false,
                'provider' => 'knmi',
                'frames' => [],
                'message' => 'KNMI nowcast data not available',
            ];
        }

        $urls = is_array($metadata['urls'] ?? null) ? $metadata['urls'] : [];
        $bounds = [[50.75, 3.2], [53.7, 7.2]];

        $frames = [];
        foreach ($metadata['times'] as $time) {
            if (!is_string($time) || $time === '') {
                continue;
            }

            $url = $urls[$time] ?? null;
            if (!is_string($url) || $url === '') {
                continue;
            }

            $frames[] = [
                'provider' => 'knmi',
                'kind' => 'image_overlay',
                'time' => $time,
                'timestamp' => $this->parseTimestamp($time),
                'url' => $url,
                'bounds' => $bounds,
                'attribution' => 'KNMI',
                'opacity' => 0.7,
            ];
        }

        return [
            'success' => true,
            'provider' => 'knmi',
            'frames' => $frames,
            'message' => null,
        ];
    }

    private function loadNoaaFrames(): array
    {
        return $this->noaaFutureFramesService->getFutureFrames(
            Setting::latitude(),
            Setting::longitude()
        );
    }

    private function parseTimestamp(string $time): ?int
    {
        try {
            return \Carbon\Carbon::parse($time)->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }
}
