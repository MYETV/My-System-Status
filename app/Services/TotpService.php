<?php
// path: app/Services/TotpService.php

namespace App\Services;

class TotpService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random 16-character Base32 secret key.
     */
    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Verify a 6-digit TOTP code against the secret key (with a +/- 1 step drift tolerance).
     */
    public function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        $currentTimeSlice = (int)(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            if (hash_equals($this->calculateCode($secret, $currentTimeSlice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate standard otpauth:// URL for authenticator apps.
     */
    public function getOtpAuthUrl(string $label, string $secret, string $issuer = 'My System Status'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($label) . '?' . http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'period' => 30,
            'digits' => 6
        ]);
    }

    private function calculateCode(string $secret, int $timeSlice): string
    {
        $secretKey = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);

        $offset = ord($hmac[19]) & 0xf;
        $hashPart = substr($hmac, $offset, 4);

        $value = unpack('N', $hashPart)[1] & 0x7fffffff;
        $modulo = $value % 1000000;

        return str_pad((string)$modulo, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $b32): string
    {
        $b32 = strtoupper($b32);
        $buffer = 0;
        $bufferSize = 0;
        $binary = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $val = strpos(self::BASE32_CHARS, $b32[$i]);
            if ($val === false) continue;

            $buffer = ($buffer << 5) | $val;
            $bufferSize += 5;

            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $binary .= chr(($buffer >> $bufferSize) & 0xff);
            }
        }

        return $binary;
    }
}