<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Update\CompatibilityChecker;
use App\Services\Update\GithubReleaseService;
use App\Services\Update\DeployerService;
use App\Services\Update\PreUpdateValidator;
use App\Services\Update\ReleaseNotesParser;
use App\Services\Update\BackupService;
use App\Models\UpdateLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class UpdateController extends Controller
{
    private CompatibilityChecker $compatibilityChecker;
    private GithubReleaseService $githubService;
    private DeployerService $deployer;
    private PreUpdateValidator $validator;
    private BackupService $backupService;

    public function __construct(
        CompatibilityChecker $compatibilityChecker,
        GithubReleaseService $githubService,
        DeployerService $deployer,
        PreUpdateValidator $validator,
        BackupService $backupService
    ) {
        $this->compatibilityChecker = $compatibilityChecker;
        $this->githubService = $githubService;
        $this->deployer = $deployer;
        $this->validator = $validator;
        $this->backupService = $backupService;
    }

    /**
     * Show the updates page
     */
    public function index()
    {
        $compatibility = $this->compatibilityChecker->checkCompatibility();
        $latestRelease = $this->githubService->getLatestRelease();
        $isUpdateAvailable = $this->githubService->isUpdateAvailable();
        $currentVersion = \App\Services\VersionService::getAppVersion();
        $releases = $this->deployer->getReleases();
        $currentRelease = $this->deployer->getCurrentRelease();
        $backups = $this->backupService->getBackups();

        // Parse release notes
        $notesParser = app(ReleaseNotesParser::class);
        $releaseNotes = null;
        if ($latestRelease && isset($latestRelease['body'])) {
            $releaseNotes = $notesParser->parse($latestRelease['body']);
        }
        
        // Get update history
        $updateHistory = UpdateLog::with(['deployedByUser', 'rollbackByUser'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('admin.settings.updates', compact(
            'compatibility',
            'latestRelease',
            'isUpdateAvailable',
            'currentVersion',
            'releases',
            'currentRelease',
            'backups',
            'releaseNotes',
            'updateHistory'
        ));
    }

    /**
     * Check for updates (AJAX)
     */
    public function check()
    {
        $latestRelease = $this->githubService->getLatestRelease();
        $isUpdateAvailable = $this->githubService->isUpdateAvailable();
        $currentVersion = \App\Services\VersionService::getAppVersion();

        return response()->json([
            'current_version' => $currentVersion,
            'latest_version' => $latestRelease['tag'] ?? null,
            'update_available' => $isUpdateAvailable,
            'latest_release' => $latestRelease,
        ]);
    }

    /**
     * Download and deploy an update
     */
    public function deploy(Request $request)
    {
        if (!config('updater.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Updater is disabled. Set UPDATER_ENABLED=true in .env',
            ], 403);
        }

        $version = $this->validatedVersion($request);

        // Check compatibility
        $compatibility = $this->compatibilityChecker->checkCompatibility();
        if (!$compatibility['supported']) {
            return response()->json([
                'success' => false,
                'message' => 'Browser update is not supported on this server',
                'compatibility' => $compatibility,
            ], 400);
        }

        try {
            // Get release info
            $releases = $this->githubService->getReleases(20);
            $targetRelease = collect($releases)->firstWhere('tag', $version);

            if (!$targetRelease) {
                return response()->json([
                    'success' => false,
                    'message' => "Release {$version} not found",
                ], 404);
            }

            // Find deploy ZIP asset
            $deployAsset = collect($targetRelease['assets'] ?? [])
                ->firstWhere('is_deploy_zip', true);

            if (!$deployAsset || !isset($deployAsset['url'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Deploy ZIP not found for release {$version}",
                ], 404);
            }

            $expectedSha = $this->resolveExpectedZipChecksum($targetRelease, $deployAsset);
            if (config('updater.require_checksum', true) && $expectedSha === null) {
                return response()->json([
                    'success' => false,
                    'message' => "Trusted SHA256 checksum is missing for release {$version}. Refusing deployment.",
                ], 400);
            }

            // Pre-update validation
            $validationResults = null;
            if (config('updater.validate_before_deploy', true)) {
                $validation = $this->validator->validate($version, $targetRelease);
                $validationResults = $validation;
                
                if (!$validation['passed']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pre-update validation failed',
                        'validation' => $validation,
                    ], 400);
                }

                // Log warnings but don't block
                if (!empty($validation['warnings'])) {
                    Log::warning('Pre-update validation warnings', [
                        'version' => $version,
                        'warnings' => $validation['warnings'],
                    ]);
                }
            }

            // Download ZIP to temporary location
            $tempZip = storage_path('app/updater_' . $version . '_' . time() . '.zip');
            $downloaded = $this->githubService->downloadAsset($deployAsset['url'], $tempZip);

            if (!$downloaded) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to download release ZIP',
                ], 500);
            }

            // Verify SHA256 checksum using trusted release metadata (never from inside the ZIP).
            $actualSha = hash_file('sha256', $tempZip);

            if ($expectedSha !== null && !hash_equals($expectedSha, strtolower($actualSha))) {
                @unlink($tempZip);
                return response()->json([
                    'success' => false,
                    'message' => 'ZIP checksum verification failed. File may be corrupted or tampered with.',
                ], 400);
            }

            // Deploy (pass user ID and validation results for audit log)
            $result = $this->deployer->deploy($tempZip, $version, auth()->id(), $validationResults);

            // Clean up temp file
            @unlink($tempZip);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Update deployment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Deployment failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update via Git (if enabled)
     */
    public function updateViaGit(Request $request)
    {
        if (!config('updater.enabled') || !config('updater.allow_git')) {
            return response()->json([
                'success' => false,
                'message' => 'Git updates are disabled',
            ], 403);
        }

        $version = $this->validatedVersion($request);

        $result = $this->deployer->updateViaGit($version);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }
    }

    /**
     * Preview an update (dry-run mode)
     */
    public function preview(Request $request)
    {
        if (!config('updater.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Updater is disabled',
            ], 403);
        }

        $version = $this->validatedVersion($request);

        try {
            // Get release info
            $releases = $this->githubService->getReleases(20);
            $targetRelease = collect($releases)->firstWhere('tag', $version);

            if (!$targetRelease) {
                return response()->json([
                    'success' => false,
                    'message' => "Release {$version} not found",
                ], 404);
            }

            // Run pre-update validation
            $validation = null;
            if (config('updater.validate_before_deploy', true)) {
                $validation = $this->validator->validate($version, $targetRelease);
            }

            // Check compatibility
            $compatibility = $this->compatibilityChecker->checkCompatibility();

            return response()->json([
                'success' => true,
                'message' => 'Preview completed',
                'version' => $version,
                'validation' => $validation,
                'compatibility' => $compatibility,
                'release' => $targetRelease,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Preview failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rollback to a previous release
     */
    public function rollback(Request $request)
    {
        if (!config('updater.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Updater is disabled',
            ], 403);
        }

        $version = $this->validatedVersion($request);

        $result = $this->deployer->rollback($version, auth()->id());

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }
    }

    /**
     * Delete an old release directory to reclaim disk space.
     */
    public function deleteRelease(Request $request)
    {
        if (!config('updater.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Updater is disabled',
            ], 403);
        }

        $version = $this->validatedVersion($request);

        $result = $this->deployer->deleteRelease($version);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Delete a single backup file to reclaim disk space.
     */
    public function deleteBackup(Request $request)
    {
        if (!config('updater.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Updater is disabled',
            ], 403);
        }

        $data = $request->validate([
            // Backup filenames are produced by BackupService: a bare filename,
            // no path separators. Reject anything else outright.
            'filename' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $result = $this->backupService->deleteBackup($data['filename']);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 400);
    }

    private function validatedVersion(Request $request): string
    {
        $data = $request->validate([
            'version' => [
                'required',
                'string',
                'max:120',
                // Defensive validation for git refs/tags: avoid shell metacharacters and invalid ref patterns.
                'regex:/^(?!-)(?!.*\.\.)(?!.*@\{)(?!.*\\\\)(?!.*\.lock$)(?!.*\/$)[A-Za-z0-9._\/-]+$/',
            ],
        ]);

        return trim($data['version']);
    }

    private function resolveExpectedZipChecksum(array $release, array $deployAsset): ?string
    {
        $assetChecksum = $this->normalizeSha256($deployAsset['sha256'] ?? null);
        if ($assetChecksum !== null) {
            return $assetChecksum;
        }

        return $this->normalizeSha256($release['sha256'] ?? null);
    }

    private function normalizeSha256(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));
        if ($value === '' || preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
