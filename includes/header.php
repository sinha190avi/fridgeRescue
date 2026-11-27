<?php
if (!defined('FRIDGE_RESCUE')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$currentUser = require_login();

// Count items expiring within user's alert_days
try {
    $db = get_db();
    $alertDays = (int)($currentUser['alert_days'] ?? 3);
    $stmtAlert = $db->prepare("
        SELECT COUNT(*) 
        FROM fridge_items 
        WHERE user_id = :uid 
          AND status = 'active' 
          AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
    ");
    $stmtAlert->execute(['uid' => $currentUser['id'], 'days' => $alertDays]);
    $expiringCount = (int)$stmtAlert->fetchColumn();
} catch (Exception $e) {
    $expiringCount = 0;
}

$pageTitle = $pageTitle ?? 'Dashboard — FridgeRescue';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($currentUser['theme'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🥦</text></svg>">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <!-- Canvas-Confetti CDN for celebrations -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
    <script>
        window.APP_BASE_URL = "<?= asset_url('') ?>";
        window.CSRF_TOKEN = "<?= csrf_token() ?>";
        window.USER_CURRENCY = "<?= e($currentUser['currency'] ?? '₹') ?>";
    </script>
</head>
<body>
    <div class="app-container">
