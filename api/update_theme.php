<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$user = require_login();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];

$theme = ($data['theme'] ?? '') === 'dark' ? 'dark' : 'light';

try {
    $db = get_db();
    $stmt = $db->prepare("UPDATE users SET theme = :theme WHERE id = :uid");
    $stmt->execute(['theme' => $theme, 'uid' => $user['id']]);
    $_SESSION['user_theme'] = $theme;
    json_response(['success' => true]);
} catch (Exception $e) {
    json_response(['success' => false], 500);
}
