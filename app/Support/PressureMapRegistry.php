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
                'url' => 'https://ocean.weather.gov/shtml/A_00hrsfc.gif',
                'label' => 'Atlantic Ocean',
            ],
            'pacific' => [
                'url' => 'https://ocean.weather.gov/shtml/P_00hrsfc.gif',
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
            // Regional cuts of NOAA's Unified Surface Analysis. Same product as
            // the ocean charts above, so they refresh on the same schedule.
            'canada' => [
                'url' => 'https://ocean.weather.gov/UA/Canada.gif',
                'label' => 'Canada',
            ],
            'alaska' => [
                'url' => 'https://ocean.weather.gov/UA/Alaska.gif',
                'label' => 'Alaska',
            ],
            'mexico' => [
                'url' => 'https://ocean.weather.gov/UA/Mexico.gif',
                'label' => 'Mexico',
            ],
            'hawaii' => [
                'url' => 'https://ocean.weather.gov/UA/Hawaii.gif',
                'label' => 'Hawaii',
            ],
            'us_east' => [
                'url' => 'https://ocean.weather.gov/UA/East_coast.gif',
                'label' => 'US East Coast',
            ],
            'us_west' => [
                'url' => 'https://ocean.weather.gov/UA/West_coast.gif',
                'label' => 'US West Coast',
            ],
            'atlantic_tropics' => [
                'url' => 'https://ocean.weather.gov/UA/Atl_Tropics.gif',
                'label' => 'Atlantic Tropics',
            ],
            'pacific_tropics' => [
                'url' => 'https://ocean.weather.gov/UA/Pac_Tropics.gif',
                'label' => 'Pacific Tropics',
            ],
            'northern_hemisphere' => [
                'url' => 'https://ocean.weather.gov/UA/entire_UA.gif',
                'label' => 'Northern Hemisphere',
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

    /** @return array<string, string> map name to its untranslated label */
    public static function labels(): array
    {
        return array_map(static fn (array $chart): string => $chart['label'], self::all());
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::all());
    }
}
