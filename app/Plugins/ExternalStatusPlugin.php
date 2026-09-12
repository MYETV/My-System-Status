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
     * Poll public status feeds (Cloudflare, GitHub, Stripe).
     */
    public function syncAll(): array
    {
        return [
            'cloudflare' => $this->syncCloudflare(),
            'github'     => $this->syncGitHub(),
            'stripe'     => $this->syncStripe()
        ];
    }

    public function syncCloudflare(): string
    {
        $json = @file_get_contents('https://www.cloudflarestatus.com/api/v2/status.json');
        if (!$json) return 'unknown';

        $data = json_decode($json, true);
        $indicator = $data['status']['indicator'] ?? 'none'; // none, minor, major, critical

        $status = match ($indicator) {
            'none'     => 'operational',
            'minor'    => 'degraded',
            default    => 'down'
        };

        $this->upsertExternalMonitor('Cloudflare Global Network', 'https://www.cloudflarestatus.com', $status);
        return $status;
    }

    public function syncGitHub(): string
    {
        $json = @file_get_contents('https://www.githubstatus.com/api/v2/status.json');
        if (!$json) return 'unknown';

        $data = json_decode($json, true);
        $indicator = $data['status']['indicator'] ?? 'none';

        $status = ($indicator === 'none') ? 'operational' : 'degraded';
        $this->upsertExternalMonitor('GitHub Services', 'https://www.githubstatus.com', $status);
        return $status;
    }

    public function syncStripe(): string
    {
        $json = @file_get_contents('https://status.stripe.com/current');
        $status = ($json && !str_contains($json, 'outage')) ? 'operational' : 'degraded';
        $this->upsertExternalMonitor('Stripe Payments Engine', 'https://status.stripe.com', $status);
        return $status;
    }

    private function upsertExternalMonitor(string $name, string $target, string $status): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO monitors (name, type, target, current_status, last_check, is_active) 
            VALUES (?, 'http', ?, ?, NOW(), 1)
            ON DUPLICATE KEY UPDATE current_status = VALUES(current_status), last_check = NOW()
        ");
        $stmt->execute([$name, $target, $status]);
    }
}