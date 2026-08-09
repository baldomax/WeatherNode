<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use App\Services\VersionService;

/**
 * The newest release seen by the scheduled `updater:check`, so the admin area
 * can show an update banner without calling GitHub.
 *
 * Reading is deliberately network-free. GithubReleaseService caches for five
 * minutes with a six second timeout, which is fine for the Updates page an
 * admin chose to open, but on every admin page load it would mean a blocking
 * call whenever the cache expires. So the scheduled command records what it
 * found and the banner only reads that.
 *
 * Stored in settings rather than the cache because the deployer runs
 * `cache:clear`, and an install that has just updated is exactly when the
 * recorded version matters least but a stale banner would be most confusing.
 * Comparison happens at read time, so the banner clears itself the moment the
 * running version catches up, without waiting for the next check.
 */
class UpdateAvailability
{
    public const SETTING_ENABLED = 'updater.check_enabled';
    public const SETTING_LATEST = 'updater.latest_version_seen';
    public const SETTING_CHECKED_AT = 'updater.last_checked_at';

    public static function enabled(): bool
    {
        return (bool) Setting::getValue(self::SETTING_ENABLED, true);
    }

    public static function remember(string $tag): void
    {
        Setting::setValue(self::SETTING_LATEST, trim($tag), 'string', 'updater');
        Setting::setValue(self::SETTING_CHECKED_AT, now()->toIso8601String(), 'string', 'updater');
    }

    public static function latestSeen(): ?string
    {
        $tag = trim((string) Setting::getValue(self::SETTING_LATEST, ''));

        return $tag === '' ? null : $tag;
    }

    public static function checkedAt(): ?string
    {
        $at = trim((string) Setting::getValue(self::SETTING_CHECKED_AT, ''));

        return $at === '' ? null : $at;
    }

    /**
     * The release to advertise, or null when there is nothing to say.
     */
    public static function pendingVersion(): ?string
    {
        if (!self::enabled()) {
            return null;
        }

        $latest = self::latestSeen();
        if ($latest === null) {
            return null;
        }

        $current = VersionService::getAppVersion();
        if (!is_string($current) || trim($current) === '') {
            return null;
        }

        return Versioning::compare($current, $latest) < 0 ? $latest : null;
    }
}
