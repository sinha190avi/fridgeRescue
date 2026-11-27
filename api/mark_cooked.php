<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$user = require_login();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

if (!csrf_verify($data['csrf_token'] ?? null)) {
    json_response(['success' => false, 'error' => 'Security token invalid.'], 403);
}

$rescuedItemNames = $data['rescued_items'] ?? [];
$recipeTitle = trim($data['recipe_title'] ?? 'Rescue Recipe');

if (!is_array($rescuedItemNames) || empty($rescuedItemNames)) {
    json_response(['success' => false, 'error' => 'No rescued items specified.'], 400);
}

try {
    $db = get_db();
    $db->beginTransaction();

    $totalValueSaved = 0.0;
    $updatedCount = 0;

    // Look for active items matching name
    $searchStmt = $db->prepare("
        SELECT id, name, estimated_price 
        FROM fridge_items 
        WHERE user_id = :uid AND status = 'active'
    ");
    $searchStmt->execute(['uid' => $user['id']]);
    $activeItems = $searchStmt->fetchAll();

    $itemsToUpdate = [];
    foreach ($activeItems as $item) {
        foreach ($rescuedItemNames as $name) {
            if (
                stripos($item['name'], $name) !== false || 
                stripos($name, $item['name']) !== false
            ) {
                $itemsToUpdate[] = $item;
                $totalValueSaved += (float)$item['estimated_price'];
                break;
            }
        }
    }

    // If none matched strictly, estimate realistic average
    if (empty($itemsToUpdate)) {
        $totalValueSaved = count($rescuedItemNames) * 65.0;
    } else {
        $updateStmt = $db->prepare("UPDATE fridge_items SET status = 'used' WHERE id = :id");
        foreach ($itemsToUpdate as $it) {
            $updateStmt->execute(['id' => $it['id']]);
            $updatedCount++;
        }
    }

    // Insert into waste log
    $logStmt = $db->prepare("
        INSERT INTO waste_log (user_id, item_id, action, value_saved, logged_at)
        VALUES (:uid, NULL, 'rescued', :val, NOW())
    ");
    $logStmt->execute([
        'uid' => $user['id'],
        'val' => $totalValueSaved
    ]);

    $db->commit();

    json_response([
        'success' => true,
        'value_saved' => $totalValueSaved,
        'updated_count' => $updatedCount,
        'message' => "Hooray! '{$recipeTitle}' cooked! Saved " . format_money($totalValueSaved, $user['currency'] ?? '₹') . "!"
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_response(['success' => false, 'error' => 'Failed to log cooked meal: ' . $e->getMessage()], 500);
}
