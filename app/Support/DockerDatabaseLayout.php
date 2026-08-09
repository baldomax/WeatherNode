<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Detects a containerised install still keeping its SQLite database inside the
 * application directory.
 *
 * That was the layout before the data volume moved to /var/lib/weathernode.
 * Mounting a volume over /var/www/html/database also covers
 * database/migrations, and Docker only seeds a named volume from the image the
 * first time it is created, so the directory froze at whatever shipped that
 * day. The entrypoint now migrates from a copy the volume cannot cover, so
 * nothing is broken by staying put, but the layout is still worth correcting
 * and an operator has no other way of finding out.
 *
 * Only containers are flagged. A shared-hosting or zip install keeping its
 * database at database/database.sqlite is perfectly normal.
 */
class DockerDatabaseLayout
{
    public const RECOMMENDED_PATH = '/var/lib/weathernode/database.sqlite';

    public static function isLegacy(): bool
    {
        if (config('database.default') !== 'sqlite') {
            return false;
        }

        if (!self::inContainer()) {
            return false;
        }

        $path = (string) config('database.connections.sqlite.database');
        if ($path === '') {
            return false;
        }

        return str_starts_with($path, rtrim(base_path('database'), '/') . '/');
    }

    public static function currentPath(): string
    {
        return (string) config('database.connections.sqlite.database');
    }

    private static function inContainer(): bool
    {
        // Docker writes /.dockerenv, Podman writes /run/.containerenv. Checked
        // rather than read from env so a cached config cannot mask it.
        return file_exists('/.dockerenv') || file_exists('/run/.containerenv');
    }
}
