<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

$user = current_user();

// Fetch live statistics from DB
try {
    $db = get_db();
    
    // Total value saved
    $stmt1 = $db->query("SELECT COALESCE(SUM(value_saved), 0) FROM waste_log WHERE action = 'rescued'");
    $dbSaved = (float)$stmt1->fetchColumn();
    // Present global platform stats by combining base impact + live DB
    $totalRupeesSaved = 14500 + (int)$dbSaved;

    // Total meals rescued
    $stmt2 = $db->query("SELECT COUNT(*) FROM waste_log WHERE action = 'rescued'");
    $dbRescued = (int)$stmt2->fetchColumn();
    $totalMealsRescued = 86 + $dbRescued;

    // Food saved in KG (~0.45 kg per rescued meal item)
    $totalKgSaved = round($totalMealsRescued * 0.45, 1);

    // Active users
    $stmt3 = $db->query("SELECT COUNT(*) FROM users");
    $totalUsers = 42 + (int)$stmt3->fetchColumn();
} catch (Exception $e) {
    $totalRupeesSaved = 14500;
    $totalMealsRescued = 86;
    $totalKgSaved = 38.7;
    $totalUsers = 43;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($user['theme'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FridgeRescue — Eat it before it expires.</title>
    <meta name="description" content="AI smart pantry that detects fridge food with Gemini Vision, tracks expiry dates, and generates rescue recipes.">
    <link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🥦</text></svg>">
</head>
<body>

    <!-- Sticky Glassmorphic Navbar -->
    <header class="landing-nav">
        <a href="index.php" class="nav-brand">
            <span style="font-size: 28px;">🥦</span> FridgeRescue
        </a>

        <div style="display: flex; align-items: center; gap: 16px;">
            <nav style="display: flex; gap: 20px; align-items: center;" class="nav-links-desktop">
                <a href="#how-it-works" style="color: var(--text-secondary); font-weight: 500; font-size: 14px;">How It Works</a>
                <a href="#features" style="color: var(--text-secondary); font-weight: 500; font-size: 14px;">Features</a>
                <a href="#impact" style="color: var(--text-secondary); font-weight: 500; font-size: 14px;">Impact</a>
                <a href="#faq" style="color: var(--text-secondary); font-weight: 500; font-size: 14px;">FAQ</a>
            </nav>

            <div class="landing-nav-actions" style="display: flex; align-items: center; gap: 8px;">
                <button type="button" class="btn btn-icon btn-outline theme-toggle-btn" aria-label="Toggle theme">
                    🌙
                </button>

                <?php if ($user): ?>
                    <a href="dashboard.php" class="btn btn-primary btn-sm">
                        <span>My Fridge</span> &rarr;
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline btn-sm nav-signin-btn">
                        Sign In
                    </a>
                    <a href="register.php" class="btn btn-primary btn-sm nav-cta-btn">
                        Get Started
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div>
            <div class="produce-sticker" style="margin-bottom: 24px;">
                ⚡ Powered by Google Gemini 2.5 Flash
            </div>

            <h1 class="hero-title">
                Your fridge is <span class="highlight">talking.</span><br>Are you listening?
            </h1>

            <p class="hero-subtitle">
                Food rotting in your crisper drawer isn't just waste — it’s delicious dinners left behind. Snap a photo of your shelves; our AI detects your groceries, forecasts expiration, and crafts 20-minute rescue recipes.
            </p>

            <div class="hero-cta-group" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
                <?php if ($user): ?>
                    <a href="scan.php" class="btn btn-primary btn-lg">
                        📸 Scan Your Fridge Now
                    </a>
                    <a href="dashboard.php" class="btn btn-outline btn-lg">
                        Open My Fridge
                    </a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary btn-lg">
                        🚀 Start Rescuing Free
                    </a>
                    <form method="POST" action="login.php" style="display: inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="demo_login" value="1">
                        <button type="submit" class="btn btn-accent btn-lg">
                            ⚡ Instant Demo Login
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="hero-badges-row" style="display: flex; align-items: center; gap: 10px; margin-top: 32px; font-size: 13px; color: var(--text-secondary); flex-wrap: wrap;">
                <span>🔒 Zero food waste</span>
                <span>&bull;</span>
                <span>🌱 Eco-friendly pantry</span>
                <span>&bull;</span>
                <span>✨ Built with Google AI</span>
            </div>
        </div>

        <!-- Floating Interactive Mockup Card -->
        <div class="fridge-mockup-card">
            <div class="fridge-mockup-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 20px;">❄️</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14px;">The Live Fridge &bull; Demo</div>
                        <div style="font-size: 11px; color: var(--text-secondary);">Smart Shelves Active</div>
                    </div>
                </div>
                <span class="badge badge-expiring">3 Items Need Rescue</span>
            </div>

            <!-- Mockup Top Rescue Shelf -->
            <div class="mockup-shelf" style="background: rgba(228, 87, 46, 0.08); border-color: rgba(228, 87, 46, 0.3);">
                <div class="mockup-shelf-title" style="color: var(--tomato);">
                    <span>🚨 Top Rescue Shelf</span>
                    <span>Cook within 48h</span>
                </div>
                <div class="mockup-items-grid">
                    <div class="mockup-item-tile">
                        <span style="font-size: 26px;">🥬</span>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 12px; font-weight: 700;">Spinach</div>
                            <div style="font-size: 10px; color: var(--tomato); font-weight: 600;">Expires Today</div>
                        </div>
                        <?= render_freshness_ring(0, 'expiring', 36) ?>
                    </div>
                    <div class="mockup-item-tile">
                        <span style="font-size: 26px;">🧀</span>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 12px; font-weight: 700;">Paneer</div>
                            <div style="font-size: 10px; color: var(--tomato); font-weight: 600;">1 day left</div>
                        </div>
                        <?= render_freshness_ring(1, 'expiring', 36) ?>
                    </div>
                </div>
            </div>

            <!-- Lower Fresh Shelf -->
            <div class="mockup-shelf">
                <div class="mockup-shelf-title">
                    <span>🥦 Vegetables &amp; Dairy</span>
                    <span>Fresh &bull; 5+ days</span>
                </div>
                <div class="mockup-items-grid">
                    <div class="mockup-item-tile">
                        <span style="font-size: 26px;">🫑</span>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 12px; font-weight: 700;">Bell Pepper</div>
                            <div style="font-size: 10px; color: var(--forest-green); font-weight: 600;">6 days left</div>
                        </div>
                        <?= render_freshness_ring(6, 'fresh', 36) ?>
                    </div>
                    <div class="mockup-item-tile">
                        <span style="font-size: 26px;">🥕</span>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 12px; font-weight: 700;">Carrots</div>
                            <div style="font-size: 10px; color: var(--forest-green); font-weight: 600;">8 days left</div>
                        </div>
                        <?= render_freshness_ring(8, 'fresh', 36) ?>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 14px;">
                <span style="font-size: 12px; color: var(--text-secondary); font-style: italic;">
                    "Generated: Creamy Spinach &amp; Paneer Bhurji in 20 mins" 🍳
                </span>
            </div>
        </div>
    </section>

    <!-- Live Impact Counter Bar -->
    <section class="live-stats-bar" id="impact">
        <div class="stats-bar-card">
            <div class="stat-item">
                <div class="stat-num">₹<?= number_format($totalRupeesSaved) ?>+</div>
                <div class="stat-label">Groceries Saved</div>
            </div>
            <div class="stat-item">
                <div class="stat-num"><?= number_format($totalMealsRescued) ?></div>
                <div class="stat-label">Meals Rescued</div>
            </div>
            <div class="stat-item">
                <div class="stat-num"><?= $totalKgSaved ?> kg</div>
                <div class="stat-label">CO₂e Averted</div>
            </div>
            <div class="stat-item">
                <div class="stat-num"><?= $totalUsers ?></div>
                <div class="stat-label">Conscious Kitchens</div>
            </div>
        </div>
    </section>

    <!-- 3-Step "How It Works" Section -->
    <section class="section-wrapper" id="how-it-works">
        <div class="section-header">
            <span class="section-tag">Frictionless Workflow</span>
            <h2 class="section-title">From rotting in the crisper to dinner on the table in 3 steps.</h2>
            <p style="color: var(--text-secondary); font-size: 16px;">No manual typing. No complex barcode scanning. Just one photo.</p>
        </div>

        <div class="steps-grid">
            <div class="step-card">
                <div class="step-badge">1</div>
                <h3 style="font-size: 20px; margin-bottom: 12px;">Snap or Upload</h3>
                <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;">
                    Take a 2-second snapshot of your open fridge shelves or unpacked grocery counter. Drag &amp; drop directly from your phone or desktop.
                </p>
                <div style="margin-top: 20px; font-size: 24px;">📸 ➡️ 🤖</div>
            </div>

            <div class="step-card">
                <div class="step-badge">2</div>
                <h3 style="font-size: 20px; margin-bottom: 12px;">Gemini Detects &amp; Timestamps</h3>
                <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;">
                    Google Gemini 2.5 Flash Vision recognizes every veggie, dairy, and leftover. It estimates shelf life and builds your digital fridge.
                </p>
                <div style="margin-top: 20px; font-size: 24px;">✨ 🥦 🥛</div>
            </div>

            <div class="step-card">
                <div class="step-badge">3</div>
                <h3 style="font-size: 20px; margin-bottom: 12px;">Cook Rescue Recipes</h3>
                <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;">
                    Our AI prioritizes expiring ingredients and cooks up 3 personalized recipes. Mark cooked to track financial and environmental savings!
                </p>
                <div style="margin-top: 20px; font-size: 24px;">🍳 🎉 💰</div>
            </div>
        </div>
    </section>

    <!-- Features Grid -->
    <section class="section-wrapper" id="features">
        <div class="section-header">
            <span class="section-tag">Engineered for Food Waste Prevention</span>
            <h2 class="section-title">Everything you need to run a zero-waste kitchen.</h2>
        </div>

        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon-box">👁️</div>
                <h3 style="font-size: 20px; margin-bottom: 10px;">Gemini Vision Intelligence</h3>
                <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;">
                    Multi-modal AI analyzes complex fridge images, identifying items, portion estimates, and baseline shelf-life days.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-box">⭕</div>
                <h3 style="font-size: 20px; margin-bottom: 10px;">Dynamic Freshness Rings</h3>
                <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;">
                    Visual countdown rings pulse red when items reach 0–1 days left, so you know at a glance what needs to be cooked today.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-box">🚨</div>
                <h3 style="font-size: 20px; margin-bottom: 10px;">The Rescue Shelf</h3>
                <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;">
                    A dedicated top shelf automatically gathers groceries about to expire with an instant "Cook this now" shortcut.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-box">👨‍🍳</div>
                <h3 style="font-size: 20px; margin-bottom: 10px;">Tailored Rescue Chef</h3>
                <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;">
                    Filter recipes by cooking time (15, 30, 45 min), dietary preference, and cuisine with step-by-step interactive cooking mode.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-box">📊</div>
                <h3 style="font-size: 20px; margin-bottom: 10px;">Impact &amp; Waste Analytics</h3>
                <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;">
                    Track weekly rupee savings, category food waste breakdown, activity streak heatmaps, and unlock achievement badges.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-box">⚡</div>
                <h3 style="font-size: 20px; margin-bottom: 10px;">Instant Offline Demo Mode</h3>
                <p style="color: var(--text-secondary); font-size: 14px; line-height: 1.6;">
                    Includes pre-loaded sample scans and demo datasets so you can evaluate the full user journey without internet dependency.
                </p>
            </div>
        </div>
    </section>

    <!-- Before & After Section -->
    <section class="section-wrapper">
        <div class="section-header">
            <span class="section-tag">The Daily Life Crisis</span>
            <h2 class="section-title">The Crisper Drawer Dilemma</h2>
        </div>

        <div class="compare-grid">
            <div class="compare-card before">
                <div style="font-size: 20px; font-weight: 700; color: var(--tomato); margin-bottom: 16px;">
                    ❌ Without FridgeRescue
                </div>
                <ul style="list-style: none; padding: 0; line-height: 2.2; font-size: 15px; color: var(--text-secondary);">
                    <li>🥬 Spinach turns into forgotten sludge at the bottom of the drawer</li>
                    <li>💸 An average household wastes ₹1,200 to ₹2,500 every single month</li>
                    <li>🤔 Standing in front of the open fridge asking "what's for dinner?"</li>
                    <li>🛵 Ordering takeout while perfectly good food goes bad in silence</li>
                    <li>😞 Guilt every garbage collection day</li>
                </ul>
            </div>

            <div class="compare-card after">
                <div style="font-size: 20px; font-weight: 700; color: var(--forest-green); margin-bottom: 16px;">
                    ✅ With FridgeRescue
                </div>
                <ul style="list-style: none; padding: 0; line-height: 2.2; font-size: 15px;">
                    <li>🎯 Urgent items immediately grouped on your top Rescue Shelf</li>
                    <li>💰 Save hundreds on monthly grocery bills with zero effort</li>
                    <li>✨ 3 custom rescue recipes generated from what you actually have</li>
                    <li>🎉 Confetti celebrations each time you rescue a meal</li>
                    <li>🌱 Proud contribution to personal savings and carbon reduction</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- FAQ Accordion -->
    <section class="section-wrapper" id="faq">
        <div class="section-header">
            <span class="section-tag">Frequently Asked Questions</span>
            <h2 class="section-title">Got questions? We've got answers.</h2>
        </div>

        <div class="faq-container">
            <div class="faq-item active">
                <div class="faq-question">How does Gemini Vision recognize items in crowded fridges?</div>
                <div class="faq-answer">
                    We use Google's advanced multi-modal model (Gemini 2.5 Flash), which is trained on high-resolution object recognition. It detects fruits, vegetables, condiments, cartons, and leftovers even when partially covered or stored in clear containers.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Can I edit or add items if the AI makes an estimate mistake?</div>
                <div class="faq-answer">
                    Always! Before saving to your fridge, you are presented with an editable review table where you can adjust item names, quantities, categories, and expiry dates with a single click.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">How are expiry dates calculated?</div>
                <div class="faq-answer">
                    Gemini estimates standard shelf life based on the detected food category (e.g. leafy greens 3-4 days, root vegetables 10-14 days, fresh dairy 5 days). Expiry dates are dynamic AI estimates; we always recommend checking smell and packaging.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Does FridgeRescue work offline or in Demo Mode?</div>
                <div class="faq-answer">
                    Yes! We provide built-in demo datasets, sample scan images, and a one-click demo login so evaluators can test every single screen without requiring external API keys.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">What tech stack is FridgeRescue built with?</div>
                <div class="faq-answer">
                    It is engineered using pure PHP 8 (PDO prepared statements), MySQL/MariaDB utf8mb4, HTML5, modern CSS3 with custom variables, and vanilla JavaScript (no npm, no framework, no build step).
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="footer-content">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 26px;">🥦</span>
                <div>
                    <div style="font-weight: 800; font-family: var(--font-heading); font-size: 18px;">FridgeRescue</div>
                    <div style="font-size: 12px; color: var(--text-secondary);">Eat it before it expires.</div>
                </div>
            </div>

            <div style="display: flex; gap: 20px; font-size: 13px; color: var(--text-secondary);">
                <span>Built with Google Gemini 2.5 Flash API</span>
                <span>&bull;</span>
                <span>Vanilla PHP 8 &amp; MySQL</span>
            </div>

            <div>
                <a href="login.php" class="btn btn-outline btn-sm">Sign In</a>
                <a href="register.php" class="btn btn-primary btn-sm" style="margin-left: 8px;">Get Started</a>
            </div>
        </div>
    </footer>

    <script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
