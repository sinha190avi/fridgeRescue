<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
?>
<!-- Mobile Top Navigation Header -->
<header class="mobile-topbar">
    <a href="dashboard.php" style="display: flex; align-items: center; gap: 8px; font-weight: 800; font-family: var(--font-heading); font-size: 18px; color: var(--primary);">
        <span style="font-size: 22px;">🥦</span> FridgeRescue
    </a>
    <div style="display: flex; align-items: center; gap: 8px;">
        <button type="button" class="btn btn-icon btn-outline theme-toggle-btn" aria-label="Toggle theme" style="width: 34px; height: 34px; font-size: 14px;">
            🌙
        </button>
        <a href="logout.php" class="btn btn-icon btn-outline" title="Logout" style="width: 34px; height: 34px; font-size: 14px;">
            🚪
        </a>
    </div>
</header>

<!-- Desktop Sidebar -->
<aside class="sidebar">
    <div style="margin-bottom: 32px; padding: 0 8px;">
        <a href="dashboard.php" style="display: flex; align-items: center; gap: 10px; font-weight: 800; font-family: var(--font-heading); font-size: 22px; color: var(--primary);">
            <span style="font-size: 28px;">🥦</span> FridgeRescue
        </a>
        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; color: var(--text-secondary); margin-top: 4px; padding-left: 38px;">
            The Live Fridge
        </div>
    </div>

    <!-- Navigation List -->
    <nav style="flex: 1;">
        <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <span style="font-size: 18px;">❄️</span>
            <span>My Fridge</span>
            <?php if (!empty($expiringCount) && $expiringCount > 0): ?>
                <span class="badge badge-expiring" style="margin-left: auto; font-size: 11px; padding: 2px 7px;">
                    <?= $expiringCount ?>
                </span>
            <?php endif; ?>
        </a>

        <a href="scan.php" class="nav-link <?= $currentPage === 'scan.php' ? 'active' : '' ?>">
            <span style="font-size: 18px;">📸</span>
            <span>Scan Groceries</span>
        </a>

        <a href="recipes.php" class="nav-link <?= $currentPage === 'recipes.php' ? 'active' : '' ?>">
            <span style="font-size: 18px;">🍳</span>
            <span>Rescue Recipes</span>
        </a>

        <a href="impact.php" class="nav-link <?= $currentPage === 'impact.php' ? 'active' : '' ?>">
            <span style="font-size: 18px;">📊</span>
            <span>Impact &amp; Stats</span>
        </a>

        <a href="settings.php" class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
            <span style="font-size: 18px;">⚙️</span>
            <span>Settings</span>
        </a>
    </nav>

    <!-- Expiry Alert Banner (if items need cooking) -->
    <?php if (!empty($expiringCount) && $expiringCount > 0): ?>
        <div style="background: var(--tomato-soft); border: 1px solid rgba(228,87,46,0.3); border-radius: var(--radius-md); padding: 12px; margin-bottom: 16px;">
            <div style="font-size: 12px; font-weight: 700; color: var(--tomato); display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                <span>🔔</span> <?= $expiringCount ?> item<?= $expiringCount > 1 ? 's' : '' ?> expiring soon!
            </div>
            <a href="recipes.php" style="font-size: 11px; font-weight: 600; color: var(--tomato); text-decoration: underline;">
                Cook them now &rarr;
            </a>
        </div>
    <?php endif; ?>

    <!-- User Profile & Theme Bar -->
    <div style="padding-top: 16px; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
            <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--lime-soft); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                👨‍🍳
            </div>
            <div style="min-width: 0;">
                <div style="font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?= e($currentUser['name'] ?? 'Chef') ?>
                </div>
                <div style="font-size: 11px; color: var(--text-secondary); text-transform: capitalize;">
                    <?= e($currentUser['diet_pref'] ?? 'all') ?>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 6px;">
            <button type="button" class="btn btn-icon btn-outline theme-toggle-btn" aria-label="Toggle dark mode" style="width: 32px; height: 32px; font-size: 14px;">
                🌙
            </button>
            <a href="logout.php" class="btn btn-icon btn-outline" title="Logout" style="width: 32px; height: 32px; font-size: 14px;">
                🚪
            </a>
        </div>
    </div>
</aside>
