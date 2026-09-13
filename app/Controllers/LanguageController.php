<?php
// path: app/Controllers/LanguageController.php

namespace App\Controllers;

use App\Core\I18n;

class LanguageController
{
    public function switch(): void
    {
        $lang = trim($_GET['lang'] ?? 'en');

        // Validate language code format (e.g., 'en', 'it', 'es')
        if (preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $lang)) {
            I18n::setLocale($lang);
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        header("Location: {$referer}");
        exit;
    }
}