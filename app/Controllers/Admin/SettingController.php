<?php
// path: app/Controllers/Admin/SettingController.php

namespace App\Controllers\Admin;

use App\Core\View;
use App\Services\SettingService;
use App\Plugins\ExternalStatusPlugin;

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
            'rate_limit_enabled',
            'cf_tunnel_is_primary',
            'edge_worker_enabled',
            'ads_enabled',
            'ads_footer_enabled'
        ];

        foreach ($checkboxKeys as $cbKey) {
            SettingService::set($cbKey, isset($_POST[$cbKey]) ? '1' : '0');
        }

        // 2. Enabled locales checkbox list
        if (isset($_POST['enabled_locales']) && is_array($_POST['enabled_locales'])) {
            $locales = array_unique(array_merge(['en'], array_map('trim', $_POST['enabled_locales'])));
            SettingService::set('enabled_locales', implode(',', $locales));
        }

        // 3. Standard text / select / url inputs
        $textKeys = [
            'app_name', 'app_url', 'app_timezone',
            'terms_url', 'privacy_policy_url',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption', 'smtp_from',
            'oauth_myetv_client_id', 'oauth_myetv_client_secret',
            'oauth_google_client_id', 'oauth_google_client_secret',
            'oauth_microsoft_client_id', 'oauth_microsoft_client_secret',
            'oauth_facebook_client_id', 'oauth_facebook_client_secret',
            'ai_provider', 'ai_api_key', 'ai_endpoint', 'ai_model',
            // OpenAI-compatible Chat Completions settings
            'ai_openai_endpoint', 'ai_openai_api_key',
            'libretranslate_endpoint', 'libretranslate_api_key',
            'turnstile_site_key', 'turnstile_secret_key',
            'rate_limit_max_attempts', 'rate_limit_lockout_minutes',
            'discord_webhook_url',
            // Cloudflare Zero Trust Tunnel
            'cf_tunnel_name', 'cf_tunnel_account_id', 'cf_tunnel_id', 'cf_tunnel_api_token',
            // Cloudflare Edge Worker Probes
            'edge_worker_url', 'edge_worker_token',
            // Google AdSense Publisher ID
            'adsense_client_id'
        ];

        foreach ($textKeys as $key) {
            if (isset($_POST[$key])) {
                SettingService::set($key, trim($_POST[$key]));
            }
        }

        // 4. Decode Base64 ad scripts to completely bypass ModSecurity / WAF XSS inspection
        if (isset($_POST['ads_header_code_b64'])) {
            $decodedHeader = base64_decode($_POST['ads_header_code_b64'], true);
            if ($decodedHeader !== false) {
                SettingService::set('ads_header_code', $decodedHeader);
            }
        } elseif (isset($_POST['ads_header_code'])) {
            SettingService::set('ads_header_code', $_POST['ads_header_code']);
        }

        if (isset($_POST['ads_footer_code_b64'])) {
            $decodedFooter = base64_decode($_POST['ads_footer_code_b64'], true);
            if ($decodedFooter !== false) {
                SettingService::set('ads_footer_code', $decodedFooter);
            }
        } elseif (isset($_POST['ads_footer_code'])) {
            SettingService::set('ads_footer_code', $_POST['ads_footer_code']);
        }

        // 5. Immediately poll Cloudflare Tunnel with forceInsert = true so new tunnels are created in database
        try {
            $plugin = new ExternalStatusPlugin();
            $plugin->syncAll(true);
        } catch (\Throwable $e) {
            // Ignore if credentials not yet configured or connection fails
        }

        header('Location: /admin/settings?saved=1');
        exit;
    }
}
