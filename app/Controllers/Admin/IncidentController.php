<?php
// path: app/Controllers/Admin/IncidentController.php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use App\Services\AiService;
use App\Services\SettingService;
use PDO;

class IncidentController
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
        // 1. Fetch Active (Ongoing) Incidents
        $stmtActive = $this->db->query("
            SELECT * FROM incidents 
            WHERE status != 'resolved' 
            ORDER BY created_at DESC
        ");
        $activeIncidents = $stmtActive->fetchAll(PDO::FETCH_ASSOC);

        foreach ($activeIncidents as &$inc) {
            $upStmt = $this->db->prepare("SELECT * FROM incident_updates WHERE incident_id = ? ORDER BY created_at DESC");
            $upStmt->execute([$inc['id']]);
            $inc['updates'] = $upStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 2. Fetch Past (Resolved) Incidents
        $stmtPast = $this->db->query("
            SELECT * FROM incidents 
            WHERE status = 'resolved' 
            ORDER BY updated_at DESC, id DESC LIMIT 50
        ");
        $resolvedIncidents = $stmtPast->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resolvedIncidents as &$inc) {
            $upStmt = $this->db->prepare("SELECT * FROM incident_updates WHERE incident_id = ? ORDER BY created_at DESC");
            $upStmt->execute([$inc['id']]);
            $inc['updates'] = $upStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. Fetch monitors list for modal dropdown selection
        $monitorsList = $this->db->query("
            SELECT id, name 
            FROM monitors 
            WHERE is_active = 1 
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Pass active incidents, resolved incidents, and monitors list to the view
        View::render('admin/incidents/index', [
            'activeIncidents'   => $activeIncidents,
            'resolvedIncidents' => $resolvedIncidents,
            'monitorsList'      => $monitorsList
        ]);
    }

    public function store(): void
    {
        $title     = trim($_POST['title'] ?? '');
        $monitorId = !empty($_POST['monitor_id']) ? (int)$_POST['monitor_id'] : null;
        $impact    = $_POST['impact'] ?? 'minor';
        $status    = $_POST['status'] ?? 'investigating';
        $message   = trim($_POST['message'] ?? '');
        $aiSummary = trim($_POST['ai_summary'] ?? '');

        if ($title && $message) {
            $stmt = $this->db->prepare("
                INSERT INTO incidents (title, monitor_id, impact, status, ai_summary) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $monitorId, $impact, $status, $aiSummary]);
            $incidentId = $this->db->lastInsertId();

            $stmtUpdate = $this->db->prepare("
                INSERT INTO incident_updates (incident_id, status, message) 
                VALUES (?, ?, ?)
            ");
            $stmtUpdate->execute([$incidentId, $status, $message]);
        }

        header('Location: /admin/incidents?created=1');
        exit;
    }

    /**
     * Post a new progress note to timeline and update master incident status
     */
    public function updateStatus(): void
    {
        $incidentId = (int)($_POST['incident_id'] ?? 0);
        $status     = $_POST['status'] ?? 'monitoring';
        $message    = trim($_POST['message'] ?? '');

        if ($incidentId > 0 && $message) {
            $stmt = $this->db->prepare("UPDATE incidents SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $incidentId]);

            $stmtUpdate = $this->db->prepare("
                INSERT INTO incident_updates (incident_id, status, message) 
                VALUES (?, ?, ?)
            ");
            $stmtUpdate->execute([$incidentId, $status, $message]);
        }

        header('Location: /admin/incidents?updated=1');
        exit;
    }

    /**
     * Edit incident details (Title, Target Monitor, Impact, AI Summary)
     */
    public function update(): void
    {
        $incidentId = (int)($_POST['incident_id'] ?? 0);
        $title      = trim($_POST['title'] ?? '');
        $monitorId  = !empty($_POST['monitor_id']) ? (int)$_POST['monitor_id'] : null;
        $impact     = $_POST['impact'] ?? 'minor';
        $aiSummary  = trim($_POST['ai_summary'] ?? '');

        if ($incidentId > 0 && $title) {
            $stmt = $this->db->prepare("
                UPDATE incidents 
                SET title = ?, monitor_id = ?, impact = ?, ai_summary = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$title, $monitorId, $impact, $aiSummary, $incidentId]);
        }

        header('Location: /admin/incidents?edited=1');
        exit;
    }

    public function delete(): void
    {
        $incidentId = (int)($_POST['incident_id'] ?? 0);
        if ($incidentId > 0) {
            $stmt = $this->db->prepare("DELETE FROM incidents WHERE id = ?");
            $stmt->execute([$incidentId]);
        }

        header('Location: /admin/incidents?deleted=1');
        exit;
    }

    public function generateAiSummary(): void
    {
        header('Content-Type: application/json');

        $serviceName  = $_POST['service_name'] ?? 'Core Infrastructure';
        $errorDetails = $_POST['error_details'] ?? 'Unexpected downtime detected by automated probes.';

        $provider = SettingService::get('ai_provider', 'gemini');
        $config = [
            'api_key'  => SettingService::get('ai_api_key', ''),
            'endpoint' => SettingService::get('ai_endpoint', ''),
            'model'    => SettingService::get('ai_model', '')
        ];

        $aiService = new AiService($provider, $config);
        $summary = $aiService->generateIncidentReport($serviceName, $errorDetails);

        echo json_encode(['status' => 'success', 'summary' => $summary]);
        exit;
    }
}
