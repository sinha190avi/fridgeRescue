<?php
/**
 * FridgeRescue - Authentication and Session Management
 */

if (!defined('FRIDGE_RESCUE')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser !== null && $cachedUser['id'] == $_SESSION['user_id']) {
        return $cachedUser;
    }

    $db = get_db();
    $stmt = $db->prepare("SELECT id, name, email, diet_pref, currency, alert_days, theme, created_at FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        $cachedUser = $user;
        return $user;
    }

    // Invalid session ID
    logout_user();
    return null;
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_theme'] = $user['theme'] ?? 'light';
    $_SESSION['user_currency'] = $user['currency'] ?? '₹';
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

function require_login(string $redirect = 'login.php'): array {
    $user = current_user();
    if (!$user) {
        // If it's an AJAX / API request, return JSON 401
        if (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
        ) {
            json_response(['success' => false, 'error' => 'Authentication required. Please log in.'], 401);
        }
        set_flash('error', 'Please log in to continue.');
        header("Location: {$redirect}");
        exit;
    }
    return $user;
}

function require_guest(string $redirect = 'dashboard.php'): void {
    if (is_logged_in()) {
        header("Location: {$redirect}");
        exit;
    }
}

/**
 * AI Rate Limiter (Max N calls per hour per user)
 */
function check_rate_limit(int $userId, string $endpoint, int $maxCallsPerHour = 20): bool {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT COUNT(*) as call_count 
        FROM rate_limits 
        WHERE user_id = :uid 
          AND endpoint = :ep 
          AND called_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute(['uid' => $userId, 'ep' => $endpoint]);
    $count = (int)$stmt->fetchColumn();
    return $count < $maxCallsPerHour;
}

function record_rate_limit(int $userId, string $endpoint): void {
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO rate_limits (user_id, endpoint, called_at) VALUES (:uid, :ep, NOW())");
    $stmt->execute(['uid' => $userId, 'ep' => $endpoint]);
}
