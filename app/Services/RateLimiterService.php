<?php
// path: app/Services/RateLimiterService.php

namespace App\Services;

use App\Core\Database;
use PDO;

class RateLimiterService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Check if a client IP is currently blocked.
     */
    public function isBlocked(string $ip, string $action = 'login'): bool
    {
        if (setting('rate_limit_enabled', '1') !== '1') {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT blocked_until FROM rate_limits 
            WHERE ip_address = ? AND action = ? AND blocked_until > NOW()
        ");
        $stmt->execute([$ip, $action]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Get remaining minutes of lockout for display.
     */
    public function getLockoutRemainingMinutes(string $ip, string $action = 'login'): int
    {
        $stmt = $this->db->prepare("
            SELECT TIMESTAMPDIFF(MINUTE, NOW(), blocked_until) 
            FROM rate_limits 
            WHERE ip_address = ? AND action = ? AND blocked_until > NOW()
        ");
        $stmt->execute([$ip, $action]);
        $minutes = (int)$stmt->fetchColumn();
        return max(1, $minutes);
    }

    /**
     * Record a failed attempt and trigger block if threshold is reached.
     */
    public function recordFailedAttempt(string $ip, string $action = 'login'): void
    {
        if (setting('rate_limit_enabled', '1') !== '1') {
            return;
        }

        $maxAttempts    = (int)setting('rate_limit_max_attempts', '5');
        $lockoutMinutes = (int)setting('rate_limit_lockout_minutes', '15');

        // Clean expired blocks
        $this->db->prepare("DELETE FROM rate_limits WHERE blocked_until <= NOW()")->execute();

        $stmt = $this->db->prepare("SELECT attempts FROM rate_limits WHERE ip_address = ? AND action = ?");
        $stmt->execute([$ip, $action]);
        $current = $stmt->fetchColumn();

        if ($current !== false) {
            $newAttempts = (int)$current + 1;
            $blockedUntil = ($newAttempts >= $maxAttempts) 
                ? date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes")) 
                : null;

            $update = $this->db->prepare("
                UPDATE rate_limits 
                SET attempts = ?, last_attempt = NOW(), blocked_until = ? 
                WHERE ip_address = ? AND action = ?
            ");
            $update->execute([$newAttempts, $blockedUntil, $ip, $action]);
        } else {
            $insert = $this->db->prepare("
                INSERT INTO rate_limits (ip_address, action, attempts, last_attempt, blocked_until) 
                VALUES (?, ?, 1, NOW(), NULL)
            ");
            $insert->execute([$ip, $action]);
        }
    }

    /**
     * Clear rate limit records on successful authentication.
     */
    public function clearAttempts(string $ip, string $action = 'login'): void
    {
        $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND action = ?");
        $stmt->execute([$ip, $action]);
    }
}