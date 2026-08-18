<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The surface pressure charts offered on /pressure-map.
 *
 * Held server side rather than in the page so the proxy can be keyed by name.
 * A caller picks a chart, never a URL, so this cannot be turned into an open
 * image proxy.
 */
class PressureMapRegistry
{
    /** @return array<string, array{url: string, label: string}> */
    public static function all(): array
    {
        return [
            'atlantic' => [
                'url' => 'https://ocean.weather.gov/A_sfc_full_ocean_color.png',
                'label' => 'Atlantic Ocean',
            ],
            'pacific' => [
                'url' => 'https://ocean.weather.gov/P_sfc_full_ocean_color.png',
                'label' => 'Pacific Ocean',
            ],
            'us' => [
                'url' => 'https://www.wpc.ncep.noaa.gov/sfc/ussatsfc.gif',
                'label' => 'United States',
            ],
            'europe' => [
                'url' => 'https://www.dwd.de/DWD/wetter/wv_spez/hobbymet/wetterkarten/bwk_bodendruck_na_ana.png',
                'label' => 'Europe',
            ],
        ];
    }

    public static function exists(string $map): bool
    {
        return array_key_exists($map, self::all());
    }

    public static function urlFor(string $map): ?string
    {
        return self::all()[$map]['url'] ?? null;
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::all());
    }
}
