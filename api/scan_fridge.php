<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/gemini.php';

$user = require_login();

// CSRF check
if (!csrf_verify()) {
    json_response(['success' => false, 'error' => 'Security token expired. Please refresh and try again.'], 403);
}

// Rate limit check
if (!check_rate_limit($user['id'], 'scan_fridge', 20)) {
    json_response(['success' => false, 'error' => 'AI Scan rate limit reached (max 20 scans per hour). Please try again later.'], 429);
}

$isDemo = !empty($_POST['demo']) || !empty($_GET['demo']);
$sampleFile = trim($_POST['sample'] ?? '');
$tempImagePath = null;

if ($sampleFile) {
    // Whitelist check for sample files
    $allowedSamples = [
        'sample_fridge_1.jpg' => APP_ROOT . '/assets/img/samples/sample_fridge_1.jpg',
        'sample_groceries_2.jpg' => APP_ROOT . '/assets/img/samples/sample_groceries_2.jpg'
    ];
    if (isset($allowedSamples[$sampleFile]) && file_exists($allowedSamples[$sampleFile])) {
        $tempImagePath = $allowedSamples[$sampleFile];
    }
}

if (!$tempImagePath && !$isDemo) {
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'error' => 'No image uploaded or upload error.'], 400);
    }

    $file = $_FILES['image'];

    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        json_response(['success' => false, 'error' => 'Image size too large. Maximum allowed size is 5 MB.'], 400);
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes)) {
        json_response(['success' => false, 'error' => 'Invalid file format. Please upload JPG, PNG, or WEBP.'], 400);
    }

    // Generate safe unique filename
    $ext = match ($mime) {
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg'
    };
    $uploadFileName = 'scan_' . bin2hex(random_bytes(10)) . '.' . $ext;
    $tempImagePath = UPLOADS_DIR . DIRECTORY_SEPARATOR . $uploadFileName;

    if (!move_uploaded_file($file['tmp_name'], $tempImagePath)) {
        json_response(['success' => false, 'error' => 'Failed to save uploaded image.'], 500);
    }

    // Resize image with GD if width/height > 1400 to optimize Gemini payload
    if (extension_loaded('gd')) {
        try {
            resize_image_if_needed($tempImagePath, $mime, 1400);
        } catch (Exception $e) {
            // Non-fatal, proceed with original
        }
    }
}

// Prepare Schema & Prompt for Gemini
$schema = [
    'type' => 'OBJECT',
    'properties' => [
        'items' => [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'name' => ['type' => 'STRING'],
                    'emoji' => ['type' => 'STRING'],
                    'category' => [
                        'type' => 'STRING',
                        'enum' => ['vegetable', 'fruit', 'dairy', 'leftover', 'other']
                    ],
                    'quantity' => ['type' => 'NUMBER'],
                    'unit' => ['type' => 'STRING'],
                    'estimated_shelf_life_days' => ['type' => 'INTEGER'],
                    'estimated_price_inr' => ['type' => 'NUMBER']
                ],
                'required' => ['name', 'category', 'estimated_shelf_life_days']
            ]
        ]
    ],
    'required' => ['items']
];

$prompt = <<<PROMPT
You are an expert food scientist and culinary inventory assistant for FridgeRescue.
Carefully examine this fridge or grocery photo. Detect and list all clearly visible food items.
Guidelines:
1. Only identify items that are actually visible in the photo.
2. For each food item, provide:
   - name: Clear concise name (e.g., 'Spinach (Palak)', 'Ripe Tomatoes', 'Greek Yogurt', 'Leftover Curry', 'Full Cream Milk')
   - emoji: Single appropriate food emoji (e.g. 🥬, 🍅, 🥣, 🥛, 🥦)
   - category: Exactly one of ['vegetable', 'fruit', 'dairy', 'leftover', 'other']
   - quantity: Realistic visible numeric portion (e.g. 500, 1, 2)
   - unit: Common grocery unit ('g', 'kg', 'pcs', 'bunch', 'L', 'pack', 'bowl')
   - estimated_shelf_life_days: Conservative estimated days until expiry assuming typical refrigeration (1 to 14 days). Leafy greens/leftovers 1-3 days, dairy 3-5 days, hard vegetables 7-14 days.
   - estimated_price_inr: Approximate grocery cost in Indian Rupees (₹).
Return ONLY JSON matching the schema.
PROMPT;

try {
    $result = gemini_generate($prompt, $tempImagePath, $schema, $isDemo);

    // Compute expiry dates
    $today = new DateTime('today');
    $items = [];
    if (!empty($result['items']) && is_array($result['items'])) {
        foreach ($result['items'] as $rawItem) {
            $shelfLife = max(1, (int)($rawItem['estimated_shelf_life_days'] ?? 3));
            $expDate = (clone $today)->modify("+{$shelfLife} days")->format('Y-m-d');
            
            $cat = strtolower($rawItem['category'] ?? 'other');
            if (!in_array($cat, ['vegetable', 'fruit', 'dairy', 'leftover', 'other'])) {
                $cat = 'other';
            }

            $items[] = [
                'name' => trim($rawItem['name'] ?? 'Food Item'),
                'emoji' => trim($rawItem['emoji'] ?? category_emoji($cat)),
                'category' => $cat,
                'quantity' => (float)($rawItem['quantity'] ?? 1),
                'unit' => trim($rawItem['unit'] ?? 'pcs'),
                'estimated_shelf_life_days' => $shelfLife,
                'expiry_date' => $expDate,
                'estimated_price' => (float)($rawItem['estimated_price_inr'] ?? 50)
            ];
        }
    }

    record_rate_limit($user['id'], 'scan_fridge');

    json_response([
        'success' => true,
        'count' => count($items),
        'items' => $items,
        'message' => 'Fridge photo successfully analyzed by Gemini!'
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'error' => 'Scanning failed: ' . $e->getMessage()], 500);
} finally {
    // Delete temporary user uploaded image (preserve sample images)
    if ($tempImagePath && str_contains($tempImagePath, 'uploads' . DIRECTORY_SEPARATOR . 'scan_') && file_exists($tempImagePath)) {
        @unlink($tempImagePath);
    }
}

/**
 * Image Resize Helper with GD
 */
function resize_image_if_needed(string $path, string $mime, int $maxDimension = 1400): void {
    [$w, $h] = getimagesize($path);
    if ($w <= $maxDimension && $h <= $maxDimension) {
        return;
    }

    $ratio = min($maxDimension / $w, $maxDimension / $h);
    $newW = (int)($w * $ratio);
    $newH = (int)($h * $ratio);

    $src = match ($mime) {
        'image/png' => imagecreatefrompng($path),
        'image/webp' => imagecreatefromwebp($path),
        default => imagecreatefromjpeg($path)
    };

    if (!$src) return;

    $dst = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

    match ($mime) {
        'image/png' => imagepng($dst, $path, 8),
        'image/webp' => imagewebp($dst, $path, 85),
        default => imagejpeg($dst, $path, 85)
    };

    imagedestroy($src);
    imagedestroy($dst);
}
