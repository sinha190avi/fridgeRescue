<?php
/**
 * FridgeRescue — Automated One-Click Database Installer
 * Designed for easy 1-click database initialization on InfinityFree / Shared Hosting
 */

// Allow slightly longer execution time for network DB setup
@set_time_limit(120);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';

$message = '';
$status = 'idle'; // idle | success | error

function run_database_installer(): array {
    $db = get_db();
    $db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 1. Drop existing tables safely
    $db->exec("DROP TABLE IF EXISTS `rate_limits`");
    $db->exec("DROP TABLE IF EXISTS `waste_log`");
    $db->exec("DROP TABLE IF EXISTS `recipes_saved`");
    $db->exec("DROP TABLE IF EXISTS `fridge_items`");
    $db->exec("DROP TABLE IF EXISTS `users`");

    // 2. Users Table
    $db->exec("
        CREATE TABLE `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(191) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `diet_pref` ENUM('all', 'vegetarian', 'vegan', 'non_veg', 'keto') NOT NULL DEFAULT 'all',
            `currency` VARCHAR(10) NOT NULL DEFAULT '₹',
            `alert_days` TINYINT UNSIGNED NOT NULL DEFAULT 3,
            `theme` ENUM('light', 'dark') NOT NULL DEFAULT 'light',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_users_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 3. Fridge Items Table
    $db->exec("
        CREATE TABLE `fridge_items` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `emoji` VARCHAR(16) NOT NULL DEFAULT '🥦',
            `category` ENUM('vegetable', 'fruit', 'dairy', 'leftover', 'other') NOT NULL DEFAULT 'vegetable',
            `quantity` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
            `unit` VARCHAR(20) NOT NULL DEFAULT 'pcs',
            `estimated_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `added_on` DATE NOT NULL,
            `expiry_date` DATE NOT NULL,
            `status` ENUM('active', 'used', 'wasted') NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_items_user_status_exp` (`user_id`, `status`, `expiry_date`),
            INDEX `idx_items_expiry` (`expiry_date`),
            INDEX `idx_items_category` (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 4. Recipes Saved Table
    $db->exec("
        CREATE TABLE `recipes_saved` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(200) NOT NULL,
            `cooking_time` VARCHAR(50) NOT NULL,
            `difficulty` VARCHAR(50) NOT NULL,
            `ingredients_json` JSON NOT NULL,
            `steps_json` JSON NOT NULL,
            `rescued_items` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_recipes_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 5. Waste Log Table
    $db->exec("
        CREATE TABLE `waste_log` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `item_id` INT UNSIGNED NULL,
            `action` ENUM('rescued', 'wasted') NOT NULL,
            `value_saved` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `logged_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_waste_user_date` (`user_id`, `logged_at`),
            INDEX `idx_waste_action` (`action`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 6. Rate Limits Table
    $db->exec("
        CREATE TABLE `rate_limits` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `endpoint` VARCHAR(64) NOT NULL,
            `called_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_rate_user_endpoint_time` (`user_id`, `endpoint`, `called_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 7. Seed Demo User & Items using our existing pure UTF-8 seeder
    require_once __DIR__ . '/database/seed.php';
    $seedResult = seed_demo_database();

    return [
        'success' => true,
        'count' => $seedResult['count'] ?? 15,
        'message' => "Database tables created successfully and seeded with " . ($seedResult['count'] ?? 15) . " fresh pantry items!"
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['auto'])) {
    try {
        $installerResult = run_database_installer();
        $status = 'success';
        $message = $installerResult['message'];
    } catch (Throwable $e) {
        $status = 'error';
        $message = "Setup failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FridgeRescue — Database Auto-Installer</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .installer-box {
            background: var(--surface-card);
            border-radius: var(--radius-xl);
            padding: 40px;
            max-width: 580px;
            width: 100%;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="installer-box">
        <div style="font-size: 54px; margin-bottom: 16px;">🥦</div>
        <h1 style="font-size: 26px; margin-bottom: 8px;">FridgeRescue Database Installer</h1>
        <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 24px;">
            One-click installer to initialize all tables, indexes, and fresh demo pantry items for your live website.
        </p>

        <?php if ($status === 'success'): ?>
            <div style="background: var(--lime-soft); color: var(--forest-green-dark); padding: 18px; border-radius: var(--radius-md); border: 1.5px solid var(--lime); margin-bottom: 24px; text-align: left; font-size: 14px;">
                <strong>🎉 Success!</strong> <?= htmlspecialchars($message) ?>
                <div style="margin-top: 8px; font-size: 13px;">
                    Demo Account ready: <strong>demo@fridgerescue.com</strong> / <strong>Demo@123</strong>
                </div>
            </div>
            <a href="login.php" class="btn btn-primary btn-lg" style="width: 100%;">
                Go to Login &amp; Pantry &rarr;
            </a>
        <?php elseif ($status === 'error'): ?>
            <div style="background: var(--tomato-soft); color: var(--tomato); padding: 18px; border-radius: var(--radius-md); border: 1.5px solid rgba(228,87,46,0.3); margin-bottom: 24px; text-align: left; font-size: 14px;">
                <strong>❌ Database Connection Error:</strong><br>
                <?= htmlspecialchars($message) ?>
                <div style="margin-top: 10px; font-size: 12px; color: var(--text-secondary);">
                    Check your <code>.env</code> file on the server to verify <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, and <code>DB_PASS</code>.
                </div>
            </div>
            <form method="POST">
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    🔄 Retry Database Installation
                </button>
            </form>
        <?php else: ?>
            <div style="background: var(--surface-card-subtle); padding: 16px; border-radius: var(--radius-md); margin-bottom: 24px; text-align: left; font-size: 13px; color: var(--text-secondary);">
                <strong>Target Database:</strong> <code><?= htmlspecialchars(env('DB_NAME', 'fridgerescue')) ?></code> on <code><?= htmlspecialchars(env('DB_HOST', '127.0.0.1')) ?></code><br>
                <strong>Action:</strong> Creates <code>users</code>, <code>fridge_items</code>, <code>recipes_saved</code>, <code>waste_log</code>, and <code>rate_limits</code> tables.
            </div>
            <form method="POST">
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    ⚡ Initialize &amp; Seed Database Now
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
