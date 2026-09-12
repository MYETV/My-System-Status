<?php
// path: app/Controllers/Api/AlertController.php

namespace App\Controllers\Api;

use App\Core\Database;
use PDO;

class AlertController
{
    public function getActiveAlerts(): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        $db = Database::getInstance();

        // 1. Check Ongoing Incidents
        $stmtInc = $db->query("
            SELECT id, title, impact, status, created_at 
            FROM incidents 
            WHERE status != 'resolved' 
            ORDER BY id DESC LIMIT 5
        ");
        $incidents = $stmtInc->fetchAll(PDO::FETCH_ASSOC);

        // 2. Check Active or Upcoming Maintenances
        $stmtMaint = $db->query("
            SELECT id, title, description, start_time, end_time, status 
            FROM maintenances 
            WHERE status IN ('scheduled', 'in_progress')
            AND end_time >= NOW()
            ORDER BY start_time ASC LIMIT 5
        ");
        $maintenances = $stmtMaint->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status'       => (empty($incidents) && empty($maintenances)) ? 'ok' : 'alert',
            'incidents'    => $incidents,
            'maintenances' => $maintenances
        ]);
        exit;
    }
}