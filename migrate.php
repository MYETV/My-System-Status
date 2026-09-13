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
 * Helper: Check if a column exists in a given table via information_schema
 */
function column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.columns 
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
        ");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Helper: Check if an index exists in a given table via information_schema
 */
function index_exists(PDO $pdo, string $table, string $indexName): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.statistics 
            WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
        ");
        $stmt->execute([$table, $indexName]);
        return (bool)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Helper: Check if a table exists in the database via information_schema
 */
function table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.tables 
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

try {
    // =========================================================================
    // HOW TO ADD FUTURE DATABASE MIGRATIONS:
    // =========================================================================
    // When releasing a new version that requires schema changes, add a new block below.
    // Always wrap ALTER operations with existence checks so migrations are idempotent.
    // =========================================================================

    // --- v1.0.2: Add parent_id for sub-services ---
    if (table_exists($pdo, 'monitors')) {
        if (!column_exists($pdo, 'monitors', 'parent_id')) {
            $pdo->exec("ALTER TABLE `monitors` ADD COLUMN `parent_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`;");
        }
    }

    // --- v1.0.3: Add sort_order for custom display positioning ---
    if (table_exists($pdo, 'monitors')) {
        if (!column_exists($pdo, 'monitors', 'sort_order')) {
            $pdo->exec("ALTER TABLE `monitors` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `parent_id`;");
        }
    }

    // --- v1.0.4: Add index for parent_id ---
    if (table_exists($pdo, 'monitors')) {
        if (column_exists($pdo, 'monitors', 'parent_id') && !index_exists($pdo, 'monitors', 'idx_parent_id')) {
            $pdo->exec("ALTER TABLE `monitors` ADD INDEX `idx_parent_id` (`parent_id`);");
        }
    }

    // --- v1.0.5: Add is_primary to distinguish Core vs Secondary services ---
    if (table_exists($pdo, 'monitors')) {
        if (!column_exists($pdo, 'monitors', 'is_primary')) {
            $pdo->exec("ALTER TABLE `monitors` ADD COLUMN `is_primary` TINYINT(1) DEFAULT 0 AFTER `is_active`;");
            $pdo->exec("ALTER TABLE `monitors` ADD INDEX `idx_is_primary` (`is_primary`);");
        }
    }

    // --- v1.0.6: Granular subscriptions & time-limited unsubscribe requests ---
    if (table_exists($pdo, 'subscribers')) {
        // Drop legacy unique index on email if it exists
        if (index_exists($pdo, 'subscribers', 'email')) {
            $pdo->exec("ALTER TABLE `subscribers` DROP INDEX `email`;");
        }
        // Add monitor_id column for single-probe subscriptions
        if (!column_exists($pdo, 'subscribers', 'monitor_id')) {
            $pdo->exec("ALTER TABLE `subscribers` ADD COLUMN `monitor_id` INT UNSIGNED NULL DEFAULT NULL AFTER `email`;");
        }
        // Add composite unique key
        if (!index_exists($pdo, 'subscribers', 'uniq_sub_email_monitor')) {
            $pdo->exec("ALTER TABLE `subscribers` ADD UNIQUE KEY `uniq_sub_email_monitor` (`email`, `monitor_id`);");
        }
        // Add index and foreign key
        if (!index_exists($pdo, 'subscribers', 'idx_monitor_id')) {
            $pdo->exec("ALTER TABLE `subscribers` ADD INDEX `idx_monitor_id` (`monitor_id`);");
            try {
                $pdo->exec("ALTER TABLE `subscribers` ADD CONSTRAINT `fk_sub_monitor` FOREIGN KEY (`monitor_id`) REFERENCES `monitors` (`id`) ON DELETE CASCADE;");
            } catch (\Throwable $e) {
                // Ignore if foreign key exists
            }
        }
    }

    // --- v1.0.16: Add 'blackout' status to monitor_logs for system gap detection ---
if (table_exists($pdo, 'monitor_logs')) {
    $pdo->exec("ALTER TABLE `monitor_logs` MODIFY COLUMN `status` ENUM('up', 'down', 'timeout', 'blackout') NOT NULL;");
}


    if (php_sapi_name() === 'cli') {
        echo "Database schema is fully up to date.\n";
    }

} catch (Exception $e) {
    error_log("Database Migration Error: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
        exit(1);
    }
}
