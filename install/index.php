<?php
// path: install/index.php

$rootDir     = dirname(__DIR__);
$installDir  = __DIR__;
$configDir   = $rootDir . '/config';
$langDir     = $rootDir . '/languages';
$lockFile    = $installDir . '/installed.lock';

if (file_exists($lockFile)) {
    die("My System Status is already installed. Delete install/installed.lock if you want to reinstall.");
}

$errors = [];
$step = (int)($_GET['step'] ?? 1);

// Comprehensive Environment & Writable Directory Requirements
$requirements = [
    'PHP Version >= 8.3'         => version_compare(PHP_VERSION, '8.3.0', '>='),
    'PDO MySQL Extension'        => extension_loaded('pdo_mysql'),
    'cURL Extension'             => extension_loaded('curl'),
    'OpenSSL Extension'          => extension_loaded('openssl'),
    'Root Dir Writable (Updates)'=> is_writable($rootDir),
    'Config Dir Writable'        => is_writable($configDir),
    'Install Dir Writable (Lock)'=> is_writable($installDir),
    'Languages Dir Writable'     => is_dir($langDir) ? is_writable($langDir) : is_writable($rootDir)
];

$allRequirementsPassed = !in_array(false, $requirements, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';

    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';

    try {
        // 1. Validate DB Connection
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // 2. Run Database Schema Migration
        $schemaFile = $rootDir . '/database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("Database schema file not found at: {$schemaFile}");
        }
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);

        // 3. Create Initial SuperAdmin
        $passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role) 
            VALUES ('Super Administrator', ?, ?, 'superadmin')
            ON DUPLICATE KEY UPDATE password = VALUES(password)
        ");
        $stmt->execute([$adminEmail, $passwordHash]);

        // 4. Ensure Languages Directory Exists
        if (!is_dir($langDir)) {
            @mkdir($langDir, 0755, true);
        }

        // 5. Write config/database.php and verify write success
        $configContent = "<?php\n// path: config/database.php\n\nreturn [\n" .
            "    'host'     => '{$dbHost}',\n" .
            "    'port'     => {$dbPort},\n" .
            "    'database' => '{$dbName}',\n" .
            "    'username' => '{$dbUser}',\n" .
            "    'password' => '" . addslashes($dbPass) . "',\n" .
            "    'charset'  => 'utf8mb4'\n" .
            "];\n";

        $dbConfigFile = $configDir . '/database.php';
        if (file_put_contents($dbConfigFile, $configContent) === false) {
            throw new Exception("Permission denied: Unable to write to {$dbConfigFile}");
        }

        // 6. Create Lock File and verify write success
        if (file_put_contents($lockFile, date('Y-m-d H:i:s')) === false) {
            throw new Exception("Permission denied: Unable to create lock file at {$lockFile}");
        }

        $step = 3;
    } catch (\Throwable $e) {
        $errors[] = "Installation Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My System Status - Installation Wizard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h4 class="mb-0 fw-bold"><i class="bi bi-shield-check me-2"></i>My System Status - Setup</h4>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="bi bi-exclamation-octagon-fill me-2"></i>
                            <?= implode('<br>', $errors) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($step === 1): ?>
                        <h5 class="fw-bold mb-3">System & Directory Permissions</h5>
                        <p class="text-muted">Ensure all requirements and directory permissions are granted to the webserver user before proceeding.</p>
                        
                        <ul class="list-group mb-4 shadow-sm">
                            <?php foreach ($requirements as $name => $ok): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?= $name ?></span>
                                    <span class="badge bg-<?= $ok ? 'success' : 'danger' ?> px-3 py-2">
                                        <?= $ok ? 'PASSED' : 'FAILED' ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if ($allRequirementsPassed): ?>
                            <a href="?step=2" class="btn btn-primary w-100 py-2 fw-bold">Next: Configure Database &rarr;</a>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                Some directories are not writable. Please fix permissions via terminal:
                                <pre class="mt-2 bg-dark text-white p-2 rounded small"><code>sudo chown -R www-data:www-data /var/www/mysystemstatus.myetv.tv/<br>sudo chmod -R 755 /var/www/mysystemstatus.myetv.tv/</code></pre>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($step === 2): ?>
                        <form method="POST">
                            <h5 class="fw-bold mb-3">1. Database Connection</h5>
                            <div class="row g-2 mb-4">
                                <div class="col-md-8">
                                    <label class="form-label">Database Host</label>
                                    <input type="text" name="db_host" value="127.0.0.1" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Port</label>
                                    <input type="number" name="db_port" value="3306" class="form-control" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Database Name</label>
                                    <input type="text" name="db_name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">DB Username</label>
                                    <input type="text" name="db_user" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">DB Password</label>
                                    <input type="password" name="db_pass" class="form-control">
                                </div>
                            </div>

                            <h5 class="fw-bold mb-3">2. Administrator Account</h5>
                            <div class="mb-3">
                                <label class="form-label">Admin Email</label>
                                <input type="email" name="admin_email" class="form-control" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Admin Password (min. 8 characters)</label>
                                <input type="password" name="admin_password" class="form-control" required minlength="8">
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-2 fw-bold">Install My System Status</button>
                        </form>

                    <?php elseif ($step === 3): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                            <h3 class="text-success fw-bold mt-2">Installation Completed!</h3>
                            <p class="text-muted">Configuration files and lock state have been written successfully.</p>
                            <a href="/" class="btn btn-primary px-4 py-2 mt-2 fw-bold">Open My System Status</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>