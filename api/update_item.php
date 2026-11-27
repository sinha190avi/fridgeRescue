<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$user = require_login();

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

if (!csrf_verify($data['csrf_token'] ?? null)) {
    json_response(['success' => false, 'error' => 'Invalid security token.'], 403);
}

$itemId = (int)($data['id'] ?? 0);
$name = trim($data['name'] ?? '');
$emoji = trim($data['emoji'] ?? '🥦');
$category = strtolower(trim($data['category'] ?? 'vegetable'));
$quantity = (float)($data['quantity'] ?? 1);
$unit = trim($data['unit'] ?? 'pcs');
$estimatedPrice = (float)($data['estimated_price'] ?? 0);
$expiryDate = trim($data['expiry_date'] ?? date('Y-m-d'));

if ($itemId <= 0 || empty($name)) {
    json_response(['success' => false, 'error' => 'Invalid item details.'], 400);
}

try {
    $db = get_db();
    $stmt = $db->prepare("
        UPDATE fridge_items 
        SET name = :name,
            emoji = :emoji,
            category = :cat,
            quantity = :qty,
            unit = :unit,
            estimated_price = :price,
            expiry_date = :exp
        WHERE id = :id AND user_id = :uid
    ");
    $stmt->execute([
        'name' => $name,
        'emoji' => $emoji ?: '🥦',
        'cat' => $category,
        'qty' => $quantity,
        'unit' => $unit ?: 'pcs',
        'price' => $estimatedPrice,
        'exp' => $expiryDate,
        'id' => $itemId,
        'uid' => $user['id']
    ]);

    json_response([
        'success' => true,
        'message' => 'Item updated successfully!'
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
