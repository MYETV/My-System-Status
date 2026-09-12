<?php
// path: core/I18n.php

namespace App\Core;

class I18n
{
    private static string $locale = 'en';
    private static array $translations = [];

    public static function init(?string $locale = null): void
    {
        if ($locale) {
            self::$locale = $locale;
        } elseif (isset($_SESSION['locale'])) {
            self::$locale = $_SESSION['locale'];
        } elseif (isset($_COOKIE['locale'])) {
            self::$locale = $_COOKIE['locale'];
        }

        $file = dirname(__DIR__) . "/languages/" . self::$locale . ".json";
        if (file_exists($file)) {
            self::$translations = json_decode(file_get_contents($file), true) ?? [];
        }
    }

    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
        $_SESSION['locale'] = $locale;
        setcookie('locale', $locale, time() + (86400 * 30), '/');
        self::init($locale);
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function trans(string $key, array $replace = []): string
    {
        $keys = explode('.', $key);
        $value = self::$translations;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $key;
            }
            $value = $value[$k];
        }

        if (!is_string($value)) {
            return $key;
        }

        foreach ($replace as $placeholder => $replacement) {
            $value = str_replace(':' . $placeholder, $replacement, $value);
        }

        return $value;
    }
}