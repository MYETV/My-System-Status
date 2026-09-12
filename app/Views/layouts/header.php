<!-- path: app/Views/layouts/header.php -->
<?php
use App\Services\SettingService;

$appName = setting('app_name', 'My System Status');
$baseUrl = app_url();
$title   = $pageTitle ?? $appName;
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?></title>

<!-- Favicon -->
<link rel="icon" type="image/png" href="<?= $baseUrl ?>/assets/img/favicon.png">

<!-- Bootstrap 5 CSS & Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
    #wrapper {
        min-height: 100vh;
        overflow-x: hidden;
    }
    #sidebar-wrapper {
        min-height: 100vh;
        width: 260px;
        transition: margin 0.25s ease-out;
    }
    .sidebar-link {
        color: #495057;
        padding: 10px 16px;
        display: flex;
        align-items: center;
        text-decoration: none;
        border-radius: 6px;
        margin: 2px 8px;
        font-size: 0.95rem;
    }
    .sidebar-link:hover, .sidebar-link.active {
        background-color: #e9ecef;
        color: #0d6efd;
        font-weight: 600;
    }
    .sidebar-link i {
        font-size: 1.2rem;
        margin-right: 12px;
    }
</style>