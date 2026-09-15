<?php
// path: app/Controllers/Admin/TranslationController.php

namespace App\Controllers\Admin;

use App\Services\TranslationService;
use App\Services\SettingService;

class TranslationController
{
    public function __construct()
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
    }

    /**
     * Translate a single language or batch translate all enabled languages.
     */
    public function sync(): void
    {
        $targetLang = trim($_POST['target_lang'] ?? '');
        $endpoint   = setting('libretranslate_endpoint');
        $apiKey     = setting('libretranslate_api_key');

        $referer = $_SERVER['HTTP_REFERER'] ?? '/admin/settings';
        $cleanReferer = strtok($referer, '?');

        if (empty($endpoint)) {
            header('Location: ' . $cleanReferer . '?error=' . urlencode('LibreTranslate endpoint is not configured in Settings.'));
            exit;
        }

        try {
            $service = new TranslationService($endpoint, $apiKey);

            // =================================================================
            // 1. BATCH SYNC: Translate ALL Enabled Languages at once
            // =================================================================
            if ($targetLang === 'all') {
                $enabledLocales = array_keys(SettingService::getEnabledLocales());
                // Filter out English source language
                $localesToSync = array_values(array_filter($enabledLocales, fn($code) => $code !== 'en'));

                if (empty($localesToSync)) {
                    header('Location: ' . $cleanReferer . '?error=' . urlencode('No secondary languages are currently enabled in Settings.'));
                    exit;
                }

                $totalTranslated = 0;
                $syncedLanguages = [];

                foreach ($localesToSync as $code) {
                    $res = $service->syncAndTranslate($code);
                    $totalTranslated += (int)($res['translated_keys'] ?? 0);
                    $syncedLanguages[] = strtoupper($code);
                }

                $msg = sprintf(
                    "Successfully synchronized all enabled languages (%s)! Total keys translated: %d.",
                    implode(', ', $syncedLanguages),
                    $totalTranslated
                );

                header('Location: ' . $cleanReferer . '?translated=1&msg=' . urlencode($msg));
                exit;
            }

            // =================================================================
            // 2. SINGLE LANGUAGE SYNC
            // =================================================================
            if (empty($targetLang) || !preg_match('/^[a-z]{2}$/', $targetLang)) {
                header('Location: ' . $cleanReferer . '?error=' . urlencode('Invalid language code.'));
                exit;
            }

            $result = $service->syncAndTranslate($targetLang);
            $msg = sprintf("Successfully translated %d missing keys into %s!", $result['translated_keys'], $result['file']);
            header('Location: ' . $cleanReferer . '?translated=1&msg=' . urlencode($msg));
            exit;

        } catch (\Throwable $e) {
            header('Location: ' . $cleanReferer . '?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}
