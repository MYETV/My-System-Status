<?php
// path: app/Services/TurnstileService.php

namespace App\Services;

class TurnstileService
{
    /**
     * Verify Turnstile response token with Cloudflare API.
     */
    public static function verify(?string $token, ?string $remoteIp = null): bool
    {
        $enabled = (bool)setting('turnstile_enabled', '0');
        if (!$enabled) {
            return true; // Bypass check if not enabled in settings
        }

        $secretKey = setting('turnstile_secret_key');
        if (empty($secretKey) || empty($token)) {
            return false;
        }

        $postData = [
            'secret'   => $secretKey,
            'response' => $token,
            'remoteip' => $remoteIp ?? $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode((string)$response, true);
        return !empty($json['success']);
    }
}