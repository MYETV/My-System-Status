<?php
// path: app/Services/TranslationService.php

namespace App\Services;

use Exception;

class TranslationService
{
    private string $endpoint;
    private ?string $apiKey;
    private string $langDir;

    public function __construct(string $endpoint, ?string $apiKey = null)
    {
        $this->endpoint = rtrim($endpoint, '/');
        $this->apiKey   = !empty($apiKey) ? $apiKey : null;
        $this->langDir  = dirname(__DIR__, 2) . '/languages';
    }

    /**
     * Synchronize and translate missing or untranslated keys from en.json into a target language.
     */
    public function syncAndTranslate(string $targetLang): array
    {
        $sourceFile = $this->langDir . '/en.json';
        $targetFile = $this->langDir . "/{$targetLang}.json";

        if (!file_exists($sourceFile)) {
            throw new Exception("Source file languages/en.json not found.");
        }

        $sourceData = json_decode(file_get_contents($sourceFile), true) ?? [];
        $targetData = file_exists($targetFile) ? (json_decode(file_get_contents($targetFile), true) ?? []) : [];

        $translatedCount = 0;
        $targetData = $this->processArray($sourceData, $targetData, $targetLang, $translatedCount);

        // Save pretty JSON with unicode characters (e.g. è, à, 日本語, العربية)
        file_put_contents($targetFile, json_encode($targetData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'status'          => 'success',
            'translated_keys' => $translatedCount,
            'file'            => "{$targetLang}.json"
        ];
    }

    /**
     * Recursively process nested JSON keys.
     * Translates if key is missing, empty, OR still matches the original English text!
     */
    private function processArray(array $source, array $target, string $targetLang, int &$count): array
    {
        foreach ($source as $key => $value) {
            if (is_array($value)) {
                $target[$key] = $this->processArray($value, $target[$key] ?? [], $targetLang, $count);
            } else {
                $sourceText = (string)$value;
                $currentVal = (string)($target[$key] ?? '');

                // Translate if:
                // 1. Target key does not exist
                // 2. Target key is empty
                // 3. Target value is identical to English source (means it was never translated before!)
                $needsTranslation = !isset($target[$key]) || empty($currentVal) || ($targetLang !== 'en' && $currentVal === $sourceText);

                if ($needsTranslation && !empty($sourceText)) {
                    $translated = $this->requestTranslation($sourceText, 'en', $targetLang);
                    $target[$key] = $translated;
                    $count++;
                }
            }
        }
        return $target;
    }

    /**
     * Call LibreTranslate API with strict endpoint normalization.
     */
    private function requestTranslation(string $text, string $from, string $to): string
    {
        $payload = [
            'q'      => $text,
            'source' => $from,
            'target' => $to,
            'format' => 'text'
        ];

        if ($this->apiKey) {
            $payload['api_key'] = $this->apiKey;
        }

        // Normalize URL: ensure /translate is only appended once!
        $url = $this->endpoint;
        if (!str_ends_with($url, '/translate')) {
            $url .= '/translate';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 12
        ]);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception("LibreTranslate connection error: {$curlErr}");
        }

        if ($httpCode !== 200 || !$res) {
            throw new Exception("LibreTranslate failed with HTTP {$httpCode} at {$url}: {$res}");
        }

        $json = json_decode((string)$res, true);
        if (!empty($json['error'])) {
            throw new Exception("LibreTranslate API error: " . $json['error']);
        }

        return $json['translatedText'] ?? $text;
    }
}
