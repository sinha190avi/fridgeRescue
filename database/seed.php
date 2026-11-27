<?php
/**
 * FridgeRescue - Pure UTF-8 Demo Pantry Database Seeder
 * Populates Demo User with 14 realistic fridge items with fresh relative dates
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

function seed_demo_database(): array {
    $db = get_db();
    $db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 1. Ensure Demo User Exists
    $hash = password_hash('Demo@123', PASSWORD_BCRYPT);
    $stmtUser = $db->prepare("
        INSERT INTO users (id, name, email, password_hash, diet_pref, currency, alert_days, theme, created_at)
        VALUES (
            1,
            'Chef Avilash',
            'demo@fridgerescue.com',
            :hash,
            'all',
            '₹',
            3,
            'light',
            DATE_SUB(NOW(), INTERVAL 60 DAY)
        ) ON DUPLICATE KEY UPDATE 
            name = VALUES(name),
            password_hash = VALUES(password_hash),
            alert_days = 3
    ");
    $stmtUser->execute(['hash' => $hash]);

    // 2. Clear previous items for Demo User
    $db->exec("DELETE FROM fridge_items WHERE user_id = 1");

    // 3. Insert 14 Fresh Items with Pure UTF-8 Emojis and dynamic dates relative to CURDATE()
    $items = [
        // Expired (demonstrates Expired badge)
        ['Fresh Paneer', '🧀', 'dairy', 200, 'g', 95, -1],
        
        // Expiring in 0-1 days (Top Rescue Shelf!)
        ['Spinach (Palak)', '🥬', 'vegetable', 1, 'bunch', 40, 0],
        ['Greek Yogurt', '🥣', 'dairy', 400, 'g', 120, 1],
        ['Ripe Tomatoes', '🍅', 'vegetable', 500, 'g', 35, 1],

        // Use Soon (2-4 days left)
        ['Full Cream Milk', '🥛', 'dairy', 1, 'L', 68, 2],
        ['Strawberries', '🍓', 'fruit', 250, 'g', 140, 2],
        ['Button Mushrooms', '🍄', 'vegetable', 200, 'g', 85, 3],
        ['Dal Tadka (Leftover)', '🍲', 'leftover', 1, 'bowl', 60, 3],
        ['Fresh Mint Leaves', '🌿', 'vegetable', 1, 'bunch', 20, 4],

        // Fresh (5+ days left)
        ['Bell Peppers (Tri-color)', '🫑', 'vegetable', 3, 'pcs', 90, 6],
        ['Ripe Alphonso Mangoes', '🥭', 'fruit', 3, 'pcs', 150, 7],
        ['Crisp Carrots', '🥕', 'vegetable', 500, 'g', 45, 8],
        ['Green Apples', '🍏', 'fruit', 4, 'pcs', 160, 10],
        ['Cheddar Cheese Block', '🧀', 'dairy', 1, 'pack', 210, 14],
        ['Garlic Cloves', '🧄', 'vegetable', 150, 'g', 30, 18]
    ];

    $stmtItem = $db->prepare("
        INSERT INTO fridge_items 
            (user_id, name, emoji, category, quantity, unit, estimated_price, added_on, expiry_date, status, created_at)
        VALUES 
            (1, :name, :emoji, :category, :qty, :unit, :price, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL :days DAY), 'active', NOW())
    ");

    foreach ($items as $item) {
        $stmtItem->execute([
            'name'     => $item[0],
            'emoji'    => $item[1],
            'category' => $item[2],
            'qty'      => $item[3],
            'unit'     => $item[4],
            'price'    => $item[5],
            'days'     => $item[6]
        ]);
    }

    // 4. Reset Saved Recipes
    $db->exec("DELETE FROM recipes_saved WHERE user_id = 1");
    $stmtRecipe = $db->prepare("
        INSERT INTO recipes_saved (user_id, title, cooking_time, difficulty, ingredients_json, steps_json, rescued_items, created_at)
        VALUES 
        (
            1,
            'Creamy Spinach & Paneer Bhurji',
            '20 mins',
            'Easy',
            '[{\"name\":\"Fresh Paneer\",\"amount\":\"200g\",\"is_rescued\":true},{\"name\":\"Spinach (Palak)\",\"amount\":\"1 bunch\",\"is_rescued\":true},{\"name\":\"Ripe Tomatoes\",\"amount\":\"2 medium\",\"is_rescued\":true},{\"name\":\"Onion\",\"amount\":\"1 chopped\",\"is_rescued\":false},{\"name\":\"Cumin & Spices\",\"amount\":\"1 tsp each\",\"is_rescued\":false}]',
            '[\"Wash and roughly chop the fresh spinach.\", \"Crumble the paneer by hand into bite-sized textures.\", \"Saut\\u00e9 onions, tomatoes, and spices in a hot pan until aromatic.\", \"Toss in the spinach until wilted, then fold in crumbled paneer.\", \"Finish with a squeeze of lemon and serve steaming hot with toast or roti.\"]',
            'Fresh Paneer, Spinach, Tomatoes',
            DATE_SUB(NOW(), INTERVAL 4 DAY)
        ),
        (
            1,
            'Spiced Rescue Dal Rasam Soup',
            '15 mins',
            'Quick',
            '[{\"name\":\"Dal Tadka (Leftover)\",\"amount\":\"1 bowl\",\"is_rescued\":true},{\"name\":\"Ripe Tomatoes\",\"amount\":\"2 mashed\",\"is_rescued\":true},{\"name\":\"Fresh Mint Leaves\",\"amount\":\"handful\",\"is_rescued\":true},{\"name\":\"Mustard Seeds & Curry Leaves\",\"amount\":\"1 tsp\",\"is_rescued\":false}]',
            '[\"Whisk the leftover dal with 1.5 cups of warm water and mashed tomatoes.\", \"Bring to a gentle boil with turmeric, crushed pepper, and rasam powder.\", \"Prepare a quick tempering of mustard seeds, cumin, and curry leaves in ghee.\", \"Pour tadka over rasam and garnish with torn mint leaves.\"]',
            'Leftover Dal, Ripe Tomatoes, Mint',
            DATE_SUB(NOW(), INTERVAL 10 DAY)
        )
    ");
    $stmtRecipe->execute();

    // 5. Reset Waste Log with dynamic relative intervals
    $db->exec("DELETE FROM waste_log WHERE user_id = 1");
    $wasteLogs = [
        ['rescued', 180, 54],
        ['rescued', 95, 52],
        ['wasted', 45, 50],
        ['rescued', 240, 47],
        ['rescued', 110, 44],
        ['rescued', 320, 39],
        ['rescued', 140, 37],
        ['wasted', 35, 36],
        ['rescued', 290, 31],
        ['rescued', 175, 29],
        ['rescued', 360, 25],
        ['rescued', 210, 23],
        ['rescued', 280, 18],
        ['rescued', 190, 16],
        ['wasted', 40, 15],
        ['rescued', 420, 11],
        ['rescued', 160, 9],
        ['rescued', 310, 6],
        ['rescued', 150, 4],
        ['rescued', 220, 2],
        ['rescued', 180, 1]
    ];

    $stmtWaste = $db->prepare("
        INSERT INTO waste_log (user_id, item_id, action, value_saved, logged_at)
        VALUES (1, NULL, :action, :val, DATE_SUB(NOW(), INTERVAL :days DAY))
    ");

    foreach ($wasteLogs as $wl) {
        $stmtWaste->execute([
            'action' => $wl[0],
            'val'    => $wl[1],
            'days'   => $wl[2]
        ]);
    }

    return [
        'success' => true,
        'message' => 'Demo pantry successfully seeded with genuine UTF-8 emojis and relative dates!',
        'count'   => count($items)
    ];
}

// If run from CLI or directly called
if (php_sapi_name() === 'cli' || basename($_SERVER['PHP_SELF'] ?? '') === 'seed.php') {
    $res = seed_demo_database();
    echo $res['message'] . " (" . $res['count'] . " items)\n";
}
