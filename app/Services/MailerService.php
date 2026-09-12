<?php
// path: app/Services/MailerService.php

namespace App\Services;

class MailerService
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption; // 'starttls', 'ssl' or 'none'
    private string $fromEmail;
    private string $fromName;

    public function __construct(array $smtpConfig)
    {
        $this->host       = $smtpConfig['host'];
        $this->port       = (int)$smtpConfig['port'];
        $this->username   = $smtpConfig['username'];
        $this->password   = $smtpConfig['password'];
        $this->encryption = strtolower($smtpConfig['encryption'] ?? 'starttls');
        $this->fromEmail  = $smtpConfig['from_email'];
        $this->fromName   = $smtpConfig['from_name'] ?? 'My System Status';
    }

    public function send(string $toEmail, string $subject, string $htmlBody): bool
    {
        $timeout = 15;
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $socket = stream_socket_client($remote, $errno, $errstr, $timeout);

        if (!$socket) {
            error_log("SMTP Error: $errstr ($errno)");
            return false;
        }

        $this->readResponse($socket);

        $this->sendCommand($socket, "EHLO " . gethostname());

        if ($this->encryption === 'starttls') {
            $this->sendCommand($socket, "STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->sendCommand($socket, "EHLO " . gethostname());
        }

        if (!empty($this->username)) {
            $this->sendCommand($socket, "AUTH LOGIN");
            $this->sendCommand($socket, base64_encode($this->username));
            $this->sendCommand($socket, base64_encode($this->password));
        }

        $this->sendCommand($socket, "MAIL FROM: <{$this->fromEmail}>");
        $this->sendCommand($socket, "RCPT TO: <{$toEmail}>");
        $this->sendCommand($socket, "DATA");

        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromEmail}>",
            "To: <{$toEmail}>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date('r'),
            "X-Mailer: My System Status Engine"
        ];

        $data = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
        $this->sendCommand($socket, $data);
        $this->sendCommand($socket, "QUIT");

        fclose($socket);
        return true;
    }

    private function sendCommand($socket, string $cmd): string
    {
        fwrite($socket, $cmd . "\r\n");
        return $this->readResponse($socket);
    }

    private function readResponse($socket): string
    {
        $response = "";
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === " ") {
                break;
            }
        }
        return $response;
    }
}