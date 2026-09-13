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

    /**
     * Subscribe to all monitors (monitorId = null) or a single specific monitor.
     */
    public function subscribe(string $email, ?int $monitorId = null): array
    {
        $email = strtolower(trim($email));

        // 1. Rule: If already subscribed to ALL services, prevent single probe subscription
        if ($monitorId !== null) {
            $stmt = $this->db->prepare("SELECT id FROM subscribers WHERE email = ? AND monitor_id IS NULL AND is_verified = 1 LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                return [
                    'status'  => 'error',
                    'message' => 'This email is already subscribed to All Services notifications.'
                ];
            }
        }

        // 2. Rule: If already subscribed to individual monitors, prevent global subscription
        if ($monitorId === null) {
            $stmt = $this->db->prepare("SELECT id FROM subscribers WHERE email = ? AND monitor_id IS NOT NULL AND is_verified = 1 LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                return [
                    'status'  => 'error',
                    'message' => 'You are currently subscribed to individual services. Please unsubscribe first before switching to All Services.'
                ];
            }
        }

        // 3. Rule: Check if already subscribed to this exact probe
        $stmt = $this->db->prepare("SELECT id, is_verified FROM subscribers WHERE email = ? AND " . ($monitorId ? "monitor_id = ?" : "monitor_id IS NULL") . " LIMIT 1");
        $params = $monitorId ? [$email, $monitorId] : [$email];
        $stmt->execute($params);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing && (int)$existing['is_verified'] === 1) {
            return [
                'status'  => 'error',
                'message' => 'You are already actively subscribed to this alert.'
            ];
        }

        // 4. Generate verification token
        $token = bin2hex(random_bytes(32));
        if ($existing) {
            $stmtUpdate = $this->db->prepare("UPDATE subscribers SET token = ? WHERE id = ?");
            $stmtUpdate->execute([$token, $existing['id']]);
        } else {
            $stmtInsert = $this->db->prepare("INSERT INTO subscribers (email, monitor_id, token, is_verified) VALUES (?, ?, ?, 0)");
            $stmtInsert->execute([$email, $monitorId, $token]);
        }

        // 5. Send Confirmation Email
        $baseUrl = SettingService::getAppUrl();
        $appName = htmlspecialchars(setting('app_name', 'My System Status'));
        $confirmUrl = "{$baseUrl}/subscribe/verify?token={$token}";

        $targetName = 'All Services & Infrastructure';
        if ($monitorId) {
            $mStmt = $this->db->prepare("SELECT name FROM monitors WHERE id = ?");
            $mStmt->execute([$monitorId]);
            $targetName = $mStmt->fetchColumn() ?: 'Selected Service';
        }

        $html = "<div style='font-family: sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h3>{$appName}</h3>
                    <p>Please confirm your subscription to status updates for: <strong>{$targetName}</strong>.</p>
                    <p><a href='{$confirmUrl}' style='display: inline-block; padding: 10px 20px; background: #0d6efd; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold;'>Confirm Subscription</a></p>
                    <p><small style='color: #6c757d;'>Or paste this link in your browser: {$confirmUrl}</small></p>
                 </div>";

        $this->mailer->send($email, "Confirm subscription to {$appName}", $html);

        return [
            'status'  => 'success',
            'message' => 'A confirmation email has been sent. Please check your inbox.'
        ];
    }

    /**
     * Request a secure, time-limited (60 min) magic link to unsubscribe from ALL probes.
     */
    public function requestUnsubscribeLink(string $email): array
    {
        $email = strtolower(trim($email));

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM subscribers WHERE email = ?");
        $stmt->execute([$email]);
        if ((int)$stmt->fetchColumn() === 0) {
            return [
                'status'  => 'error',
                'message' => 'No active subscriptions found for this email address.'
            ];
        }

        // Create time-limited token (valid for 1 hour)
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+60 minutes'));

        // Clean expired tokens
        $this->db->prepare("DELETE FROM unsubscribe_requests WHERE expires_at < NOW()")->execute();

        $insert = $this->db->prepare("INSERT INTO unsubscribe_requests (email, token, expires_at) VALUES (?, ?, ?)");
        $insert->execute([$email, $token, $expiresAt]);

        $baseUrl = SettingService::getAppUrl();
        $appName = htmlspecialchars(setting('app_name', 'My System Status'));
        $unsubUrl = "{$baseUrl}/subscribe/confirm-unsubscribe?token={$token}";

        $html = "<div style='font-family: sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h3>{$appName}</h3>
                    <p>We received a request to unsubscribe <strong>{$email}</strong> from all incident and maintenance notifications.</p>
                    <p>Click the button below to confirm. <strong>This link is valid for 60 minutes</strong>:</p>
                    <p><a href='{$unsubUrl}' style='display: inline-block; padding: 10px 20px; background: #dc3545; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold;'>Unsubscribe from All Probes</a></p>
                    <p><small style='color: #6c757d;'>If you did not request this, you can safely ignore this email.</small></p>
                 </div>";

        $this->mailer->send($email, "Unsubscribe request for {$appName}", $html);

        return [
            'status'  => 'success',
            'message' => 'A time-limited unsubscribe link has been sent to your email.'
        ];
    }

    /**
     * Process time-limited unsubscribe token and purge all subscriptions.
     */
    public function confirmUnsubscribe(string $token): bool
    {
        $stmt = $this->db->prepare("SELECT email FROM unsubscribe_requests WHERE token = ? AND expires_at >= NOW() LIMIT 1");
        $stmt->execute([$token]);
        $email = $stmt->fetchColumn();

        if ($email) {
            // Delete all subscriptions linked to this email
            $del = $this->db->prepare("DELETE FROM subscribers WHERE email = ?");
            $del->execute([$email]);

            // Consume token
            $this->db->prepare("DELETE FROM unsubscribe_requests WHERE token = ?")->execute([$token]);
            return true;
        }

        return false;
    }
}
