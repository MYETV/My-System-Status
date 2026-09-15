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
     * Complete catalog of 20 major world languages supported by LibreTranslate.
     */
    public static function getAllLanguages(): array
    {
        return [
            // European
            'en' => ['name' => 'English',            'flag' => 'EN'],
            'it' => ['name' => 'Italiano',           'flag' => 'IT'],
            'es' => ['name' => 'Español',            'flag' => 'ES'],
            'fr' => ['name' => 'Français',           'flag' => 'FR'],
            'de' => ['name' => 'Deutsch',            'flag' => 'DE'],
            'pt' => ['name' => 'Português',          'flag' => 'PT'],
            'nl' => ['name' => 'Nederlands',         'flag' => 'NL'],
            'pl' => ['name' => 'Polski',             'flag' => 'PL'],
            'sv' => ['name' => 'Svenska',            'flag' => 'SE'],
            'el' => ['name' => 'Ελληνικά',           'flag' => 'GR'],
            'ru' => ['name' => 'Русский',            'flag' => 'RU'],
            'uk' => ['name' => 'Українська',         'flag' => 'UA'],

            // Asian & Middle Eastern
            'tr' => ['name' => 'Türkçe',             'flag' => 'TR'],
            'ar' => ['name' => 'العربية',            'flag' => 'AR'],
            'hi' => ['name' => 'हिन्दी',              'flag' => 'IN'],
            'zh' => ['name' => '简体中文',           'flag' => 'CN'],
            'ja' => ['name' => '日本語',             'flag' => 'JP'],
            'ko' => ['name' => '한국어',             'flag' => 'KR'],
            'vi' => ['name' => 'Tiếng Việt',         'flag' => 'VN'],
            'id' => ['name' => 'Bahasa Indonesia',   'flag' => 'ID']
        ];
    }

    /**
     * Get array of active/enabled languages chosen by administrator for header dropdowns.
     */
    public static function getEnabledLocales(): array
    {
        $saved = self::get('enabled_locales', 'en,it');
        $activeCodes = array_map('trim', explode(',', strtolower($saved)));

        // English is the master language and is always enabled
        if (!in_array('en', $activeCodes, true)) {
            array_unshift($activeCodes, 'en');
        }

        $allSupported = self::getAllLanguages();

        $result = [];
        foreach ($activeCodes as $code) {
            if (isset($allSupported[$code])) {
                $result[$code] = $allSupported[$code];
            }
        }

        return $result;
    }
}
