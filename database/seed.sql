-- ==========================================================
-- FridgeRescue Seed Data
-- Demo user, 14 realistic fridge items, saved recipes, and 8-week waste log
-- ==========================================================

USE `fridgerescue`;

-- Clear existing demo records
DELETE FROM `users` WHERE `email` = 'demo@fridgerescue.com';

-- 1. Insert Demo User (Password: Demo@123)
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `diet_pref`, `currency`, `alert_days`, `theme`, `created_at`)
VALUES (
    1,
    'Chef Avilash',
    'demo@fridgerescue.com',
    '$2y$10$CkSU210uhUEzms0Zy3FCOeKI4DmxUzRqjQXPeyoRcCYxP8YKo5.G.',
    'all',
    '₹',
    3,
    'light',
    DATE_SUB(NOW(), INTERVAL 60 DAY)
) ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `password_hash` = VALUES(`password_hash`);

-- 2. Insert 14 Realistic Fridge Items
DELETE FROM `fridge_items` WHERE `user_id` = 1;

INSERT INTO `fridge_items` (`user_id`, `name`, `emoji`, `category`, `quantity`, `unit`, `estimated_price`, `added_on`, `expiry_date`, `status`, `created_at`) VALUES
-- Expired / Expiring (0-1 days left) -> Perfect for Rescue Shelf
(1, 'Fresh Paneer', '🧀', 'dairy', 200.00, 'g', 95.00, DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'active', NOW()),
(1, 'Spinach (Palak)', '🥬', 'vegetable', 1.00, 'bunch', 40.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), CURDATE(), 'active', NOW()),
(1, 'Greek Yogurt', '🥣', 'dairy', 400.00, 'g', 120.00, DATE_SUB(CURDATE(), INTERVAL 6 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'active', NOW()),
(1, 'Ripe Tomatoes', '🍅', 'vegetable', 500.00, 'g', 35.00, DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'active', NOW()),

-- Use Soon (2-4 days left)
(1, 'Full Cream Milk', '🥛', 'dairy', 1.00, 'L', 68.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'active', NOW()),
(1, 'Strawberries', '🍓', 'fruit', 250.00, 'g', 140.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'active', NOW()),
(1, 'Button Mushrooms', '🍄', 'vegetable', 200.00, 'g', 85.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'active', NOW()),
(1, 'Dal Tadka (Leftover)', '🍲', 'leftover', 1.00, 'bowl', 60.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'active', NOW()),
(1, 'Fresh Mint Leaves', '🌿', 'vegetable', 1.00, 'bunch', 20.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'active', NOW()),

-- Fresh (5+ days left)
(1, 'Bell Peppers (Tri-color)', '🫑', 'vegetable', 3.00, 'pcs', 90.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 6 DAY), 'active', NOW()),
(1, 'Crisp Carrots', '🥕', 'vegetable', 500.00, 'g', 45.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 8 DAY), 'active', NOW()),
(1, 'Green Apples', '🍏', 'fruit', 4.00, 'pcs', 160.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'active', NOW()),
(1, 'Cheddar Cheese Block', '🧀', 'dairy', 1.00, 'pack', 210.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'active', NOW()),
(1, 'Garlic Cloves', '🧄', 'vegetable', 150.00, 'g', 30.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 18 DAY), 'active', NOW());

-- 3. Insert Saved Recipes
DELETE FROM `recipes_saved` WHERE `user_id` = 1;

