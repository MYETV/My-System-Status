<?php
// path: app/Controllers/Admin/LogController.php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use PDO;

class LogController
{
    private PDO $db;

    public function __construct()
    {
        // Authentication guard
        if (empty($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }

        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        // 1. Determine selected 24-hour date window (Defaults to Today)
        $selectedDate = trim($_GET['date'] ?? date('Y-m-d'));

        // Validate YYYY-MM-DD format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = date('Y-m-d');
        }

        $startWindow = "{$selectedDate} 00:00:00";
        $endWindow   = "{$selectedDate} 23:59:59";

        // 2. Fetch all probe logs for the full 24-hour window (No LIMIT 100!)
        $stmt = $this->db->prepare("
            SELECT l.*, m.name as monitor_name 
            FROM monitor_logs l 
            JOIN monitors m ON m.id = l.monitor_id 
            WHERE l.created_at >= ? AND l.created_at <= ? 
            ORDER BY l.created_at DESC
        ");
        $stmt->execute([$startWindow, $endWindow]);
        $probeLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Quick metrics for the 24-hour day
        $totalChecks = count($probeLogs);
        $upChecks    = 0;
        $downChecks  = 0;
        $blackouts   = 0;
        $totalLatency = 0;

        foreach ($probeLogs as $log) {
            if ($log['status'] === 'up') {
                $upChecks++;
                $totalLatency += (int)($log['response_time_ms'] ?? 0);
            } elseif ($log['status'] === 'blackout') {
                $blackouts++;
            } else {
                $downChecks++;
            }
        }

        $avgLatency = $upChecks > 0 ? round($totalLatency / $upChecks) : 0;

        View::render('admin/logs/index', [
            'probeLogs'    => $probeLogs,
            'selectedDate' => $selectedDate,
            'totalChecks'  => $totalChecks,
            'upChecks'     => $upChecks,
            'downChecks'   => $downChecks,
            'blackouts'    => $blackouts,
            'avgLatency'   => $avgLatency
        ]);
    }
}
