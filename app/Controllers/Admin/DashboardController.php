<?php
// path: app/Controllers/Admin/DashboardController.php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use PDO;

class DashboardController
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
        // 1. Monitors statistics
        $monitorsTotal = (int)$this->db->query("SELECT COUNT(*) FROM monitors")->fetchColumn();
        $monitorsUp    = (int)$this->db->query("SELECT COUNT(*) FROM monitors WHERE current_status = 'operational'")->fetchColumn();
        $monitorsDown  = (int)$this->db->query("SELECT COUNT(*) FROM monitors WHERE current_status = 'down'")->fetchColumn();

        // 2. Active Incidents & Maintenance
        $activeIncidents = (int)$this->db->query("SELECT COUNT(*) FROM incidents WHERE status != 'resolved'")->fetchColumn();
        $upcomingMaint   = (int)$this->db->query("SELECT COUNT(*) FROM maintenances WHERE status IN ('scheduled', 'in_progress') AND end_time >= NOW()")->fetchColumn();

        // 3. Verified Subscribers
        $subscribersCount = (int)$this->db->query("SELECT COUNT(*) FROM subscribers WHERE is_verified = 1")->fetchColumn();

        // 4. Recent Heartbeat Logs
        $stmt = $this->db->query("
            SELECT l.*, m.name as monitor_name 
            FROM monitor_logs l 
            JOIN monitors m ON m.id = l.monitor_id 
            ORDER BY l.id DESC LIMIT 8
        ");
        $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('admin/dashboard/index', [
            'monitorsTotal'    => $monitorsTotal,
            'monitorsUp'       => $monitorsUp,
            'monitorsDown'     => $monitorsDown,
            'activeIncidents'  => $activeIncidents,
            'upcomingMaint'    => $upcomingMaint,
            'subscribersCount' => $subscribersCount,
            'recentLogs'       => $recentLogs
        ]);
    }
}