<?php
// path: app/Services/ApiKeyService.php

namespace App\Services;

use App\Core\Database;
use PDO;

class ApiKeyService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Generate a new cryptographically secure API key and store its SHA-256 hash.
     */
    public function generate(string $name): array
    {
        $randomBytes = bin2hex(random_bytes(24));
        $plainKey    = "mss_live_{$randomBytes}";
        $keyPrefix   = substr($plainKey, 0, 14) . '...';
        $keyHash     = hash('sha256', $plainKey);

        $stmt = $this->db->prepare("INSERT INTO api_keys (name, key_hash, key_prefix) VALUES (?, ?, ?)");
        $stmt->execute([trim($name), $keyHash, $keyPrefix]);

        return [
            'id'        => (int)$this->db->lastInsertId(),
            'name'      => $name,
            'plain_key' => $plainKey, // Shown only once to user
            'prefix'    => $keyPrefix
        ];
    }

    /**
     * Authenticate an incoming request bearer token.
     */
    public function authenticate(?string $plainKey): bool
    {
        if (empty($plainKey) || !str_starts_with($plainKey, 'mss_live_')) {
            return false;
        }

        $hash = hash('sha256', $plainKey);
        $stmt = $this->db->prepare("SELECT id FROM api_keys WHERE key_hash = ? LIMIT 1");
        $stmt->execute([$hash]);
        $keyId = $stmt->fetchColumn();

        if ($keyId) {
            // Update last used timestamp
            $upd = $this->db->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?");
            $upd->execute([$keyId]);
            return true;
        }

        return false;
    }

    public function revoke(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM api_keys WHERE id = ?");
        return $stmt->execute([$id]);
    }
}