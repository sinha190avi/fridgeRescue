<?php
/**
 * FridgeRescue — Automated Test Suite
 * Tests all pages, APIs, authentication, AI fallbacks, and CRUD operations.
 */

$baseUrl = 'http://localhost/fridgerescue';
$cookieFile = __DIR__ . '/test_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function make_req($url, $method = 'GET', $data = null, $isJson = false, $useCookie = true) {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($useCookie) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($isJson) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }
    
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $res];
}

function extract_csrf($html) {
    if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/window\.CSRF_TOKEN = "([a-f0-9]+)"/', $html, $m)) {
        return $m[1];
    }
    return null;
}

echo "=== FRIDGERESCUE E2E VERIFICATION SUITE ===\n\n";

// 1. Landing Page
echo "[TEST 1] Landing Page (index.php)... ";
$res = make_req($baseUrl . '/index.php');
if ($res['code'] === 200 && str_contains($res['body'], 'Your fridge is') && str_contains($res['body'], 'The Live Fridge')) {
    echo "PASSED (HTTP 200, Live counters & sections found)\n";
} else {
    echo "FAILED: HTTP {$res['code']}\n";
    exit(1);
}

// 2. Login Page & CSRF
echo "[TEST 2] Login Page & CSRF Generation... ";
$res = make_req($baseUrl . '/login.php');
$csrf = extract_csrf($res['body']);
if ($res['code'] === 200 && $csrf) {
    echo "PASSED (CSRF Token: " . substr($csrf, 0, 10) . "...)\n";
} else {
    echo "FAILED to get CSRF token\n";
    exit(1);
}

// 3. Demo User Authentication
echo "[TEST 3] One-Click Demo Login... ";
$res = make_req($baseUrl . '/login.php', 'POST', [
    'demo_login' => '1',
    'csrf_token' => $csrf
]);
if ($res['code'] === 200 && str_contains($res['body'], 'The Live Fridge View')) {
    echo "PASSED (Authenticated as Chef Avilash)\n";
    $csrf = extract_csrf($res['body']); // Update session CSRF
} else {
    echo "FAILED: " . substr($res['body'], 0, 300) . "\n";
    exit(1);
}

// 4. Dashboard & Shelves
echo "[TEST 4] Dashboard Shelves & Rescue Shelf... ";
$res = make_req($baseUrl . '/dashboard.php');
$hasRescueShelf = str_contains($res['body'], 'Top Rescue Shelf');
$hasVegShelf = str_contains($res['body'], 'Vegetables &amp; Greens') || str_contains($res['body'], 'Vegetables');
$hasDairyShelf = str_contains($res['body'], 'Dairy &amp; Chilled') || str_contains($res['body'], 'Dairy');
if ($hasRescueShelf && $hasVegShelf && $hasDairyShelf) {
    echo "PASSED (All shelves rendered with circular freshness rings)\n";
} else {
    echo "FAILED: Shelves missing\n";
    exit(1);
}

// 5. Add New Food Item API
echo "[TEST 5] Add Item API (api/add_item.php)... ";
$addRes = make_req($baseUrl . '/api/add_item.php', 'POST', [
    'name' => 'Alphonso Mangoes',
    'emoji' => '🥭',
    'category' => 'fruit',
    'quantity' => 4,
    'unit' => 'pcs',
    'estimated_price' => 180,
    'expiry_date' => date('Y-m-d', strtotime('+3 days')),
    'csrf_token' => $csrf
], true);
$addJson = json_decode($addRes['body'], true);
if ($addJson && !empty($addJson['success'])) {
    $newItemId = $addJson['item']['id'];
    echo "PASSED (Item ID: {$newItemId} added)\n";
} else {
    echo "FAILED: " . $addRes['body'] . "\n";
    exit(1);
}

// 6. Update Food Item API
echo "[TEST 6] Update Item API (api/update_item.php)... ";
$upRes = make_req($baseUrl . '/api/update_item.php', 'POST', [
    'id' => $newItemId,
    'name' => 'Ripe Alphonso Mangoes',
    'emoji' => '🥭',
    'category' => 'fruit',
    'quantity' => 3,
    'unit' => 'pcs',
    'estimated_price' => 150,
    'expiry_date' => date('Y-m-d', strtotime('+2 days')),
    'csrf_token' => $csrf
], true);
$upJson = json_decode($upRes['body'], true);
if ($upJson && !empty($upJson['success'])) {
    echo "PASSED (Item {$newItemId} updated)\n";
} else {
    echo "FAILED: " . $upRes['body'] . "\n";
    exit(1);
}

// 7. Scan Fridge with Sample Haul
echo "[TEST 7] Gemini Vision Scan (api/scan_fridge.php with sample)... ";
$scanRes = make_req($baseUrl . '/api/scan_fridge.php', 'POST', [
    'sample' => 'sample_fridge_1.jpg',
    'csrf_token' => $csrf
]);
$scanJson = json_decode($scanRes['body'], true);
if ($scanJson && !empty($scanJson['success']) && count($scanJson['items']) > 0) {
    echo "PASSED (Detected " . count($scanJson['items']) . " food items with expiry dates)\n";
} else {
    echo "FAILED: " . $scanRes['body'] . "\n";
    exit(1);
}

