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
        // 1. Monitors with status
        $monitors = $this->db->query("SELECT * FROM monitors WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        // 2. Active Maintenances
        $maintenances = $this->db->query("
            SELECT * FROM maintenances 
            WHERE status IN ('scheduled', 'in_progress') 
            AND end_time >= NOW() 
            ORDER BY start_time ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 3. Active Incidents with updates
        $incidents = $this->db->query("
            SELECT * FROM incidents 
            WHERE status != 'resolved' 
            ORDER BY created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($incidents as &$incident) {
            $stmt = $this->db->prepare("SELECT * FROM incident_updates WHERE incident_id = ? ORDER BY created_at DESC");
            $stmt->execute([$incident['id']]);
            $incident['updates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 4. Overall system status calculation
        $overallStatus = 'operational';
        foreach ($monitors as $m) {
            if ($m['current_status'] === 'down') {
                $overallStatus = 'major_outage';
                break;
            } elseif ($m['current_status'] === 'degraded' && $overallStatus !== 'major_outage') {
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
        if ($email) {
            $smtpConfig = [
                'host'       => SettingService::get('smtp_host', 'localhost'),
                'port'       => SettingService::get('smtp_port', '587'),
                'username'   => SettingService::get('smtp_user', ''),
                'password'   => SettingService::get('smtp_pass', ''),
                'encryption' => SettingService::get('smtp_encryption', 'starttls'),
                'from_email' => SettingService::get('smtp_from', 'noreply@myetv.tv'),
                'from_name'  => SettingService::get('app_name', 'My System Status')
            ];

            $mailer = new MailerService($smtpConfig);
            $subscriberService = new SubscriberService($mailer);
            $subscriberService->subscribe($email);
        }

        header('Location: /?subscribed=1');
        exit;
    }
}