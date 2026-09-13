<?php
// path: app/Plugins/ExternalStatusPlugin.php

namespace App\Plugins;

use App\Core\Database;
use PDO;

class ExternalStatusPlugin
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function syncAll(): array
    {
        $results = [];

        // 1. Cloudflare Public Services & Optional Zero Trust Tunnel
        if (setting('feed_cloudflare_enabled', '1') === '1') {
            $results['cloudflare'] = $this->syncCloudflare();
        } else {
            $this->disableFeedMonitors('https://www.cloudflarestatus.com');
        }

        // 2. Amazon AWS
        if (setting('feed_aws_enabled', '1') === '1') {
            $results['aws'] = $this->syncAws();
        } else {
            $this->disableFeedMonitors('https://health.aws.amazon.com');
        }

        // 3. Microsoft Azure
        if (setting('feed_azure_enabled', '1') === '1') {
            $results['azure'] = $this->syncAzure();
        } else {
            $this->disableFeedMonitors('https://azure.status.microsoft');
        }

        // 4. Stripe
        if (setting('feed_stripe_enabled', '1') === '1') {
            $results['stripe'] = $this->syncStripe();
        } else {
            $this->disableFeedMonitors('https://status.stripe.com');
        }

        // 5. PayPal
        if (setting('feed_paypal_enabled', '1') === '1') {
            $results['paypal'] = $this->syncPayPal();
        } else {
            $this->disableFeedMonitors('https://www.paypal-status.com');
        }

        // 6. GitHub
        if (setting('feed_github_enabled', '1') === '1') {
            $results['github'] = $this->syncGitHub();
        } else {
            $this->disableFeedMonitors('https://www.githubstatus.com');
        }

        return $results;
    }

    /**
     * Parse all core Cloudflare platforms dynamically and check custom Zero Trust Tunnel
     */
    public function syncCloudflare(): string
    {
        $opts = ['http' => ['timeout' => 10, 'user_agent' => 'MySystemStatus/1.0']];
        $json = @file_get_contents('https://www.cloudflarestatus.com/api/v2/summary.json', false, stream_context_create($opts));
        if (!$json) return 'unknown';

        $data = json_decode($json, true);
        $overallIndicator = $data['status']['indicator'] ?? 'none';

        $parentStatus = match ($overallIndicator) {
            'none'     => 'operational',
            'minor'    => 'degraded',
            default    => 'down'
        };

        // 1. Upsert Parent Monitor
        $parentId = $this->upsertMonitor('Cloudflare Global Network', 'https://www.cloudflarestatus.com', $parentStatus, null);

        // 2. Comprehensive keywords mapping matching actual Cloudflare Statuspage labels
        $coreKeywords = [
            'worker'    => ['name' => 'Workers & Pages Platform', 'slug' => 'workers'],
            'authoritative' => ['name' => 'Authoritative DNS Service', 'slug' => 'authoritative-dns'],
            'recursive' => ['name' => 'Recursive DNS (1.1.1.1)', 'slug' => '1111-dns'],
            'cdn'       => ['name' => 'Edge CDN & Cache Network', 'slug' => 'cdn-cache'],
            'cache'     => ['name' => 'Edge CDN & Cache Network', 'slug' => 'cdn-cache'],
            'dashboard' => ['name' => 'Dashboard & Control Panel API', 'slug' => 'dashboard-api'],
            'access'    => ['name' => 'Zero Trust, Access & Gateway', 'slug' => 'zero-trust'],
            'turnstile' => ['name' => 'Turnstile Captcha Engine', 'slug' => 'turnstile'],
            'stream'    => ['name' => 'Cloudflare Stream Video', 'slug' => 'stream'],
            'warp'      => ['name' => 'WARP Client & Network', 'slug' => 'warp']
        ];

        $matchedSlugs = [];
        $components = $data['components'] ?? [];

        foreach ($components as $comp) {
            $name = $comp['name'] ?? '';

            // Ignore colos/datacenters (which contain ' - ' like 'AMS - Amsterdam' or three-letter airport codes)
            if (str_contains($name, ' - ') || !empty($comp['group'])) {
                continue;
            }

            foreach ($coreKeywords as $keyword => $info) {
                if (stripos($name, $keyword) !== false && !in_array($info['slug'], $matchedSlugs, true)) {
                    $cStatus = match ($comp['status'] ?? '') {
                        'operational' => 'operational',
                        'degraded_performance', 'partial_outage' => 'degraded',
                        default => 'down'
                    };

                    $targetUrl = "https://www.cloudflarestatus.com#{$info['slug']}";
                    $this->upsertMonitor("Cloudflare - {$info['name']}", $targetUrl, $cStatus, $parentId);
                    $matchedSlugs[] = $info['slug'];
                    break;
                }
            }
        }

        // 3. OPTIONAL: Check Custom Cloudflare Zero Trust Tunnel via Cloudflare API v4
        $this->checkCustomCloudflareTunnel($parentId);

        return $parentStatus;
    }

    /**
     * Check individual Cloudflare Zero Trust Tunnel health if credentials are set
     */
    private function checkCustomCloudflareTunnel(int $parentId): void
    {
        $accountId = setting('cf_tunnel_account_id');
        $tunnelId  = setting('cf_tunnel_id');
        $apiToken  = setting('cf_tunnel_api_token');

        if (empty($accountId) || empty($tunnelId) || empty($apiToken)) {
            return;
        }

        $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/cfd_tunnel/{$tunnelId}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$apiToken}",
                "Content-Type: application/json"
            ]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['success']) && !empty($data['result'])) {
                $tResult = $data['result'];
                $tunnelStatus = strtolower($tResult['status'] ?? 'down');
                $customName   = setting('cf_tunnel_name') ?: ($tResult['name'] ?? 'Zero Trust Tunnel');

                $status = match ($tunnelStatus) {
                    'healthy'  => 'operational',
                    'degraded' => 'degraded',
                    default    => 'down'
                };

                $targetUrl = "https://dash.cloudflare.com/{$accountId}/networks/tunnels/{$tunnelId}";
                $this->upsertMonitor("Tunnel: {$customName}", $targetUrl, $status, $parentId);
            }
        }
    }

    public function syncAws(): string
    {
        $opts = ['http' => ['timeout' => 8, 'user_agent' => 'MySystemStatus/1.0']];
        $json = @file_get_contents('https://status.aws.amazon.com/data.json', false, stream_context_create($opts));

        $status = 'operational';
        if ($json) {
            $data = json_decode($json, true);
            if (!empty($data['current']) && is_array($data['current'])) {
                $status = 'degraded';
            }
        }

        $this->upsertMonitor('Amazon AWS Infrastructure', 'https://health.aws.amazon.com', $status, null);
        return $status;
    }

    public function syncAzure(): string
    {
        $opts = ['http' => ['timeout' => 8, 'user_agent' => 'MySystemStatus/1.0']];
        $html = @file_get_contents('https://azure.status.microsoft/en-us/status', false, stream_context_create($opts));

        $status = 'operational';
        if ($html && (str_contains($html, 'warning-icon') || str_contains($html, 'Incident'))) {
            $status = 'degraded';
        }

        $this->upsertMonitor('Microsoft Azure Cloud', 'https://azure.status.microsoft', $status, null);
        return $status;
    }

    public function syncPayPal(): string
    {
        $opts = ['http' => ['timeout' => 8, 'user_agent' => 'MySystemStatus/1.0']];
        $json = @file_get_contents('https://www.paypal-status.com/api/v1/components', false, stream_context_create($opts));

        $status = 'operational';
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                foreach ($data as $item) {
                    $itemStatus = strtoupper($item['status'] ?? '');
                    if ($itemStatus !== 'OPERATIONAL' && $itemStatus !== '') {
                        $status = 'degraded';
                        break;
                    }
                }
            }
        }

        $this->upsertMonitor('PayPal Payments Engine', 'https://www.paypal-status.com', $status, null);
        return $status;
    }

    public function syncStripe(): string
    {
        $json = @file_get_contents('https://status.stripe.com/current');
        $status = ($json && !str_contains($json, 'outage')) ? 'operational' : 'degraded';
        $this->upsertMonitor('Stripe Payments Engine', 'https://status.stripe.com', $status, null);
        return $status;
    }

    public function syncGitHub(): string
    {
        $json = @file_get_contents('https://www.githubstatus.com/api/v2/status.json');
        $status = 'operational';

        if ($json) {
            $data = json_decode($json, true);
            $indicator = $data['status']['indicator'] ?? 'none';
            $status = ($indicator === 'none') ? 'operational' : 'degraded';
        }

        $this->upsertMonitor('GitHub Services', 'https://www.githubstatus.com', $status, null);
        return $status;
    }

    private function upsertMonitor(string $name, string $target, string $status, ?int $parentId): int
    {
        $stmt = $this->db->prepare("SELECT id FROM monitors WHERE target = ? LIMIT 1");
        $stmt->execute([$target]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $update = $this->db->prepare("
                UPDATE monitors 
                SET name = ?, current_status = ?, last_check = NOW(), parent_id = ?, is_active = 1 
                WHERE id = ?
            ");
            $update->execute([$name, $status, $parentId, $existingId]);
            return (int)$existingId;
        }

        $insert = $this->db->prepare("
            INSERT INTO monitors (name, type, target, parent_id, sort_order, current_status, last_check, is_active) 
            VALUES (?, 'http', ?, ?, 99, ?, NOW(), 1)
        ");
        $insert->execute([$name, $target, $parentId, $status]);
        return (int)$this->db->lastInsertId();
    }

    private function disableFeedMonitors(string $targetPrefix): void
    {
        $stmt = $this->db->prepare("UPDATE monitors SET is_active = 0 WHERE target LIKE ?");
        $stmt->execute(["{$targetPrefix}%"]);
    }
}
