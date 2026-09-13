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
        // 1. Fetch root monitors ordered by custom sort order
        $stmt = $this->db->query("
            SELECT * FROM monitors 
            WHERE is_active = 1 AND parent_id IS NULL 
            ORDER BY sort_order ASC, name ASC
        ");
        $monitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Attach sub-services to each parent
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

        // 4. Active Incidents with updates
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

        // 5. SEPARATE HEALTH CALCULATIONS: Primary vs Secondary
        $primaryMonitors   = array_values(array_filter($monitors, fn($m) => ((int)($m['is_primary'] ?? 0) === 1)));
        $secondaryMonitors = array_values(array_filter($monitors, fn($m) => ((int)($m['is_primary'] ?? 0) === 0)));

        // A. Primary Core Health (Powers Top Banner)
        $primaryStatus = 'operational';
        foreach ($primaryMonitors as $m) {
            if ($m['current_status'] === 'down') {
                $primaryStatus = 'major_outage';
                break;
            } elseif ($m['current_status'] === 'degraded' && $primaryStatus !== 'major_outage') {
                $primaryStatus = 'degraded';
            }
        }

        // B. Secondary External Health (Powers Third-Party Section Banner)
        $secondaryStatus = 'operational';
        foreach ($secondaryMonitors as $m) {
            if ($m['current_status'] === 'down') {
                $secondaryStatus = 'major_outage';
            } elseif ($m['current_status'] === 'degraded' && $secondaryStatus !== 'major_outage') {
                $secondaryStatus = 'degraded';
            }

            foreach ($m['children'] as $child) {
                if ($child['current_status'] === 'down') {
                    $secondaryStatus = 'major_outage';
                } elseif ($child['current_status'] === 'degraded' && $secondaryStatus !== 'major_outage') {
                    $secondaryStatus = 'degraded';
                }
            }
        }

        View::render('public/index', [
            'monitors'          => $monitors,
            'primaryMonitors'   => $primaryMonitors,
            'secondaryMonitors' => $secondaryMonitors,
            'incidents'         => $incidents,
            'maintenances'      => $maintenances,
            'primaryStatus'     => $primaryStatus,
            'secondaryStatus'   => $secondaryStatus,
            'overallStatus'     => $primaryStatus // Embed widgets only alert if core services fail
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
