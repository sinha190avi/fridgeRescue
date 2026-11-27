<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$user = require_login();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];

if (!csrf_verify($data['csrf_token'] ?? null)) {
    json_response(['success' => false, 'error' => 'Security token invalid.'], 403);
}

$items = $data['items'] ?? [];
if (!is_array($items) || empty($items)) {
    json_response(['success' => false, 'error' => 'No items provided to save.'], 400);
}

try {
    $db = get_db();
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO fridge_items (user_id, name, emoji, category, quantity, unit, estimated_price, added_on, expiry_date, status, created_at)
        VALUES (:uid, :name, :emoji, :cat, :qty, :unit, :price, CURDATE(), :exp, 'active', NOW())
    ");

    $validCategories = ['vegetable', 'fruit', 'dairy', 'leftover', 'other'];
    $savedCount = 0;

    foreach ($items as $item) {
        $name = trim($item['name'] ?? '');
        if (empty($name)) continue;

        $cat = strtolower(trim($item['category'] ?? 'vegetable'));
        if (!in_array($cat, $validCategories)) $cat = 'other';

        $emoji = trim($item['emoji'] ?? '') ?: category_emoji($cat);
        $qty = (float)($item['quantity'] ?? 1);
        $unit = trim($item['unit'] ?? 'pcs') ?: 'pcs';
        $price = (float)($item['estimated_price'] ?? 0);
        $exp = trim($item['expiry_date'] ?? date('Y-m-d', strtotime('+3 days')));

        $stmt->execute([
            'uid' => $user['id'],
            'name' => $name,
            'emoji' => $emoji,
            'cat' => $cat,
            'qty' => $qty,
            'unit' => $unit,
            'price' => $price,
            'exp' => $exp
        ]);
        $savedCount++;
    }

    $db->commit();

    set_flash('success', "{$savedCount} food items safely added to your fridge!");
    json_response([
        'success' => true,
        'saved_count' => $savedCount,
        'message' => "Successfully stocked {$savedCount} items into your fridge!"
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_response(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
