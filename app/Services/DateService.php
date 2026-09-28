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
     * Convert a local datetime input (e.g. from datetime-local input) from a specific timezone into UTC string for database storage.
     */
    public static function toUtc(?string $localDateString, ?string $fromTimezone = null): ?string
    {
        if (empty($localDateString)) {
            return null;
        }

        try {
            $tzString = (!empty($fromTimezone) && self::isValidTimezone($fromTimezone))
                ? $fromTimezone
                : self::getActiveTimezone();

            $tz = new DateTimeZone($tzString);
            $date = new DateTime($localDateString, $tz);
            $date->setTimezone(new DateTimeZone('UTC'));

            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Convert a UTC datetime string into a specified timezone formatted for datetime-local or custom view.
     */
    public static function toLocal(?string $utcDateString, ?string $toTimezone = null, string $format = 'Y-m-d\TH:i'): ?string
    {
        if (empty($utcDateString)) {
            return null;
        }

        try {
            $tzString = (!empty($toTimezone) && self::isValidTimezone($toTimezone))
                ? $toTimezone
                : self::getActiveTimezone();

            $tz = new DateTimeZone($tzString);
            $date = new DateTime($utcDateString, new DateTimeZone('UTC'));
            $date->setTimezone($tz);

            return $date->format($format);
        } catch (Exception $e) {
            return null;
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
