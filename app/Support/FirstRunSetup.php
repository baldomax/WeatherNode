<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * Where a new install is in the first-run setup, and whether it is in one at all.
 *
 * The whole thing hangs on one rule: a missing row means finished. Nothing has
 * to be written to the thousands of installs that are already running, and the
 * test suite, which migrates into an empty database and never seeds, is out of
 * it for the same reason. Only a fresh `db:seed` writes the row, and only when
 * it is genuinely the first one.
 *
 * Stored in settings rather than the cache for the reason given in
 * UpdateAvailability: the deployer runs `cache:clear`, and losing this halfway
 * through setup would drop somebody back to a half-configured site with no
 * sign that anything was left to do.
 */
class FirstRunSetup
{
    public const SETTING = 'setup.state';

    public const GROUP = 'setup';

    /** Waiting on step one: where the station is. */
    public const STATION = 'station';

    /** Waiting on step two: where the readings come from. */
    public const SOURCE = 'source';

    /** "I will do this later": no more redirects, but the notice stays. */
    public const SKIPPED = 'skipped';

    public const DONE = 'done';

    /**
     * Read from the row, never through Setting::getValue.
     *
     * getValue caches for an hour and clears that entry on write, which is
     * right for settings but wrong for this one. In Docker every
     * `docker exec php artisan ...` runs as root, and anything it reads leaves
     * a root-owned file in the cache directory. php-fpm serves as www-data, so
     * its Cache::forget on that key fails silently and it keeps reading the
     * old value. A stuck flag here is either a wizard that will not go away or
     * a notice that hides work still to do.
     *
     * The cost is one indexed lookup on a tiny table, twice per admin page,
     * against the dozens of settings reads that page already makes.
     */
    public static function state(): string
    {
        $state = trim((string) (Setting::query()->where('key', self::SETTING)->value('value') ?? ''));

        return in_array($state, [self::STATION, self::SOURCE, self::SKIPPED, self::DONE], true)
            ? $state
            : self::DONE;
    }

    /** Still owed a step, so the wizard may take over. */
    public static function pending(): bool
    {
        return in_array(self::state(), [self::STATION, self::SOURCE], true);
    }

    /** Not finished, whether or not it was put off. The notice follows this. */
    public static function unfinished(): bool
    {
        return self::state() !== self::DONE;
    }

    /**
     * Open the setup, once. firstOrCreate rather than setValue, so re-running
     * the seeder on a finished install cannot send it back to step one.
     */
    public static function begin(): void
    {
        Setting::firstOrCreate(
            ['key' => self::SETTING],
            ['value' => self::STATION, 'type' => 'string', 'group' => self::GROUP]
        );

        Setting::forgetCached(self::SETTING);
    }

    public static function moveTo(string $state): void
    {
        Setting::setValue(self::SETTING, $state, 'string', self::GROUP);
    }

    /** The step to send somebody to, or null when there is nothing owed. */
    public static function nextRoute(): ?string
    {
        return match (self::state()) {
            self::STATION => 'admin.setup.station',
            self::SOURCE => 'admin.setup.source',
            default => null,
        };
    }
}
