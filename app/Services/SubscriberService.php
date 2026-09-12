<?php
// path: app/Services/SubscriberService.php

namespace App\Services;

use App\Core\Database;
use App\Services\SettingService;
use PDO;

class SubscriberService
{
    private PDO $db;
    private MailerService $mailer;

    public function __construct(MailerService $mailer)
    {
        $this->db = Database::getInstance();
        $this->mailer = $mailer;
    }

    public function subscribe(string $email): bool
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare("
            INSERT INTO subscribers (email, token, is_verified) 
            VALUES (?, ?, 0) 
            ON DUPLICATE KEY UPDATE token = VALUES(token)
        ");
        $stmt->execute([$email, $token]);

        $baseUrl = SettingService::getAppUrl();
        $appName = htmlspecialchars(SettingService::get('app_name', 'My System Status'));
        $confirmUrl = "{$baseUrl}/subscribe/verify?token={$token}";

        $html = "<h3>{$appName}</h3>
                 <p>Please confirm your subscription to status updates:</p>
                 <p><a href='{$confirmUrl}' style='padding: 10px 18px; background: #0d6efd; color: white; text-decoration: none; border-radius: 4px;'>Confirm Subscription</a></p>
                 <p><small>Link: {$confirmUrl}</small></p>";

        return $this->mailer->send($email, "Confirm your subscription - {$appName}", $html);
    }

    public function notifySubscribers(string $subject, string $message): void
    {
        $stmt = $this->db->prepare("SELECT email, token FROM subscribers WHERE is_verified = 1");
        $stmt->execute();
        $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $baseUrl = SettingService::getAppUrl();

        foreach ($subscribers as $sub) {
            $unsubUrl = "{$baseUrl}/subscribe/unsubscribe?token={$sub['token']}";
            $body = $message . "<hr><small>To stop receiving alerts, <a href='{$unsubUrl}'>unsubscribe here</a>.</small>";
            $this->mailer->send($sub['email'], $subject, $body);
        }
    }
}