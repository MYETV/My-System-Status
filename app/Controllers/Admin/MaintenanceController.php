<?php
// path: app/Controllers/Admin/MaintenanceController.php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use PDO;

class MaintenanceController
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
        // 1. Active & Upcoming Maintenances
        $stmtUpcoming = $this->db->query("
            SELECT * FROM maintenances 
            WHERE status IN ('scheduled', 'in_progress') 
            ORDER BY start_time ASC
        ");
        $upcoming = $stmtUpcoming->fetchAll(PDO::FETCH_ASSOC);

        // 2. Past & Completed Maintenances
        $stmtPast = $this->db->query("
            SELECT * FROM maintenances 
            WHERE status = 'completed' OR end_time < NOW() 
            ORDER BY end_time DESC LIMIT 50
        ");
        $past = $stmtPast->fetchAll(PDO::FETCH_ASSOC);

        // 3. Fetch monitors list for modal dropdown selection
        $monitorsList = $this->db->query("
            SELECT id, name 
            FROM monitors 
            WHERE is_active = 1 
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        View::render('admin/maintenance/index', [
            'upcoming'     => $upcoming,
            'past'         => $past,
            'monitorsList' => $monitorsList
        ]);
    }

    /**
     * Feed for FullCalendar (JSON) with full payload including monitor_id
     */
    public function events(): void
    {
        header('Content-Type: application/json');
        $stmt = $this->db->query("SELECT * FROM maintenances");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mapped = array_map(function ($ev) {
            $color = match ($ev['status']) {
                'scheduled'   => '#0d6efd',
                'in_progress' => '#ffc107',
                'completed'   => '#198754',
                default       => '#6c757d'
            };
            return [
                'id'            => (string)$ev['id'],
                'title'         => $ev['title'],
                'start'         => date('c', strtotime($ev['start_time'])),
                'end'           => date('c', strtotime($ev['end_time'])),
                'color'         => $color,
                'extendedProps' => [
                    'description' => $ev['description'] ?? '',
                    'status'      => $ev['status'],
                    'monitor_id'  => $ev['monitor_id'] ?? '',
                    'start_raw'   => date('Y-m-d\TH:i', strtotime($ev['start_time'])),
                    'end_raw'     => date('Y-m-d\TH:i', strtotime($ev['end_time']))
                ]
            ];
        }, $events);

        echo json_encode($mapped);
        exit;
    }

    public function store(): void
    {
        $title       = trim($_POST['title'] ?? '');
        $monitorId   = !empty($_POST['monitor_id']) ? (int)$_POST['monitor_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $start       = $_POST['start_time'] ?? '';
        $end         = $_POST['end_time'] ?? '';
        $status      = $_POST['status'] ?? 'scheduled';

        if ($title && $start && $end) {
            $stmt = $this->db->prepare("
                INSERT INTO maintenances (title, monitor_id, description, start_time, end_time, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $monitorId, $description, $start, $end, $status]);
        }

        header('Location: /admin/maintenance?created=1');
        exit;
    }

    public function update(): void
    {
        $id          = (int)($_POST['maintenance_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $monitorId   = !empty($_POST['monitor_id']) ? (int)$_POST['monitor_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $start       = $_POST['start_time'] ?? '';
        $end         = $_POST['end_time'] ?? '';
        $status      = $_POST['status'] ?? 'scheduled';

        if ($id > 0 && $title && $start && $end) {
            $stmt = $this->db->prepare("
                UPDATE maintenances 
                SET title = ?, monitor_id = ?, description = ?, start_time = ?, end_time = ?, status = ? 
                WHERE id = ?
            ");
            $stmt->execute([$title, $monitorId, $description, $start, $end, $status, $id]);
        }

        header('Location: /admin/maintenance?updated=1');
        exit;
    }

    public function delete(): void
    {
        $id = (int)($_POST['maintenance_id'] ?? 0);
        if ($id > 0) {
            $stmt = $this->db->prepare("DELETE FROM maintenances WHERE id = ?");
            $stmt->execute([$id]);
        }

        header('Location: /admin/maintenance?deleted=1');
        exit;
    }
}
