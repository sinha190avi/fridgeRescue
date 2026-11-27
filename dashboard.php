<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
$currentUser = require_login();

// Handle quick reseed / demo reset or auto-repair stale/corrupted demo pantry
$isDemoUser = (($currentUser['email'] ?? '') === 'demo@fridgerescue.com' || (int)($currentUser['id'] ?? 0) === 1);
if ($isDemoUser) {
    require_once __DIR__ . '/database/seed.php';
    if (isset($_GET['reseed'])) {
        seed_demo_database();
        header('Location: dashboard.php');
        exit;
    }
    
    // Auto-detect if demo pantry contains expired items (> 2 days ago)
    try {
        $dbCheck = get_db();
        $staleStmt = $dbCheck->query("
            SELECT 
                SUM(CASE WHEN expiry_date < DATE_SUB(CURDATE(), INTERVAL 2 DAY) THEN 1 ELSE 0 END) as expired_cnt,
                COUNT(*) as total_cnt
            FROM fridge_items 
            WHERE user_id = 1 AND status = 'active'
        ");
        $statsCheck = $staleStmt ? $staleStmt->fetch() : null;
        if ($statsCheck && (($statsCheck['total_cnt'] ?? 0) < 5 || ($statsCheck['expired_cnt'] ?? 0) >= 2)) {
            seed_demo_database();
            header('Location: dashboard.php');
            exit;
        }
    } catch (Throwable $ignore) {}
}

$pageTitle = 'The Live Fridge — FridgeRescue';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$userId = (int)$currentUser['id'];
$currency = $currentUser['currency'] ?? '₹';
$alertDays = (int)($currentUser['alert_days'] ?? 3);

// Fetch all active fridge items
$db = get_db();
$stmtItems = $db->prepare("
    SELECT * 
    FROM fridge_items 
    WHERE user_id = :uid AND status = 'active'
    ORDER BY expiry_date ASC, name ASC
");
$stmtItems->execute(['uid' => $userId]);
$allItems = $stmtItems->fetchAll();

// Group items for shelves
$rescueItems = [];
$categoryShelves = [
    'vegetable' => ['title' => 'Vegetables & Greens', 'emoji' => '🥦', 'items' => []],
    'fruit'     => ['title' => 'Fresh Fruits', 'emoji' => '🍎', 'items' => []],
    'dairy'     => ['title' => 'Dairy & Chilled', 'emoji' => '🥛', 'items' => []],
    'leftover'  => ['title' => 'Leftovers & Prepped Meals', 'emoji' => '🍲', 'items' => []],
    'other'     => ['title' => 'Pantry & Other', 'emoji' => '🥫', 'items' => []]
];

$totalActive = count($allItems);
$expiringSoonCount = 0;

foreach ($allItems as $item) {
    $freshness = get_freshness_data($item['expiry_date'], $item['added_on']);
    $item['freshness'] = $freshness;

    // Rescue shelf collects anything expiring within 2 days or expired
    if ($freshness['days'] <= 2) {
        $rescueItems[] = $item;
    }

    if ($freshness['days'] <= $alertDays) {
        $expiringSoonCount++;
    }

    $cat = strtolower($item['category']);
    if (isset($categoryShelves[$cat])) {
        $categoryShelves[$cat]['items'][] = $item;
    } else {
        $categoryShelves['other']['items'][] = $item;
    }
}

// Stats: Money Saved This Month & Recipes Cooked
$stmtStats = $db->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN action = 'rescued' AND logged_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN value_saved ELSE 0 END), 0) AS saved_month,
        COUNT(CASE WHEN action = 'rescued' THEN 1 END) AS recipes_cooked
    FROM waste_log
    WHERE user_id = :uid
");
$stmtStats->execute(['uid' => $userId]);
$stats = $stmtStats->fetch() ?: ['saved_month' => 0, 'recipes_cooked' => 0];

