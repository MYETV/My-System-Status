<?php
// path: app/Controllers/Admin/UpdaterController.php

namespace App\Controllers\Admin;

use App\Core\View;
use App\Services\UpdaterService;

class UpdaterController
{
    private UpdaterService $updater;

    public function __construct()
    {
        // Authentication guard
        if (empty($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }

        $this->updater = new UpdaterService();
    }

    public function index(): void
    {
        $currentVersion = $this->updater->getCurrentVersion();
        $updateInfo     = $this->updater->checkForUpdates();
        $permCheck      = $this->updater->checkWritePermissions();

        View::render('admin/updater/index', [
            'currentVersion' => $currentVersion,
            'updateInfo'     => $updateInfo,
            'permCheck'      => $permCheck
        ]);
    }

    public function apply(): void
    {
        $zipUrl = $_POST['zip_url'] ?? '';

        if (empty($zipUrl)) {
            header('Location: /admin/updater?error=invalid_url');
            exit;
        }

        try {
            $this->updater->applyUpdate($zipUrl);
            header('Location: /admin/updater?updated=1');
            exit;
        } catch (\Throwable $e) {
            header('Location: /admin/updater?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}
