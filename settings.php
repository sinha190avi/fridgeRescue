<?php
$pageTitle = 'Settings — FridgeRescue';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$userId = (int)$currentUser['id'];
$db = get_db();
$flash = get_flash();
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errorMsg = 'Security token invalid. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Update Profile & Preferences
        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $dietPref = $_POST['diet_pref'] ?? 'all';
            $currency = $_POST['currency'] ?? '₹';
            $alertDays = max(1, min(7, (int)($_POST['alert_days'] ?? 3)));
            $theme = ($_POST['theme'] ?? '') === 'dark' ? 'dark' : 'light';

            if (empty($name)) {
                $errorMsg = 'Name cannot be empty.';
            } else {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET name = :name, diet_pref = :diet, currency = :currency, alert_days = :alert, theme = :theme
                    WHERE id = :uid
                ");
                $stmt->execute([
                    'name' => $name,
                    'diet' => $dietPref,
                    'currency' => $currency,
                    'alert' => $alertDays,
                    'theme' => $theme,
                    'uid' => $userId
                ]);

                $_SESSION['user_name'] = $name;
                $_SESSION['user_theme'] = $theme;
                $_SESSION['user_currency'] = $currency;

                $successMsg = 'Profile and kitchen preferences updated!';
                // Refresh currentUser
                $currentUser = current_user();
            }
        }

        // 2. Change Password
        elseif ($action === 'change_password') {
            $currentPass = $_POST['current_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';

            if (empty($currentPass) || empty($newPass)) {
                $errorMsg = 'Please fill in all password fields.';
            } elseif (strlen($newPass) < 6) {
                $errorMsg = 'New password must be at least 6 characters.';
            } elseif ($newPass !== $confirmPass) {
                $errorMsg = 'New passwords do not match.';
            } else {
                // Verify current password
                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :uid");
                $stmt->execute(['uid' => $userId]);
                $hash = $stmt->fetchColumn();

                if ($hash && password_verify($currentPass, $hash)) {
                    $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                    $upStmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :uid");
                    $upStmt->execute(['hash' => $newHash, 'uid' => $userId]);
                    $successMsg = 'Password changed successfully!';
                } else {
                    $errorMsg = 'Current password is incorrect.';
                }
            }
        }

        // 3. Delete Account
        elseif ($action === 'delete_account') {
            $delConfirm = $_POST['delete_confirm'] ?? '';
            if ($delConfirm === 'DELETE') {
                $delStmt = $db->prepare("DELETE FROM users WHERE id = :uid");
                $delStmt->execute(['uid' => $userId]);
                logout_user();
                set_flash('info', 'Your FridgeRescue account has been deleted.');
                header('Location: index.php');
                exit;
            } else {
                $errorMsg = 'Please type "DELETE" exactly to confirm account deletion.';
            }
        }
    }
}
?>

