<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/gemini.php';

$user = require_login();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

if (!csrf_verify($data['csrf_token'] ?? null)) {
    json_response(['success' => false, 'error' => 'Security token expired. Please refresh and try again.'], 403);
}

if (!check_rate_limit($user['id'], 'generate_recipes', 25)) {
    json_response(['success' => false, 'error' => 'Recipe generation rate limit reached. Please try again shortly.'], 429);
}

$ingredients = $data['ingredients'] ?? [];
if (!is_array($ingredients) || empty($ingredients)) {
    json_response(['success' => false, 'error' => 'Please select at least one ingredient to rescue.'], 400);
}

$cookingTime = (int)($data['cooking_time'] ?? 30);
$diet = trim($data['diet'] ?? ($user['diet_pref'] ?? 'all'));
$cuisine = trim($data['cuisine'] ?? 'Any');
$spiceLevel = (int)($data['spice_level'] ?? 3);
$isDemo = !empty($data['demo']);

$ingredientListStr = implode(', ', array_map('trim', $ingredients));

$schema = [
    'type' => 'OBJECT',
    'properties' => [
        'recipes' => [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'title' => ['type' => 'STRING'],
                    'cooking_time' => ['type' => 'STRING'],
                    'difficulty' => ['type' => 'STRING'],
                    'rescued_items' => [
                        'type' => 'ARRAY',
                        'items' => ['type' => 'STRING']
                    ],
                    'ingredients_have' => [
                        'type' => 'ARRAY',
                        'items' => ['type' => 'STRING']
                    ],
                    'ingredients_missing' => [
                        'type' => 'ARRAY',
                        'items' => ['type' => 'STRING']
                    ],
                    'steps' => [
                        'type' => 'ARRAY',
                        'items' => ['type' => 'STRING']
                    ]
                ],
                'required' => ['title', 'cooking_time', 'difficulty', 'rescued_items', 'ingredients_have', 'steps']
            ]
        ]
    ],
    'required' => ['recipes']
];

$prompt = <<<PROMPT
You are a celebrated zero-waste chef for FridgeRescue.
Your mission is to invent exactly 3 creative, delicious home-cooked rescue recipes that prioritize using these expiring ingredients: {$ingredientListStr}.

Preferences:
- Maximum Cooking Time: {$cookingTime} minutes
- Dietary Preference: {$diet}
- Cuisine Style: {$cuisine}
- Spice Level: {$spiceLevel} out of 5

For each recipe:
1. Title: Appetizing and modern (e.g. 'Crispy Skillet Paneer & Wilted Greens', 'Creamy One-Pot Tomato Soup').
2. Cooking time: Realistic time (e.g. '15 mins', '20 mins').
3. Rescued items: Explicitly list which of the user's provided ingredients are used.
4. Ingredients have: Clear portions of the rescued ingredients and very basic staples (salt, oil, cumin, water).
5. Ingredients missing: Minimal additional ingredients (if any) the user might need.
6. Steps: 4 to 6 concise, actionable, step-by-step cooking steps.

Return ONLY a JSON object containing an array of exactly 3 recipes matching the schema.
PROMPT;

try {
    $result = gemini_generate($prompt, null, $schema, $isDemo);

    if (empty($result['recipes']) || !is_array($result['recipes'])) {
        throw new Exception("Invalid response structure from Gemini API.");
    }

    // Ensure exactly 3 recipes
    $recipes = array_slice($result['recipes'], 0, 3);

    record_rate_limit($user['id'], 'generate_recipes');

    json_response([
        'success' => true,
        'recipes' => $recipes,
        'rescued_count' => count($ingredients)
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'error' => 'Recipe generation failed: ' . $e->getMessage()], 500);
}
