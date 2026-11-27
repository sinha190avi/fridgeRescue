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
$action = ($data['action'] ?? 'wasted') === 'used' ? 'rescued' : 'wasted';
$itemStatus = $action === 'rescued' ? 'used' : 'wasted';

if ($itemId <= 0) {
    json_response(['success' => false, 'error' => 'Invalid item ID.'], 400);
}

try {
    $db = get_db();
    
    // Get item price
    $stmt = $db->prepare("SELECT id, estimated_price, name FROM fridge_items WHERE id = :id AND user_id = :uid");
    $stmt->execute(['id' => $itemId, 'uid' => $user['id']]);
    $item = $stmt->fetch();

    if (!$item) {
        json_response(['success' => false, 'error' => 'Item not found.'], 404);
    }

    $price = (float)($item['estimated_price'] ?? 0);

    // Update item status
    $upStmt = $db->prepare("UPDATE fridge_items SET status = :status WHERE id = :id");
    $upStmt->execute(['status' => $itemStatus, 'id' => $itemId]);

    // Insert into waste log
    $logStmt = $db->prepare("
        INSERT INTO waste_log (user_id, item_id, action, value_saved, logged_at)
        VALUES (:uid, :item_id, :action, :val, NOW())
    ");
    $logStmt->execute([
        'uid' => $user['id'],
        'item_id' => $itemId,
        'action' => $action,
        'val' => $price
    ]);

    json_response([
        'success' => true,
        'message' => $action === 'rescued' ? "{$item['name']} marked as consumed!" : "{$item['name']} logged as wasted."
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
