<?php
// path: app/Controllers/Api/MaintenanceApiController.php

namespace App\Controllers\Api;

use App\Core\Database;
use App\Services\ApiKeyService;
use PDO;

class MaintenanceApiController
{
    private PDO $db;
    private ApiKeyService $auth;

    public function __construct()
    {
        $this->db   = Database::getInstance();
        $this->auth = new ApiKeyService();
    }

    private function requireAuth(): void
    {
        header('Content-Type: application/json');

        // Check Authorization header: Bearer <API_KEY>
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $apiKey = '';

        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $apiKey = $matches[1];
        } elseif (!empty($_SERVER['HTTP_X_API_KEY'])) {
            $apiKey = trim($_SERVER['HTTP_X_API_KEY']);
        }

        if (!$this->auth->authenticate($apiKey)) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error'   => 'Unauthorized. Missing or invalid API key.'
            ]);
            exit;
        }
    }

    /**
     * POST /api/v1/maintenance/enable
     * Declares a new active maintenance window.
     */
    public function enable(): void
    {
        $this->requireAuth();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $title       = trim($input['title'] ?? 'Emergency Infrastructure Maintenance');
        $description = trim($input['description'] ?? 'Automated maintenance mode deployed via remote workflow.');
        $monitorId   = !empty($input['monitor_id']) ? (int)$input['monitor_id'] : null;
        $startTime   = $input['start_time'] ?? date('Y-m-d H:i:s');
        $endTime     = $input['end_time'] ?? date('Y-m-d H:i:s', strtotime('+3 hours'));

        $stmt = $this->db->prepare("
            INSERT INTO maintenances (title, monitor_id, description, status, start_time, end_time) 
            VALUES (?, ?, ?, 'in_progress', ?, ?)
        ");
        $stmt->execute([$title, $monitorId, $description, $startTime, $endTime]);
        $maintenanceId = (int)$this->db->lastInsertId();

        // Record in system audit logs
        $audit = $this->db->prepare("INSERT INTO system_logs (action, details, ip_address, created_at) VALUES (?, ?, ?, NOW())");
        $audit->execute([
            'API_MAINTENANCE_ENABLED', 
            "Maintenance #{$maintenanceId} ('{$title}') initiated via REST API.", 
            $_SERVER['REMOTE_ADDR'] ?? 'REMOTE'
        ]);

        echo json_encode([
            'success'        => true,
            'action'         => 'maintenance_enabled',
            'maintenance_id' => $maintenanceId,
            'status'         => 'in_progress',
            'start_time'     => $startTime,
            'end_time'       => $endTime
        ]);
        exit;
    }

    /**
     * POST /api/v1/maintenance/disable
     * Completes and closes all ongoing active maintenances.
     */
    public function disable(): void
    {
        $this->requireAuth();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $maintenanceId = !empty($input['maintenance_id']) ? (int)$input['maintenance_id'] : null;

        if ($maintenanceId) {
            $stmt = $this->db->prepare("
                UPDATE maintenances 
                SET status = 'completed', end_time = NOW() 
                WHERE id = ? AND status != 'completed'
            ");
            $stmt->execute([$maintenanceId]);
            $affected = $stmt->rowCount();
        } else {
            // Close ALL active or in-progress maintenances
            $stmt = $this->db->query("
                UPDATE maintenances 
                SET status = 'completed', end_time = NOW() 
                WHERE status IN ('scheduled', 'in_progress')
            ");
            $affected = $stmt->rowCount();
        }

        // Record in system audit logs
        $audit = $this->db->prepare("INSERT INTO system_logs (action, details, ip_address, created_at) VALUES (?, ?, ?, NOW())");
        $audit->execute([
            'API_MAINTENANCE_DISABLED', 
            "Closed {$affected} active maintenance window(s) via REST API.", 
            $_SERVER['REMOTE_ADDR'] ?? 'REMOTE'
        ]);

        echo json_encode([
            'success'        => true,
            'action'         => 'maintenance_disabled',
            'closed_count'   => $affected,
            'closed_at'      => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}