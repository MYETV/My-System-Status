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
        unset($m);

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
        unset($incident);

        // 5. Separate Primary vs Secondary Monitors
        $primaryMonitors   = array_values(array_filter($monitors, fn($m) => ((int)($m['is_primary'] ?? 0) === 1)));
        $secondaryMonitors = array_values(array_filter($monitors, fn($m) => ((int)($m['is_primary'] ?? 0) === 0)));

        $primaryStatus = 'operational';
        foreach ($primaryMonitors as $m) {
            if ($m['current_status'] === 'down') {
                $primaryStatus = 'major_outage';
                break;
            } elseif ($m['current_status'] === 'degraded' && $primaryStatus !== 'major_outage') {
                $primaryStatus = 'degraded';
            }
        }

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

        // 6. Real 90-Day Probe Logs Aggregation
        $histStmt = $this->db->query("
            SELECT monitor_id, DATE(created_at) as check_date,
                   SUM(status = 'blackout') as blackout_count,
                   SUM(status = 'down') as down_count,
                   SUM(status = 'up') as up_count,
                   COUNT(*) as total_checks
            FROM monitor_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY monitor_id, DATE(created_at)
        ");
        $historyRows = $histStmt->fetchAll(PDO::FETCH_ASSOC);

        $uptimeHistory = [];
        foreach ($historyRows as $row) {
            $mId  = (int)$row['monitor_id'];
            $date = $row['check_date'];
            $uptimeHistory[$mId][$date] = [
                'blackout' => (int)$row['blackout_count'],
                'down'     => (int)$row['down_count'],
                'up'       => (int)$row['up_count'],
                'total'    => (int)$row['total_checks']
            ];
        }

        // 7. Map 90-Day Incidents and Maintenances per Monitor and Date
        $incStmt = $this->db->query("
            SELECT id, monitor_id, title, impact, status, created_at, updated_at 
            FROM incidents 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) OR status != 'resolved'
        ");
        $allIncidents = $incStmt->fetchAll(PDO::FETCH_ASSOC);

        $maintStmt = $this->db->query("
            SELECT id, monitor_id, title, description, status, start_time, end_time 
            FROM maintenances 
            WHERE start_time >= DATE_SUB(NOW(), INTERVAL 90 DAY) OR end_time >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $allMaintenances = $maintStmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('public/index', [
            'monitors'          => $monitors,
            'primaryMonitors'   => $primaryMonitors,
            'secondaryMonitors' => $secondaryMonitors,
            'incidents'         => $incidents,
            'maintenances'      => $maintenances,
            'primaryStatus'     => $primaryStatus,
            'secondaryStatus'   => $secondaryStatus,
            'uptimeHistory'     => $uptimeHistory,
            'allIncidents'      => $allIncidents,
            'allMaintenances'   => $allMaintenances,
            'overallStatus'     => $primaryStatus
        ], 'layouts/public');
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

    public function subscribe(): void
    {
        $email     = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $monitorId = !empty($_POST['monitor_id']) ? (int)$_POST['monitor_id'] : null;
        $smtpHost  = setting('smtp_host', '');

        if (!$email || empty($smtpHost)) {
            header('Location: /?error=' . urlencode('Email address invalid or SMTP not configured.'));
            exit;
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
        $subscriberService = new SubscriberService($mailer);
        $res = $subscriberService->subscribe($email, $monitorId);

        if ($res['status'] === 'error') {
            header('Location: /?sub_error=' . urlencode($res['message']));
        } else {
            header('Location: /?sub_success=' . urlencode($res['message']));
        }
        exit;
    }

    public function requestUnsubscribe(): void
    {
        $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
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
            $res = $subscriberService->requestUnsubscribeLink($email);

            if ($res['status'] === 'error') {
                header('Location: /?unsub_error=' . urlencode($res['message']));
            } else {
                header('Location: /?unsub_sent=1');
            }
            exit;
        }

        header('Location: /?unsub_error=' . urlencode('Please provide a valid email address.'));
        exit;
    }

    public function confirmUnsubscribe(): void
    {
        $token    = trim($_GET['token'] ?? '');
        $smtpHost = setting('smtp_host', '');

        if ($token && !empty($smtpHost)) {
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
            
            if ($subscriberService->confirmUnsubscribe($token)) {
                header('Location: /?unsub_success=1');
                exit;
            }
        }

        header('Location: /?unsub_error=' . urlencode('This unsubscribe link is invalid or has expired (links expire in 60 minutes).'));
        exit;
    }
}