// Dynamic Greeting based on current hour
$hour = (int)date('H');
if ($hour < 12) {
    $greeting = "Good morning";
} elseif ($hour < 17) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}
?>

<main class="main-content">
    <!-- Top Greeting & Header Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div class="produce-sticker" style="margin-bottom: 10px;">
                🥦 The Live Fridge View
            </div>
            <h1 style="font-size: 32px; letter-spacing: -0.01em;">
                <?= $greeting ?>, <?= e(explode(' ', $currentUser['name'])[0]) ?>!
            </h1>
            <p style="color: var(--text-secondary); font-size: 14px;">
                <?= date('l, F j, Y') ?> &bull; 
                <?php if ($expiringSoonCount > 0): ?>
                    <strong style="color: var(--tomato);"><?= $expiringSoonCount ?> item<?= $expiringSoonCount > 1 ? 's' : '' ?></strong> need your culinary attention!
                <?php else: ?>
                    All items are fresh and accounted for.
                <?php endif; ?>
            </p>
        </div>

        <div class="header-action-group" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="scan.php" class="btn btn-primary btn-sm">
                📸 Scan Fridge
            </a>
            <button type="button" class="btn btn-outline btn-sm" onclick="openModal('addItemModal')">
                + Add Item
            </button>
            <a href="scan.php?demo=1" class="btn btn-accent btn-sm" title="Test instant scan demo">
                ⚡ Demo Scan
            </a>
            <?php if (($currentUser['email'] ?? '') === 'demo@fridgerescue.com'): ?>
                <a href="dashboard.php?reseed=1" class="btn btn-outline btn-sm" title="Reset demo items with today's fresh dates & emojis">
                    🔄 Reset Demo
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 Summary Metric Cards -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-icon" style="background: var(--lime-soft); color: var(--forest-green);">
                ❄️
            </div>
            <div>
                <div class="metric-val"><?= $totalActive ?></div>
                <div class="metric-label">Items in Fridge</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon" style="background: var(--tomato-soft); color: var(--tomato);">
                ⏰
            </div>
            <div>
                <div class="metric-val" style="color: <?= $expiringSoonCount > 0 ? 'var(--tomato)' : 'inherit' ?>;">
                    <?= $expiringSoonCount ?>
                </div>
                <div class="metric-label">Expiring Soon (≤ <?= $alertDays ?>d)</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon" style="background: var(--mango-soft); color: #9C6B05;">
                💰
            </div>
            <div>
                <div class="metric-val"><?= format_money($stats['saved_month'], $currency) ?></div>
                <div class="metric-label">Saved This Month</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon" style="background: rgba(31, 77, 58, 0.1); color: var(--forest-green);">
                🍳
            </div>
            <div>
                <div class="metric-val"><?= (int)$stats['recipes_cooked'] ?></div>
                <div class="metric-label">Rescued Meals Cooked</div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="filter-bar">
        <div class="filter-chips">
            <button class="chip active" data-filter="all" onclick="filterItems('all', this)">All (<?= $totalActive ?>)</button>
            <button class="chip" data-filter="expiring" onclick="filterItems('expiring', this)">🚨 Expiring (0–1d)</button>
            <button class="chip" data-filter="soon" onclick="filterItems('soon', this)">⚠️ Use Soon (2–4d)</button>
            <button class="chip" data-filter="fresh" onclick="filterItems('fresh', this)">🌱 Fresh (5+ d)</button>
            <button class="chip" data-filter="expired" onclick="filterItems('expired', this)">💀 Expired</button>
        </div>

        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" id="fridgeSearch" class="search-input" placeholder="Search tomatoes, milk, paneer..." oninput="searchItems(this.value)">
        </div>
    </div>

    <!-- THE LIVE FRIDGE CONTAINER -->
    <div class="live-fridge-container">

        <!-- 1. TOP RESCUE SHELF (Urgent items expiring within 2 days) -->
        <?php if (!empty($rescueItems)): ?>
            <div class="rescue-shelf-card" id="rescueShelfSection">
                <div class="rescue-shelf-header">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">🚨</span>
                        <div>
                            <h2 style="font-size: 20px; color: var(--tomato); margin-bottom: 2px;">
                                Top Rescue Shelf
                            </h2>
                            <div style="font-size: 13px; color: var(--text-secondary);">
                                These items need to be eaten within 48 hours to avoid spoiling!
                            </div>
                        </div>
                    </div>

                    <?php
                        $rescueNames = array_map(fn($i) => $i['name'], $rescueItems);
                        $rescueUrlParam = urlencode(implode(',', array_slice($rescueNames, 0, 4)));
                    ?>
                    <a href="recipes.php?items=<?= $rescueUrlParam ?>" class="btn btn-rescue btn-sm">
                        👨‍🍳 Cook All Rescued Items Now &rarr;
                    </a>
                </div>

                <div class="shelf-items-grid">
                    <?php foreach ($rescueItems as $item): ?>
                        <?= renderFoodCard($item, $currency, true) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2. CATEGORIZED SHELVES -->
        <?php foreach ($categoryShelves as $catKey => $shelf): ?>
            <div class="fridge-shelf" id="shelf-<?= e($catKey) ?>" data-category="<?= e($catKey) ?>">
                <div class="shelf-header">
                    <div class="shelf-title">
                        <span style="font-size: 22px;"><?= $shelf['emoji'] ?></span>
                        <span><?= e($shelf['title']) ?></span>
                        <span style="font-size: 12px; font-weight: 500; color: var(--text-secondary); background: var(--surface-card); padding: 2px 8px; border-radius: var(--radius-full);">
                            <span class="shelf-count"><?= count($shelf['items']) ?></span> items
                        </span>
                    </div>

                    <button type="button" class="btn btn-outline btn-sm" onclick="openAddWithCategory('<?= e($catKey) ?>')">
                        + Add <?= ucfirst($catKey) ?>
                    </button>
                </div>

                <?php if (empty($shelf['items'])): ?>
                    <div class="empty-shelf-msg" style="padding: 24px; text-align: center; color: var(--text-secondary); font-size: 13px; background: rgba(255,255,255,0.4); border-radius: var(--radius-md);">
                        No <?= strtolower($shelf['title']) ?> currently in fridge. 
                        <a href="#" onclick="openAddWithCategory('<?= e($catKey) ?>'); return false;" style="font-weight: 600; text-decoration: underline;">Add some here</a>.
                    </div>
                <?php else: ?>
                    <div class="shelf-items-grid">
                        <?php foreach ($shelf['items'] as $item): ?>
                            <?= renderFoodCard($item, $currency) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div id="noSearchResults" style="display: none; padding: 40px; text-align: center; color: var(--text-secondary);">
            <div style="font-size: 40px; margin-bottom: 10px;">🔍</div>
            <h3 style="font-size: 18px; margin-bottom: 6px;">No groceries found</h3>
            <p style="font-size: 14px;">Try searching for a different food name or clear your filters.</p>
        </div>
    </div>
