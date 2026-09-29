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
        // Available Cloudflare sub-services definition
        $cfAvailableSubservices = [
            'workers'           => 'Workers & Pages Platform',
            'authoritative-dns' => 'Authoritative DNS Service',
            '1111-dns'          => 'Recursive DNS (1.1.1.1)',
            'cdn-cache'         => 'Edge CDN & Cache Network',
            'dashboard-api'     => 'Dashboard & Control Panel API',
            'zero-trust'        => 'Zero Trust, Access & Gateway',
            'turnstile'         => 'Turnstile Captcha Engine',
            'stream'            => 'Cloudflare Stream Video',
            'warp'              => 'WARP Client & Network'
        ];

        // Retrieve enabled Cloudflare sub-services from settings (defaults to all)
        $defaultSlugs = 'workers,authoritative-dns,1111-dns,cdn-cache,dashboard-api,zero-trust,turnstile,stream,warp';
        $enabledRaw = setting('cf_subservices', $defaultSlugs);
        $cfEnabledSubservices = array_filter(array_map('trim', explode(',', $enabledRaw)));

        View::render('admin/plugins/index', [
            'discordConfigured'      => !empty(setting('discord_webhook_url')),
            'aiProvider'             => setting('ai_provider', 'gemini'),
            'aiModel'                => setting('ai_model', 'gemini-1.5-flash'),
            'translateEndpoint'      => setting('libretranslate_endpoint'),
            'cfAvailableSubservices' => $cfAvailableSubservices,
            'cfEnabledSubservices'   => $cfEnabledSubservices
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

        // Save selected Cloudflare sub-services
        $cfSubservices = $_POST['cf_subservices'] ?? [];
        if (is_array($cfSubservices)) {
            SettingService::set('cf_subservices', implode(',', $cfSubservices));
        } else {
            SettingService::set('cf_subservices', '');
        }

        // Run sync with forceInsert = true to synchronize and clean up sub-services
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
