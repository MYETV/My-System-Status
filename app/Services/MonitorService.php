<?php
// path: app/Services/MonitorService.php

namespace App\Services;

use App\Core\Database;
use PDO;

class MonitorService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Run checks for all monitors due for execution.
     */
    public function runPendingChecks(): void
    {
        $stmt = $this->db->prepare("
            SELECT * FROM monitors 
            WHERE is_active = 1 
            AND (last_check IS NULL OR TIMESTAMPADD(SECOND, interval_seconds, last_check) <= NOW())
        ");
        $stmt->execute();
        $monitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($monitors)) {
            return;
        }

        $httpMonitors = [];
        foreach ($monitors as $monitor) {
            match ($monitor['type']) {
                'http' => $httpMonitors[] = $monitor,
                'port' => $this->checkPort($monitor),
                'ssl'  => $this->checkSsl($monitor),
                'ping' => $this->checkPing($monitor),
            };
        }

        if (!empty($httpMonitors)) {
            $this->checkHttpConcurrent($httpMonitors);
        }
    }

    /**
     * Run concurrent HTTP checks using curl_multi.
     */
    private function checkHttpConcurrent(array $monitors): void
    {
        $mh = curl_multi_init();
        $curlHandles = [];

        foreach ($monitors as $monitor) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $monitor['target'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => false,
                CURLOPT_TIMEOUT        => (int)$monitor['timeout_seconds'],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'MySystemStatus/1.0 (+https://mysystemstatus.myetv.tv)'
            ]);
            curl_multi_add_handle($mh, $ch);
            $curlHandles[(int)$ch] = ['handle' => $ch, 'monitor' => $monitor, 'start' => microtime(true)];
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        foreach ($curlHandles as $item) {
            $ch = $item['handle'];
            $monitor = $item['monitor'];
            $duration = (int)((microtime(true) - $item['start']) * 1000);
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            $status = ($httpCode >= 200 && $httpCode < 400) ? 'up' : 'down';

            $this->logResult(
                monitorId: (int)$monitor['id'],
                status: $status,
                responseTimeMs: $duration,
                httpCode: $httpCode ?: null,
                error: $curlError ?: null
            );

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
    }

    /**
     * TCP Port ping.
     */
    private function checkPort(array $monitor): void
    {
        $start = microtime(true);
        $fp = @fsockopen($monitor['target'], (int)$monitor['port'], $errno, $errstr, (float)$monitor['timeout_seconds']);
        $duration = (int)((microtime(true) - $start) * 1000);

        if ($fp) {
            fclose($fp);
            $this->logResult((int)$monitor['id'], 'up', $duration);
        } else {
            $this->logResult((int)$monitor['id'], 'down', $duration, null, "$errstr ($errno)");
        }
    }

    /**
     * SSL Certificate Expiration Check.
     */
    private function checkSsl(array $monitor): void
    {
        $url = parse_url($monitor['target'], PHP_URL_HOST) ?? $monitor['target'];
        $context = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
        $client = @stream_socket_client("ssl://{$url}:443", $errno, $errstr, (float)$monitor['timeout_seconds'], STREAM_CLIENT_CONNECT, $context);

        if ($client) {
            $params = stream_context_get_params($client);
            $cert = openssl_x509_parse($params["options"]["ssl"]["peer_certificate"]);
            $validTo = date('Y-m-d H:i:s', $cert['validTo_time_t']);

            $stmt = $this->db->prepare("UPDATE monitors SET ssl_expiration = ? WHERE id = ?");
            $stmt->execute([$validTo, $monitor['id']]);

            $this->logResult((int)$monitor['id'], 'up', 0);
            fclose($client);
        } else {
            $this->logResult((int)$monitor['id'], 'down', 0, null, "SSL Handshake Failed: $errstr");
        }
    }

    private function checkPing(array $monitor): void
    {
        $target = escapeshellarg($monitor['target']);
        $start = microtime(true);
        exec("ping -c 1 -W " . (int)$monitor['timeout_seconds'] . " {$target}", $output, $result);
        $duration = (int)((microtime(true) - $start) * 1000);

        if ($result === 0) {
            $this->logResult((int)$monitor['id'], 'up', $duration);
        } else {
            $this->logResult((int)$monitor['id'], 'down', $duration, null, "ICMP packet loss");
        }
    }

    private function logResult(int $monitorId, string $status, int $responseTimeMs, ?int $httpCode = null, ?string $error = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO monitor_logs (monitor_id, status, response_time_ms, http_code, error_message)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$monitorId, $status, $responseTimeMs, $httpCode, $error]);

        // Update monitor operational state
        $opStatus = ($status === 'up') ? 'operational' : 'down';
        $updateStmt = $this->db->prepare("
            UPDATE monitors 
            SET current_status = ?, last_check = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$opStatus, $monitorId]);
    }
}