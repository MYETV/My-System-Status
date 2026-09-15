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
     * Translate or synchronize missing keys for an existing language.
     */
    public function sync(): void
    {
        $targetLang = trim($_POST['target_lang'] ?? '');
        $endpoint   = setting('libretranslate_endpoint');
        $apiKey     = setting('libretranslate_api_key');

        if (empty($endpoint)) {
            header('Location: /admin/plugins?error=' . urlencode('LibreTranslate endpoint is not configured in Settings.'));
            exit;
        }

        if (empty($targetLang) || !preg_match('/^[a-z]{2}$/', $targetLang)) {
            header('Location: /admin/plugins?error=invalid_language_code');
            exit;
        }

        try {
            $service = new TranslationService($endpoint, $apiKey);
            $result  = $service->syncAndTranslate($targetLang);

            $msg = sprintf("Successfully translated %d missing keys into %s!", $result['translated_keys'], $result['file']);
            header('Location: /admin/plugins?translated=1&msg=' . urlencode($msg));
            exit;
        } catch (\Throwable $e) {
            header('Location: /admin/plugins?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}
