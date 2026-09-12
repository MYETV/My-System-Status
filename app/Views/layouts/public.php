<!-- path: app/Views/layouts/public.php -->
<?php
use App\Services\SettingService;
use App\Services\DateService;
use App\Core\I18n;

$appName = htmlspecialchars(SettingService::get('app_name', 'My System Status'));
$appUrl  = SettingService::getAppUrl();
$userTz  = DateService::getActiveTimezone();
$locale  = I18n::getLocale();
?>
<!DOCTYPE html>
<html lang="<?= $locale ?>" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $appName ?> — Status</title>

    <!-- Bootstrap 5.3 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Custom Modern UI Enhancements -->
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .footer {
            margin-top: auto;
            border-top: 1px solid #e2e8f0;
            background: #ffffff;
            padding: 24px 0;
        }
        .uptime-day {
            transition: transform 0.15s ease-in-out;
            cursor: pointer;
        }
        .uptime-day:hover {
            transform: scaleY(1.3);
        }
    </style>
</head>
<body>

<!-- Public Top Navigation -->
<nav class="navbar navbar-expand-lg bg-white border-bottom py-3 shadow-sm">
    <div class="container" style="max-width: 900px;">
        <a class="navbar-brand d-flex align-items-center gap-2 text-dark text-decoration-none" href="/">
            <i class="bi bi-shield-check text-primary fs-3"></i>
            <span class="fs-4"><?= $appName ?></span>
        </a>

        <div class="d-flex align-items-center gap-2">
            <!-- Timezone Switcher -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" title="Timezone">
                    <i class="bi bi-clock"></i>
                    <span class="d-none d-sm-inline"><?= htmlspecialchars($userTz) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="max-height: 280px; overflow-y: auto;">
                    <li><h6 class="dropdown-header">Select Timezone</h6></li>
                    <?php foreach (['UTC', 'Europe/Rome', 'Europe/London', 'Europe/Paris', 'America/New_York', 'America/Los_Angeles', 'Asia/Tokyo'] as $tz): ?>
                        <li>
                            <a class="dropdown-item <?= $userTz === $tz ? 'active fw-bold' : '' ?>" href="/timezone/set?timezone=<?= urlencode($tz) ?>">
                                <?= $tz ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Language Switcher -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle text-uppercase" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-translate me-1"></i><?= $locale ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item <?= $locale === 'en' ? 'active' : '' ?>" href="/lang/switch?lang=en">English</a></li>
                    <li><a class="dropdown-item <?= $locale === 'it' ? 'active' : '' ?>" href="/lang/switch?lang=it">Italiano</a></li>
                </ul>
            </div>

            <!-- Admin Login Link -->
            <a href="/admin" class="btn btn-sm btn-primary">
                <i class="bi bi-person-fill-lock me-1"></i> Admin
            </a>
        </div>
    </div>
</nav>

<!-- Page Content Injection -->
<main class="flex-grow-1">
    <?= $content ?>
</main>

<!-- Public Footer -->
<footer class="footer">
    <div class="container text-center text-muted small" style="max-width: 900px;">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <span>&copy; <?= date('Y') ?> <?= $appName ?>. All rights reserved.</span>
            <div class="d-flex gap-3 mt-2 mt-sm-0">
                <a href="https://developers.myetv.tv" target="_blank" class="text-decoration-none text-muted">API</a>
                <a href="https://github.com/myetv" target="_blank" class="text-decoration-none text-muted">Powered by My System Status</a>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Timezone Auto-Detection Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Enable Bootstrap Tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));

    // Auto-detect browser timezone on first visit
    const hasTzCookie = document.cookie.split(';').some(item => item.trim().startsWith('user_timezone='));
    if (!hasTzCookie) {
        try {
            const detectedTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (detectedTz) {
                fetch('/timezone/set', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'timezone=' + encodeURIComponent(detectedTz)
                });
            }
        } catch (e) {
            console.warn('Could not auto-detect timezone', e);
        }
    }
});
</script>
</body>
</html>