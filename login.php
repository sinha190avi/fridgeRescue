<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_guest();

$error = '';
$flash = get_flash();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $isDemo = isset($_POST['demo_login']);
        $email = $isDemo ? 'demo@fridgerescue.com' : trim($_POST['email'] ?? '');
        $password = $isDemo ? 'Demo@123' : ($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            try {
                $db = get_db();

                // 1. Auto-initialize tables if database is empty on hosting
                try {
                    $tableCheck = $db->query("SHOW TABLES LIKE 'users'")->fetch();
                    if (!$tableCheck) {
                        require_once __DIR__ . '/setup_db.php';
                        run_database_installer();
                    }
                } catch (Throwable $t) {
                    // Fallback attempt
                    require_once __DIR__ . '/setup_db.php';
                    @run_database_installer();
                }

                $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();

                // 2. If Demo User is missing from database, auto-seed immediately
                if (!$user && ($isDemo || $email === 'demo@fridgerescue.com')) {
                    require_once __DIR__ . '/database/seed.php';
                    seed_demo_database();
                    $stmt->execute(['email' => $email]);
                    $user = $stmt->fetch();
                }

                // 3. Verify Credentials
                if ($user && password_verify($password, $user['password_hash'])) {
                    if ($isDemo || (int)($user['id'] ?? 0) === 1) {
                        require_once __DIR__ . '/database/seed.php';
                        try {
                            $staleCheck = $db->query("
                                SELECT 
                                    SUM(CASE WHEN expiry_date < DATE_SUB(CURDATE(), INTERVAL 2 DAY) THEN 1 ELSE 0 END) as expired_cnt,
                                    COUNT(*) as total_cnt
                                FROM fridge_items 
                                WHERE user_id = 1 AND status = 'active'
                            ");
                            $itemStats = $staleCheck ? $staleCheck->fetch() : null;
                            if (!$itemStats || (int)($itemStats['total_cnt'] ?? 0) < 5 || (int)($itemStats['expired_cnt'] ?? 0) >= 2) {
                                seed_demo_database();
                            }
                        } catch (Throwable $ignore) {
                            @seed_demo_database();
                        }
                    }
                    login_user($user);
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid email or password.';
                }
            } catch (Throwable $e) {
                $error = 'Authentication error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — FridgeRescue</title>
    <link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🥦</text></svg>">
</head>
<body>
    <div class="auth-split-wrapper">
        <!-- Brand / Value Proposition Showcase -->
        <div class="auth-brand-side">
            <div>
                <a href="index.php" style="display: inline-flex; align-items: center; gap: 10px; color: #FAF6EE; margin-bottom: 40px; font-weight: 800; font-size: 24px; font-family: var(--font-heading);">
                    <span style="font-size: 32px;">🥦</span> FridgeRescue
                </a>
                <div class="produce-sticker" style="margin-bottom: 24px;">AI Shelf-Life Sentinel</div>
                <h1 style="font-size: 42px; line-height: 1.15; margin-bottom: 18px; color: #FAF6EE;">
                    Eat it before it expires.
                </h1>
                <p style="font-size: 16px; opacity: 0.9; max-width: 440px; line-height: 1.6;">
                    Snap a photo of your fridge. Gemini AI tracks expiring groceries and generates tailored rescue recipes before food turns into waste.
                </p>
            </div>

            <!-- Mini Live Stat Card -->
            <div style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.15); border-radius: 20px; padding: 22px; max-width: 420px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--lime);">Rescue Live Feed</span>
                    <span class="badge badge-fresh" style="background: rgba(166,216,84,0.25); color: var(--lime);">Active</span>
                </div>
                <div style="display: flex; gap: 20px;">
                    <div>
                        <div style="font-size: 26px; font-weight: 800; font-family: var(--font-heading); color: #FAF6EE;">₹3,450+</div>
                        <div style="font-size: 12px; opacity: 0.8;">Money Saved</div>
                    </div>
                    <div style="border-left: 1px solid rgba(255,255,255,0.15); padding-left: 20px;">
                        <div style="font-size: 26px; font-weight: 800; font-family: var(--font-heading); color: var(--lime);">21</div>
                        <div style="font-size: 12px; opacity: 0.8;">Meals Rescued</div>
                    </div>
                </div>
            </div>

            <div style="font-size: 13px; opacity: 0.7;">
                Powered by Google Gemini 2.5 Flash Vision &bull; Zero Frameworks
            </div>
        </div>

        <!-- Form Side -->
        <div class="auth-form-side">
            <div class="auth-card">
                <div style="margin-bottom: 28px;">
                    <h2 style="font-size: 28px; margin-bottom: 8px;">Welcome back</h2>
                    <p style="color: var(--text-secondary); font-size: 14px;">Sign in to check what’s ready to cook in your fridge.</p>
                </div>

                <?php if ($flash): ?>
                    <div style="padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 14px; background: var(--lime-soft); color: var(--forest-green); border: 1px solid rgba(166,216,84,0.5);">
                        <?= e($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div style="padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 14px; background: var(--tomato-soft); color: var(--tomato); border: 1px solid rgba(228,87,46,0.4);">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <!-- 1-Click Demo Login Box -->
                <form method="POST" action="login.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="demo_login" value="1">
                    <div class="demo-login-box">
                        <div style="font-size: 13px; font-weight: 600; color: var(--forest-green); margin-bottom: 6px;">
                            🚀 Quick Evaluation Access
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 12px;">
                            Preloaded with 14 fridge items, expiring alerts &amp; 8-week impact analytics.
                        </div>
                        <button type="submit" class="btn btn-accent btn-sm" style="width: 100%;">
                            ⚡ One-Click Demo Login (Chef Avilash)
                        </button>
                    </div>
                </form>

                <div style="display: flex; align-items: center; margin: 20px 0; color: var(--muted-light); font-size: 12px; text-transform: uppercase;">
                    <div style="flex: 1; height: 1px; background: var(--border-color);"></div>
                    <span style="padding: 0 12px;">or with email</span>
                    <div style="flex: 1; height: 1px; background: var(--border-color);"></div>
                </div>

                <form method="POST" action="login.php">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="chef@example.com" value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 7px;">
                            <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                        </div>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px; padding: 12px;">
                        Sign In to My Fridge
                    </button>
                </form>

                <div style="margin-top: 24px; text-align: center; font-size: 14px; color: var(--text-secondary);">
                    Don't have an account yet? <a href="register.php" style="font-weight: 600;">Create account</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
