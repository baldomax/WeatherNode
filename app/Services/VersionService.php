<?php

namespace App\Services;

use App\Support\Versioning;

class VersionService
{
    /**
     * Get the application version from VERSION file or fallback
     */
    public static function getAppVersion(): string
    {
        $versionFile = base_path('VERSION');
        
        if (file_exists($versionFile)) {
            $version = trim(file_get_contents($versionFile));
            if (!empty($version)) {
                return $version;
            }
        }
        
        // Fallback if VERSION file doesn't exist
        return Versioning::defaultDevVersion();
    }
    
    /**
     * Get release info from release.json if available
     */
    public static function getReleaseInfo(): ?array
    {
        $releaseFile = base_path('release.json');
        
        if (file_exists($releaseFile)) {
            $content = file_get_contents($releaseFile);
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
        
        return null;
    }
}
