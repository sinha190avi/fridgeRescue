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

$title = trim($data['title'] ?? '');
$cookingTime = trim($data['cooking_time'] ?? '20 mins');
$difficulty = trim($data['difficulty'] ?? 'Easy');
$ingredients = $data['ingredients'] ?? [];
$steps = $data['steps'] ?? [];
$rescuedItems = is_array($data['rescued_items'] ?? null) 
    ? implode(', ', $data['rescued_items']) 
    : trim($data['rescued_items'] ?? '');

if (empty($title)) {
    json_response(['success' => false, 'error' => 'Recipe title is required.'], 400);
}

try {
    $db = get_db();
    $stmt = $db->prepare("
        INSERT INTO recipes_saved (user_id, title, cooking_time, difficulty, ingredients_json, steps_json, rescued_items, created_at)
        VALUES (:uid, :title, :ctime, :diff, :ing, :steps, :rescued, NOW())
    ");
    $stmt->execute([
        'uid' => $user['id'],
        'title' => $title,
        'ctime' => $cookingTime,
        'diff' => $difficulty,
        'ing' => json_encode($ingredients, JSON_UNESCAPED_UNICODE),
        'steps' => json_encode($steps, JSON_UNESCAPED_UNICODE),
        'rescued' => $rescuedItems
    ]);

    json_response([
        'success' => true,
        'message' => "Recipe '{$title}' saved to your favorites!"
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'error' => 'Failed to save recipe: ' . $e->getMessage()], 500);
}
