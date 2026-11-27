<?php
$pageTitle = 'Impact & Food Savings — FridgeRescue';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$userId = (int)$currentUser['id'];
$currency = $currentUser['currency'] ?? '₹';

// Fetch summary metrics for initial server-side render
$db = get_db();
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN action = 'rescued' THEN value_saved ELSE 0 END), 0) AS total_saved,
        COUNT(CASE WHEN action = 'rescued' THEN 1 END) AS meals_rescued
    FROM waste_log
    WHERE user_id = :uid
");
$stmt->execute(['uid' => $userId]);
$row = $stmt->fetch() ?: ['total_saved' => 0, 'meals_rescued' => 0];

$totalSaved = (float)$row['total_saved'];
$mealsRescued = (int)$row['meals_rescued'];
$kgSaved = round($mealsRescued * 0.45, 1);
?>

<main class="main-content">
    <div style="max-width: 1100px; margin: 0 auto;">

        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
            <div>
                <div class="produce-sticker" style="margin-bottom: 10px;">
                    🌱 Zero Waste Impact
                </div>
                <h1 style="font-size: 32px; letter-spacing: -0.01em;">Your Food Rescue Footprint</h1>
                <p style="color: var(--text-secondary); font-size: 14px;">
                    Every meal cooked is money kept in your wallet and food kept out of landfills.
                </p>
            </div>

            <button type="button" class="btn btn-primary" onclick="shareMyImpact()">
                📤 Share My Impact
            </button>
        </div>

        <!-- Hero Impact Banner -->
        <div class="impact-hero-banner">
            <div>
                <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; opacity: 0.9; margin-bottom: 6px;">
                    Total Money Saved from Spoiled Groceries
                </div>
                <div class="counter-giant" id="animatedSavedCounter">
                    <?= $currency ?> <?= number_format($totalSaved, 0) ?>
                </div>
                <div style="font-size: 15px; margin-top: 10px; opacity: 0.95; max-width: 580px; line-height: 1.5;">
                    🎉 You saved the equivalent of <strong><?= $mealsRescued ?> meals</strong> and prevented <strong><?= $kgSaved ?> kg</strong> of greenhouse CO₂e emissions!
                </div>
            </div>

            <div style="display: flex; gap: 24px; border-left: 1px solid rgba(255,255,255,0.2); padding-left: 28px;">
                <div>
                    <div style="font-size: 32px; font-weight: 800; font-family: var(--font-heading); color: var(--lime);">
                        <?= $mealsRescued ?>
                    </div>
                    <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.85;">
                        Meals Rescued
                    </div>
                </div>
                <div>
                    <div style="font-size: 32px; font-weight: 800; font-family: var(--font-heading); color: #FAF6EE;">
                        <?= $kgSaved ?> <span style="font-size: 18px;">kg</span>
                    </div>
                    <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.85;">
                        Food Saved
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Grid (Weekly Bar Chart + Category Doughnut) -->
        <div class="charts-double-grid">
            <!-- Weekly Savings vs Loss Bar Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h2 style="font-size: 18px; margin-bottom: 2px;">Weekly Savings &amp; Waste Trend</h2>
                        <div style="font-size: 12px; color: var(--text-secondary);">Last 8 weeks of food rescue activity</div>
                    </div>
                    <span class="badge badge-fresh">Weekly</span>
                </div>
                <div style="position: relative; height: 280px;">
                    <canvas id="weeklySavingsChart"></canvas>
                </div>
            </div>

            <!-- Food Category Breakdown Doughnut -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <h2 style="font-size: 18px; margin-bottom: 2px;">Rescued Food Categories</h2>
                        <div style="font-size: 12px; color: var(--text-secondary);">Distribution of ingredients saved</div>
                    </div>
                </div>
                <div style="position: relative; height: 280px; display: flex; align-items: center; justify-content: center;">
                    <canvas id="categoryDonutChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 8-Week Streak Activity Heatmap -->
        <div class="heatmap-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div>
                    <h2 style="font-size: 18px; margin-bottom: 2px;">🔥 8-Week Rescue Streak Activity</h2>
                    <div style="font-size: 12px; color: var(--text-secondary);">Every cell represents daily food rescues. Hover for details.</div>
                </div>
                <div style="display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--text-secondary);">
                    <span>Less</span>
                    <span class="heatmap-cell level-0" style="display: inline-block;"></span>
                    <span class="heatmap-cell level-1" style="display: inline-block;"></span>
                    <span class="heatmap-cell level-2" style="display: inline-block;"></span>
                    <span class="heatmap-cell level-3" style="display: inline-block;"></span>
                    <span class="heatmap-cell level-4" style="display: inline-block;"></span>
                    <span>More</span>
                </div>
            </div>

            <div class="heatmap-grid" id="heatmapGrid">
                <!-- Dynamically populated via JS -->
            </div>
        </div>

        <!-- Achievement Badges -->
        <div style="margin-top: 36px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 22px;">🎖️ Zero-Waste Achievements</h2>
                <div style="font-size: 13px; color: var(--text-secondary);">Earn badges as you cook and preserve</div>
            </div>

            <div class="badges-grid" id="badgesContainer">
                <!-- Dynamically populated via JS -->
            </div>
        </div>

    </div>
