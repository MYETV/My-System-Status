<?php
// path: app/Controllers/Admin/ApiKeyController.php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\View;
use App\Services\ApiKeyService;
use PDO;

class ApiKeyController
{
    private PDO $db;
    private ApiKeyService $service;

    public function __construct()
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }

        $this->db      = Database::getInstance();
        $this->service = new ApiKeyService();
    }

    public function index(): void
    {
        $stmt = $this->db->query("SELECT id, name, key_prefix, last_used_at, created_at FROM api_keys ORDER BY id DESC");
        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $newKey = $_SESSION['newly_generated_api_key'] ?? null;
        unset($_SESSION['newly_generated_api_key']);

        View::render('admin/api_keys/index', [
            'keys'   => $keys,
            'newKey' => $newKey
        ]);
    }

    public function store(): void
    {
        $name = trim($_POST['name'] ?? 'Deployment Script Key');
        if ($name) {
            $generated = $this->service->generate($name);
            $_SESSION['newly_generated_api_key'] = $generated['plain_key'];
        }

        header('Location: /admin/api-keys');
        exit;
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->service->revoke($id);
        }

        header('Location: /admin/api-keys?revoked=1');
        exit;
    }
}