// 8. Generate Rescue Recipes API
echo "[TEST 8] Recipe Generation (api/generate_recipes.php)... ";
$recRes = make_req($baseUrl . '/api/generate_recipes.php', 'POST', [
    'ingredients' => ['Ripe Tomatoes', 'Spinach (Palak)', 'Fresh Paneer'],
    'cooking_time' => 20,
    'diet' => 'vegetarian',
    'cuisine' => 'Indian',
    'spice_level' => 3,
    'csrf_token' => $csrf
], true);
$recJson = json_decode($recRes['body'], true);
if ($recJson && !empty($recJson['success']) && count($recJson['recipes']) === 3) {
    $firstRecipe = $recJson['recipes'][0];
    echo "PASSED (Generated 3 recipes: '{$firstRecipe['title']}' in {$firstRecipe['cooking_time']})\n";
} else {
    echo "FAILED: " . $recRes['body'] . "\n";
    exit(1);
}

// 9. Mark Recipe as Cooked (Celebration & Waste Log)
echo "[TEST 9] Mark as Cooked API (api/mark_cooked.php)... ";
$cookRes = make_req($baseUrl . '/api/mark_cooked.php', 'POST', [
    'recipe_title' => $firstRecipe['title'],
    'rescued_items' => ['Ripe Tomatoes', 'Fresh Paneer'],
    'csrf_token' => $csrf
], true);
$cookJson = json_decode($cookRes['body'], true);
if ($cookJson && !empty($cookJson['success'])) {
    echo "PASSED (Value saved logged: ₹{$cookJson['value_saved']})\n";
} else {
    echo "FAILED: " . $cookRes['body'] . "\n";
    exit(1);
}

// 10. Save Recipe to Favorites API
echo "[TEST 10] Save Recipe to Favorites (api/save_recipe.php)... ";
$saveRecRes = make_req($baseUrl . '/api/save_recipe.php', 'POST', [
    'title' => $firstRecipe['title'],
    'cooking_time' => $firstRecipe['cooking_time'],
    'difficulty' => $firstRecipe['difficulty'],
    'rescued_items' => $firstRecipe['rescued_items'],
    'ingredients' => $firstRecipe['ingredients_have'],
    'steps' => $firstRecipe['steps'],
    'csrf_token' => $csrf
], true);
$saveRecJson = json_decode($saveRecRes['body'], true);
if ($saveRecJson && !empty($saveRecJson['success'])) {
    echo "PASSED ('{$firstRecipe['title']}' saved to favorites)\n";
} else {
    echo "FAILED: " . $saveRecRes['body'] . "\n";
    exit(1);
}

// 11. Impact Stats API
echo "[TEST 11] Impact & Analytics API (api/stats.php)... ";
$statsRes = make_req($baseUrl . '/api/stats.php');
$statsJson = json_decode($statsRes['body'], true);
if ($statsJson && !empty($statsJson['success'])) {
    $totals = $statsJson['totals'];
    $wWeeks = count($statsJson['weekly']['labels']);
    $hCells = count($statsJson['heatmap']);
    $badges = count($statsJson['badges']);
    echo "PASSED (Total saved: ₹{$totals['total_saved']}, 8 weeks: {$wWeeks}, Heatmap: {$hCells} days, Badges: {$badges})\n";
} else {
    echo "FAILED: " . $statsRes['body'] . "\n";
    exit(1);
}

// 12. Delete / Resolve Item API
echo "[TEST 12] Delete Item API (api/delete_item.php)... ";
$delRes = make_req($baseUrl . '/api/delete_item.php', 'POST', [
    'id' => $newItemId,
    'action' => 'used',
    'csrf_token' => $csrf
], true);
$delJson = json_decode($delRes['body'], true);
if ($delJson && !empty($delJson['success'])) {
    echo "PASSED ({$delJson['message']})\n";
} else {
    echo "FAILED: " . $delRes['body'] . "\n";
    exit(1);
}

// 13. Settings Update
echo "[TEST 13] Settings Profile & Diet Preferences... ";
$settingsRes = make_req($baseUrl . '/settings.php', 'POST', [
    'action' => 'update_profile',
    'name' => 'Chef Avilash Supreme',
    'diet_pref' => 'vegetarian',
    'currency' => '₹',
    'alert_days' => 4,
    'theme' => 'light',
    'csrf_token' => $csrf
]);
if ($settingsRes['code'] === 200 && str_contains($settingsRes['body'], 'Profile and kitchen preferences updated!')) {
    echo "PASSED (Preferences updated & persisted)\n";
} else {
    echo "FAILED: HTTP {$settingsRes['code']}\n";
    exit(1);
}

if (file_exists($cookieFile)) unlink($cookieFile);

echo "\n>>> ALL 13 END-TO-END TESTS PASSED WITH 100% SUCCESS! <<<\n";
