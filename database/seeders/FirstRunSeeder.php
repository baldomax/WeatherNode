<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\FirstRunSetup;
use Illuminate\Database\Seeder;

/**
 * Opens the first-run setup, and only on a genuine first run.
 *
 * A seeder rather than a migration, deliberately. Tests use RefreshDatabase,
 * which migrates into an empty database before every test and never seeds, so
 * a migration that wrote this flag would put the entire suite into the wizard
 * and redirect every admin test.
 *
 * Runs before SettingsSeeder, and the test for "first run" is the absence of
 * station.name. An empty settings table would be wrong: a dozen migrations
 * create settings rows of their own, so the table already has content by the
 * time any seeder runs. station.name comes from SettingsSeeder and nowhere
 * else, which makes its absence the moment before the first seed.
 */
class FirstRunSeeder extends Seeder
{
    public function run(): void
    {
        if (Setting::query()->where('key', 'station.name')->exists()) {
            return;
        }

        FirstRunSetup::begin();
    }
}
