<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The radar sources an admin can put on the radar page.
 *
 * Which sources appear is a choice, not something derived from the main
 * provider: a Dutch station commonly runs RainViewer as its provider and still
 * wants the KNMI and Buienradar cards beside it, while a station elsewhere
 * wants neither. Adding a source means adding an entry here and the markup
 * that renders it.
 */
class RadarSourceRegistry
{
    /** @return array<string, array{label: string, coverage: string}> */
    public static function all(): array
    {
        return [
            'rainviewer' => ['label' => 'RainViewer', 'coverage' => 'Worldwide'],
            'knmi' => ['label' => 'KNMI', 'coverage' => 'Netherlands'],
            'buienradar' => ['label' => 'Buienradar', 'coverage' => 'Netherlands'],
        ];
    }

    public static function exists(string $id): bool
    {
        return array_key_exists($id, self::all());
    }

    /**
     * Ids from the stored comma separated value, unknown ones dropped.
     *
     * @return list<string>
     */
    public static function parse(?string $value): array
    {
        $ids = array_filter(array_map('trim', explode(',', (string) $value)));

        return array_values(array_filter($ids, fn (string $id) => self::exists($id)));
    }

    /**
     * Sources to render, in registry order so the tabs keep a stable position.
     * The main provider is always present, whatever else was chosen.
     *
     * @return list<string>
     */
    public static function visible(?string $selected, string $provider): array
    {
        $chosen = self::parse($selected);

        if (self::exists($provider)) {
            $chosen[] = $provider;
        }

        return array_values(array_filter(
            array_keys(self::all()),
            fn (string $id) => in_array($id, $chosen, true)
        ));
    }
}
