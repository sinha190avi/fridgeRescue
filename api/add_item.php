<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$user = require_login();

// Read input (support JSON payload or POST)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

if (!csrf_verify($data['csrf_token'] ?? null)) {
    json_response(['success' => false, 'error' => 'Invalid security token.'], 403);
}

$name = trim($data['name'] ?? '');
$emoji = trim($data['emoji'] ?? '🥦');
$category = strtolower(trim($data['category'] ?? 'vegetable'));
$quantity = (float)($data['quantity'] ?? 1);
$unit = trim($data['unit'] ?? 'pcs');
$estimatedPrice = (float)($data['estimated_price'] ?? 0);
$expiryDate = trim($data['expiry_date'] ?? date('Y-m-d', strtotime('+3 days')));

$validCategories = ['vegetable', 'fruit', 'dairy', 'leftover', 'other'];
if (!in_array($category, $validCategories)) {
    $category = 'other';
}

if (empty($name)) {
    json_response(['success' => false, 'error' => 'Item name is required.'], 400);
}

try {
    $db = get_db();
    $stmt = $db->prepare("
        INSERT INTO fridge_items (user_id, name, emoji, category, quantity, unit, estimated_price, added_on, expiry_date, status, created_at)
        VALUES (:uid, :name, :emoji, :cat, :qty, :unit, :price, CURDATE(), :exp, 'active', NOW())
    ");
    $stmt->execute([
        'uid' => $user['id'],
        'name' => $name,
        'emoji' => $emoji ?: '🥦',
        'cat' => $category,
        'qty' => $quantity,
        'unit' => $unit ?: 'pcs',
        'price' => $estimatedPrice,
        'exp' => $expiryDate
    ]);

    $itemId = (int)$db->lastInsertId();

    json_response([
        'success' => true,
        'message' => 'Item added to your fridge!',
        'item' => [
            'id' => $itemId,
            'name' => $name,
            'emoji' => $emoji,
            'category' => $category,
            'quantity' => $quantity,
            'unit' => $unit,
            'estimated_price' => $estimatedPrice,
            'expiry_date' => $expiryDate
        ]
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
