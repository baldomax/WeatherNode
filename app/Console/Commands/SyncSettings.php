<?php

namespace App\Console\Commands;

use Database\Seeders\SettingsSeeder;
use Illuminate\Console\Command;

class SyncSettings extends Command
{
    protected $signature = 'settings:sync';
    protected $description = 'Sync settings table with SettingsSeeder definitions';

    public function handle(): int
    {
        $this->info('Syncing settings from SettingsSeeder...');

        try {
            (new SettingsSeeder())->run();
        } catch (\Exception $e) {
            $this->error('Failed to sync settings: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info('Settings synced.');

        return Command::SUCCESS;
    }
}
