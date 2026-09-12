<?php
// path: cron/runner.php

if (php_sapi_name() !== 'cli') {
    die("CLI access only.");
}

require_once dirname(__DIR__) . '/public/index.php';

use App\Services\MonitorService;
use App\Services\SettingService;
use App\Core\Database;

echo "[" . date('Y-m-d H:i:s') . "] Starting My System Status Health Checks...\n";

$monitorService = new MonitorService();
$monitorService->runPendingChecks();

// Check if any monitor just failed and send Discord Webhook if configured
$webhookUrl = SettingService::get('discord_webhook_url');
if (!empty($webhookUrl)) {
    $db = Database::getInstance();
    $downMonitors = $db->query("
        SELECT name, target FROM monitors 
        WHERE current_status = 'down' AND last_check >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($downMonitors as $down) {
        $payload = [
            'content' => "🚨 **Outage Alert**: Service **{$down['name']}** ({$down['target']}) is DOWN!",
            'username' => 'My System Status Probe'
        ];

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Finished successfully.\n";