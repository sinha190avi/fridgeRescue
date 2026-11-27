<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_guest();

$error = '';
$name = '';
$email = '';
$dietPref = 'all';
$currency = '₹';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $dietPref = $_POST['diet_pref'] ?? 'all';
        $currency = $_POST['currency'] ?? '₹';

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Passwords do not match.';
        } else {
            $db = get_db();
            // Check if email exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $error = 'An account with that email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $insert = $db->prepare("
                    INSERT INTO users (name, email, password_hash, diet_pref, currency, alert_days, theme, created_at)
                    VALUES (:name, :email, :hash, :diet, :currency, 3, 'light', NOW())
                ");
                $insert->execute([
                    'name' => $name,
                    'email' => $email,
                    'hash' => $hash,
                    'diet' => $dietPref,
                    'currency' => $currency
                ]);

                $userId = (int)$db->lastInsertId();

                // Add starter pantry items
                $starterItems = [
                    ['Tomatoes', '🍅', 'vegetable', 500, 'g', 40.00, 1],
                    ['Fresh Milk', '🥛', 'dairy', 1, 'L', 65.00, 2],
                    ['Spinach', '🥬', 'vegetable', 1, 'bunch', 30.00, 3],
                    ['Eggs', '🥚', 'other', 6, 'pcs', 60.00, 7]
                ];
                $itemStmt = $db->prepare("
                    INSERT INTO fridge_items (user_id, name, emoji, category, quantity, unit, estimated_price, added_on, expiry_date, status, created_at)
                    VALUES (:uid, :name, :emoji, :cat, :qty, :unit, :price, CURDATE(), DATE_ADD(CURDATE(), INTERVAL :exp DAY), 'active', NOW())
                ");
                foreach ($starterItems as $si) {
                    $itemStmt->execute([
                        'uid' => $userId,
                        'name' => $si[0],
                        'emoji' => $si[1],
                        'cat' => $si[2],
                        'qty' => $si[3],
                        'unit' => $si[4],
                        'price' => $si[5],
                        'exp' => $si[6]
                    ]);
                }

                // Log user in
                $user = [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'theme' => 'light',
                    'currency' => $currency
                ];
                login_user($user);
                set_flash('success', "Welcome to FridgeRescue, {$name}! Your fridge is ready.");
                header('Location: dashboard.php');
                exit;
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
    <title>Create Account — FridgeRescue</title>
    <link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🥦</text></svg>">
</head>
<body>
    <div class="auth-split-wrapper">
        <div class="auth-brand-side">
            <div>
                <a href="index.php" style="display: inline-flex; align-items: center; gap: 10px; color: #FAF6EE; margin-bottom: 40px; font-weight: 800; font-size: 24px; font-family: var(--font-heading);">
                    <span style="font-size: 32px;">🥦</span> FridgeRescue
                </a>
                <div class="produce-sticker" style="margin-bottom: 24px;">Zero Food Waste</div>
                <h1 style="font-size: 42px; line-height: 1.15; margin-bottom: 18px; color: #FAF6EE;">
                    Never throw away spoiled groceries again.
                </h1>
                <p style="font-size: 16px; opacity: 0.9; max-width: 440px; line-height: 1.6;">
                    Join conscious foodies saving an average of ₹1,200 every month with AI-guided fridge management and creative rescue recipes.
                </p>
            </div>

            <div style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.15); border-radius: 20px; padding: 22px; max-width: 420px;">
                <div style="font-size: 15px; font-weight: 600; color: var(--lime); margin-bottom: 8px;">🌱 What you get:</div>
                <ul style="list-style: none; padding: 0; font-size: 14px; opacity: 0.9; line-height: 1.8;">
                    <li>✓ Gemini Vision instant fridge inventory</li>
                    <li>✓ Smart expiry date alerts before food spoils</li>
                    <li>✓ 3 tailored rescue recipes matching what’s in stock</li>
                    <li>✓ Impact tracker measuring money and food saved</li>
                </ul>
            </div>

            <div style="font-size: 13px; opacity: 0.7;">
                Privacy-first &bull; Local server execution &bull; No credit card
            </div>
        </div>

        <div class="auth-form-side">
            <div class="auth-card" style="max-width: 500px;">
                <div style="margin-bottom: 24px;">
                    <h2 style="font-size: 28px; margin-bottom: 8px;">Create your account</h2>
                    <p style="color: var(--text-secondary); font-size: 14px;">Start rescuing food and saving money today.</p>
                </div>

                <?php if ($error): ?>
                    <div style="padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 14px; background: var(--tomato-soft); color: var(--tomato); border: 1px solid rgba(228,87,46,0.4);">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="form-label" for="name">Your Name</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="Avilash" value="<?= e($name) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" value="<?= e($email) ?>" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Min 6 chars" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="password_confirm">Confirm Password</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Repeat password" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="diet_pref">Diet Preference</label>
                            <select id="diet_pref" name="diet_pref" class="form-control">
                                <option value="all" <?= $dietPref === 'all' ? 'selected' : '' ?>>No restrictions (All)</option>
                                <option value="vegetarian" <?= $dietPref === 'vegetarian' ? 'selected' : '' ?>>Vegetarian</option>
                                <option value="vegan" <?= $dietPref === 'vegan' ? 'selected' : '' ?>>Vegan</option>
                                <option value="non_veg" <?= $dietPref === 'non_veg' ? 'selected' : '' ?>>Non-Vegetarian</option>
                                <option value="keto" <?= $dietPref === 'keto' ? 'selected' : '' ?>>Keto</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="currency">Currency</label>
                            <select id="currency" name="currency" class="form-control">
                                <option value="₹" <?= $currency === '₹' ? 'selected' : '' ?>>₹ INR</option>
                                <option value="$" <?= $currency === '$' ? 'selected' : '' ?>>$ USD</option>
                                <option value="€" <?= $currency === '€' ? 'selected' : '' ?>>€ EUR</option>
                                <option value="£" <?= $currency === '£' ? 'selected' : '' ?>>£ GBP</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px; padding: 13px;">
                        Create My Smart Fridge
                    </button>
                </form>

                <div style="margin-top: 24px; text-align: center; font-size: 14px; color: var(--text-secondary);">
                    Already have an account? <a href="login.php" style="font-weight: 600;">Sign in here</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
