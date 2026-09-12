<!-- path: app/Views/layouts/admin.php -->
<?php
use App\Services\SettingService;
use App\Services\DateService;

$appName = htmlspecialchars(setting('app_name', 'My System Status'));
$appUrl  = app_url();
$userTz  = DateService::getActiveTimezone();
?>
<!DOCTYPE html>
<html lang="<?= \App\Core\I18n::getLocale() ?>" data-bs-theme="light">
<head>
    <?php require __DIR__ . '/header.php'; ?>
</head>
<body class="bg-body-tertiary">

<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- Page Content Wrapper -->
    <div id="page-content-wrapper" class="w-100">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-2 shadow-sm">
            <button class="btn btn-sm btn-outline-secondary" id="sidebarToggle" title="Toggle Sidebar">
                <i class="bi bi-list fs-5"></i>
            </button>

            <div class="ms-auto d-flex align-items-center gap-3">
                <!-- Public Page Link -->
                <a href="<?= $appUrl ?>/" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i> <?= __('nav.view_public_page') ?>
                </a>

                <!-- Timezone Selector -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Change Timezone">
                        <i class="bi bi-clock me-1"></i> <?= htmlspecialchars($userTz) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="max-height: 280px; overflow-y: auto;">
                        <li><h6 class="dropdown-header">Timezone</h6></li>
                        <?php foreach (['UTC', 'Europe/Rome', 'Europe/London', 'America/New_York', 'America/Los_Angeles', 'Asia/Tokyo'] as $tz): ?>
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
                    <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-translate me-1"></i> <?= strtoupper(\App\Core\I18n::getLocale()) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="/lang/switch?lang=en">English (EN)</a></li>
                        <li><a class="dropdown-item" href="/lang/switch?lang=it">Italiano (IT)</a></li>
                    </ul>
                </div>

                <!-- User Profile Link (Clickable with icon) -->
                <a href="/admin/profile" class="btn btn-sm btn-outline-dark d-flex align-items-center gap-2 text-decoration-none" title="Edit Profile, Password & 2FA">
                    <i class="bi bi-person-circle text-primary fs-6"></i>
                    <span class="fw-semibold"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                </a>

                <!-- Logout Button -->
                <a href="/auth/logout" class="btn btn-sm btn-danger" title="Sign Out">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </nav>

        <!-- Main View Dynamic Container -->
        <main class="container-fluid p-4">
            <?= $content ?? '' ?>
        </main>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>