-- ==========================================================
-- FridgeRescue Database Schema
-- Charset: utf8mb4, Engine: InnoDB
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `fridgerescue` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `fridgerescue`;

-- Drop tables in reverse dependency order if rebuilding
DROP TABLE IF EXISTS `rate_limits`;
DROP TABLE IF EXISTS `waste_log`;
DROP TABLE IF EXISTS `recipes_saved`;
DROP TABLE IF EXISTS `fridge_items`;
DROP TABLE IF EXISTS `users`;

-- 1. Users Table
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

-- 2. Fridge Items Table
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

-- 3. Recipes Saved Table
CREATE TABLE `recipes_saved` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `cooking_time` VARCHAR(50) NOT NULL DEFAULT '25 mins',
    `difficulty` VARCHAR(30) NOT NULL DEFAULT 'Easy',
    `ingredients_json` LONGTEXT NOT NULL,
    `steps_json` LONGTEXT NOT NULL,
    `rescued_items` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_recipes_user` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Waste Log Table
CREATE TABLE `waste_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `item_id` INT UNSIGNED NULL,
    `action` ENUM('rescued', 'wasted') NOT NULL,
    `value_saved` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `logged_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `fridge_items`(`id`) ON DELETE SET NULL,
    INDEX `idx_waste_user_logged` (`user_id`, `logged_at`),
    INDEX `idx_waste_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Rate Limits Table (Protection for AI endpoints)
CREATE TABLE `rate_limits` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `endpoint` VARCHAR(50) NOT NULL,
    `called_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_ratelimit_user_ep` (`user_id`, `endpoint`, `called_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
