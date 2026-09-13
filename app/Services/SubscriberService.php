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

        // 5. Send Confirmation Email with Professional Template
        $baseUrl = SettingService::getAppUrl();
        $appName = htmlspecialchars(setting('app_name', 'My System Status'));
        $confirmUrl = "{$baseUrl}/subscribe/verify?token={$token}";

        $targetName = 'All Platform Services';
        if ($monitorId) {
            $mStmt = $this->db->prepare("SELECT name FROM monitors WHERE id = ?");
            $mStmt->execute([$monitorId]);
            $targetName = $mStmt->fetchColumn() ?: 'Selected Service';
        }

        $content = "
            <h2 style='margin-top:0; color:#0f172a; font-size:20px; font-weight:700;'>Confirm Your Status Subscription</h2>
            <p style='color:#475569; font-size:15px; line-height:1.6;'>
                You requested to receive real-time incident and maintenance alerts for:
            </p>
            <div style='background-color:#f1f5f9; padding:14px 18px; border-radius:8px; font-weight:600; color:#1e293b; margin-bottom:20px;'>
                📡 {$targetName}
            </div>
            <p style='color:#475569; font-size:15px; line-height:1.6;'>
                Click the button below to verify your email address and activate your notifications:
            </p>
            <div style='text-align:center; margin:30px 0;'>
                <a href='{$confirmUrl}' style='display:inline-block; background-color:#0d6efd; color:#ffffff; font-weight:700; font-size:15px; text-decoration:none; padding:12px 28px; border-radius:6px; box-shadow:0 2px 4px rgba(13,110,253,0.25);'>
                    Confirm Subscription
                </a>
            </div>
            <p style='color:#94a3b8; font-size:13px; margin-top:25px;'>
                Button not working? Copy and paste this link into your browser:<br>
                <a href='{$confirmUrl}' style='color:#0d6efd; word-break:break-all;'>{$confirmUrl}</a>
            </p>
        ";

        $html = $this->renderEmailTemplate($appName, $content, $baseUrl);
        $this->mailer->send($email, "Action Required: Confirm subscription to {$appName}", $html);

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

        $content = "
            <h2 style='margin-top:0; color:#0f172a; font-size:20px; font-weight:700;'>Unsubscribe Confirmation Request</h2>
            <p style='color:#475569; font-size:15px; line-height:1.6;'>
                We received a request to unsubscribe <strong>{$email}</strong> from all status updates and probe notifications on <strong>{$appName}</strong>.
            </p>
            <div style='background-color:#fee2e2; border-left:4px solid #ef4444; padding:12px 16px; border-radius:4px; color:#991b1b; font-size:14px; margin:20px 0;'>
                ⚠️ <strong>Security Notice:</strong> This link is valid for <strong>60 minutes only</strong>.
            </div>
            <p style='color:#475569; font-size:15px; line-height:1.6;'>
                If you wish to proceed and permanently delete all your subscriptions, click below:
            </p>
            <div style='text-align:center; margin:30px 0;'>
                <a href='{$unsubUrl}' style='display:inline-block; background-color:#dc3545; color:#ffffff; font-weight:700; font-size:15px; text-decoration:none; padding:12px 28px; border-radius:6px; box-shadow:0 2px 4px rgba(220,53,69,0.25);'>
                    Unsubscribe from All Alerts
                </a>
            </div>
            <p style='color:#94a3b8; font-size:13px; margin-top:25px;'>
                If you did not request this, no action is needed. Your subscriptions will remain active.<br>
                Link: <a href='{$unsubUrl}' style='color:#dc3545; word-break:break-all;'>{$unsubUrl}</a>
            </p>
        ";

        $html = $this->renderEmailTemplate($appName, $content, $baseUrl);
        $this->mailer->send($email, "Unsubscribe Request for {$appName}", $html);

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
            $del = $this->db->prepare("DELETE FROM subscribers WHERE email = ?");
            $del->execute([$email]);

            $this->db->prepare("DELETE FROM unsubscribe_requests WHERE token = ?")->execute([$token]);
            return true;
        }

        return false;
    }

    /**
     * Professional responsive HTML email template wrapper.
     */
    private function renderEmailTemplate(string $appName, string $bodyContent, string $baseUrl): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$appName}</title>
        </head>
        <body style='margin:0; padding:0; background-color:#f8fafc; font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
            <table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0' style='background-color:#f8fafc; padding:40px 15px;'>
                <tr>
                    <td align='center'>
                        <!-- Main Card -->
                        <table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0' style='max-width:580px; background-color:#ffffff; border-radius:10px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);'>
                            <!-- Top Brand Header -->
                            <tr>
                                <td style='background-color:#ffffff; padding:25px 35px; border-bottom:1px solid #f1f5f9;'>
                                    <table role='presentation' width='100%' cellspacing='0' cellpadding='0' border='0'>
                                        <tr>
                                            <td>
                                                <a href='{$baseUrl}' style='text-decoration:none; color:#0f172a; font-size:18px; font-weight:700; letter-spacing:-0.5px;'>
                                                    🛡️ {$appName}
                                                </a>
                                            </td>
                                            <td align='right'>
                                                <a href='{$baseUrl}' style='font-size:13px; color:#0d6efd; text-decoration:none; font-weight:600;'>
                                                    View Live Status &rarr;
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Email Body -->
                            <tr>
                                <td style='padding:35px;'>
                                    {$bodyContent}
                                </td>
                            </tr>

                            <!-- Footer with Direct Unsubscribe Option -->
                            <tr>
                                <td style='background-color:#f8fafc; padding:20px 35px; border-top:1px solid #f1f5f9; text-align:center;'>
                                    <p style='margin:0 0 6px 0; font-size:12px; color:#64748b;'>
                                        You received this email because an alert subscription was initiated on <a href='{$baseUrl}' style='color:#64748b; text-decoration:underline;'>{$appName}</a>.
                                    </p>
                                    <p style='margin:0; font-size:12px; color:#94a3b8;'>
                                        Need to cancel your alerts? <a href='{$baseUrl}' style='color:#dc3545; text-decoration:underline;'>Unsubscribe / Manage alerts here</a>.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
}
