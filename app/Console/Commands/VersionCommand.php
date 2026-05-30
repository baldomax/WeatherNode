<?php

namespace App\Console\Commands;

use App\Support\Versioning;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class VersionCommand extends Command
{
    private const BUMP_TYPES = ['year', 'month', 'patch', 'major', 'minor'];

    protected $signature = 'version 
                            {action : Action to perform (show|set|bump|release)}
                            {value? : Version value (for set) or type (year|month|patch for bump)}';

    protected $description = 'Manage application version';

    private string $versionFile;

    public function __construct()
    {
        parent::__construct();
        $this->versionFile = base_path('VERSION');
    }

    public function handle()
    {
        $action = $this->argument('action');

        return match ($action) {
            'show' => $this->showVersion(),
            'set' => $this->setVersion(),
            'bump' => $this->bumpVersion(),
            'release' => $this->releaseVersion(),
            default => $this->unknownAction($action),
        };
    }

    private function showVersion(): int
    {
        $version = $this->getCurrentVersion();
        $this->info("Current version: {$version}");
        return 0;
    }

    private function setVersion(): int
    {
        $value = $this->argument('value');
        
        if (!$value) {
            $this->error('Please provide a version value. Example: php artisan version set v2026.03.0');
            return 1;
        }

        // Validate format
        if (!$this->isValidVersion($value)) {
            $this->error('Invalid version format. Use: vYYYY.MM.patch or vYYYY.MM.patch-dev');
            return 1;
        }

        // Ensure 'v' prefix
        if (!str_starts_with($value, 'v')) {
            $value = 'v' . $value;
        }

        $this->setVersionFile($value);
        $this->info("Version set to: {$value}");
        return 0;
    }

    private function bumpVersion(): int
    {
        $type = $this->argument('value') ?? 'patch';
        
        if (!in_array($type, self::BUMP_TYPES, true)) {
            $this->error('Bump type must be: year, month, or patch');
            return 1;
        }

        $current = $this->getCurrentVersion();
        if (!$this->isValidVersion($current)) {
            $this->error("Current VERSION file is invalid: {$current}");
            return 1;
        }
        $newVersion = $this->bumpVersionNumber($current, $type);
        
        $this->setVersionFile($newVersion);
        $this->info("Version bumped from {$current} to {$newVersion}");
        return 0;
    }

    private function releaseVersion(): int
    {
        $current = $this->getCurrentVersion();
        if (!$this->isValidVersion($current)) {
            $this->error("Current VERSION file is invalid: {$current}");
            return 1;
        }
        
        // Check if already a release version (no -dev suffix)
        if (!str_ends_with($current, '-dev')) {
            if (!$this->confirm("Version {$current} is already a release version. Continue anyway?")) {
                return 0;
            }
            $newVersion = $current;
        } else {
            // Remove -dev suffix
            $newVersion = str_replace('-dev', '', $current);
            $this->setVersionFile($newVersion);
            $this->info("Version changed from {$current} to {$newVersion} (ready for release)");
        }

        $this->newLine();
        $this->info('Next steps:');
        $this->line('1. Review your changes: git status');
        $this->line('2. Commit VERSION file: git add VERSION && git commit -m "Release ' . $newVersion . '"');
        $this->line('3. Create tag: git tag ' . $newVersion);
        $this->line('4. Push: git push origin main --tags');
        $this->line('5. Create GitHub release with tag: ' . $newVersion);
        
        return 0;
    }

    private function getCurrentVersion(): string
    {
        if (File::exists($this->versionFile)) {
            $version = trim(File::get($this->versionFile));
            if (!empty($version)) {
                return $version;
            }
        }
        return Versioning::defaultDevVersion();
    }

    private function setVersionFile(string $version): void
    {
        File::put($this->versionFile, $version . "\n");
    }

    private function bumpVersionNumber(string $current, string $type): string
    {
        $newVersion = Versioning::bumpYearBased($current, $type);

        if ($newVersion === null) {
            throw new \RuntimeException("Cannot bump invalid year-based version: {$current}");
        }

        return $newVersion;
    }

    private function isValidVersion(string $version): bool
    {
        return Versioning::isValidYearBased($version);
    }

    private function unknownAction(string $action): int
    {
        $this->error("Unknown action: {$action}. Use: show, set, bump, or release");
        return 1;
    }
}
