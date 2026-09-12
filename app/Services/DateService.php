<?php
// path: app/Services/DateService.php

namespace App\Services;

use DateTime;
use DateTimeZone;
use Exception;

class DateService
{
    /**
     * Determine the active timezone based on User preference, then Admin setting, fallback to UTC.
     */
    public static function getActiveTimezone(): string
    {
        // 1. Check user preference (Cookie or Session)
        if (!empty($_COOKIE['user_timezone']) && self::isValidTimezone($_COOKIE['user_timezone'])) {
            return $_COOKIE['user_timezone'];
        }

        if (!empty($_SESSION['user_timezone']) && self::isValidTimezone($_SESSION['user_timezone'])) {
            return $_SESSION['user_timezone'];
        }

        // 2. Fallback to Admin platform default setting
        $adminTimezone = SettingService::get('app_timezone', 'UTC');
        if (self::isValidTimezone($adminTimezone)) {
            return $adminTimezone;
        }

        // 3. Fallback to UTC
        return 'UTC';
    }

    /**
     * Convert and format a UTC date string into the user/system active timezone.
     */
    public static function format(?string $utcDateString, string $format = 'M d, Y H:i'): string
    {
        if (empty($utcDateString)) {
            return 'N/A';
        }

        try {
            $date = new DateTime($utcDateString, new DateTimeZone('UTC'));
            $targetTimezone = new DateTimeZone(self::getActiveTimezone());
            $date->setTimezone($targetTimezone);

            return $date->format($format);
        } catch (Exception $e) {
            return $utcDateString;
        }
    }

    /**
     * Get list of all standard PHP timezone identifiers grouped by region.
     */
    public static function getTimezonesList(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    public static function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }
}