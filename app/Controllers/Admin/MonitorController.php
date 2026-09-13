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
        if (empty($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        // 1. Fetch only root/parent monitors ordered by custom order
        $stmt = $this->db->query("
            SELECT * FROM monitors 
            WHERE parent_id IS NULL 
            ORDER BY sort_order ASC, id DESC
        ");
        $monitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Attach children sub-services to each parent
        foreach ($monitors as &$m) {
            $childStmt = $this->db->prepare("SELECT * FROM monitors WHERE parent_id = ? ORDER BY id ASC");
            $childStmt->execute([$m['id']]);
            $m['children'] = $childStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. Parent groups list for the creation modal
        $parentsList = array_filter($monitors, fn($item) => empty($item['parent_id']));

        View::render('admin/monitors/index', [
            'monitors'    => $monitors,
            'parentsList' => $parentsList
        ]);
    }

    public function store(): void
    {
        $name      = trim($_POST['name'] ?? '');
        $type      = $_POST['type'] ?? 'http';
        $target    = trim($_POST['target'] ?? '');
        $port      = !empty($_POST['port']) ? (int)$_POST['port'] : null;
        $interval  = (int)($_POST['interval_seconds'] ?? 60);
        $parentId  = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $isPrimary = isset($_POST['is_primary']) ? 1 : 0;

        if ($name && $target) {
            $stmt = $this->db->prepare("
                INSERT INTO monitors (name, type, target, port, interval_seconds, parent_id, is_primary) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $type, $target, $port, $interval, $parentId, $isPrimary]);
        }

        header('Location: /admin/monitors');
        exit;
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

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Deleting parent automatically deletes all child sub-services via CASCADE
            $stmt = $this->db->prepare("DELETE FROM monitors WHERE id = ?");
            $stmt->execute([$id]);
        }

        header('Location: /admin/monitors');
        exit;
    }
}
