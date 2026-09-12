<?php
// path: core/Database.php

namespace App\Core;

use PDO;
use PDOException;
use Exception;

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    /**
     * Get single PDO database instance.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $configFile = dirname(__DIR__) . '/config/database.php';

            if (!file_exists($configFile)) {
                // If the config file does not exist, redirect to installer
                if (file_exists(dirname(__DIR__) . '/install/index.php')) {
                    header('Location: /install/');
                    exit;
                }
                throw new Exception("Database configuration file missing. Please run the installer.");
            }

            $config = require $configFile;

            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 3306,
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );

            try {
                self::$instance = new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }
}