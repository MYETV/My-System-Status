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
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        $stmt = $this->db->query("SELECT * FROM incidents ORDER BY id DESC");
        $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('admin/incidents/index', ['incidents' => $incidents]);
    }

    public function store(): void
    {
        $title = trim($_POST['title'] ?? '');
        $impact = $_POST['impact'] ?? 'minor';
        $status = $_POST['status'] ?? 'investigating';
        $message = trim($_POST['message'] ?? '');
        $aiSummary = trim($_POST['ai_summary'] ?? '');

        if ($title && $message) {
            $stmt = $this->db->prepare("
                INSERT INTO incidents (title, impact, status, ai_summary) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$title, $impact, $status, $aiSummary]);
            $incidentId = $this->db->lastInsertId();

            $stmtUpdate = $this->db->prepare("
                INSERT INTO incident_updates (incident_id, status, message) 
                VALUES (?, ?, ?)
            ");
            $stmtUpdate->execute([$incidentId, $status, $message]);
        }

        header('Location: /admin/incidents');
        exit;
    }

    /**
     * AJAX Endpoint to ask Ollama or Gemini to summarize an incident.
     */
    public function generateAiSummary(): void
    {
        header('Content-Type: application/json');

        $serviceName = $_POST['service_name'] ?? 'Core Infrastructure';
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