INSERT INTO `recipes_saved` (`user_id`, `title`, `cooking_time`, `difficulty`, `ingredients_json`, `steps_json`, `rescued_items`, `created_at`) VALUES
(
    1,
    'Creamy Spinach & Paneer Bhurji',
    '20 mins',
    'Easy',
    '[{"name":"Fresh Paneer","amount":"200g","is_rescued":true},{"name":"Spinach (Palak)","amount":"1 bunch","is_rescued":true},{"name":"Ripe Tomatoes","amount":"2 medium","is_rescued":true},{"name":"Onion","amount":"1 chopped","is_rescued":false},{"name":"Cumin & Spices","amount":"1 tsp each","is_rescued":false}]',
    '["Wash and roughly chop the fresh spinach.", "Crumble the paneer by hand into bite-sized textures.", "Sauté onions, tomatoes, and spices in a hot pan until aromatic.", "Toss in the spinach until wilted, then fold in crumbled paneer.", "Finish with a squeeze of lemon and serve steaming hot with toast or roti."]',
    'Fresh Paneer, Spinach, Tomatoes',
    DATE_SUB(NOW(), INTERVAL 4 DAY)
),
(
    1,
    'Spiced Rescue Dal Rasam Soup',
    '15 mins',
    'Quick',
    '[{"name":"Dal Tadka (Leftover)","amount":"1 bowl","is_rescued":true},{"name":"Ripe Tomatoes","amount":"2 mashed","is_rescued":true},{"name":"Fresh Mint Leaves","amount":"handful","is_rescued":true},{"name":"Mustard Seeds & Curry Leaves","amount":"1 tsp","is_rescued":false}]',
    '["Whisk the leftover dal with 1.5 cups of warm water and mashed tomatoes.", "Bring to a gentle boil with turmeric, crushed pepper, and rasam powder.", "Prepare a quick tempering of mustard seeds, cumin, and curry leaves in ghee.", "Pour tadka over rasam and garnish with torn mint leaves."]',
    'Leftover Dal, Ripe Tomatoes, Mint',
    DATE_SUB(NOW(), INTERVAL 10 DAY)
);

-- 4. Insert 8-Week Historical Waste Log for Rich Impact Charts
DELETE FROM `waste_log` WHERE `user_id` = 1;

INSERT INTO `waste_log` (`user_id`, `item_id`, `action`, `value_saved`, `logged_at`) VALUES
-- Week -8
(1, NULL, 'rescued', 180.00, DATE_SUB(NOW(), INTERVAL 54 DAY)),
(1, NULL, 'rescued', 95.00, DATE_SUB(NOW(), INTERVAL 52 DAY)),
(1, NULL, 'wasted', 45.00, DATE_SUB(NOW(), INTERVAL 50 DAY)),

-- Week -7
(1, NULL, 'rescued', 240.00, DATE_SUB(NOW(), INTERVAL 47 DAY)),
(1, NULL, 'rescued', 110.00, DATE_SUB(NOW(), INTERVAL 44 DAY)),

-- Week -6
(1, NULL, 'rescued', 320.00, DATE_SUB(NOW(), INTERVAL 39 DAY)),
(1, NULL, 'rescued', 140.00, DATE_SUB(NOW(), INTERVAL 37 DAY)),
(1, NULL, 'wasted', 35.00, DATE_SUB(NOW(), INTERVAL 36 DAY)),

-- Week -5
(1, NULL, 'rescued', 290.00, DATE_SUB(NOW(), INTERVAL 31 DAY)),
(1, NULL, 'rescued', 175.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),

-- Week -4
(1, NULL, 'rescued', 360.00, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(1, NULL, 'rescued', 210.00, DATE_SUB(NOW(), INTERVAL 23 DAY)),

-- Week -3
(1, NULL, 'rescued', 280.00, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(1, NULL, 'rescued', 190.00, DATE_SUB(NOW(), INTERVAL 16 DAY)),
(1, NULL, 'wasted', 40.00, DATE_SUB(NOW(), INTERVAL 15 DAY)),

-- Week -2
(1, NULL, 'rescued', 420.00, DATE_SUB(NOW(), INTERVAL 11 DAY)),
(1, NULL, 'rescued', 160.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),

-- Week -1 (Past 7 days)
(1, NULL, 'rescued', 310.00, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, NULL, 'rescued', 150.00, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, NULL, 'rescued', 220.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, NULL, 'rescued', 180.00, DATE_SUB(NOW(), INTERVAL 1 DAY));
