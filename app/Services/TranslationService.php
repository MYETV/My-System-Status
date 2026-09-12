<?php
// path: app/Services/TranslationService.php

namespace App\Services;

class TranslationService
{
    private string $endpoint;
    private ?string $apiKey;
    private string $langDir;

    public function __construct(string $endpoint = 'https://libretranslate.com', ?string $apiKey = null)
    {
        $this->endpoint = rtrim($endpoint, '/');
        $this->apiKey   = $apiKey;
        $this->langDir  = dirname(__DIR__, 2) . '/languages';
    }

    /**
     * Synchronize and translate missing keys from en.json into a target language.
     */
    public function syncAndTranslate(string $targetLang): array
    {
        $sourceFile = $this->langDir . '/en.json';
        $targetFile = $this->langDir . "/{$targetLang}.json";

        if (!file_exists($sourceFile)) {
            throw new \Exception("Source file en.json not found.");
        }

        $sourceData = json_decode(file_get_contents($sourceFile), true) ?? [];
        $targetData = file_exists($targetFile) ? (json_decode(file_get_contents($targetFile), true) ?? []) : [];

        $translatedCount = 0;
        $targetData = $this->processArray($sourceData, $targetData, $targetLang, $translatedCount);

        file_put_contents($targetFile, json_encode($targetData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'status' => 'success',
            'translated_keys' => $translatedCount,
            'file' => "{$targetLang}.json"
        ];
    }

    private function processArray(array $source, array $target, string $targetLang, int &$count): array
    {
        foreach ($source as $key => $value) {
            if (is_array($value)) {
                $target[$key] = $this->processArray($value, $target[$key] ?? [], $targetLang, $count);
            } else {
                if (!isset($target[$key]) || empty($target[$key])) {
                    $target[$key] = $this->requestTranslation((string)$value, 'en', $targetLang);
                    $count++;
                }
            }
        }
        return $target;
    }

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

        $ch = curl_init($this->endpoint . '/translate');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15
        ]);

        $res = curl_exec($ch);
        curl_close($ch);

        $json = json_decode((string)$res, true);
        return $json['translatedText'] ?? $text;
    }
}