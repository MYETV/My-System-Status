<!-- path: app/Views/auth/2fa.php -->
<?php
$appName = htmlspecialchars(setting('app_name', 'My System Status'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication &mdash; <?= $appName ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
<div class="container" style="max-width: 380px;">
    <div class="card border-0 shadow-sm rounded-4 p-3">
        <div class="card-body">
            <div class="text-center mb-4">
                <i class="bi bi-shield-check text-primary" style="font-size: 3rem;"></i>
                <h4 class="fw-bold mt-2">Security Verification</h4>
                <p class="text-muted small">Enter the 6-digit verification code from your authenticator app</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger py-2 small text-center">
                    Invalid or expired 2FA code. Please try again.
                </div>
            <?php endif; ?>

            <form action="/auth/2fa-verify" method="POST">
                <div class="mb-3">
                    <input type="text" name="code" class="form-control form-control-lg text-center fw-bold fs-3 tracking-wide" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus autocomplete="one-time-code">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Verify & Sign In</button>
            </form>

            <div class="text-center mt-4">
                <a href="/auth/login" class="text-decoration-none small text-muted">&larr; Cancel and return to Login</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>