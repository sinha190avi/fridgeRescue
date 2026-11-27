<?php
/**
 * FridgeRescue - Helper Functions
 */

if (!defined('FRIDGE_RESCUE')) {
    require_once dirname(__DIR__) . '/config/config.php';
}

/**
 * Escape HTML output
 */
function e(?string $string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate asset URL based on deployment path with automatic cache busting
 */
function asset_url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($script));
        if ($dir === '/' || $dir === '\\' || $dir === '.') {
            $base = '';
        } else {
            // Remove /api if called from an endpoint
            $dir = preg_replace('#/api$#', '', $dir);
            $base = rtrim($dir, '/');
        }
    }
    if ($path === '') {
        return $base;
    }
    $cleanPath = ltrim($path, '/');
    $url = $base . '/' . $cleanPath;

    // Append modification timestamp for automatic cache invalidation
    if (defined('APP_ROOT')) {
        $fullPath = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath);
        if (file_exists($fullPath)) {
            $url .= '?v=' . filemtime($fullPath);
        }
    }
    return $url;
}

/**
 * Generate or get CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render CSRF input field
 */
function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Verify CSRF token
 */
function csrf_verify(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Set a session flash message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Send JSON response and terminate
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Calculate days remaining until expiry
 */
function get_days_left(string $expiryDate): int {
    $today = new DateTime('today');
    $exp = new DateTime($expiryDate);
    $diff = $today->diff($exp);
    $days = (int)$diff->format('%r%a');
    return $days;
}

/**
 * Get comprehensive freshness metadata
 */
function get_freshness_data(string $expiryDate, ?string $addedDate = null): array {
    $days = get_days_left($expiryDate);

    if ($days < 0) {
        $status = 'expired';
        $badgeClass = 'badge-expired';
        $color = '#E4572E';
        $label = abs($days) === 1 ? 'Expired 1d ago' : "Expired " . abs($days) . "d ago";
        $percent = 0;
    } elseif ($days === 0) {
        $status = 'expiring';
        $badgeClass = 'badge-expiring';
        $color = '#E4572E';
        $label = 'Expires today!';
        $percent = 15;
    } elseif ($days === 1) {
        $status = 'expiring';
        $badgeClass = 'badge-expiring';
        $color = '#E4572E';
        $label = '1 day left';
        $percent = 25;
    } elseif ($days <= 4) {
        $status = 'soon';
        $badgeClass = 'badge-soon';
        $color = '#F4B942';
        $label = "{$days} days left";
        $percent = 55;
    } else {
        $status = 'fresh';
        $badgeClass = 'badge-fresh';
        $color = '#1F4D3A';
        $label = "{$days} days left";
        $percent = 90;
    }

    return [
        'days' => $days,
        'status' => $status,
        'badge_class' => $badgeClass,
        'color' => $color,
        'label' => $label,
        'percent' => $percent
    ];
}

/**
 * Render circular SVG Freshness Ring
 */
function render_freshness_ring(int $daysLeft, string $status, int $size = 44): string {
    $radius = 16;
    $circumference = 2 * M_PI * $radius; // ~100.53

    // Percentage remaining (scaled 0 to 100)
    if ($daysLeft <= 0) {
        $pct = 5;
    } elseif ($daysLeft <= 1) {
        $pct = 20;
    } elseif ($daysLeft <= 3) {
        $pct = 50;
    } elseif ($daysLeft <= 5) {
        $pct = 75;
    } else {
        $pct = 100;
    }

    $offset = $circumference - ($pct / 100 * $circumference);

    $color = '#1F4D3A';
    $pulseClass = '';
    if ($status === 'expired' || $status === 'expiring') {
        $color = '#E4572E';
        $pulseClass = 'ring-pulse';
    } elseif ($status === 'soon') {
        $color = '#F4B942';
    }

    $labelDays = $daysLeft < 0 ? abs($daysLeft) . 'd' : ($daysLeft === 0 ? '0d' : $daysLeft . 'd');

    return <<<HTML
    <div class="freshness-ring-wrapper {$pulseClass}" title="{$labelDays} left" style="width: {$size}px; height: {$size}px;">
        <svg class="freshness-ring-svg" width="{$size}" height="{$size}" viewBox="0 0 40 40">
            <circle class="ring-bg" cx="20" cy="20" r="{$radius}" stroke-width="3.5" fill="none" />
            <circle class="ring-progress ring-{$status}" cx="20" cy="20" r="{$radius}" stroke-width="3.5"
                stroke="{$color}"
                stroke-dasharray="{$circumference}"
                stroke-dashoffset="{$offset}"
                stroke-linecap="round"
                fill="none"
                transform="rotate(-90 20 20)" />
        </svg>
        <span class="ring-text" style="color: {$color};">{$labelDays}</span>
    </div>
HTML;
}

/**
 * Format currency
 */
function format_money($amount, string $currency = '₹'): string {
    return $currency . ' ' . number_format((float)$amount, 0);
}

/**
 * Map Category to Default Emoji
 */
function category_emoji(string $category): string {
    return match (strtolower($category)) {
        'vegetable' => '🥦',
        'fruit' => '🍎',
        'dairy' => '🥛',
        'leftover' => '🍲',
        default => '🥫'
    };
}