<main class="main-content">
    <div style="max-width: 800px; margin: 0 auto;">
        <!-- Header -->
        <div style="margin-bottom: 28px;">
            <div class="produce-sticker" style="margin-bottom: 10px;">
                ⚙️ Kitchen Configuration
            </div>
            <h1 style="font-size: 32px; letter-spacing: -0.01em;">Settings &amp; Preferences</h1>
            <p style="color: var(--text-secondary); font-size: 14px;">
                Configure your shelf-life notifications, dietary restrictions, and kitchen credentials.
            </p>
        </div>

        <?php if ($successMsg): ?>
            <div style="padding: 14px 18px; border-radius: var(--radius-md); background: var(--lime-soft); color: var(--forest-green); border: 1px solid rgba(166,216,84,0.5); margin-bottom: 24px; font-size: 14px; font-weight: 500;">
                ✅ <?= e($successMsg) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div style="padding: 14px 18px; border-radius: var(--radius-md); background: var(--tomato-soft); color: var(--tomato); border: 1px solid rgba(228,87,46,0.4); margin-bottom: 24px; font-size: 14px; font-weight: 500;">
                ⚠️ <?= e($errorMsg) ?>
            </div>
        <?php endif; ?>

        <!-- Card 1: Profile & Preferences -->
        <div class="chart-card" style="margin-bottom: 28px;">
            <h2 style="font-size: 20px; margin-bottom: 6px;">Pantry Profile &amp; Preferences</h2>
            <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                Control how Gemini tailors recipe recommendations and expiry radar notifications.
            </p>

            <form method="POST" action="settings.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="settingsName">Full Name</label>
                        <input type="text" id="settingsName" name="name" class="form-control" value="<?= e($currentUser['name']) ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="settingsEmail">Email Address (Read-only)</label>
                        <input type="email" id="settingsEmail" class="form-control" value="<?= e($currentUser['email']) ?>" disabled style="opacity: 0.7;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="settingsDiet">Dietary Style</label>
                        <select id="settingsDiet" name="diet_pref" class="form-control">
                            <option value="all" <?= ($currentUser['diet_pref'] ?? '') === 'all' ? 'selected' : '' ?>>No restrictions (All)</option>
                            <option value="vegetarian" <?= ($currentUser['diet_pref'] ?? '') === 'vegetarian' ? 'selected' : '' ?>>Vegetarian</option>
                            <option value="vegan" <?= ($currentUser['diet_pref'] ?? '') === 'vegan' ? 'selected' : '' ?>>Vegan</option>
                            <option value="non_veg" <?= ($currentUser['diet_pref'] ?? '') === 'non_veg' ? 'selected' : '' ?>>Non-Vegetarian</option>
                            <option value="keto" <?= ($currentUser['diet_pref'] ?? '') === 'keto' ? 'selected' : '' ?>>Keto</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="settingsCurrency">Currency Symbol</label>
                        <select id="settingsCurrency" name="currency" class="form-control">
                            <option value="₹" <?= ($currentUser['currency'] ?? '') === '₹' ? 'selected' : '' ?>>₹ INR (Rupees)</option>
                            <option value="$" <?= ($currentUser['currency'] ?? '') === '$' ? 'selected' : '' ?>>$ USD (Dollars)</option>
                            <option value="€" <?= ($currentUser['currency'] ?? '') === '€' ? 'selected' : '' ?>>€ EUR (Euros)</option>
                            <option value="£" <?= ($currentUser['currency'] ?? '') === '£' ? 'selected' : '' ?>>£ GBP (Pounds)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="settingsAlert">Expiry Alert Days</label>
                        <select id="settingsAlert" name="alert_days" class="form-control">
                            <option value="1" <?= (int)($currentUser['alert_days'] ?? 3) === 1 ? 'selected' : '' ?>>1 day before</option>
                            <option value="2" <?= (int)($currentUser['alert_days'] ?? 3) === 2 ? 'selected' : '' ?>>2 days before</option>
                            <option value="3" <?= (int)($currentUser['alert_days'] ?? 3) === 3 ? 'selected' : '' ?>>3 days before (Recommended)</option>
                            <option value="5" <?= (int)($currentUser['alert_days'] ?? 3) === 5 ? 'selected' : '' ?>>5 days before</option>
                            <option value="7" <?= (int)($currentUser['alert_days'] ?? 3) === 7 ? 'selected' : '' ?>>7 days before</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Theme Mode</label>
                    <div style="display: flex; gap: 16px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="theme" value="light" <?= ($currentUser['theme'] ?? 'light') === 'light' ? 'checked' : '' ?> onchange="setTheme('light', true)">
                            <span>☀️ Light Mode</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="theme" value="dark" <?= ($currentUser['theme'] ?? 'light') === 'dark' ? 'checked' : '' ?> onchange="setTheme('dark', true)">
                            <span>🌙 Dark Mode</span>
                        </label>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                </div>
            </form>
        </div>

        <!-- Card 2: Security & Password -->
        <div class="chart-card" style="margin-bottom: 28px;">
            <h2 style="font-size: 20px; margin-bottom: 6px;">Security &amp; Password</h2>
            <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                Update your login credentials securely.
            </p>

            <form method="POST" action="settings.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label class="form-label" for="currentPass">Current Password</label>
                    <input type="password" id="currentPass" name="current_password" class="form-control" placeholder="••••••••" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="newPass">New Password</label>
                        <input type="password" id="newPass" name="new_password" class="form-control" placeholder="Min 6 characters" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="confirmPass">Confirm New Password</label>
                        <input type="password" id="confirmPass" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-outline">Change Password</button>
                </div>
            </form>
        </div>

        <!-- Card 3: Danger Zone -->
        <div class="chart-card" style="border-color: rgba(228, 87, 46, 0.4); background: rgba(228, 87, 46, 0.03);">
            <h2 style="font-size: 20px; color: var(--tomato); margin-bottom: 6px;">Danger Zone</h2>
            <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                Permanently delete your FridgeRescue account, all saved fridge items, and rescue logs. This action cannot be undone.
            </p>

            <form method="POST" action="settings.php" onsubmit="return confirmAccountDeletion(event)">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_account">

                <div class="form-group">
                    <label class="form-label" style="color: var(--tomato);">
                        To confirm deletion, type <strong>DELETE</strong> below:
                    </label>
                    <input type="text" id="deleteConfirmInput" name="delete_confirm" class="form-control" placeholder="DELETE" style="max-width: 240px;" required>
                </div>

                <button type="submit" class="btn btn-rescue">
                    🗑️ Permanently Delete My Account
                </button>
            </form>
        </div>

    </div>
</main>

<script>
function confirmAccountDeletion(e) {
    const val = document.getElementById('deleteConfirmInput').value.trim();
    if (val !== 'DELETE') {
        alert('Please type DELETE in capital letters to confirm.');
        e.preventDefault();
        return false;
    }
    return confirm('Are you absolutely sure you want to delete your entire fridge account?');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
