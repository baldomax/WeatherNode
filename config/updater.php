<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Updater Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the in-app updater system. This allows admins to
    | update the application from within the admin panel when the server
    | supports it.
    |
    */

    'enabled' => env('UPDATER_ENABLED', false),
    
    'allow_git' => env('UPDATER_ALLOW_GIT', false),
    
    'installer_mode' => env('INSTALLER_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Deployment Paths
    |--------------------------------------------------------------------------
    |
    | These paths define where releases are stored and how the current
    | version is symlinked. For atomic deployments with rollback support.
    |
    */

    'deploy_root' => env('UPDATER_DEPLOY_ROOT', base_path()),
    
    'releases_path' => env('UPDATER_RELEASES_PATH', 'releases'),
    
    'shared_path' => env('UPDATER_SHARED_PATH', 'shared'),
    
    'current_symlink' => env('UPDATER_CURRENT_SYMLINK', 'current'),

    /*
    |--------------------------------------------------------------------------
    | Release Retention
    |--------------------------------------------------------------------------
    |
    | How many previous releases to keep for rollback purposes.
    |
    */

    'keep_releases' => env('UPDATER_KEEP_RELEASES', 5),

    /*
    |--------------------------------------------------------------------------
    | GitHub Configuration
    |--------------------------------------------------------------------------
    |
    | GitHub repository information for fetching releases.
    |
    */

    'github_repo' => env('UPDATER_GITHUB_REPO', 'centauri/WeatherNode'),
    
    'github_token' => env('UPDATER_GITHUB_TOKEN', null),

    /*
    |--------------------------------------------------------------------------
    | Release Asset Name
    |--------------------------------------------------------------------------
    |
    | The name of the release asset ZIP file to download.
    |
    */

    'release_asset_name' => env('UPDATER_RELEASE_ASSET', 'weathernode-deploy.zip'),

    // Require checksum from trusted release metadata before allowing deployment.
    'require_checksum' => env('UPDATER_REQUIRE_CHECKSUM', true),

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Health check settings for post-deployment verification.
    |
    */

    'health_check_enabled' => env('UPDATER_HEALTH_CHECK', true),
    
    'health_check_timeout' => env('UPDATER_HEALTH_CHECK_TIMEOUT', 30),
    
    'health_check_endpoints' => env('UPDATER_HEALTH_CHECK_ENDPOINTS', '/,api/weather/dashboard') 
        ? explode(',', env('UPDATER_HEALTH_CHECK_ENDPOINTS', '/,api/weather/dashboard'))
        : ['/'],

    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    |
    | Automatic backup settings before updates.
    |
    */

    'backup_enabled' => env('UPDATER_BACKUP_ENABLED', true),
    
    'backup_keep_count' => env('UPDATER_BACKUP_KEEP', 5),

    /*
    |--------------------------------------------------------------------------
    | Pre-Update Validation
    |--------------------------------------------------------------------------
    |
    | Validate system requirements before deployment.
    |
    */

    'validate_before_deploy' => env('UPDATER_VALIDATE', true),

    /*
    |--------------------------------------------------------------------------
    | Update Notifications
    |--------------------------------------------------------------------------
    |
    | Email and in-app notifications when updates are available.
    |
    */

    'notify_email' => env('UPDATER_NOTIFY_EMAIL', false),
    
    'notify_check_schedule' => env('UPDATER_NOTIFY_SCHEDULE', 'daily'),
];
