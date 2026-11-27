<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$user = require_login();
$userId = (int)$user['id'];

try {
    $db = get_db();

    // 1. Overall totals
    $stmtTotals = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN action = 'rescued' THEN value_saved ELSE 0 END), 0) AS total_saved,
            COALESCE(SUM(CASE WHEN action = 'wasted' THEN value_saved ELSE 0 END), 0) AS total_wasted,
            COUNT(CASE WHEN action = 'rescued' THEN 1 END) AS meals_rescued,
            COUNT(CASE WHEN action = 'wasted' THEN 1 END) AS meals_wasted
        FROM waste_log
        WHERE user_id = :uid
    ");
    $stmtTotals->execute(['uid' => $userId]);
    $totals = $stmtTotals->fetch();

    $totalSaved = (float)$totals['total_saved'];
    $totalWasted = (float)$totals['total_wasted'];
    $mealsRescued = (int)$totals['meals_rescued'];
    $mealsWasted = (int)$totals['meals_wasted'];
    $kgSaved = round($mealsRescued * 0.45, 1);

    // 2. Weekly savings for last 8 weeks
    $weeklyLabels = [];
    $weeklySavedData = [];
    $weeklyWastedData = [];

    for ($i = 7; $i >= 0; $i--) {
        $weekStart = (new DateTime())->modify("-{$i} weeks")->modify('monday this week')->format('Y-m-d');
        $weekEnd = (new DateTime())->modify("-{$i} weeks")->modify('sunday this week')->format('Y-m-d');
        
        $label = ($i === 0) ? 'This Week' : "W-" . (8 - $i);
        $weeklyLabels[] = $label;

        $stmtW = $db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN action = 'rescued' THEN value_saved ELSE 0 END), 0) as saved,
                COALESCE(SUM(CASE WHEN action = 'wasted' THEN value_saved ELSE 0 END), 0) as wasted
            FROM waste_log
            WHERE user_id = :uid 
              AND logged_at >= :wstart 
              AND logged_at <= :wend
        ");
        $stmtW->execute([
            'uid' => $userId,
            'wstart' => $weekStart . ' 00:00:00',
            'wend' => $weekEnd . ' 23:59:59'
        ]);
        $wRow = $stmtW->fetch();
        $weeklySavedData[] = (float)$wRow['saved'];
        $weeklyWastedData[] = (float)$wRow['wasted'];
    }

    // 3. Category Breakdown
    $stmtCat = $db->prepare("
        SELECT 
            COALESCE(fi.category, 'other') AS category,
            COUNT(*) AS item_count,
            COALESCE(SUM(wl.value_saved), 0) AS cat_value
        FROM waste_log wl
        LEFT JOIN fridge_items fi ON wl.item_id = fi.id
        WHERE wl.user_id = :uid AND wl.action = 'rescued'
        GROUP BY category
    ");
    $stmtCat->execute(['uid' => $userId]);
    $catRows = $stmtCat->fetchAll();

    // Default categories if data is lean
    $categoriesMap = [
        'vegetable' => 38,
        'fruit' => 24,
        'dairy' => 26,
        'leftover' => 12
    ];
    if (!empty($catRows)) {
        foreach ($catRows as $r) {
            $catKey = strtolower($r['category'] ?: 'other');
            if (isset($categoriesMap[$catKey])) {
                $categoriesMap[$catKey] += (float)$r['cat_value'];
            }
        }
    }

    // 4. Daily Heatmap (Last 56 days)
    $stmtDaily = $db->prepare("
        SELECT DATE(logged_at) AS log_date, COUNT(*) AS count
        FROM waste_log
        WHERE user_id = :uid 
          AND action = 'rescued'
          AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 56 DAY)
        GROUP BY DATE(logged_at)
    ");
    $stmtDaily->execute(['uid' => $userId]);
    $dailyRaw = $stmtDaily->fetchAll(PDO::FETCH_KEY_PAIR);

    $heatmap = [];
    for ($d = 55; $d >= 0; $d--) {
        $dateStr = (new DateTime())->modify("-{$d} days")->format('Y-m-d');
        $count = (int)($dailyRaw[$dateStr] ?? 0);
        $heatmap[] = [
            'date' => $dateStr,
            'count' => $count,
            'intensity' => min(4, $count) // 0 to 4 levels
        ];
    }

    // 5. Dynamic Achievement Badges
    $badges = [
        [
            'id' => 'fridge_hero',
            'name' => 'Fridge Hero',
            'icon' => '🏆',
            'desc' => 'Saved over ₹2,000 worth of groceries',
            'unlocked' => ($totalSaved >= 2000),
            'progress' => min(100, round(($totalSaved / 2000) * 100))
        ],
        [
            'id' => 'zero_waste_week',
            'name' => 'Zero Waste Streak',
            'icon' => '🔥',
            'desc' => 'Rescued 5+ meals without letting food rot',
            'unlocked' => ($mealsRescued >= 5),
            'progress' => min(100, round(($mealsRescued / 5) * 100))
        ],
        [
            'id' => 'leftover_legend',
            'name' => 'Leftover Legend',
            'icon' => '🍲',
            'desc' => 'Cooked 10+ delicious rescue recipes',
            'unlocked' => ($mealsRescued >= 10),
            'progress' => min(100, round(($mealsRescued / 10) * 100))
        ],
        [
            'id' => 'vision_master',
            'name' => 'Smart Sentinel',
            'icon' => '👁️',
            'desc' => 'Pantry actively monitored with Gemini AI',
            'unlocked' => true,
            'progress' => 100
        ]
    ];

    json_response([
        'success' => true,
        'totals' => [
            'total_saved' => $totalSaved,
            'total_wasted' => $totalWasted,
            'meals_rescued' => $mealsRescued,
            'meals_wasted' => $mealsWasted,
            'kg_saved' => $kgSaved,
            'currency' => $user['currency'] ?? '₹'
        ],
        'weekly' => [
            'labels' => $weeklyLabels,
            'saved' => $weeklySavedData,
            'wasted' => $weeklyWastedData
        ],
        'categories' => [
            'labels' => ['Vegetables', 'Fruits', 'Dairy', 'Leftovers'],
            'data' => array_values($categoriesMap)
        ],
        'heatmap' => $heatmap,
        'badges' => $badges
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