</main>

<?php
/**
 * Helper to render individual food item card
 */
function renderFoodCard(array $item, string $currency, bool $isRescue = false): string {
    $freshness = $item['freshness'];
    $itemId = (int)$item['id'];
    $name = e($item['name']);
    $emoji = e(clean_item_emoji($item['emoji'] ?? '', $item['name'] ?? '', $item['category'] ?? 'vegetable'));
    $qty = (float)$item['quantity'];
    $unit = e($item['unit']);
    $price = (float)$item['estimated_price'];
    $priceFormatted = $currency . ' ' . number_format($price, 0);
    $status = $freshness['status'];
    $label = e($freshness['label']);
    $ring = render_freshness_ring($freshness['days'], $status, 42);
    $cat = e($item['category']);
    $expiryDate = e($item['expiry_date']);

    $cookParam = urlencode($item['name']);

    return <<<HTML
    <div class="food-card" 
         data-id="{$itemId}"
         data-name="{$name}"
         data-emoji="{$emoji}"
         data-category="{$cat}"
         data-qty="{$qty}"
         data-unit="{$unit}"
         data-price="{$price}"
         data-expiry="{$expiryDate}"
         data-status="{$status}">
        <div class="food-card-top">
            <div class="food-emoji">{$emoji}</div>
            <div class="food-info">
                <div class="food-name" title="{$name}">{$name}</div>
                <div class="food-meta">
                    {$qty} {$unit} &bull; {$priceFormatted}
                </div>
            </div>
            {$ring}
        </div>

        <div class="food-card-bottom">
            <span class="badge {$freshness['badge_class']}">
                {$label}
            </span>

            <div class="food-actions">
                <a href="recipes.php?items={$cookParam}" class="btn btn-icon btn-outline" title="Cook rescue recipe with this" style="width: 30px; height: 30px; font-size: 13px;">
                    🍳
                </a>
                <button type="button" class="btn btn-icon btn-outline" title="Edit item" onclick="triggerEditItem({$itemId})" style="width: 30px; height: 30px; font-size: 13px;">
                    ✏️
                </button>
                <button type="button" class="btn btn-icon btn-outline" title="Remove item" onclick="triggerDeleteItem({$itemId}, '{$name}')" style="width: 30px; height: 30px; font-size: 13px; color: var(--tomato);">
                    🗑️
                </button>
            </div>
        </div>
    </div>
HTML;
}
?>

