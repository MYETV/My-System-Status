<?php
// path: app/Services/SettingService.php

namespace App\Services;

use App\Core\Database;
use PDO;

class SettingService
{
    private static ?PDO $db = null;
    private static array $cache = [];

    private static function init(): void
    {
        if (self::$db === null) {
            self::$db = Database::getInstance();
            $stmt = self::$db->query("SELECT `key`, `value` FROM settings");
            self::$cache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        }
    }

    /**
     * Get a setting by key with an optional default value.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        self::init();
        return self::$cache[$key] ?? $default;
    }

    /**
     * Set or update a setting.
     */
    public static function set(string $key, ?string $value): void
    {
        self::init();
        $stmt = self::$db->prepare("
            INSERT INTO settings (`key`, `value`) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ");
        $stmt->execute([$key, $value]);
        self::$cache[$key] = $value;
    }

    /**
     * Dynamically determine the application base URL without hardcoding.
     */
    public static function getAppUrl(): string
    {
        $savedUrl = self::get('app_url');
        if (!empty($savedUrl)) {
            return rtrim($savedUrl, '/');
        }

        // Auto-detect protocol and host if not configured in database
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                   ($_SERVER['SERVER_PORT'] ?? '') == 443 || 
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return rtrim($protocol . $host, '/');
    }

    /**
     * Get array of enabled languages for header switcher dropdowns.
     */
    public static function getEnabledLocales(): array
    {
        $saved = self::get('enabled_locales', 'en,it');
        $activeCodes = array_map('trim', explode(',', strtolower($saved)));

        // English is the master language and is always enabled
        if (!in_array('en', $activeCodes, true)) {
            array_unshift($activeCodes, 'en');
        }

        $allSupported = [
            'en' => ['name' => 'English',    'flag' => 'EN'],
            'it' => ['name' => 'Italiano',   'flag' => 'IT'],
            'es' => ['name' => 'Español',    'flag' => 'ES'],
            'fr' => ['name' => 'Français',   'flag' => 'FR'],
            'de' => ['name' => 'Deutsch',    'flag' => 'DE'],
            'pt' => ['name' => 'Português',  'flag' => 'PT']
        ];

        $result = [];
        foreach ($activeCodes as $code) {
            if (isset($allSupported[$code])) {
                $result[$code] = $allSupported[$code];
            }
        }

        return $result;
    }
}
