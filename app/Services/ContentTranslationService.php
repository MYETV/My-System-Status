<?php
// path: app/Services/ContentTranslationService.php

namespace App\Services;

use App\Core\Database;
use PDO;

class ContentTranslationService
{
    private PDO $db;
    private string $endpoint;
    private ?string $apiKey;

    public function __construct()
    {
        $this->db       = Database::getInstance();
        $this->endpoint = rtrim(setting('libretranslate_endpoint', ''), '/');
        $this->apiKey   = setting('libretranslate_api_key', '');
    }

    /**
     * Check if LibreTranslate is configured in settings.
     */
    public function isConfigured(): bool
    {
        return !empty($this->endpoint);
    }

    /**
     * Translate or retrieve cached translation for a specific entity field.
     */
    public function getOrTranslate(string $entityType, int $entityId, string $field, string $originalText, string $targetLocale, string $sourceLocale = 'en'): string
    {
        if (empty($originalText) || $targetLocale === $sourceLocale) {
            return $originalText;
        }

        // 1. Check if translation exists in database cache
        $stmt = $this->db->prepare("
            SELECT content FROM translations_cache 
            WHERE entity_type = ? AND entity_id = ? AND locale = ? AND field = ? 
            LIMIT 1
        ");
        $stmt->execute([$entityType, $entityId, $targetLocale, $field]);
        $cached = $stmt->fetchColumn();

        if ($cached !== false && !empty($cached)) {
            return (string)$cached; // Return instant cached translation (0ms)
        }

        // 2. If no cache and LibreTranslate is configured, query the API
        if (!$this->isConfigured()) {
            return $originalText;
        }

        $translated = $this->queryLibreTranslate($originalText, $sourceLocale, $targetLocale);

        // 3. Save to database cache
        if ($translated && $translated !== $originalText) {
            $saveStmt = $this->db->prepare("
                INSERT INTO translations_cache (entity_type, entity_id, locale, field, content) 
                VALUES (?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE content = VALUES(content), created_at = NOW()
            ");
            $saveStmt->execute([$entityType, $entityId, $targetLocale, $field, $translated]);
            return $translated;
        }

        return $originalText;
    }

    /**
     * Clear all cached translations for a specific entity (invoked on edit/update).
     */
    public function clearCache(string $entityType, int $entityId): void
    {
        $stmt = $this->db->prepare("DELETE FROM translations_cache WHERE entity_type = ? AND entity_id = ?");
        $stmt->execute([$entityType, $entityId]);
    }

    /**
     * Query LibreTranslate API endpoint.
     */
    private function queryLibreTranslate(string $text, string $from, string $to): string
    {
        $payload = [
            'q'      => $text,
            'source' => $from,
            'target' => $to,
            'format' => 'text'
        ];

        if (!empty($this->apiKey)) {
            $payload['api_key'] = $this->apiKey;
        }

        $url = $this->endpoint . (str_ends_with($this->endpoint, '/translate') ? '' : '/translate');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 10
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $res) {
            $json = json_decode($res, true);
            if (!empty($json['translatedText'])) {
                return (string)$json['translatedText'];
            }
        }

        return $text;
    }
}