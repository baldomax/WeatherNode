<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CreateDeploymentPackage extends Command
{
    protected $signature = 'deploy:list-files {--package : Create a deployment package file}';
    protected $description = 'List files that need to be deployed to production';

    public function handle()
    {
        $files = $this->getFilesToDeploy();
        
        $this->info('Files to deploy to production:');
        $this->newLine();
        
        foreach ($files as $category => $fileList) {
            $this->line("<fg=cyan>{$category}:</>");
            foreach ($fileList as $file) {
                $exists = File::exists(base_path($file)) ? '✓' : '✗';
                $this->line("  {$exists} {$file}");
            }
            $this->newLine();
        }
        
        $this->info('Database migration to run:');
        $this->line('  php artisan migrate');
        $this->newLine();
        
        $this->info('After deployment, run:');
        $this->line('  php artisan cache:clear');
        $this->line('  php artisan config:clear');
        $this->line('  php artisan view:clear');
        $this->line('  php artisan route:clear');
        $this->newLine();
        
        if ($this->option('package')) {
            $this->createPackageFile($files);
        }
        
        return 0;
    }
    
    private function getFilesToDeploy(): array
    {
        return [
            'New Services' => [
                'app/Services/Update/HealthCheckService.php',
                'app/Services/Update/BackupService.php',
                'app/Services/Update/PreUpdateValidator.php',
                'app/Services/Update/ReleaseNotesParser.php',
                'app/Services/Update/CompatibilityChecker.php',
                'app/Services/Update/GithubReleaseService.php',
                'app/Services/Update/DeployerService.php',
            ],
            'New Models' => [
                'app/Models/UpdateLog.php',
            ],
            'New Commands' => [
                'app/Console/Commands/CheckForUpdates.php',
                'app/Console/Commands/CleanExpiredCache.php',
                'app/Console/Commands/VersionCommand.php',
            ],
            'New Notifications' => [
                'app/Notifications/UpdateAvailableNotification.php',
            ],
            'New Migrations' => [
                'database/migrations/2026_01_22_223213_create_update_logs_table.php',
            ],
            'New Config' => [
                'config/updater.php',
            ],
            'Modified Controllers' => [
                'app/Http/Controllers/Admin/UpdateController.php',
                'app/Http/Controllers/Admin/SettingsController.php',
            ],
            'Modified Views' => [
                'resources/views/admin/settings/updates.blade.php',
                'resources/views/admin/help.blade.php',
                'resources/views/layouts/admin.blade.php',
            ],
            'Modified Routes' => [
                'routes/web.php',
                'routes/console.php',
            ],
            'Documentation' => [
                'README.md',
                'ADMIN_GUIDE.md',
                'DEPLOY_TO_PRODUCTION.md',
            ],
        ];
    }
    
    private function createPackageFile(array $files): void
    {
        $packageFile = storage_path('app/deployment_files.txt');
        $content = "# Files to deploy to production\n\n";
        
        foreach ($files as $category => $fileList) {
            $content .= "## {$category}\n\n";
            foreach ($fileList as $file) {
                if (File::exists(base_path($file))) {
                    $content .= "{$file}\n";
                }
            }
            $content .= "\n";
        }
        
        $content .= "## Commands to run on production\n\n";
        $content .= "php artisan migrate\n";
        $content .= "php artisan cache:clear\n";
        $content .= "php artisan config:clear\n";
        $content .= "php artisan view:clear\n";
        $content .= "php artisan route:clear\n";
        
        File::put($packageFile, $content);
        
        $this->info("Package file created: {$packageFile}");
    }
}
