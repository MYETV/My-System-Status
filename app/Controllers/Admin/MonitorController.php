<?php
// path: app/Controllers/Admin/MonitorController.php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use PDO;

class MonitorController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        $stmt = $this->db->query("SELECT * FROM monitors ORDER BY id DESC");
        $monitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('admin/monitors/index', ['monitors' => $monitors]);
    }

    public function saveOrder(): void
    {
        $orders = $_POST['order'] ?? [];
        if (is_array($orders)) {
            $stmt = $this->db->prepare("UPDATE monitors SET sort_order = ? WHERE id = ?");
            foreach ($orders as $id => $pos) {
                $stmt->execute([(int)$pos, (int)$id]);
            }
        }

        header('Location: /admin/monitors?reordered=1');
        exit;
    }

    public function store(): void
    {
        $name     = trim($_POST['name'] ?? '');
        $type     = $_POST['type'] ?? 'http';
        $target   = trim($_POST['target'] ?? '');
        $port     = !empty($_POST['port']) ? (int)$_POST['port'] : null;
        $interval = (int)($_POST['interval_seconds'] ?? 60);

        if ($name && $target) {
            $stmt = $this->db->prepare("
                INSERT INTO monitors (name, type, target, port, interval_seconds) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $type, $target, $port, $interval]);
        }

        header('Location: /admin/monitors');
        exit;
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $this->db->prepare("DELETE FROM monitors WHERE id = ?");
            $stmt->execute([$id]);
        }

        header('Location: /admin/monitors');
        exit;
    }
}
