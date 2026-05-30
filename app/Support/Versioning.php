<?php

namespace App\Support;

use DateTimeInterface;

final class Versioning
{
    private const YEAR_BASED_REGEX = '/^v?(?<year>\d{4})\.(?<month>0[1-9]|1[0-2])\.(?<patch>\d+)(?<suffix>-dev)?$/';

    /**
     * Compare version strings. Returns -1, 0, 1 (left < right, equal, left > right).
     * Supports both legacy semver-like tags and vYYYY.MM.patch(-dev).
     */
    public static function compare(string $left, string $right): int
    {
        $leftNormalized = self::normalizeForCompare($left);
        $rightNormalized = self::normalizeForCompare($right);

        if ($leftNormalized === '' && $rightNormalized === '') {
            return 0;
        }

        if ($leftNormalized === '') {
            return -1;
        }

        if ($rightNormalized === '') {
            return 1;
        }

        return version_compare($leftNormalized, $rightNormalized);
    }

    /**
     * Validate vYYYY.MM.patch format (optionally with -dev suffix).
     */
    public static function isValidYearBased(string $version, bool $allowDevSuffix = true): bool
    {
        $version = trim($version);
        if ($version === '') {
            return false;
        }

        if (preg_match(self::YEAR_BASED_REGEX, $version, $matches) !== 1) {
            return false;
        }

        if (!$allowDevSuffix && !empty($matches['suffix'])) {
            return false;
        }

        return true;
    }

    /**
     * Bump a year-based version using year/month/patch semantics.
     * Returns null when input is not a valid year-based version.
     */
    public static function bumpYearBased(string $version, string $bumpType): ?string
    {
        $version = trim($version);
        if (preg_match(self::YEAR_BASED_REGEX, $version, $matches) !== 1) {
            return null;
        }

        $normalizedType = match ($bumpType) {
            'major', 'year' => 'year',
            'minor', 'month' => 'month',
            'patch' => 'patch',
            default => null,
        };

        if ($normalizedType === null) {
            return null;
        }

        $year = (int) $matches['year'];
        $month = (int) $matches['month'];
        $patch = (int) $matches['patch'];
        $suffix = $matches['suffix'] ?? '';

        if ($normalizedType === 'year') {
            $year++;
            $month = 1;
            $patch = 0;
        } elseif ($normalizedType === 'month') {
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
            $patch = 0;
        } else {
            $patch++;
        }

        return sprintf('v%04d.%02d.%d%s', $year, $month, $patch, $suffix);
    }

    /**
     * Default development version for the current month.
     */
    public static function defaultDevVersion(?DateTimeInterface $now = null): string
    {
        $now ??= now();

        return sprintf(
            'v%04d.%02d.0-dev',
            (int) $now->format('Y'),
            (int) $now->format('m')
        );
    }

    private static function normalizeForCompare(string $version): string
    {
        $version = trim($version);
        if ($version === '') {
            return '';
        }

        return ltrim($version, "vV");
    }
}