</main>

<script>
let statsData = null;

document.addEventListener('DOMContentLoaded', () => {
    loadStatsAndRenderCharts();
});

async function loadStatsAndRenderCharts() {
    try {
        const res = await fetch((window.APP_BASE_URL || '') + '/api/stats.php');
        const data = await res.json();
        if (!data.success) return;

        statsData = data;

        // Animate counter
        animateCounter('animatedSavedCounter', data.totals.total_saved, data.totals.currency);

        // Render Bar Chart
        renderWeeklyChart(data.weekly, data.totals.currency);

        // Render Doughnut Chart
        renderCategoryChart(data.categories);

        // Render Heatmap
        renderHeatmap(data.heatmap);

        // Render Badges
        renderBadges(data.badges);
    } catch (err) {
        console.error("Error loading stats:", err);
    }
}

function animateCounter(id, targetVal, currency) {
    const el = document.getElementById(id);
    if (!el) return;
    const duration = 1200;
    const start = 0;
    const startTime = performance.now();

    function update(time) {
        const elapsed = time - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const current = Math.floor(progress * targetVal);
        el.innerText = `${currency} ${current.toLocaleString()}`;
        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }
    requestAnimationFrame(update);
}

function renderWeeklyChart(weekly, currency) {
    const ctx = document.getElementById('weeklySavingsChart').getContext('2d');
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: weekly.labels,
            datasets: [
                {
                    label: `Money Saved (${currency})`,
                    data: weekly.saved,
                    backgroundColor: '#1F4D3A',
                    borderRadius: 6,
                    borderSkipped: false
                },
                {
                    label: `Food Loss (${currency})`,
                    data: weekly.wasted,
                    backgroundColor: '#E4572E',
                    borderRadius: 6,
                    borderSkipped: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        color: isDark ? '#FAF6EE' : '#1E1E1E',
                        font: { family: 'Inter', size: 12 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ${currency} ${ctx.raw}`
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: isDark ? '#9EACA5' : '#6B6B6B' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)' },
                    ticks: {
                        color: isDark ? '#9EACA5' : '#6B6B6B',
                        callback: (v) => `${currency}${v}`
                    }
                }
            }
        }
    });
}

function renderCategoryChart(categories) {
    const ctx = document.getElementById('categoryDonutChart').getContext('2d');
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: categories.labels,
            datasets: [{
                data: categories.data,
                backgroundColor: [
                    '#1F4D3A', // Vegetables
                    '#E4572E', // Fruits
                    '#F4B942', // Dairy
                    '#A6D854'  // Leftovers
                ],
                borderWidth: 2,
                borderColor: isDark ? '#182620' : '#FFFFFF'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: isDark ? '#FAF6EE' : '#1E1E1E',
                        font: { family: 'Inter', size: 12 }
                    }
                }
            },
            cutout: '68%'
        }
    });
}

function renderHeatmap(heatmap) {
    const grid = document.getElementById('heatmapGrid');
    grid.innerHTML = '';

    heatmap.forEach(cell => {
        const div = document.createElement('div');
        div.className = `heatmap-cell level-${cell.intensity}`;
        div.title = `${cell.date}: ${cell.count} meal(s) rescued`;
        grid.appendChild(div);
    });
}

function renderBadges(badges) {
    const container = document.getElementById('badgesContainer');
    container.innerHTML = '';

    badges.forEach(b => {
        const card = document.createElement('div');
        card.className = `badge-card ${b.unlocked ? '' : 'locked'}`;
        card.innerHTML = `
            <div class="badge-icon-bubble">${b.icon}</div>
            <h3 style="font-size: 16px; margin-bottom: 6px;">${b.name}</h3>
            <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 12px; line-height: 1.4;">${b.desc}</p>
            <div style="height: 6px; background: var(--surface-card-subtle); border-radius: 3px; overflow: hidden; margin-top: auto;">
                <div style="width: ${b.progress}%; height: 100%; background: ${b.unlocked ? 'var(--lime)' : 'var(--muted)'};"></div>
            </div>
            <div style="font-size: 10px; font-weight: 700; color: ${b.unlocked ? 'var(--forest-green)' : 'var(--muted)'}; margin-top: 6px; text-transform: uppercase;">
                ${b.unlocked ? '✓ Unlocked' : `${b.progress}% in progress`}
            </div>
        `;
        container.appendChild(card);
    });
}

/* ==========================================================
   Share My Impact
   ========================================================== */
function shareMyImpact() {
    if (!statsData) return;
    const totals = statsData.totals;
    const text = `🌱 I've saved ${totals.currency} ${totals.total_saved.toLocaleString()} and rescued ${totals.meals_rescued} meals from spoiling using FridgeRescue! "Eat it before it expires." 🥦\n\nCheck out FridgeRescue!`;

    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Impact summary copied to clipboard! Share it with friends.', 'success');
        }).catch(() => {
            prompt('Copy your impact summary:', text);
        });
    } else {
        prompt('Copy your impact summary:', text);
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
