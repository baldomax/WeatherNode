<?php

namespace App\Services\GeoIp;

use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\Log;

class GeoIpService
{
    private static ?Reader $reader = null;

    public function lookupCountryCode(?string $ip): ?string
    {
        if (!$ip || !config('visitorlog.geoip.enabled')) {
            return null;
        }

        $databasePath = config('visitorlog.geoip.database_path');
        if (!$databasePath || !is_file($databasePath)) {
            return null;
        }

        if (!class_exists(Reader::class)) {
            return null;
        }

        try {
            if (!self::$reader) {
                self::$reader = new Reader($databasePath);
            }

            $record = self::$reader->country($ip);
            return $record->country->isoCode ?: null;
        } catch (\Exception $exception) {
            Log::warning('GeoIP lookup failed', [
                'error' => $exception->getMessage(),
            ]);
        }

        return null;
    }
}
