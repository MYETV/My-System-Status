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
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = date('Y-m-d');
        }

        $startWindow = "{$selectedDate} 00:00:00";
        $endWindow   = "{$selectedDate} 23:59:59";

        // 2. Fetch all probe logs for the full 24-hour window
        $stmt = $this->db->prepare("
            SELECT l.*, m.name as monitor_name 
            FROM monitor_logs l 
            JOIN monitors m ON m.id = l.monitor_id 
            WHERE l.created_at >= ? AND l.created_at <= ? 
            ORDER BY l.created_at DESC
        ");
        $stmt->execute([$startWindow, $endWindow]);
        $probeLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Quick metrics & prepare lightweight dataset for DataTables
        $totalChecks  = count($probeLogs);
        $upChecks     = 0;
        $downChecks   = 0;
        $blackouts    = 0;
        $totalLatency = 0;
        $formattedLogs = [];

        foreach ($probeLogs as $log) {
            $st = $log['status'] ?? 'down';

            if ($st === 'up') {
                $upChecks++;
                $totalLatency += (int)($log['response_time_ms'] ?? 0);
                $statusBadge = '<span class="badge bg-success">UP</span>';
            } elseif ($st === 'blackout') {
                $blackouts++;
                $statusBadge = '<span class="badge bg-dark">BLACKOUT</span>';
            } elseif ($st === 'timeout') {
                $downChecks++;
                $statusBadge = '<span class="badge bg-warning text-dark">TIMEOUT</span>';
            } else {
                $downChecks++;
                $statusBadge = '<span class="badge bg-danger">DOWN</span>';
            }

            $detailsHtml = !empty($log['error_message'])
                ? '<small class="text-danger fw-semibold">' . htmlspecialchars($log['error_message']) . '</small>'
                : '<small class="text-success"><i class="bi bi-check2"></i> Operational</small>';

            $timeDisplay = '<span class="font-monospace small text-muted">' . 
                           format_date($log['created_at'], 'H:i:s') . 
                           ' <small class="text-secondary">(' . format_date($log['created_at'], 'M d') . ')</small></span>';

            $formattedLogs[] = [
                'time'        => $timeDisplay,
                'raw_time'    => $log['created_at'],
                'monitor'     => htmlspecialchars($log['monitor_name'] ?? 'Unknown Service'),
                'status'      => $statusBadge,
                'latency'     => '<span class="font-monospace">' . (int)($log['response_time_ms'] ?? 0) . ' ms</span>',
                'raw_latency' => (int)($log['response_time_ms'] ?? 0),
                'http_code'   => '<code>' . htmlspecialchars($log['http_code'] ?? '-') . '</code>',
                'details'     => $detailsHtml
            ];
        }

        $avgLatency = $upChecks > 0 ? round($totalLatency / $upChecks) : 0;

        View::render('admin/logs/index', [
            'formattedLogs' => $formattedLogs,
            'selectedDate'  => $selectedDate,
            'totalChecks'   => $totalChecks,
            'upChecks'      => $upChecks,
            'downChecks'    => $downChecks,
            'blackouts'     => $blackouts,
            'avgLatency'    => $avgLatency
        ]);
    }
}