<script>
// Category emoji map
const defaultCategoryEmojis = {
    vegetable: '🥦',
    fruit: '🍎',
    dairy: '🥛',
    leftover: '🍲',
    other: '🥫'
};

function autoUpdateEmoji(category) {
    const emojiInput = document.getElementById('addEmoji');
    if (emojiInput && defaultCategoryEmojis[category]) {
        emojiInput.value = defaultCategoryEmojis[category];
    }
}

function openAddWithCategory(category) {
    const catSelect = document.getElementById('addCategory');
    if (catSelect) {
        catSelect.value = category;
        autoUpdateEmoji(category);
    }
    openModal('addItemModal');
}

/* ==========================================================
   Filter & Search Client Logic
   ========================================================== */
let currentFilter = 'all';
let currentSearch = '';

function filterItems(status, chipElement) {
    currentFilter = status;
    document.querySelectorAll('.filter-chips .chip').forEach(c => c.classList.remove('active'));
    chipElement.classList.add('active');
    applyFilters();
}

function searchItems(query) {
    currentSearch = query.toLowerCase().trim();
    applyFilters();
}

function applyFilters() {
    const cards = document.querySelectorAll('.food-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const name = (card.getAttribute('data-name') || '').toLowerCase();
        const status = card.getAttribute('data-status');

        const matchesSearch = !currentSearch || name.includes(currentSearch);
        let matchesStatus = true;
        if (currentFilter !== 'all') {
            matchesStatus = (status === currentFilter);
        }

        if (matchesSearch && matchesStatus) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Check empty state
    const noResults = document.getElementById('noSearchResults');
    if (noResults) {
        noResults.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
    }
}

/* ==========================================================
   Manual Add Item
   ========================================================== */
async function handleManualAddItem(e) {
    e.preventDefault();
    const saveBtn = document.getElementById('saveAddBtn');
    saveBtn.disabled = true;
    saveBtn.innerText = 'Saving...';

    const payload = {
        name: document.getElementById('addName').value,
        emoji: document.getElementById('addEmoji').value || '🥦',
        category: document.getElementById('addCategory').value,
        quantity: parseFloat(document.getElementById('addQty').value) || 1,
        unit: document.getElementById('addUnit').value,
        estimated_price: parseFloat(document.getElementById('addPrice').value) || 0,
        expiry_date: document.getElementById('addExpiryDate').value,
        csrf_token: window.CSRF_TOKEN
    };

    try {
        const res = await fetch((window.APP_BASE_URL || '') + '/api/add_item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'Item added!', 'success');
            closeModal('addItemModal');
            setTimeout(() => window.location.reload(), 400);
        } else {
            showToast(data.error || 'Failed to save item', 'error');
        }
    } catch (err) {
        showToast('Network error while saving item', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerText = 'Save to Fridge';
    }
}

/* ==========================================================
   Edit Item Logic
   ========================================================== */
function triggerEditItem(id) {
    const card = document.querySelector(`.food-card[data-id="${id}"]`);
    if (!card) return;

    document.getElementById('editItemId').value = id;
    document.getElementById('editName').value = card.getAttribute('data-name');
    document.getElementById('editEmoji').value = card.getAttribute('data-emoji');
    document.getElementById('editCategory').value = card.getAttribute('data-category');
    document.getElementById('editQty').value = card.getAttribute('data-qty');
    document.getElementById('editUnit').value = card.getAttribute('data-unit');
    document.getElementById('editPrice').value = card.getAttribute('data-price');
    document.getElementById('editExpiryDate').value = card.getAttribute('data-expiry');

    openModal('editItemModal');
}

async function handleEditItemSubmit(e) {
    e.preventDefault();
    const saveBtn = document.getElementById('saveEditBtn');
    saveBtn.disabled = true;
    saveBtn.innerText = 'Updating...';

    const payload = {
        id: document.getElementById('editItemId').value,
        name: document.getElementById('editName').value,
        emoji: document.getElementById('editEmoji').value,
        category: document.getElementById('editCategory').value,
        quantity: parseFloat(document.getElementById('editQty').value) || 1,
        unit: document.getElementById('editUnit').value,
        estimated_price: parseFloat(document.getElementById('editPrice').value) || 0,
        expiry_date: document.getElementById('editExpiryDate').value,
        csrf_token: window.CSRF_TOKEN
    };

    try {
        const res = await fetch((window.APP_BASE_URL || '') + '/api/update_item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showToast('Item updated successfully!', 'success');
            closeModal('editItemModal');
            setTimeout(() => window.location.reload(), 400);
        } else {
            showToast(data.error || 'Failed to update item', 'error');
        }
    } catch (err) {
        showToast('Network error while updating item', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerText = 'Update Item';
    }
}

/* ==========================================================
   Delete Item Logic
   ========================================================== */
let pendingDeleteId = null;

function triggerDeleteItem(id, name) {
    pendingDeleteId = id;
    document.getElementById('deleteConfirmPrompt').innerHTML = 
        `Are you removing <strong>${name}</strong> from your fridge? Select how this food was resolved:`;
    openModal('deleteConfirmModal');
}

document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
    if (!pendingDeleteId) return;

    const actionVal = document.querySelector('input[name="delete_reason"]:checked')?.value || 'wasted';
    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true;
    btn.innerText = 'Removing...';

    try {
        const res = await fetch((window.APP_BASE_URL || '') + '/api/delete_item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: pendingDeleteId,
                action: actionVal,
                csrf_token: window.CSRF_TOKEN
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'info');
            closeModal('deleteConfirmModal');
            // Remove element from DOM smoothly
            const cards = document.querySelectorAll(`.food-card[data-id="${pendingDeleteId}"]`);
            cards.forEach(c => {
                c.style.transition = 'all 0.3s ease';
                c.style.opacity = '0';
                c.style.transform = 'scale(0.8)';
                setTimeout(() => c.remove(), 300);
            });
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.error || 'Failed to delete item', 'error');
        }
    } catch (err) {
        showToast('Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Remove';
        pendingDeleteId = null;
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
