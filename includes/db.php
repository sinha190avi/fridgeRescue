<?php
/**
 * FridgeRescue - Database Connection (PDO)
 */

if (!defined('FRIDGE_RESCUE')) {
    require_once dirname(__DIR__) . '/config/config.php';
}

function get_db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');
        $dbname = env('DB_NAME', 'fridgerescue');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            if (env('APP_ENV') === 'development') {
                die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
            } else {
                die("Internal Server Error. Please ensure the database is running.");
            }
        }
    }

    return $pdo;
}
