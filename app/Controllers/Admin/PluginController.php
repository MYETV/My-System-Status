<?php
// path: app/Controllers/Admin/PluginController.php

namespace App\Controllers\Admin;

use App\Core\View;
use App\Plugins\ExternalStatusPlugin;
use App\Services\SettingService;

class PluginController
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
        $discordWebhook = SettingService::get('discord_webhook_url');
        $aiProvider     = SettingService::get('ai_provider', 'gemini');
        $aiModel        = SettingService::get('ai_model', 'gemini-1.5-flash');
        $translateEp    = SettingService::get('libretranslate_endpoint', 'https://libretranslate.com');

        View::render('admin/plugins/index', [
            'discordConfigured' => !empty($discordWebhook),
            'aiProvider'        => $aiProvider,
            'aiModel'           => $aiModel,
            'translateEndpoint' => $translateEp
        ]);
    }

    /**
     * Trigger 1-click external status synchronization for Cloudflare, GitHub, Stripe
     */
    public function syncFeeds(): void
    {
        $importer = new ExternalStatusPlugin();
        $results = $importer->syncAll();

        $summary = sprintf(
            "Cloudflare: %s, GitHub: %s, Stripe: %s",
            strtoupper($results['cloudflare']),
            strtoupper($results['github']),
            strtoupper($results['stripe'])
        );

        header('Location: /admin/plugins?synced=1&summary=' . urlencode($summary));
        exit;
    }
}