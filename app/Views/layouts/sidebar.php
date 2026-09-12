<!-- path: app/Views/layouts/sidebar.php -->
<?php
use App\Services\SettingService;
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$appName = htmlspecialchars(SettingService::get('app_name', 'My System Status'));

$navItems = [
    ['uri' => '/admin',             'icon' => 'bi-speedometer2',      'label' => __('nav.dashboard')],
    ['uri' => '/admin/monitors',    'icon' => 'bi-activity',          'label' => __('nav.monitors')],
    ['uri' => '/admin/incidents',   'icon' => 'bi-exclamation-octagon','label' => __('nav.incidents')],
    ['uri' => '/admin/maintenance', 'icon' => 'bi-calendar-event',    'label' => __('nav.maintenance')],
    ['uri' => '/admin/subscribers', 'icon' => 'bi-envelope-check',    'label' => __('nav.subscribers')],
    ['uri' => '/admin/plugins',     'icon' => 'bi-puzzle',            'label' => __('nav.plugins')],
    ['uri' => '/admin/logs',        'icon' => 'bi-journal-text',      'label' => __('nav.logs')],
    ['uri' => '/admin/settings',    'icon' => 'bi-gear',              'label' => __('nav.settings')],
    ['uri' => '/admin/updater',     'icon' => 'bi-arrow-repeat',      'label' => __('nav.updates')]
];
?>
<div class="bg-white border-end shadow-sm" id="sidebar-wrapper">
    <div class="sidebar-heading p-3 border-bottom d-flex align-items-center gap-2">
        <i class="bi bi-shield-check text-primary fs-3"></i>
        <span class="fs-5 fw-bold text-dark"><?= $appName ?></span>
    </div>
    
    <div class="py-3">
        <ul class="nav flex-column">
            <?php foreach ($navItems as $item): ?>
                <?php 
                    $isActive = ($item['uri'] === '/admin' && $currentUri === '/admin') || 
                                ($item['uri'] !== '/admin' && str_starts_with($currentUri, $item['uri']));
                ?>
                <li class="nav-item">
                    <a href="<?= $item['uri'] ?>" class="sidebar-link <?= $isActive ? 'active' : '' ?>">
                        <i class="bi <?= $item['icon'] ?>"></i>
                        <span><?= $item['label'] ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>