<?php
/**
 * FridgeRescue - Core Configuration & Environment Loader
 */

define('FRIDGE_RESCUE', true);
define('APP_ROOT', dirname(__DIR__));
define('UPLOADS_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads');

// Load .env file

function load_env($filePath) {
    if (!file_exists($filePath)) {
        return;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            // Strip outer quotes if present
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
            putenv("{$key}={$val}");
        }
    }
}

load_env(APP_ROOT . DIRECTORY_SEPARATOR . '.env');

function env($key, $default = null) {
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    $val = getenv($key);
    return ($val !== false) ? $val : $default;
}

// Error reporting
if (env('APP_ENV', 'development') === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Custom session directory for guaranteed portability across local XAMPP and shared hosts
$sessionDir = APP_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0777, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    @session_save_path($sessionDir);
}

// Secure Session Initialization
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    // Set 7 days lifetime
    ini_set('session.gc_maxlifetime', '604800');
    session_start();
}
