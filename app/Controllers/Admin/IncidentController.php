<?php
// path: app/Controllers/Admin/IncidentController.php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use App\Services\AiService;
use App\Services\SettingService;
use App\Services\MailerService;
use App\Services\DateService;
use App\Services\ContentTranslationService;
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
        $activeTz = DateService::getActiveTimezone();

        // 1. Fetch Active (Ongoing) Incidents
        $stmtActive = $this->db->query("
            SELECT * FROM incidents 
            WHERE status != 'resolved' 
            ORDER BY COALESCE(start_time, created_at) DESC
        ");
        $activeIncidents = $stmtActive->fetchAll(PDO::FETCH_ASSOC);

        foreach ($activeIncidents as &$inc) {
            $inc['start_local'] = DateService::toLocal($inc['start_time'] ?? $inc['created_at'], $activeTz);
            $inc['end_local']   = !empty($inc['end_time']) ? DateService::toLocal($inc['end_time'], $activeTz) : '';

            $upStmt = $this->db->prepare("SELECT * FROM incident_updates WHERE incident_id = ? ORDER BY created_at DESC");
            $upStmt->execute([$inc['id']]);
            $inc['updates'] = $upStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($inc);

        // 2. Fetch Past (Resolved) Incidents
        $stmtPast = $this->db->query("
            SELECT * FROM incidents 
            WHERE status = 'resolved' 
            ORDER BY COALESCE(end_time, updated_at) DESC, id DESC LIMIT 50
        ");
        $resolvedIncidents = $stmtPast->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resolvedIncidents as &$inc) {
            $inc['start_local'] = DateService::toLocal($inc['start_time'] ?? $inc['created_at'], $activeTz);
            $inc['end_local']   = !empty($inc['end_time']) ? DateService::toLocal($inc['end_time'], $activeTz) : '';

            $upStmt = $this->db->prepare("SELECT * FROM incident_updates WHERE incident_id = ? ORDER BY created_at DESC");
            $upStmt->execute([$inc['id']]);
            $inc['updates'] = $upStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($inc);

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
            'monitorsList'      => $monitorsList,
            'activeTimezone'    => $activeTz,
            'timezonesList'     => DateService::getTimezonesList()
        ]);
    }

    public function store(): void
    {
        $title          = trim($_POST['title'] ?? '');
        $monitorId      = !empty($_POST['monitor_id']) ? (int)$_POST['monitor_id'] : null;
        $impact         = $_POST['impact'] ?? 'minor';
        $status         = $_POST['status'] ?? 'investigating';
        $message        = trim($_POST['message'] ?? '');
        $aiSummary      = trim($_POST['ai_summary'] ?? '');
        $timezone       = trim($_POST['timezone'] ?? DateService::getActiveTimezone());
        $startTimeInput = trim($_POST['start_time'] ?? '');
        $endTimeInput   = trim($_POST['end_time'] ?? '');

        if (!DateService::isValidTimezone($timezone)) {
            $timezone = DateService::getActiveTimezone();
        }

        // Convert localized input times to UTC for database persistence
        $utcStartTime = !empty($startTimeInput)
            ? DateService::toUtc($startTimeInput, $timezone)
            : gmdate('Y-m-d H:i:s');

        $utcEndTime = !empty($endTimeInput)
            ? DateService::toUtc($endTimeInput, $timezone)
            : null;

        if ($title && $message) {
            $stmt = $this->db->prepare("
                INSERT INTO incidents (title, monitor_id, impact, status, ai_summary, start_time, end_time, timezone) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $monitorId, $impact, $status, $aiSummary, $utcStartTime, $utcEndTime, $timezone]);
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
            $stmtInc = $this->db->prepare("SELECT title, monitor_id, impact, end_time FROM incidents WHERE id = ? LIMIT 1");
            $stmtInc->execute([$incidentId]);
            $incident = $stmtInc->fetch(PDO::FETCH_ASSOC);

            if ($incident) {
                // If resolving incident, auto-stamp end_time if not already set
                if ($status === 'resolved') {
                    $stmt = $this->db->prepare("
                        UPDATE incidents 
                        SET status = ?, end_time = COALESCE(end_time, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP() 
                        WHERE id = ?
                    ");
                } else {
                    $stmt = $this->db->prepare("
                        UPDATE incidents 
                        SET status = ?, updated_at = UTC_TIMESTAMP() 
                        WHERE id = ?
                    ");
                }
                $stmt->execute([$status, $incidentId]);

                $stmtUpdate = $this->db->prepare("
                    INSERT INTO incident_updates (incident_id, status, message) 
                    VALUES (?, ?, ?)
                ");
                $stmtUpdate->execute([$incidentId, $status, $message]);

                // Clear cached translations so fresh status/message is reflected
                (new ContentTranslationService())->clearCache('incident', $incidentId);

                // Notify subscribers about the incident progress/resolution update
                $monitorId = $incident['monitor_id'] ? (int)$incident['monitor_id'] : null;
                $this->sendIncidentEmailNotifications($incident['title'], $monitorId, $incident['impact'], $status, $message, true);
            }
        }

        header('Location: /admin/incidents?updated=1');
        exit;
    }

    /**
     * Edit incident details (Title, Target Monitor, Impact, AI Summary, Times & Timezone)
     */
    public function update(): void
    {
        $incidentId     = (int)($_POST['incident_id'] ?? 0);
        $title          = trim($_POST['title'] ?? '');
        $monitorId      = !empty($_POST['monitor_id']) ? (int)$_POST['monitor_id'] : null;
        $impact         = $_POST['impact'] ?? 'minor';
        $aiSummary      = trim($_POST['ai_summary'] ?? '');
        $timezone       = trim($_POST['timezone'] ?? DateService::getActiveTimezone());
        $startTimeInput = trim($_POST['start_time'] ?? '');
        $endTimeInput   = trim($_POST['end_time'] ?? '');

        if (!DateService::isValidTimezone($timezone)) {
            $timezone = DateService::getActiveTimezone();
        }

        $utcStartTime = !empty($startTimeInput) ? DateService::toUtc($startTimeInput, $timezone) : null;
        $utcEndTime   = !empty($endTimeInput) ? DateService::toUtc($endTimeInput, $timezone) : null;

        if ($incidentId > 0 && $title) {
            $stmt = $this->db->prepare("
                UPDATE incidents 
                SET title = ?, monitor_id = ?, impact = ?, ai_summary = ?, start_time = COALESCE(?, start_time), end_time = ?, timezone = ?, updated_at = UTC_TIMESTAMP() 
                WHERE id = ?
            ");
            $stmt->execute([$title, $monitorId, $impact, $aiSummary, $utcStartTime, $utcEndTime, $timezone, $incidentId]);

            // Reset translation cache when details are edited
            (new ContentTranslationService())->clearCache('incident', $incidentId);
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

            // Purge cached translations
            (new ContentTranslationService())->clearCache('incident', $incidentId);
        }

        header('Location: /admin/incidents?deleted=1');
        exit;
    }

    /**
     * Translate incident title and AI summary on demand with LibreTranslate and cache it.
     */
    public function translate(): void
    {
        header('Content-Type: application/json');
        $incidentId = (int)($_POST['incident_id'] ?? 0);
        $targetLang = trim($_POST['target_lang'] ?? 'it');

        if ($incidentId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid incident ID']);
            exit;
        }

        $stmt = $this->db->prepare("SELECT title, ai_summary FROM incidents WHERE id = ? LIMIT 1");
        $stmt->execute([$incidentId]);
        $inc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inc) {
            echo json_encode(['success' => false, 'error' => 'Incident not found']);
            exit;
        }

        $service = new ContentTranslationService();
        if (!$service->isConfigured()) {
            echo json_encode(['success' => false, 'error' => 'LibreTranslate endpoint is not configured in Settings']);
            exit;
        }

        $transTitle = $service->getOrTranslate('incident', $incidentId, 'title', $inc['title'], $targetLang);
        $transAi    = !empty($inc['ai_summary']) ? $service->getOrTranslate('incident', $incidentId, 'ai_summary', $inc['ai_summary'], $targetLang) : '';

        echo json_encode([
            'success'          => true,
            'target_lang'      => $targetLang,
            'translated_title' => $transTitle,
            'translated_ai'    => $transAi
        ]);
        exit;
    }

    public function generateAiSummary(): void
    {
        header('Content-Type: application/json');

        $serviceName  = $_POST['service_name'] ?? 'Core Infrastructure';
        $errorDetails = $_POST['error_details'] ?? 'Unexpected downtime detected by automated probes.';

        $provider = SettingService::get('ai_provider', 'gemini');

        // Dynamically select the correct endpoint and credentials based on the active provider
        if ($provider === 'openai-chat') {
            $endpoint = SettingService::get('ai_openai_endpoint', '') ?: SettingService::get('ai_endpoint', '');
            $apiKey   = SettingService::get('ai_openai_api_key', '') ?: SettingService::get('ai_api_key', '');
        } else {
            $endpoint = SettingService::get('ai_endpoint', '');
            $apiKey   = SettingService::get('ai_api_key', '');
        }

        $model = SettingService::get('ai_model', '');

        $config = [
            'api_key'  => $apiKey,
            'endpoint' => $endpoint,
            'model'    => $model
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
            return;
        }

        $serviceName = 'All Services (Global)';
        if ($monitorId !== null) {
            $stmtM = $this->db->prepare("SELECT name FROM monitors WHERE id = ? LIMIT 1");
            $stmtM->execute([$monitorId]);
            $mName = $stmtM->fetchColumn();
            if ($mName) {
                $serviceName = $mName;
            }
        }

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
            } catch (\Throwable $e) {}
        }
    }
}
