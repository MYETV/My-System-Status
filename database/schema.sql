-- path: database/schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Settings Table
CREATE TABLE IF NOT EXISTS `settings` (
    `key` VARCHAR(64) PRIMARY KEY,
    `value` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users & Roles (Includes 2FA TOTP Columns)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password` VARCHAR(255) NULL,
    `two_factor_secret` VARCHAR(64) NULL,
    `two_factor_enabled` TINYINT(1) DEFAULT 0,
    `role` ENUM('superadmin', 'admin', 'operator') DEFAULT 'admin',
    `oauth_provider` VARCHAR(50) NULL,
    `oauth_id` VARCHAR(191) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate Limiting & Anti-Brute Force Tracking
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `action` VARCHAR(50) NOT NULL DEFAULT 'login',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
    `last_attempt` DATETIME NOT NULL,
    `blocked_until` DATETIME NULL,
    UNIQUE KEY `uniq_ip_action` (`ip_address`, `action`),
    INDEX `idx_blocked` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monitors & Routes (Includes parent_id, sort_order, and is_primary)
CREATE TABLE IF NOT EXISTS `monitors` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `parent_id` INT UNSIGNED NULL DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `name` VARCHAR(150) NOT NULL,
    `type` ENUM('http', 'ping', 'port', 'ssl') NOT NULL DEFAULT 'http',
    `target` VARCHAR(255) NOT NULL,
    `port` INT NULL,
    `interval_seconds` INT UNSIGNED DEFAULT 60,
    `timeout_seconds` INT UNSIGNED DEFAULT 10,
    `current_status` ENUM('operational', 'degraded', 'down', 'maintenance') DEFAULT 'operational',
    `uptime_percentage` DECIMAL(5,2) DEFAULT 100.00,
    `last_check` DATETIME NULL,
    `ssl_expiration` DATETIME NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `is_primary` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_parent_id` (`parent_id`),
    INDEX `idx_sort_order` (`sort_order`),
    INDEX `idx_is_primary` (`is_primary`),
    FOREIGN KEY (`parent_id`) REFERENCES `monitors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monitor Logs (Heartbeats & Probes History)
CREATE TABLE IF NOT EXISTS `monitor_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `monitor_id` INT UNSIGNED NOT NULL,
    `status` ENUM('up', 'down', 'timeout', 'blackout') NOT NULL,
    `response_time_ms` INT UNSIGNED NULL,
    `http_code` INT NULL,
    `error_message` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_monitor_check` (`monitor_id`, `created_at`),
    FOREIGN KEY (`monitor_id`) REFERENCES `monitors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Incidents (Includes monitor_id for target probe association)
CREATE TABLE IF NOT EXISTS `incidents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `monitor_id` INT UNSIGNED NULL DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `impact` ENUM('none', 'minor', 'major', 'critical') NOT NULL DEFAULT 'minor',
    `status` ENUM('investigating', 'identified', 'monitoring', 'resolved') NOT NULL DEFAULT 'investigating',
    `ai_summary` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_inc_monitor` (`monitor_id`),
    FOREIGN KEY (`monitor_id`) REFERENCES `monitors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Incident Updates (Timeline)
CREATE TABLE IF NOT EXISTS `incident_updates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `incident_id` INT UNSIGNED NOT NULL,
    `status` ENUM('investigating', 'identified', 'monitoring', 'resolved') NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduled Maintenance (Includes monitor_id for target probe association)
CREATE TABLE IF NOT EXISTS `maintenances` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `monitor_id` INT UNSIGNED NULL DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('scheduled', 'in_progress', 'completed') DEFAULT 'scheduled',
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_maint_monitor` (`monitor_id`),
    FOREIGN KEY (`monitor_id`) REFERENCES `monitors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subscribers (Granular per-probe or all services alerts)
CREATE TABLE IF NOT EXISTS `subscribers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(191) NOT NULL,
    `monitor_id` INT UNSIGNED NULL DEFAULT NULL,
    `token` VARCHAR(64) NOT NULL,
    `is_verified` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_sub_email_monitor` (`email`, `monitor_id`),
    INDEX `idx_monitor_id` (`monitor_id`),
    FOREIGN KEY (`monitor_id`) REFERENCES `monitors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Time-Limited (60 min) Magic Link Unsubscribe Requests
CREATE TABLE IF NOT EXISTS `unsubscribe_requests` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(191) NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Audit Logs
CREATE TABLE IF NOT EXISTS `system_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
