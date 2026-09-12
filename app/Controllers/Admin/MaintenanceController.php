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
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        View::render('admin/maintenance/index');
    }

    /**
     * Feed for FullCalendar (JSON)
     */
    public function events(): void
    {
        header('Content-Type: application/json');
        $stmt = $this->db->query("
            SELECT id, title, start_time as start, end_time as end, status 
            FROM maintenances
        ");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Map status to calendar colors
        $mapped = array_map(function ($ev) {
            $color = match ($ev['status']) {
                'scheduled'   => '#0d6efd',
                'in_progress' => '#ffc107',
                'completed'   => '#198754'
            };
            return [
                'id'    => $ev['id'],
                'title' => $ev['title'],
                'start' => $ev['start'],
                'end'   => $ev['end'],
                'color' => $color
            ];
        }, $events);

        echo json_encode($mapped);
        exit;
    }

    public function store(): void
    {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';

        if ($title && $start && $end) {
            $stmt = $this->db->prepare("
                INSERT INTO maintenances (title, description, start_time, end_time, status)
                VALUES (?, ?, ?, ?, 'scheduled')
            ");
            $stmt->execute([$title, $description, $start, $end]);
        }

        header('Location: /admin/maintenance');
        exit;
    }
}