<?php
// path: app/Controllers/TimezoneController.php

namespace App\Controllers;

use App\Services\DateService;

class TimezoneController
{
    public function set(): void
    {
        $timezone = trim($_POST['timezone'] ?? $_GET['timezone'] ?? '');

        if (!empty($timezone) && DateService::isValidTimezone($timezone)) {
            $_SESSION['user_timezone'] = $timezone;
            setcookie('user_timezone', $timezone, [
                'expires'  => time() + (86400 * 365), // 1 year
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
        }

        // Return JSON for AJAX or redirect back
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'timezone' => $timezone]);
            exit;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        header("Location: {$referer}");
        exit;
    }
}