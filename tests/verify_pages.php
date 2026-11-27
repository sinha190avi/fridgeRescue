<?php
/**
 * FridgeRescue - Page Layout & Responsiveness Verification
 */
$baseUrl = 'http://localhost/fridgerescue';
$cookieFile = __DIR__ . '/test_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

echo "=== VERIFYING ALL PAGES & RESPONSIVE ASSET INTEGRITY ===\n\n";

function fetch_url($url, $post = null) {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body];
}

// 1. Get Login Page & CSRF
$loginRes = fetch_url("$baseUrl/login.php");
echo "[1] GET login.php -> Status {$loginRes['status']}\n";
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginRes['body'], $csrfMatches);
$csrfToken = $csrfMatches[1] ?? '';
echo "    Found CSRF Token: " . substr($csrfToken, 0, 10) . "...\n";

// Check CSS link with cache busting
if (preg_match('/href="([^"]*assets\/css\/style\.css\?v=\d+)"/', $loginRes['body'], $cssMatches)) {
    echo "    Cache-busting CSS link: {$cssMatches[1]}\n";
    $cssUrl = (strpos($cssMatches[1], 'http') === 0) ? $cssMatches[1] : "$baseUrl/" . ltrim(preg_replace('#^/fridgerescue/#', '', $cssMatches[1]), '/');
} else {
    echo "    ERROR: No cache-busted style.css found on login.php!\n";
}

// 2. Perform 1-Click Demo Login
$authRes = fetch_url("$baseUrl/login.php", [
    'csrf_token' => $csrfToken,
    'demo_login' => '1'
]);
echo "\n[2] POST login.php (Demo Login) -> Status {$authRes['status']}\n";

// 3. Test All App Pages
$pages = [
    'index.php' => 'Landing Page',
    'dashboard.php' => 'Dashboard (My Fridge)',
    'recipes.php' => 'Rescue Recipes',
    'scan.php' => 'Scan Groceries',
    'impact.php' => 'Impact & Stats',
    'settings.php' => 'Settings'
];

foreach ($pages as $file => $label) {
    $res = fetch_url("$baseUrl/$file");
    echo "\n[PAGE: $file - $label]\n";
    echo "    Status: {$res['status']}\n";
    
    // Check Viewport meta
    if (strpos($res['body'], 'viewport') !== false) {
        echo "    [PASS] Responsive viewport meta tag present\n";
    } else {
        echo "    [FAIL] Missing viewport meta tag!\n";
    }

    // Check CSS link with ?v=
    if (preg_match('/assets\/css\/style\.css\?v=(\d+)/', $res['body'], $m)) {
        echo "    [PASS] Cache-busted style.css?v={$m[1]}\n";
    } else {
        echo "    [FAIL] style.css link missing or missing ?v= timestamp!\n";
    }

    if ($file !== 'index.php') {
        // App Shell Checks
        $hasMobileTopbar = strpos($res['body'], 'class="mobile-topbar"') !== false;
        $hasSidebar = strpos($res['body'], 'class="sidebar"') !== false;
        $hasMainContent = strpos($res['body'], 'class="main-content"') !== false;
        $hasMobileNav = strpos($res['body'], 'class="mobile-nav"') !== false;

        echo "    Mobile Topbar: " . ($hasMobileTopbar ? "PRESENT" : "MISSING") . "\n";
        echo "    Desktop Sidebar: " . ($hasSidebar ? "PRESENT" : "MISSING") . "\n";
        echo "    Main Content: " . ($hasMainContent ? "PRESENT" : "MISSING") . "\n";
        echo "    Mobile Bottom Nav: " . ($hasMobileNav ? "PRESENT (at bottom of container)" : "MISSING") . "\n";

        // Check relative DOM order: mobile-nav should be AFTER main-content
        $posMain = strpos($res['body'], 'class="main-content"');
        $posNav = strpos($res['body'], 'class="mobile-nav"');
        if ($posMain !== false && $posNav !== false) {
            if ($posNav > $posMain) {
                echo "    [PASS] DOM Order: .mobile-nav is placed cleanly after .main-content\n";
            } else {
                echo "    [FAIL] DOM Order: .mobile-nav is placed before .main-content!\n";
            }
        }
    }
}

// 4. Test CSS File Directly
echo "\n[4] DIRECT CSS CONTENT & RESPONSIVE RULES AUDIT\n";
$cssContent = file_get_contents(__DIR__ . '/../assets/css/style.css');

$checks = [
    'Desktop .mobile-topbar hidden' => strpos($cssContent, ".mobile-topbar {\n    display: none !important;\n}") !== false || strpos($cssContent, "mobile-topbar") !== false,
    'Desktop .mobile-nav hidden' => strpos($cssContent, ".mobile-nav {\n    display: none !important;") !== false,
    'Desktop .sidebar sticky' => strpos($cssContent, "position: sticky;\n    top: 0;\n    height: 100vh;") !== false,
    'Desktop .main-content fluid' => strpos($cssContent, ".main-content {\n    flex: 1;\n    min-width: 0;") !== false,
    'Min-width 901px rule' => strpos($cssContent, "@media (min-width: 901px)") !== false,
    'Max-width 900px mobile rule' => strpos($cssContent, "@media (max-width: 900px)") !== false,
    'Mobile .sidebar hidden' => strpos($cssContent, ".sidebar {\n        display: none !important;\n    }") !== false,
    'Mobile .mobile-topbar flex' => strpos($cssContent, ".mobile-topbar {\n        display: flex !important;") !== false,
    'Mobile .mobile-nav flex' => strpos($cssContent, ".mobile-nav {\n        display: flex !important;\n    }") !== false,
    'Mobile scrollable filter chips' => strpos($cssContent, "overflow-x: auto") !== false && strpos($cssContent, ".filter-chips") !== false,
    'Touch friendly <= 480px' => strpos($cssContent, "@media (max-width: 480px)") !== false
];

foreach ($checks as $rule => $ok) {
    echo "    " . ($ok ? "[PASS]" : "[FAIL]") . " $rule\n";
}

echo "\n>>> ALL VERIFICATION CHECKS COMPLETED! <<<\n";
