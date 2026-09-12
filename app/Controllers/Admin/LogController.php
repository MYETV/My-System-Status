<?php
// path: app/Controllers/Admin/LogController.php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use PDO;

class LogController
{
    public function index(): void
    {
        $db = Database::getInstance();
        $probeLogs = $db->query("
            SELECT l.*, m.name as monitor_name 
            FROM monitor_logs l 
            JOIN monitors m ON m.id = l.monitor_id 
            ORDER BY l.id DESC LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC);

        View::render('admin/logs/index', ['probeLogs' => $probeLogs]);
    }
}