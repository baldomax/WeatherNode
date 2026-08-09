<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * Registry for the Quick Stats bar at the top of the dashboard.
 *
 * Mirrors the dashboard widget system: which tiles are on lives in
 * `widgets.stats_enabled` (toggled in admin), the order they appear in lives in
 * `widgets.layout.stat_order` (dragged on the dashboard itself in edit mode).
 *
 * Tiles are not widgets — they have no card, no body and no per-tile settings —
 * so they get their own registry rather than being squeezed into the widget one.
 */
class StatTileRegistry
{
    public const SETTING_ENABLED = 'widgets.stats_enabled';

    /**
     * Tile id => label, description and optional MenuFeatureMap requirement.
     *
     * The array order is the factory order of the bar: it is the fallback
     * whenever `widgets.layout.stat_order` is missing or incomplete.
     *
     * @var array<string, array{label: string, description: string, feature?: string}>
     */
    private const TILES = [
        'today' => [
            'label' => 'Today',
            'description' => 'Highest and lowest temperature so far today',
        ],
        'max_wind' => [
            'label' => 'Max wind',
            'description' => 'Strongest wind gust measured today',
        ],
        'precipitation' => [
            'label' => 'Precipitation',
            'description' => 'Rainfall total for today',
        ],
        'aqi' => [
            'label' => 'AQI',
            'description' => 'Air quality index from the configured air-quality source',
            'feature' => MenuFeatureMap::FEATURE_AIR_POLLEN,
        ],
        'uv' => [
            'label' => 'UV Index',
            'description' => 'Current UV radiation level',
        ],
        'earthquakes' => [
            'label' => 'Earthquakes',
            'description' => 'Number of recent nearby earthquakes',
            'feature' => MenuFeatureMap::FEATURE_EARTHQUAKES,
        ],
        'next_rain' => [
            'label' => 'Next Rain',
            'description' => 'When rain is next expected',
        ],
        'advisory' => [
            'label' => 'Advisory',
            'description' => 'Plain-language summary of the current temperature',
        ],
        'vs_yesterday' => [
            'label' => 'vs. Yesterday',
            'description' => 'Temperature difference against the same time yesterday',
        ],
        'best_time' => [
            'label' => 'Best time',
            'description' => 'Best hours to be outdoors today',
        ],
    ];

    /**
     * @return array<string, array{label: string, description: string, feature?: string}>
     */
    public static function all(): array
    {
        return self::TILES;
    }

    /**
     * @return array<int, string>
     */
    public static function ids(): array
    {
        return array_keys(self::TILES);
    }

    public static function has(string $tileId): bool
    {
        return array_key_exists($tileId, self::TILES);
    }

    public static function featureFor(string $tileId): ?string
    {
        return self::TILES[$tileId]['feature'] ?? null;
    }

    /**
     * A tile is available when the navigation feature it depends on is on.
     * Tiles without a requirement are always available.
     */
    public static function isAvailable(string $tileId): bool
    {
        $feature = self::featureFor($tileId);

        return $feature === null || MenuFeatureMap::enabled($feature);
    }

    /**
     * Ids the operator has switched on, in registry order.
     *
     * Unknown ids are dropped so a stale setting cannot resurrect a removed tile.
     * Availability is deliberately not applied here — the admin UI needs to show
     * the stored state even for tiles whose navigation feature is currently off.
     *
     * @return array<int, string>
     */
    public static function enabledIds(): array
    {
        $stored = Setting::getValue(self::SETTING_ENABLED, null);

        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($stored)) {
            return self::ids();
        }

        return self::sanitizeEnabled($stored);
    }

    /**
     * Reduce a submitted set of ids to known ones in registry order.
     *
     * Order carries no meaning for the enabled set — that is what
     * `widgets.layout.stat_order` is for — so it is normalised to keep the
     * stored value stable and readable.
     *
     * @param  array<int|string, mixed>  $ids
     * @return array<int, string>
     */
    public static function sanitizeEnabled(array $ids): array
    {
        $clean = self::sanitizeOrder($ids);

        return array_values(array_filter(self::ids(), static fn (string $id): bool => in_array($id, $clean, true)));
    }

    /**
     * Ids to emit into the dashboard, in the operator's saved order.
     *
     * Everything available is emitted, enabled or not — disabled tiles are
     * rendered hidden so the dashboard can show and hide them live when the
     * settings change under a long-running page. Tiles whose navigation feature
     * is off are left out entirely.
     *
     * @return array<int, string>
     */
    public static function renderableIds(): array
    {
        $available = array_values(array_filter(self::ids(), static fn (string $id): bool => self::isAvailable($id)));

        return self::applyOrder($available, self::storedOrder());
    }

    /**
     * Reduce an arbitrary list of ids to known ones, without duplicates.
     *
     * @param  array<int|string, mixed>  $ids
     * @return array<int, string>
     */
    public static function sanitizeOrder(array $ids): array
    {
        $clean = [];

        foreach ($ids as $id) {
            if (!is_string($id) && !is_int($id)) {
                continue;
            }

            $id = (string) $id;

            if (self::has($id) && !in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }

        return $clean;
    }

    /**
     * The saved drag order from `widgets.layout.stat_order`.
     *
     * @return array<int, string>
     */
    public static function storedOrder(): array
    {
        $layout = Setting::getValue('widgets.layout', []);

        if (is_string($layout)) {
            $decoded = json_decode($layout, true);
            $layout = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($layout) || !is_array($layout['stat_order'] ?? null)) {
            return [];
        }

        return self::sanitizeOrder($layout['stat_order']);
    }

    /**
     * Sort $ids by $order, appending anything $order does not mention.
     *
     * Keeps newly added registry tiles visible for operators whose saved order
     * predates them, instead of silently dropping them off the bar.
     *
     * @param  array<int, string>  $ids
     * @param  array<int, string>  $order
     * @return array<int, string>
     */
    private static function applyOrder(array $ids, array $order): array
    {
        $sorted = [];

        foreach ($order as $id) {
            if (in_array($id, $ids, true)) {
                $sorted[] = $id;
            }
        }

        foreach ($ids as $id) {
            if (!in_array($id, $sorted, true)) {
                $sorted[] = $id;
            }
        }

        return $sorted;
    }
}
