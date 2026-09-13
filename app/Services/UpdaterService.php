<?php
// path: app/Services/UpdaterService.php

namespace App\Services;

use App\Core\Database;
use ZipArchive;
use Exception;
use PDO;

class UpdaterService
{
    private string $currentVersion;
    private string $repo;

    public function __construct()
    {
        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $this->currentVersion = $appConfig['version'] ?? '1.0.0';
        $this->repo           = setting('github_repo', 'OskarCosimo/My-System-Status');
    }

    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    /**
     * Check GitHub Releases for new version tags.
     */
    public function checkForUpdates(): ?array
    {
        $url = "https://api.github.com/repos/{$this->repo}/releases/latest";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'MySystemStatus-Updater',
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $release = json_decode($response, true);
        if (empty($release['tag_name'])) {
            return null;
        }

        $latestVersion = ltrim($release['tag_name'], 'v');

        if (version_compare($latestVersion, $this->currentVersion, '>')) {
            return [
                'has_update'    => true,
                'version'       => $latestVersion,
                'zip_url'       => $release['zipball_url'],
                'release_notes' => $release['body'] ?? 'No release notes provided.',
                'published_at'  => $release['published_at'] ?? null
            ];
        }

        return [
            'has_update'    => false,
            'version'       => $latestVersion
        ];
    }

    /**
     * Download archive, extract, and execute database migrations.
     */
    public function applyUpdate(string $zipUrl): bool
    {
        $rootDir  = dirname(__DIR__, 2);
        $tempZip  = sys_get_temp_dir() . '/mysystemstatus_update.zip';

        // 1. Download Release ZIP
        $fp = fopen($tempZip, 'wb');
        $ch = curl_init($zipUrl);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'MySystemStatus-Updater',
            CURLOPT_TIMEOUT        => 120
        ]);
        $success = curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        if (!$success) {
            throw new Exception("Failed to download update package from GitHub.");
        }

        // 2. Extract Archive
        $zip = new ZipArchive();
        if ($zip->open($tempZip) !== true) {
            @unlink($tempZip);
            throw new Exception("Unable to extract update ZIP file.");
        }

        $tempExtractDir = sys_get_temp_dir() . '/mss_extract_' . time();
        $zip->extractTo($tempExtractDir);
        $zip->close();
        @unlink($tempZip);

        // GitHub zip packages contain a root folder like 'owner-repo-tag'
        $extractedItems = scandir($tempExtractDir);
        $innerFolder = null;
        foreach ($extractedItems as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($tempExtractDir . '/' . $item)) {
                $innerFolder = $tempExtractDir . '/' . $item;
                break;
            }
        }

        $sourceDir = $innerFolder ?: $tempExtractDir;

        // 3. Copy files over root (protect custom configs and lock files)
        $this->copyRecursive($sourceDir, $rootDir);
        $this->deleteDirectory($tempExtractDir);

        // 4. Run database migrations via migrate.php
        $this->runMigrations();

        return true;
    }

    private function copyRecursive(string $source, string $dest): void
    {
        $dir = opendir($source);
        @mkdir($dest, 0755, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..' || $file === '.git') {
                continue;
            }

            // Never overwrite active database configuration or installer lock file
            if ($file === 'database.php' && basename($dest) === 'config') {
                continue;
            }
            if ($file === 'installed.lock' && basename($dest) === 'install') {
                continue;
            }

            $srcPath = $source . '/' . $file;
            $dstPath = $dest . '/' . $file;

            if (is_dir($srcPath)) {
                $this->copyRecursive($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    /**
     * Run unified incremental database migrations with forced OPcache invalidation.
     */
    private function runMigrations(): void
    {
        $migrateScript = dirname(__DIR__, 2) . '/migrate.php';
        
        if (file_exists($migrateScript)) {
            // 1. Force OPcache to flush so PHP does not execute stale in-memory bytecode
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($migrateScript, true);
            }
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            // 2. Execute the fresh migration script from disk
            try {
                require $migrateScript;
            } catch (\Throwable $e) {
                error_log("Updater Migration Execution Error: " . $e->getMessage());
            }
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deleteDirectory("$dir/$file") : @unlink("$dir/$file");
        }
        @rmdir($dir);
    }
}
