<?php
// path: app/Controllers/Admin/IncidentController.php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use App\Services\AiService;
use App\Services\SettingService;
use App\Services\MailerService;
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
            $incidentId = (int)$this->db->lastInsertId();

            $stmtUpdate = $this->db->prepare("
                INSERT INTO incident_updates (incident_id, status, message) 
                VALUES (?, ?, ?)
            ");
            $stmtUpdate->execute([$incidentId, $status, $message]);

            // Notify verified subscribers via SMTP email
            $this->sendIncidentEmailNotifications($title, $monitorId, $impact, $status, $message, false);
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
            // Fetch existing incident details to retrieve title and monitor_id
            $stmtInc = $this->db->prepare("SELECT title, monitor_id, impact FROM incidents WHERE id = ? LIMIT 1");
            $stmtInc->execute([$incidentId]);
            $incident = $stmtInc->fetch(PDO::FETCH_ASSOC);

            if ($incident) {
                $stmt = $this->db->prepare("UPDATE incidents SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $incidentId]);

                $stmtUpdate = $this->db->prepare("
                    INSERT INTO incident_updates (incident_id, status, message) 
                    VALUES (?, ?, ?)
                ");
                $stmtUpdate->execute([$incidentId, $status, $message]);

                // Notify subscribers about the incident progress/resolution update
                $monitorId = $incident['monitor_id'] ? (int)$incident['monitor_id'] : null;
                $this->sendIncidentEmailNotifications($incident['title'], $monitorId, $incident['impact'], $status, $message, true);
            }
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

    /**
     * Send email notifications to all verified global and monitor-specific subscribers.
     */
    private function sendIncidentEmailNotifications(string $title, ?int $monitorId, string $impact, string $status, string $message, bool $isUpdate = false): void
    {
        $smtpHost = setting('smtp_host', '');
        if (empty($smtpHost)) {
            return; // SMTP is disabled or not configured
        }

        // 1. Resolve affected service name
        $serviceName = 'All Services (Global)';
        if ($monitorId !== null) {
            $stmtM = $this->db->prepare("SELECT name FROM monitors WHERE id = ? LIMIT 1");
            $stmtM->execute([$monitorId]);
            $mName = $stmtM->fetchColumn();
            if ($mName) {
                $serviceName = $mName;
            }
        }

        // 2. Query verified subscribers (Global feed OR specifically targeting this monitor)
        if ($monitorId !== null) {
            $stmtSub = $this->db->prepare("
                SELECT DISTINCT email, token 
                FROM subscribers 
                WHERE is_verified = 1 AND (monitor_id IS NULL OR monitor_id = ?)
            ");
            $stmtSub->execute([$monitorId]);
        } else {
            $stmtSub = $this->db->query("
                SELECT DISTINCT email, token 
                FROM subscribers 
                WHERE is_verified = 1
            ");
        }

        $subscribers = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
        if (empty($subscribers)) {
            return;
        }

        // 3. Configure Mailer
        $smtpConfig = [
            'host'       => $smtpHost,
            'port'       => setting('smtp_port', '587'),
            'username'   => setting('smtp_user', ''),
            'password'   => setting('smtp_pass', ''),
            'encryption' => setting('smtp_encryption', 'starttls'),
            'from_email' => setting('smtp_from', ''),
            'from_name'  => setting('app_name', 'My System Status')
        ];

        $mailer = new MailerService($smtpConfig);
        $appName = setting('app_name', 'My System Status');
        $appUrl = rtrim(setting('app_url', app_url()), '/');

        $actionText = $isUpdate ? "Updated" : "Declared";
        $subjectPrefix = $isUpdate ? "[UPDATE]" : "[INCIDENT]";
        $subject = "{$subjectPrefix} {$title} - {$appName}";

        // 4. Dispatch email to each subscriber
        foreach ($subscribers as $sub) {
            $unsubUrl = $appUrl . "/subscribe/confirm-unsubscribe?token=" . urlencode($sub['token']);

            $htmlBody = "
                <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff;\">
                    <h2 style=\"color: #dc2626; margin-top: 0; font-size: 20px;\">🚨 Incident {$actionText}</h2>
                    <p style=\"color: #475569; font-size: 15px;\">An incident has been <strong>{$actionText}</strong> on <a href=\"{$appUrl}\" style=\"color: #2563eb; text-decoration: none;\">{$appName}</a>.</p>

                    <div style=\"background: #f8fafc; padding: 16px; border-left: 4px solid #dc2626; margin: 20px 0; border-radius: 4px;\">
                        <p style=\"margin: 0 0 8px 0; font-size: 14px; color: #1e293b;\"><strong>Incident Title:</strong> " . htmlspecialchars($title) . "</p>
                        <p style=\"margin: 0 0 8px 0; font-size: 14px; color: #1e293b;\"><strong>Target Service:</strong> " . htmlspecialchars($serviceName) . "</p>
                        <p style=\"margin: 0 0 8px 0; font-size: 14px; color: #1e293b;\"><strong>Impact Level:</strong> " . strtoupper(htmlspecialchars($impact)) . "</p>
                        <p style=\"margin: 0; font-size: 14px; color: #1e293b;\"><strong>Current Status:</strong> " . strtoupper(htmlspecialchars($status)) . "</p>
                    </div>

                    <p style=\"color: #334155; font-size: 14px;\"><strong>Timeline Message / Note:</strong></p>
                    <div style=\"background: #ffffff; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; color: #334155; line-height: 1.5;\">
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>

                    <div style=\"margin-top: 24px; text-align: center;\">
                        <a href=\"{$appUrl}\" style=\"display: inline-block; padding: 10px 20px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px;\">View System Status Page</a>
                    </div>

                    <hr style=\"border: none; border-top: 1px solid #e2e8f0; margin: 30px 0 15px 0;\">
                    <p style=\"font-size: 12px; color: #94a3b8; text-align: center; margin: 0;\">
                        You received this email because you are subscribed to alert notifications on {$appName}.<br>
                        <a href=\"{$unsubUrl}\" style=\"color: #64748b; text-decoration: underline;\">Unsubscribe from all notifications</a>
                    </p>
                </div>
            ";

            try {
                $mailer->send($sub['email'], $subject, $htmlBody);
            } catch (\Throwable $e) {
                // Silently continue to next subscriber if individual email fails
            }
        }
    }
}
