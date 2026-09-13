<?php
// path: app/Controllers/Api/EdgeRecoveryController.php

namespace App\Controllers\Api;

use App\Core\Database;
use App\Services\SettingService;
use PDO;

class EdgeRecoveryController
{
    public function recordRecovery(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        // 1. Verify Authorization Token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $expectedToken = 'Bearer ' . setting('edge_worker_token');

        if (empty(setting('edge_worker_token')) || !hash_equals($expectedToken, $authHeader)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }

        $downtimeMinutes = (int)($input['downtime_minutes'] ?? 1);
        $downSince       = $input['down_since'] ?? date('Y-m-d H:i:s', strtotime("-{$downtimeMinutes} minutes"));
        $recoveredAt     = $input['recovered_at'] ?? date('Y-m-d H:i:s');
        $reason          = $input['reason'] ?? 'Edge Sentinel Detected Outage';

        $db = Database::getInstance();

        // 2. Identify Primary Core Monitor (e.g. MYETV Core or server root)
        $stmt = $db->query("SELECT id FROM monitors WHERE is_primary = 1 AND parent_id IS NULL LIMIT 1");
        $primaryMonitorId = $stmt->fetchColumn();

        if ($primaryMonitorId) {
            // A. Insert down log representing the downtime period
            $logStmt = $db->prepare("
                INSERT INTO monitor_logs (monitor_id, status, response_time_ms, http_code, error_message, created_at) 
                VALUES (?, 'down', 0, 502, ?, ?)
            ");
            $logStmt->execute([
                $primaryMonitorId, 
                "Edge Outage Detected: Down for {$downtimeMinutes} min ({$reason})",
                date('Y-m-d H:i:s', strtotime($downSince))
            ]);

            // B. Insert recovery log
            $recStmt = $db->prepare("
                INSERT INTO monitor_logs (monitor_id, status, response_time_ms, http_code, error_message, created_at) 
                VALUES (?, 'up', 100, 200, 'Recovered back online', ?)
            ");
            $recStmt->execute([$primaryMonitorId, date('Y-m-d H:i:s', strtotime($recoveredAt))]);

            // C. Update current status to operational
            $upd = $db->prepare("UPDATE monitors SET current_status = 'operational', last_check = NOW() WHERE id = ?");
            $upd->execute([$primaryMonitorId]);
        }

        // 3. Record in System Audit Logs
        $auditStmt = $db->prepare("
            INSERT INTO system_logs (action, details, ip_address, created_at) 
            VALUES ('EDGE_OUTAGE_RECOVERED', ?, ?, NOW())
        ");
        $details = "Server recovered from outage. Total downtime: {$downtimeMinutes} minute(s). Outage window: {$downSince} to {$recoveredAt}.";
        $auditStmt->execute([$details, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

        echo json_encode([
            'status'           => 'success',
            'downtime_minutes' => $downtimeMinutes,
            'recorded_at'      => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}