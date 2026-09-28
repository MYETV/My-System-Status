<?php
// path: app/Controllers/Admin/MaintenanceController.php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use App\Services\MailerService;
use App\Services\DateService;
use App\Services\ContentTranslationService;
use PDO;

class MaintenanceController
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

        // 1. Active & Upcoming Maintenances
        $stmtUpcoming = $this->db->query("
            SELECT * FROM maintenances 
            WHERE status IN ('scheduled', 'in_progress') 
            ORDER BY start_time ASC
        ");
        $upcoming = $stmtUpcoming->fetchAll(PDO::FETCH_ASSOC);

        foreach ($upcoming as &$m) {
            $m['start_local'] = DateService::toLocal($m['start_time'], $activeTz);
            $m['end_local']   = DateService::toLocal($m['end_time'], $activeTz);
        }
        unset($m);

        // 2. Past & Completed Maintenances
        $stmtPast = $this->db->query("
            SELECT * FROM maintenances 
            WHERE status = 'completed' OR end_time < UTC_TIMESTAMP() 
            ORDER BY end_time DESC LIMIT 50
        ");
        $past = $stmtPast->fetchAll(PDO::FETCH_ASSOC);

        foreach ($past as &$m) {
            $m['start_local'] = DateService::toLocal($m['start_time'], $activeTz);
            $m['end_local']   = DateService::toLocal($m['end_time'], $activeTz);
        }
        unset($m);

        // 3. Fetch monitors list for modal dropdown selection
        $monitorsList = $this->db->query("
            SELECT id, name 
            FROM monitors 
            WHERE is_active = 1 
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        View::render('admin/maintenance/index', [
            'upcoming'       => $upcoming,
            'past'           => $past,
            'monitorsList'   => $monitorsList,
            'activeTimezone' => $activeTz,
            'timezonesList'  => DateService::getTimezonesList()
        ]);
    }

    /**
     * Feed for FullCalendar (JSON) with full payload including monitor_id and timezone conversion
     */
    public function events(): void
    {
        header('Content-Type: application/json');
        $activeTz = DateService::getActiveTimezone();

        $stmt = $this->db->query("SELECT * FROM maintenances");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mapped = array_map(function ($ev) use ($activeTz) {
            $color = match ($ev['status']) {
                'scheduled'   => '#0d6efd',
                'in_progress' => '#ffc107',
                'completed'   => '#198754',
                default       => '#6c757d'
            };

            $startLocal = DateService::toLocal($ev['start_time'], $activeTz);
            $endLocal   = DateService::toLocal($ev['end_time'], $activeTz);

            return [
                'id'            => (string)$ev['id'],
                'title'         => $ev['title'],
                'start'         => $startLocal,
                'end'           => $endLocal,
                'color'         => $color,
                'extendedProps' => [
                    'description' => $ev['description'] ?? '',
                    'status'      => $ev['status'],
                    'monitor_id'  => $ev['monitor_id'] ?? '',
                    'timezone'    => $ev['timezone'] ?? $activeTz,
                    'start_raw'   => $startLocal,
                    'end_raw'     => $endLocal
                ]
            ];
        }, $events);

        echo json_encode($mapped);
        exit;
    }

    public function store(): void
    {
        $title       = trim($_POST['title'] ?? '');
        $monitorId   = !empty($_POST['monitor_id']) ? (int)$_POST['monitor_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $startInput  = $_POST['start_time'] ?? '';
        $endInput    = $_POST['end_time'] ?? '';
        $status      = $_POST['status'] ?? 'scheduled';
        $timezone    = trim($_POST['timezone'] ?? DateService::getActiveTimezone());

        if (!DateService::isValidTimezone($timezone)) {
            $timezone = DateService::getActiveTimezone();
        }

        $utcStart = DateService::toUtc($startInput, $timezone);
        $utcEnd   = DateService::toUtc($endInput, $timezone);

        if ($title && $utcStart && $utcEnd) {
            $stmt = $this->db->prepare("
                INSERT INTO maintenances (title, monitor_id, description, start_time, end_time, timezone, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $monitorId, $description, $utcStart, $utcEnd, $timezone, $status]);

            // Notify verified subscribers via SMTP email
            $this->sendMaintenanceEmailNotifications($title, $monitorId, $description, $utcStart, $utcEnd, $status, false);
        }

        header('Location: /admin/maintenance?created=1');
        exit;
    }

    public function update(): void
    {
        $id          = (int)($_POST['maintenance_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $monitorId   = !empty($_POST['monitor_id']) ? (int)$_POST['monitor_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $startInput  = $_POST['start_time'] ?? '';
        $endInput    = $_POST['end_time'] ?? '';
        $status      = $_POST['status'] ?? 'scheduled';
        $timezone    = trim($_POST['timezone'] ?? DateService::getActiveTimezone());

        if (!DateService::isValidTimezone($timezone)) {
            $timezone = DateService::getActiveTimezone();
        }

        $utcStart = DateService::toUtc($startInput, $timezone);
        $utcEnd   = DateService::toUtc($endInput, $timezone);

        if ($id > 0 && $title && $utcStart && $utcEnd) {
            $stmt = $this->db->prepare("
                UPDATE maintenances 
                SET title = ?, monitor_id = ?, description = ?, start_time = ?, end_time = ?, timezone = ?, status = ? 
                WHERE id = ?
            ");
            $stmt->execute([$title, $monitorId, $description, $utcStart, $utcEnd, $timezone, $status, $id]);

            // Clear cached translations for this maintenance window
            (new ContentTranslationService())->clearCache('maintenance', $id);

            // Notify verified subscribers about the maintenance update/completion
            $this->sendMaintenanceEmailNotifications($title, $monitorId, $description, $utcStart, $utcEnd, $status, true);
        }

        header('Location: /admin/maintenance?updated=1');
        exit;
    }

    public function delete(): void
    {
        $id = (int)($_POST['maintenance_id'] ?? 0);
        if ($id > 0) {
            $stmt = $this->db->prepare("DELETE FROM maintenances WHERE id = ?");
            $stmt->execute([$id]);

            // Purge cached translations
            (new ContentTranslationService())->clearCache('maintenance', $id);
        }

        header('Location: /admin/maintenance?deleted=1');
        exit;
    }

    /**
     * Translate maintenance title and description on demand with LibreTranslate and cache it.
     */
    public function translate(): void
    {
        header('Content-Type: application/json');
        $maintenanceId = (int)($_POST['maintenance_id'] ?? 0);
        $targetLang    = trim($_POST['target_lang'] ?? 'it');

        if ($maintenanceId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid maintenance ID']);
            exit;
        }

        $stmt = $this->db->prepare("SELECT title, description FROM maintenances WHERE id = ? LIMIT 1");
        $stmt->execute([$maintenanceId]);
        $maint = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$maint) {
            echo json_encode(['success' => false, 'error' => 'Maintenance not found']);
            exit;
        }

        $service = new ContentTranslationService();
        if (!$service->isConfigured()) {
            echo json_encode(['success' => false, 'error' => 'LibreTranslate endpoint is not configured in Settings']);
            exit;
        }

        $transTitle = $service->getOrTranslate('maintenance', $maintenanceId, 'title', $maint['title'], $targetLang);
        $transDesc  = !empty($maint['description']) ? $service->getOrTranslate('maintenance', $maintenanceId, 'description', $maint['description'], $targetLang) : '';

        echo json_encode([
            'success'                => true,
            'target_lang'            => $targetLang,
            'translated_title'       => $transTitle,
            'translated_description' => $transDesc
        ]);
        exit;
    }

    /**
     * Send email notifications to all verified global and monitor-specific subscribers.
     */
    private function sendMaintenanceEmailNotifications(string $title, ?int $monitorId, string $description, string $utcStart, string $utcEnd, string $status, bool $isUpdate = false): void
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

        $actionText = $isUpdate ? "Updated" : "Scheduled";
        $subjectPrefix = $isUpdate ? "[UPDATE]" : "[MAINTENANCE]";
        $subject = "{$subjectPrefix} {$title} - {$appName}";

        $formattedStart = format_date($utcStart, 'M d, Y H:i');
        $formattedEnd   = format_date($utcEnd, 'M d, Y H:i T');

        foreach ($subscribers as $sub) {
            $unsubUrl = $appUrl . "/subscribe/confirm-unsubscribe?token=" . urlencode($sub['token']);

            $htmlBody = "
                <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff;\">
                    <h2 style=\"color: #2563eb; margin-top: 0; font-size: 20px;\">🛠️ Scheduled Maintenance {$actionText}</h2>
                    <p style=\"color: #475569; font-size: 15px;\">A maintenance window has been <strong>{$actionText}</strong> on <a href=\"{$appUrl}\" style=\"color: #2563eb; text-decoration: none;\">{$appName}</a>.</p>

                    <div style=\"background: #f8fafc; padding: 16px; border-left: 4px solid #2563eb; margin: 20px 0; border-radius: 4px;\">
                        <p style=\"margin: 0 0 8px 0; font-size: 14px; color: #1e293b;\"><strong>Event Title:</strong> " . htmlspecialchars($title) . "</p>
                        <p style=\"margin: 0 0 8px 0; font-size: 14px; color: #1e293b;\"><strong>Target Service:</strong> " . htmlspecialchars($serviceName) . "</p>
                        <p style=\"margin: 0 0 8px 0; font-size: 14px; color: #1e293b;\"><strong>Current Status:</strong> " . strtoupper(htmlspecialchars(str_replace('_', ' ', $status))) . "</p>
                        <p style=\"margin: 0; font-size: 14px; color: #1e293b;\"><strong>Execution Window:</strong> {$formattedStart} &mdash; {$formattedEnd}</p>
                    </div>

                    " . (!empty($description) ? "
                    <p style=\"color: #334155; font-size: 14px;\"><strong>Maintenance Details & Impact:</strong></p>
                    <div style=\"background: #ffffff; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; color: #334155; line-height: 1.5;\">
                        " . nl2br(htmlspecialchars($description)) . "
                    </div>
                    " : "") . "

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
