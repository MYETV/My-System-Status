<?php
// path: app/Controllers/Admin/SubscriberController.php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use App\Services\MailerService;
use App\Services\SettingService;
use PDO;

class SubscriberController
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
        $stmt = $this->db->query("SELECT * FROM subscribers ORDER BY id DESC");
        $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalCount    = count($subscribers);
        $verifiedCount = count(array_filter($subscribers, fn($s) => (int)$s['is_verified'] === 1));

        View::render('admin/subscribers/index', [
            'subscribers'   => $subscribers,
            'totalCount'    => $totalCount,
            'verifiedCount' => $verifiedCount
        ]);
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $this->db->prepare("DELETE FROM subscribers WHERE id = ?");
            $stmt->execute([$id]);
        }

        header('Location: /admin/subscribers');
        exit;
    }

    /**
     * Send broadcast email announcement to all verified subscribers
     */
    public function broadcast(): void
    {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($subject && $message) {
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
            $baseUrl = SettingService::getAppUrl();

            $stmt = $this->db->query("SELECT email, token FROM subscribers WHERE is_verified = 1");
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($recipients as $sub) {
                $unsubUrl = "{$baseUrl}/subscribe/unsubscribe?token={$sub['token']}";
                $body = nl2br(htmlspecialchars($message)) . 
                        "<hr><small style='color: #6c757d;'>To stop receiving these notifications, <a href='{$unsubUrl}'>unsubscribe here</a>.</small>";

                $mailer->send($sub['email'], $subject, $body);
            }
        }

        header('Location: /admin/subscribers?broadcast=sent');
        exit;
    }
}