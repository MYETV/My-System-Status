<?php
// path: app/Controllers/Admin/ProfileController.php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use App\Services\TotpService;
use PDO;

class ProfileController
{
    private PDO $db;

    public function __construct()
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $totp = new TotpService();
        
        // Generate or retain temporary secret for 2FA activation
        if (empty($_SESSION['pending_2fa_secret']) || (int)$user['two_factor_enabled'] === 1) {
            $_SESSION['pending_2fa_secret'] = $totp->generateSecret();
        }

        $qrCodeUrl = $totp->getOtpAuthUrl($user['email'], $_SESSION['pending_2fa_secret'], setting('app_name', 'My System Status'));

        View::render('admin/profile/index', [
            'user'            => $user,
            'tempSecret'      => $_SESSION['pending_2fa_secret'],
            'qrCodeUrl'       => $qrCodeUrl
        ]);
    }

    public function updateInfo(): void
    {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $this->db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $_SESSION['user_id']]);
            $_SESSION['user_name'] = $name;
        }

        header('Location: /admin/profile?saved=1');
        exit;
    }

    public function updatePassword(): void
    {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < 8 || $newPass !== $confirmPass) {
            header('Location: /admin/profile?error=password_mismatch');
            exit;
        }

        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($currentPass, $user['password'])) {
            header('Location: /admin/profile?error=invalid_current_password');
            exit;
        }

        $update = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([password_hash($newPass, PASSWORD_BCRYPT), $_SESSION['user_id']]);

        header('Location: /admin/profile?saved=1');
        exit;
    }

    public function enable2fa(): void
    {
        $code = trim($_POST['code'] ?? '');
        $secret = $_SESSION['pending_2fa_secret'] ?? '';

        $totp = new TotpService();
        if ($totp->verifyCode($secret, $code)) {
            $stmt = $this->db->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1 WHERE id = ?");
            $stmt->execute([$secret, $_SESSION['user_id']]);
            unset($_SESSION['pending_2fa_secret']);
            header('Location: /admin/profile?saved_2fa=enabled');
            exit;
        }

        header('Location: /admin/profile?error=invalid_2fa_code');
        exit;
    }

    public function disable2fa(): void
    {
        $password = $_POST['password'] ?? '';

        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $stmt = $this->db->prepare("UPDATE users SET two_factor_secret = NULL, two_factor_enabled = 0 WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            header('Location: /admin/profile?saved_2fa=disabled');
            exit;
        }

        header('Location: /admin/profile?error=invalid_password_2fa');
        exit;
    }
}