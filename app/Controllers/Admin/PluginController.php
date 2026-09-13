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
        if (empty($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
    }

    public function index(): void
    {
        View::render('admin/plugins/index', [
            'discordConfigured' => !empty(setting('discord_webhook_url')),
            'aiProvider'        => setting('ai_provider', 'gemini'),
            'aiModel'           => setting('ai_model', 'gemini-1.5-flash'),
            'translateEndpoint' => setting('libretranslate_endpoint')
        ]);
    }

    public function saveFeedsConfig(): void
    {
        $feeds = [
            'feed_cloudflare_enabled', 'feed_aws_enabled', 'feed_azure_enabled',
            'feed_stripe_enabled', 'feed_github_enabled', 'feed_paypal_enabled'
        ];
        foreach ($feeds as $feed) {
            SettingService::set($feed, isset($_POST[$feed]) ? '1' : '0');
        }

        // Run sync with forceInsert = true to initialize enabled feeds from admin panel
        $importer = new ExternalStatusPlugin();
        $importer->syncAll(true);

        header('Location: /admin/plugins?saved=1');
        exit;
    }

    public function syncFeeds(): void
    {
        $importer = new ExternalStatusPlugin();
        $results  = $importer->syncAll();

        $parts = [];
        foreach ($results as $service => $st) {
            $parts[] = ucfirst($service) . ': ' . strtoupper($st);
        }

        header('Location: /admin/plugins?synced=1&summary=' . urlencode(implode(', ', $parts)));
        exit;
    }
}
