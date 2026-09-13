<?php
// path: cron/runner.php

declare(strict_types=1);

// CLI execution only
if (php_sapi_name() !== 'cli') {
    die("CLI access only.\n");
}

$rootDir = dirname(__DIR__);

// 1. PSR-4 Autoloader
spl_autoload_register(function ($class) use ($rootDir) {
    $prefix = 'App\\';
    $baseDir = $rootDir . '/app/';

    if (str_starts_with($class, 'App\\Core\\')) {
        $file = $rootDir . '/core/' . substr($class, 9) . '.php';
        if (file_exists($file)) require_once $file;
        return;
    }

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) require_once $file;
});

// 2. Load Global Helpers
require_once $rootDir . '/core/helpers.php';

use App\Services\MonitorService;
use App\Services\SettingService;
use App\Plugins\ExternalStatusPlugin;
use App\Core\Database;

echo "[" . date('Y-m-d H:i:s') . "] Starting My System Status Health Checks...\n";

// 3. Run Custom Monitor Probes (HTTP, Ping, Port, SSL)
try {
    $monitorService = new MonitorService();
    $monitorService->runPendingChecks();
    echo "[" . date('Y-m-d H:i:s') . "] Custom probes executed successfully.\n";
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error running probes: " . $e->getMessage() . "\n";
}

// 4. Automatically Poll Enabled External Feeds (Cloudflare, AWS, Stripe, etc.)
try {
    $externalPlugin = new ExternalStatusPlugin();
    $externalPlugin->syncAll();
    echo "[" . date('Y-m-d H:i:s') . "] External feeds polled successfully.\n";
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error syncing external feeds: " . $e->getMessage() . "\n";
}

// 5. Discord Webhook Outage Alerts
$webhookUrl = setting('discord_webhook_url');
if (!empty($webhookUrl)) {
    try {
        $db = Database::getInstance();
        $downMonitors = $db->query("
            SELECT name, target FROM monitors 
            WHERE current_status = 'down' AND is_active = 1 
            AND last_check >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($downMonitors as $down) {
            $payload = [
                'content' => "🚨 **Outage Alert**: Service **{$down['name']}** ({$down['target']}) is DOWN!",
                'username' => setting('app_name', 'My System Status Probe')
            ];

            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    } catch (\Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error sending Discord alert: " . $e->getMessage() . "\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Finished successfully.\n";
