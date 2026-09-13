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

    /**
     * Poll enabled external feeds.
     */
    public function syncAll(): array
    {
        $results = [];

        // 1. Cloudflare
        if (setting('feed_cloudflare_enabled', '1') === '1') {
            $results['cloudflare'] = $this->syncCloudflare();
        } else {
            $this->disableFeedMonitors('https://www.cloudflarestatus.com');
        }

        // 2. Stripe
        if (setting('feed_stripe_enabled', '1') === '1') {
            $results['stripe'] = $this->syncStripe();
        } else {
            $this->disableFeedMonitors('https://status.stripe.com');
        }

        // 3. GitHub
        if (setting('feed_github_enabled', '1') === '1') {
            $results['github'] = $this->syncGitHub();
        } else {
            $this->disableFeedMonitors('https://www.githubstatus.com');
        }

        // 4. PayPal
        if (setting('feed_paypal_enabled', '1') === '1') {
            $results['paypal'] = $this->syncPayPal();
        } else {
            $this->disableFeedMonitors('https://www.paypal-status.com');
        }

        return $results;
    }

    /**
     * Cloudflare granular components polling
     */
    public function syncCloudflare(): string
    {
        $json = @file_get_contents('https://www.cloudflarestatus.com/api/v2/summary.json');
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

        // 2. Parse and upsert child components (Workers, DNS, CDN, Dashboard, etc.)
        $components = $data['components'] ?? [];
        $trackedComponents = [
            'Cloudflare Workers' => 'Workers & Pages Platform',
            'Authoritative DNS'  => 'Authoritative DNS Service',
            'CDN / Cache'        => 'Edge CDN & Cache Network',
            'Cloudflare Dashboard' => 'Dashboard & Control Panel',
            'Cloudflare Access'  => 'Zero Trust & Access Gateway'
        ];

        foreach ($components as $comp) {
            $name = $comp['name'] ?? '';
            foreach ($trackedComponents as $key => $displayName) {
                if (stripos($name, $key) !== false) {
                    $cStatus = match ($comp['status'] ?? '') {
                        'operational' => 'operational',
                        'degraded_performance', 'partial_outage' => 'degraded',
                        default => 'down'
                    };
                    $this->upsertMonitor("Cloudflare - {$displayName}", "https://www.cloudflarestatus.com#{$key}", $cStatus, $parentId);
                    break;
                }
            }
        }

        return $parentStatus;
    }

    public function syncPayPal(): string
    {
        // Query PayPal Status API
        $opts = ['http' => ['timeout' => 8, 'user_agent' => 'MySystemStatus-Probe/1.0']];
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
                SET current_status = ?, last_check = NOW(), parent_id = ?, is_active = 1 
                WHERE id = ?
            ");
            $update->execute([$status, $parentId, $existingId]);
            return (int)$existingId;
        }

        $insert = $this->db->prepare("
            INSERT INTO monitors (name, type, target, parent_id, current_status, last_check, is_active) 
            VALUES (?, 'http', ?, ?, ?, NOW(), 1)
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
