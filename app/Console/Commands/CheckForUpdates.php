<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Update\GithubReleaseService;
use App\Services\VersionService;
use App\Support\Versioning;
use App\Models\User;
use App\Notifications\UpdateAvailableNotification;
use Illuminate\Support\Facades\Notification;

class CheckForUpdates extends Command
{
    protected $signature = 'updater:check {--notify}';
    protected $description = 'Check for available updates and optionally notify admins';

    public function handle(GithubReleaseService $githubService)
    {
        $this->info('Checking for updates...');

        $latestRelease = $githubService->getLatestRelease();
        if (!$latestRelease) {
            $this->warn('Could not fetch latest release from GitHub');
            return 1;
        }

        $currentVersion = VersionService::getAppVersion();
        $latestVersion = $latestRelease['tag'] ?? '';

        if (!is_string($latestVersion) || trim($latestVersion) === '') {
            $this->warn('Latest GitHub release has no tag_name');
            return 1;
        }

        $this->info("Current version: {$currentVersion}");
        $this->info("Latest version: {$latestVersion}");

        $comparison = Versioning::compare($currentVersion, $latestVersion);

        if ($comparison >= 0) {
            $this->info('You are running the latest version.');
            return 0;
        }

        $this->info("Update available: {$latestVersion}");

        // Send notifications if requested
        if ($this->option('notify') && config('updater.notify_email', false)) {
            $admins = User::where('is_admin', true)->get();
            
            if ($admins->isEmpty()) {
                $this->warn('No admin users found to notify');
                return 0;
            }

            Notification::send($admins, new UpdateAvailableNotification($latestRelease));
            $this->info("Notifications sent to {$admins->count()} admin(s)");
        }

        return 0;
    }
}
