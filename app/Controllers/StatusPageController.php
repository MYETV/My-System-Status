<?php
// path: app/Controllers/StatusPageController.php

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use App\Services\MailerService;
use App\Services\SubscriberService;
use App\Services\SettingService;
use PDO;

class StatusPageController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        // 1. Fetch only root monitors (no parent_id)
        $stmt = $this->db->query("
            SELECT * FROM monitors 
            WHERE is_active = 1 AND parent_id IS NULL 
            ORDER BY name ASC
        ");
        $monitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Attach child sub-services to each parent monitor (e.g. Cloudflare Workers, DNS, CDN)
        foreach ($monitors as &$m) {
            $childStmt = $this->db->prepare("
                SELECT * FROM monitors 
                WHERE is_active = 1 AND parent_id = ? 
                ORDER BY name ASC
            ");
            $childStmt->execute([$m['id']]);
            $m['children'] = $childStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. Active Maintenances
        $maintenances = $this->db->query("
            SELECT * FROM maintenances 
            WHERE status IN ('scheduled', 'in_progress') 
            AND end_time >= NOW() 
            ORDER BY start_time ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 4. Active Incidents with chronological updates
        $incidents = $this->db->query("
            SELECT * FROM incidents 
            WHERE status != 'resolved' 
            ORDER BY created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($incidents as &$incident) {
            $upStmt = $this->db->prepare("
                SELECT * FROM incident_updates 
                WHERE incident_id = ? 
                ORDER BY created_at DESC
            ");
            $upStmt->execute([$incident['id']]);
            $incident['updates'] = $upStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 5. Calculate overall system health (checking both parents and children)
        $overallStatus = 'operational';
        foreach ($monitors as $m) {
            $statuses = [$m['current_status']];
            foreach ($m['children'] as $child) {
                $statuses[] = $child['current_status'];
            }

            if (in_array('down', $statuses, true)) {
                $overallStatus = 'major_outage';
                break;
            }
            if (in_array('degraded', $statuses, true) && $overallStatus !== 'major_outage') {
                $overallStatus = 'degraded';
            }
        }

        View::render('public/index', [
            'monitors'      => $monitors,
            'incidents'     => $incidents,
            'maintenances'  => $maintenances,
            'overallStatus' => $overallStatus
        ], 'layouts/public');
    }

    public function subscribe(): void
    {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $smtpHost = setting('smtp_host', '');

        if ($email && !empty($smtpHost)) {
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
            $subscriberService = new SubscriberService($mailer);
            $subscriberService->subscribe($email);
        }

        header('Location: /?subscribed=1');
        exit;
    }

    public function verify(): void
    {
        $token = trim($_GET['token'] ?? '');
        if ($token) {
            $stmt = $this->db->prepare("UPDATE subscribers SET is_verified = 1 WHERE token = ?");
            $stmt->execute([$token]);
        }

        header('Location: /?verified=1');
        exit;
    }

    public function unsubscribe(): void
    {
        $token = trim($_GET['token'] ?? '');
        if ($token) {
            $stmt = $this->db->prepare("DELETE FROM subscribers WHERE token = ?");
            $stmt->execute([$token]);
        }

        header('Location: /?unsubscribed=1');
        exit;
    }
}
