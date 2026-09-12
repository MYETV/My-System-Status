<?php
// path: migrate.php
// Safe incremental database migration script executed automatically during system updates

// Load core database singleton
require_once __DIR__ . '/core/Database.php';

try {
    $pdo = \App\Core\Database::getInstance();
} catch (\Throwable $e) {
    // CLI fallback if core bootstrap is not loaded
    $configFile = __DIR__ . '/config/database.php';
    if (!file_exists($configFile)) {
        exit("Database configuration not found. Run installer first.\n");
    }
    $dbConfig = require $configFile;
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
}

if (!isset($pdo)) {
    exit('Database connection unavailable.');
}

/**
 * Helper: Check if a column exists in a given table
 */
function column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Helper: Check if an index exists in a given table
 */
function index_exists(PDO $pdo, string $table, string $indexName): bool {
    try {
        $stmt = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
        $stmt->execute([$indexName]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Helper: Check if a table exists in the database
 */
function table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

try {
    // =========================================================================
    // HOW TO ADD FUTURE DATABASE MIGRATIONS:
    // =========================================================================
    // When releasing a new version that requires schema changes, add a new block below.
    // Always wrap ALTER operations with existence checks so migrations are idempotent.
    //
    // Example 1: Adding a new column to an existing table:
    // ---------------------------------------------------
    // if (table_exists($pdo, 'monitors')) {
    //     if (!column_exists($pdo, 'monitors', 'custom_headers')) {
    //         $pdo->exec("ALTER TABLE `monitors` ADD COLUMN `custom_headers` TEXT NULL AFTER `target`;");
    //     }
    // }
    //
    // Example 2: Creating a new table:
    // ---------------------------------------------------
    // $pdo->exec("
    //     CREATE TABLE IF NOT EXISTS `probe_locations` (
    //         `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    //         `name` VARCHAR(100) NOT NULL,
    //         `country_code` VARCHAR(2) NOT NULL,
    //         `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    //     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    // ");
    //
    // Example 3: Adding a new database index:
    // ---------------------------------------------------
    // if (table_exists($pdo, 'monitor_logs')) {
    //     if (!index_exists($pdo, 'monitor_logs', 'idx_created_at')) {
    //         $pdo->exec("ALTER TABLE `monitor_logs` ADD INDEX `idx_created_at` (`created_at`);");
    //     }
    // }
    // =========================================================================

    // --- MIGRATION FOR v1.1.0 (Template ready for future releases) ---
    /*
    if (table_exists($pdo, 'example_table')) {
        // Add incremental migration code here
    }
    */

    if (php_sapi_name() === 'cli') {
        echo "Database migrations are up to date.\n";
    }

} catch (Exception $e) {
    error_log("Database Migration Error: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
        exit(1);
    }
}