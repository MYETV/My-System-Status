<?php
// path: app/Controllers/Admin/SettingController.php

namespace App\Controllers\Admin;

use App\Core\View;
use App\Services\SettingService;

class SettingController
{
    public function __construct()
    {
        // Authentication guard
        if (empty($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
    }

    public function index(): void
    {
        View::render('admin/settings/index');
    }

    public function update(): void
    {
        // 1. Checkbox toggle fields (must be saved as '0' if unchecked)
        $checkboxKeys = [
            'turnstile_enabled',
            'rate_limit_enabled'
        ];

        foreach ($checkboxKeys as $cbKey) {
            SettingService::set($cbKey, isset($_POST[$cbKey]) ? '1' : '0');
        }

        // 2. Standard text / select inputs
        $textKeys = [
            'app_name', 'app_url', 'app_timezone',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption', 'smtp_from',
            'oauth_myetv_client_id', 'oauth_myetv_client_secret',
            'oauth_google_client_id', 'oauth_google_client_secret',
            'oauth_microsoft_client_id', 'oauth_microsoft_client_secret',
            'oauth_facebook_client_id', 'oauth_facebook_client_secret',
            'ai_provider', 'ai_api_key', 'ai_endpoint', 'ai_model',
            'libretranslate_endpoint', 'libretranslate_api_key',
            'turnstile_site_key', 'turnstile_secret_key',
            'rate_limit_max_attempts', 'rate_limit_lockout_minutes',
            'discord_webhook_url','cf_tunnel_name', 'cf_tunnel_account_id', 'cf_tunnel_id', 'cf_tunnel_api_token',
        ];

        foreach ($textKeys as $key) {
            if (isset($_POST[$key])) {
                SettingService::set($key, trim($_POST[$key]));
            }
        }

        header('Location: /admin/settings?saved=1');
        exit;
    }
}
