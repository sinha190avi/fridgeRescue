-- ==========================================================
-- FridgeRescue — Complete Database Setup for InfinityFree
-- Compatible with shared hosting phpMyAdmin (No CREATE DATABASE / USE)
-- Charset: utf8mb4, Engine: InnoDB
-- ==========================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 1. Drop existing tables if re-importing
DROP TABLE IF EXISTS `rate_limits`;
DROP TABLE IF EXISTS `waste_log`;
DROP TABLE IF EXISTS `recipes_saved`;
DROP TABLE IF EXISTS `fridge_items`;
DROP TABLE IF EXISTS `users`;

-- 2. Create Users Table
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `diet_pref` ENUM('all', 'vegetarian', 'vegan', 'non_veg', 'keto') NOT NULL DEFAULT 'all',
    `currency` VARCHAR(10) NOT NULL DEFAULT '₹',
    `alert_days` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `theme` ENUM('light', 'dark') NOT NULL DEFAULT 'light',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create Fridge Items Table
CREATE TABLE `fridge_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `emoji` VARCHAR(16) NOT NULL DEFAULT '🥦',
    `category` ENUM('vegetable', 'fruit', 'dairy', 'leftover', 'other') NOT NULL DEFAULT 'vegetable',
    `quantity` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    `unit` VARCHAR(20) NOT NULL DEFAULT 'pcs',
    `estimated_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `added_on` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `status` ENUM('active', 'used', 'wasted') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_items_user_status_exp` (`user_id`, `status`, `expiry_date`),
    INDEX `idx_items_expiry` (`expiry_date`),
    INDEX `idx_items_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create Saved Recipes Table
CREATE TABLE `recipes_saved` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `cooking_time` VARCHAR(50) NOT NULL,
    `difficulty` VARCHAR(50) NOT NULL,
    `ingredients_json` JSON NOT NULL,
    `steps_json` JSON NOT NULL,
    `rescued_items` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_recipes_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create Waste Log Table
CREATE TABLE `waste_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NULL,
    `action` ENUM('rescued', 'wasted') NOT NULL,
    `value_saved` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `logged_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_waste_user_date` (`user_id`, `logged_at`),
    INDEX `idx_waste_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create AI Rate Limiter Table
CREATE TABLE `rate_limits` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `endpoint` VARCHAR(64) NOT NULL,
    `called_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_rate_user_endpoint_time` (`user_id`, `endpoint`, `called_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Insert Demo User (demo@fridgerescue.com / Demo@123)
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `diet_pref`, `currency`, `alert_days`, `theme`, `created_at`)
VALUES (
    1,
    'Chef Avilash',
    'demo@fridgerescue.com',
    '$2y$10$o6U9.7/hJb6yXoB3gO3P/.MhP2t2dFp266i67m9gqfX6nsqK5WauC',
    'all',
    '₹',
    3,
    'light',
    DATE_SUB(NOW(), INTERVAL 60 DAY)
);

-- 8. Insert 15 Realistic Fridge Items with Relative Dates
INSERT INTO `fridge_items` (`user_id`, `name`, `emoji`, `category`, `quantity`, `unit`, `estimated_price`, `added_on`, `expiry_date`, `status`, `created_at`) VALUES
-- Expired (-1 days)
(1, 'Fresh Paneer', '🧀', 'dairy', 200.00, 'g', 95.00, DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'active', NOW()),

-- Expiring in 0-1 days (Rescue Shelf)
(1, 'Spinach (Palak)', '🥬', 'vegetable', 1.00, 'bunch', 40.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), CURDATE(), 'active', NOW()),
(1, 'Greek Yogurt', '🥣', 'dairy', 400.00, 'g', 120.00, DATE_SUB(CURDATE(), INTERVAL 6 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'active', NOW()),
(1, 'Ripe Tomatoes', '🍅', 'vegetable', 500.00, 'g', 35.00, DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'active', NOW()),

-- Use Soon (2-4 days)
(1, 'Full Cream Milk', '🥛', 'dairy', 1.00, 'L', 68.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'active', NOW()),
(1, 'Strawberries', '🍓', 'fruit', 250.00, 'g', 140.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'active', NOW()),
(1, 'Button Mushrooms', '🍄', 'vegetable', 200.00, 'g', 85.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'active', NOW()),
(1, 'Dal Tadka (Leftover)', '🍲', 'leftover', 1.00, 'bowl', 60.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'active', NOW()),
(1, 'Fresh Mint Leaves', '🌿', 'vegetable', 1.00, 'bunch', 20.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'active', NOW()),

-- Fresh (5-18 days)
(1, 'Bell Peppers (Tri-color)', '🫑', 'vegetable', 3.00, 'pcs', 90.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 6 DAY), 'active', NOW()),
(1, 'Ripe Alphonso Mangoes', '🥭', 'fruit', 3.00, 'pcs', 150.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'active', NOW()),
(1, 'Crisp Carrots', '🥕', 'vegetable', 500.00, 'g', 45.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 8 DAY), 'active', NOW()),
(1, 'Green Apples', '🍏', 'fruit', 4.00, 'pcs', 160.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'active', NOW()),
(1, 'Cheddar Cheese Block', '🧀', 'dairy', 1.00, 'pack', 210.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'active', NOW()),
(1, 'Garlic Cloves', '🧄', 'vegetable', 150.00, 'g', 30.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 18 DAY), 'active', NOW());

-- 9. Insert Saved Recipes
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

-- 10. Insert 8-Week Historical Waste Log
INSERT INTO `waste_log` (`user_id`, `item_id`, `action`, `value_saved`, `logged_at`) VALUES
(1, NULL, 'rescued', 180.00, DATE_SUB(NOW(), INTERVAL 54 DAY)),
(1, NULL, 'rescued', 95.00, DATE_SUB(NOW(), INTERVAL 52 DAY)),
(1, NULL, 'wasted', 45.00, DATE_SUB(NOW(), INTERVAL 50 DAY)),
(1, NULL, 'rescued', 240.00, DATE_SUB(NOW(), INTERVAL 47 DAY)),
(1, NULL, 'rescued', 110.00, DATE_SUB(NOW(), INTERVAL 44 DAY)),
(1, NULL, 'rescued', 320.00, DATE_SUB(NOW(), INTERVAL 39 DAY)),
(1, NULL, 'rescued', 140.00, DATE_SUB(NOW(), INTERVAL 37 DAY)),
(1, NULL, 'wasted', 35.00, DATE_SUB(NOW(), INTERVAL 36 DAY)),
(1, NULL, 'rescued', 290.00, DATE_SUB(NOW(), INTERVAL 31 DAY)),
(1, NULL, 'rescued', 175.00, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(1, NULL, 'rescued', 360.00, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(1, NULL, 'rescued', 210.00, DATE_SUB(NOW(), INTERVAL 23 DAY)),
(1, NULL, 'rescued', 280.00, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(1, NULL, 'rescued', 190.00, DATE_SUB(NOW(), INTERVAL 16 DAY)),
(1, NULL, 'wasted', 40.00, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(1, NULL, 'rescued', 420.00, DATE_SUB(NOW(), INTERVAL 11 DAY)),
(1, NULL, 'rescued', 160.00, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(1, NULL, 'rescued', 310.00, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, NULL, 'rescued', 150.00, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, NULL, 'rescued', 220.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, NULL, 'rescued', 180.00, DATE_SUB(NOW(), INTERVAL 1 DAY));
