<?php
$pageTitle = 'Rescue Recipes — FridgeRescue';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$userId = (int)$currentUser['id'];
$currency = $currentUser['currency'] ?? '₹';

// Fetch all active fridge items
$db = get_db();
$stmt = $db->prepare("
    SELECT id, name, emoji, category, expiry_date, estimated_price 
    FROM fridge_items 
    WHERE user_id = :uid AND status = 'active'
    ORDER BY expiry_date ASC, name ASC
");
$stmt->execute(['uid' => $userId]);
$activeItems = $stmt->fetchAll();

// Determine pre-selected items
$queryItems = $_GET['items'] ?? '';
$initialSelected = [];

if (!empty($queryItems)) {
    $initialSelected = array_filter(array_map('trim', explode(',', $queryItems)));
} else {
    // Default to items expiring in 2 days or less
    foreach ($activeItems as $it) {
        $days = get_days_left($it['expiry_date']);
        if ($days <= 2) {
            $initialSelected[] = $it['name'];
        }
    }
}

// Fetch user's saved recipes
$stmtSaved = $db->prepare("
    SELECT * 
    FROM recipes_saved 
    WHERE user_id = :uid 
    ORDER BY created_at DESC 
    LIMIT 6
");
$stmtSaved->execute(['uid' => $userId]);
$savedRecipes = $stmtSaved->fetchAll();
?>

<main class="main-content">
    <div style="max-width: 1100px; margin: 0 auto;">

        <!-- Header -->
        <div style="margin-bottom: 28px;">
            <div class="produce-sticker" style="margin-bottom: 10px;">
                🍳 AI Zero-Waste Chef
            </div>
            <h1 style="font-size: 32px; letter-spacing: -0.01em;">Rescue Recipes</h1>
            <p style="color: var(--text-secondary); font-size: 14px;">
                Cook delicious meals using your soon-to-expire groceries first. Gemini AI balances your ingredients so nothing goes to waste.
            </p>
        </div>

        <!-- 1. Ingredient Dock -->
        <div class="ingredient-dock">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <label class="form-label" style="margin-bottom: 0; font-size: 14px;">
                    🎯 Selected Ingredients to Rescue (<span id="selectedCount">0</span>)
                </label>
                <div style="font-size: 12px; color: var(--text-secondary);">
                    Click an ingredient to remove it, or pick more from your fridge below
                </div>
            </div>

            <!-- Active Selected Chips -->
            <div class="selected-chips-container" id="selectedChipsContainer">
                <!-- Injected via JavaScript -->
            </div>

            <!-- Custom Ingredient Input -->
            <div style="display: flex; gap: 10px; margin-bottom: 18px;">
                <input type="text" id="customIngredientInput" class="form-control" placeholder="Add custom pantry staple (e.g. Rice, Olive Oil, Bread)..." style="font-size: 13px;" onkeydown="if(event.key==='Enter'){event.preventDefault();addCustomIngredient();}">
                <button type="button" class="btn btn-outline btn-sm" onclick="addCustomIngredient()" style="white-space: nowrap;">
                    + Add Staple
                </button>
            </div>

            <!-- Quick Add from Fridge Cloud -->
            <?php if (!empty($activeItems)): ?>
                <div>
                    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); margin-bottom: 8px;">
                        Quick Add from Your Fridge:
                    </div>
                    <div class="pantry-add-cloud" id="pantryAddCloud">
                        <?php foreach ($activeItems as $item): 
                            $days = get_days_left($item['expiry_date']);
                            $urgent = $days <= 2 ? '🚨' : '';
                        ?>
                            <button type="button" class="pantry-add-chip" data-name="<?= e($item['name']) ?>" onclick="togglePantryItem('<?= e(addslashes($item['name'])) ?>')">
                                <span><?= e(clean_item_emoji($item['emoji'], $item['name'], $item['category'])) ?></span>
                                <span><?= e($item['name']) ?></span>
                                <?php if ($urgent): ?>
                                    <span style="font-size: 10px; color: var(--tomato); font-weight: 700;"><?= $days ?>d left</span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. Recipe Preferences & Generation Controls -->
        <div style="background: var(--surface-card); border-radius: var(--radius-xl); padding: 24px; border: 1px solid var(--border-color); margin-bottom: 32px;">
            <div class="pref-grid">
                <div>
                    <label class="form-label">Cooking Time</label>
                    <select id="recipeTime" class="form-control">
                        <option value="15">⚡ Quick (15 mins)</option>
                        <option value="30" selected>⏱️ Medium (30 mins)</option>
                        <option value="45">🍲 Slow (45 mins)</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">Dietary Preference</label>
                    <select id="recipeDiet" class="form-control">
                        <option value="all" <?= ($currentUser['diet_pref'] ?? '') === 'all' ? 'selected' : '' ?>>Any (No restrictions)</option>
                        <option value="vegetarian" <?= ($currentUser['diet_pref'] ?? '') === 'vegetarian' ? 'selected' : '' ?>>Vegetarian</option>
                        <option value="vegan" <?= ($currentUser['diet_pref'] ?? '') === 'vegan' ? 'selected' : '' ?>>Vegan</option>
                        <option value="non_veg" <?= ($currentUser['diet_pref'] ?? '') === 'non_veg' ? 'selected' : '' ?>>Non-Vegetarian</option>
                        <option value="keto" <?= ($currentUser['diet_pref'] ?? '') === 'keto' ? 'selected' : '' ?>>Keto</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">Cuisine Style</label>
                    <select id="recipeCuisine" class="form-control">
                        <option value="Any" selected>Any Cuisine</option>
                        <option value="Indian">Indian / Desi</option>
                        <option value="Continental">Continental / Western</option>
                        <option value="Asian">Asian Stir-Fry / Bowls</option>
                        <option value="Mediterranean">Mediterranean</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">
                        Spice Level: <span id="spiceValText" style="font-weight: 700; color: var(--primary);">Medium (3/5)</span>
                    </label>
                    <input type="range" id="recipeSpice" min="1" max="5" value="3" class="form-control" style="padding: 4px;" oninput="updateSpiceText(this.value)">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 14px;">
                <button type="button" class="btn btn-primary btn-lg" id="generateRecipesBtn" onclick="generateRescueRecipes()">
                    ✨ Generate 3 Rescue Recipes
                </button>
            </div>
        </div>

        <!-- 3. Generated Recipe Cards Grid -->
        <div id="recipeResultsSection" style="display: none; margin-bottom: 48px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 24px;">Chef's 3 Rescue Recommendations</h2>
                <span class="badge badge-fresh">AI Tailored</span>
            </div>

            <div class="recipe-cards-grid" id="recipeCardsGrid">
                <!-- Injected via JavaScript -->
            </div>
        </div>

        <!-- Loading Skeleton -->
        <div id="recipesLoadingState" style="display: none; margin-bottom: 48px; text-align: center; padding: 40px;">
            <div class="ring-pulse" style="font-size: 40px; margin-bottom: 12px;">👨‍🍳</div>
            <h3 style="font-size: 20px; margin-bottom: 6px;">Gemini is inventing recipes...</h3>
            <p style="color: var(--text-secondary); font-size: 14px;">Prioritizing expiring ingredients and balancing nutrition.</p>
        </div>

        <!-- 4. Saved Recipes Section -->
        <?php if (!empty($savedRecipes)): ?>
            <div style="margin-top: 48px; padding-top: 32px; border-top: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="font-size: 22px;">❤️ Your Favorite Rescue Recipes</h2>
                    <span style="font-size: 13px; color: var(--text-secondary);"><?= count($savedRecipes) ?> saved</span>
                </div>

                <div class="recipe-cards-grid">
                    <?php foreach ($savedRecipes as $sRec): 
                        $rescuedStr = e($sRec['rescued_items'] ?? '');
                        $ingList = json_decode($sRec['ingredients_json'] ?? '[]', true) ?: [];
                        $stepList = json_decode($sRec['steps_json'] ?? '[]', true) ?: [];
                    ?>
                        <div class="recipe-card">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                    <span class="badge badge-soon">⏱️ <?= e($sRec['cooking_time']) ?></span>
                                    <span class="badge badge-fresh"><?= e($sRec['difficulty']) ?></span>
                                </div>
                                <h3 style="font-size: 18px; margin-bottom: 8px;"><?= e($sRec['title']) ?></h3>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 16px;">
                                    <strong>Rescues:</strong> <?= $rescuedStr ?: 'Fridge items' ?>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline btn-sm" onclick='viewRecipeDetailModal(<?= htmlspecialchars(json_encode([
                                "title" => $sRec["title"],
                                "cooking_time" => $sRec["cooking_time"],
                                "difficulty" => $sRec["difficulty"],
                                "rescued_items" => explode(",", $sRec["rescued_items"] ?? ""),
                                "ingredients_have" => array_map(fn($x) => is_array($x) ? ($x["amount"] . " " . $x["name"]) : $x, $ingList),
                                "ingredients_missing" => [],
                                "steps" => $stepList
                            ]), ENT_QUOTES, "UTF-8") ?>)'>
                                View &amp; Cook Again &rarr;
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- Recipe Detail Modal (Interactive Step-by-Step Cooking Mode) -->
<div class="modal-backdrop" id="recipeDetailModal">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <div>
                <span class="badge badge-fresh" id="modalDifficultyBadge" style="margin-bottom: 6px;">Easy</span>
                <h2 id="modalRecipeTitle" style="font-size: 22px;">Recipe Details</h2>
                <div style="font-size: 13px; color: var(--text-secondary);" id="modalCookingTime">⏱️ 20 mins</div>
            </div>
            <button type="button" class="btn btn-icon btn-outline" onclick="closeModal('recipeDetailModal')">&times;</button>
        </div>

        <div class="modal-body">
            <!-- Rescued items banner -->
            <div style="background: var(--lime-soft); border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 22px;">🌱</span>
                <div>
                    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--forest-green-dark);">Rescued from waste</div>
                    <div style="font-size: 13px; font-weight: 600;" id="modalRescuedTags"></div>
                </div>
            </div>

            <!-- Ingredients Checklist -->
            <div style="margin-bottom: 24px;">
                <h3 style="font-size: 16px; margin-bottom: 10px; font-weight: 700;">🛒 Ingredients Checklist</h3>
                <ul id="modalIngredientsList" style="list-style: none; padding: 0;">
                    <!-- Injected via JS -->
                </ul>
            </div>

            <!-- Step by Step Cooking Instructions -->
            <div>
                <h3 style="font-size: 16px; margin-bottom: 12px; font-weight: 700;">🍳 Step-by-Step Instructions</h3>
                <div id="modalStepsList">
                    <!-- Injected via JS -->
                </div>
            </div>
        </div>

        <div class="modal-footer" style="justify-content: space-between;">
            <button type="button" class="btn btn-outline" id="modalSaveRecipeBtn" onclick="saveCurrentRecipe()">
                ❤️ Save Recipe
            </button>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('recipeDetailModal')">Close</button>
                <button type="button" class="btn btn-rescue btn-lg" id="modalMarkCookedBtn" onclick="markRecipeCooked()">
                    🎉 Mark as Cooked &amp; Rescued!
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedIngredients = <?= json_encode($initialSelected, JSON_UNESCAPED_UNICODE) ?>;
let activeRecipes = [];
let currentViewingRecipe = null;

document.addEventListener('DOMContentLoaded', () => {
    renderSelectedChips();
});

function updateSpiceText(val) {
    const labels = {
        1: 'Mild (1/5) 🌿',
        2: 'Gentle (2/5) 🌶️',
        3: 'Medium (3/5) 🌶️🌶️',
        4: 'Spicy (4/5) 🔥',
        5: 'Extra Hot (5/5) 🔥🔥'
    };
    document.getElementById('spiceValText').innerText = labels[val] || `${val}/5`;
}

function renderSelectedChips() {
    const container = document.getElementById('selectedChipsContainer');
    container.innerHTML = '';

    if (selectedIngredients.length === 0) {
        container.innerHTML = `<span style="color: var(--text-secondary); font-size: 13px;">No ingredients selected yet. Pick from your fridge below or type a staple.</span>`;
    } else {
        selectedIngredients.forEach((name, idx) => {
            const chip = document.createElement('div');
            chip.className = 'selected-chip';
            chip.innerHTML = `
                <span>${name}</span>
                <button type="button" class="chip-remove-btn" onclick="removeIngredient(${idx})" title="Remove">&times;</button>
            `;
            container.appendChild(chip);
        });
    }

    document.getElementById('selectedCount').innerText = selectedIngredients.length;
}

function togglePantryItem(name) {
    const index = selectedIngredients.indexOf(name);
    if (index > -1) {
        selectedIngredients.splice(index, 1);
    } else {
        selectedIngredients.push(name);
    }
    renderSelectedChips();
}

function removeIngredient(index) {
    selectedIngredients.splice(index, 1);
    renderSelectedChips();
}

function addCustomIngredient() {
    const input = document.getElementById('customIngredientInput');
    const val = input.value.trim();
    if (val && !selectedIngredients.includes(val)) {
        selectedIngredients.push(val);
        input.value = '';
        renderSelectedChips();
    }
}

/* ==========================================================
   Generate Rescue Recipes API Call
   ========================================================== */
async function generateRescueRecipes() {
    if (selectedIngredients.length === 0) {
        showToast('Please select at least 1 ingredient to rescue', 'warning');
        return;
    }

    const genBtn = document.getElementById('generateRecipesBtn');
    genBtn.disabled = true;
    genBtn.innerText = 'Cooking Up Recipes...';

    document.getElementById('recipeResultsSection').style.display = 'none';
    document.getElementById('recipesLoadingState').style.display = 'block';

    const payload = {
        ingredients: selectedIngredients,
        cooking_time: parseInt(document.getElementById('recipeTime').value),
        diet: document.getElementById('recipeDiet').value,
        cuisine: document.getElementById('recipeCuisine').value,
        spice_level: parseInt(document.getElementById('recipeSpice').value),
        csrf_token: window.CSRF_TOKEN
    };

    try {
        const res = await fetch((window.APP_BASE_URL || '') + '/api/generate_recipes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        document.getElementById('recipesLoadingState').style.display = 'none';

        if (data.success && data.recipes && data.recipes.length > 0) {
            activeRecipes = data.recipes;
            renderRecipeCards(data.recipes);
            showToast('Chef Gemini prepared 3 rescue recipes!', 'success');
        } else {
            showToast(data.error || 'Failed to generate recipes', 'error');
        }
    } catch (err) {
        document.getElementById('recipesLoadingState').style.display = 'none';
        showToast('Network error while generating recipes', 'error');
    } finally {
        genBtn.disabled = false;
        genBtn.innerText = '✨ Generate 3 Rescue Recipes';
    }
}

function renderRecipeCards(recipes) {
    const grid = document.getElementById('recipeCardsGrid');
    grid.innerHTML = '';

    recipes.forEach((rec, idx) => {
        const card = document.createElement('div');
        card.className = 'recipe-card';

        const rescuedHtml = (rec.rescued_items || []).map(item => `
            <span class="rescued-tag">🌱 ${item}</span>
        `).join(' ');

        card.innerHTML = `
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px;">
                    <span class="badge badge-soon">⏱️ ${rec.cooking_time || '20 mins'}</span>
                    <span class="badge badge-fresh">${rec.difficulty || 'Easy'}</span>
                </div>
                <h3 style="font-size: 20px; margin-bottom: 10px; line-height: 1.3;">${rec.title}</h3>
                <div style="margin-bottom: 18px;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); margin-bottom: 6px;">Rescues:</div>
                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                        ${rescuedHtml}
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 16px;">
                <button type="button" class="btn btn-primary btn-sm" style="flex: 1;" onclick="viewRecipeDetailModal(activeRecipes[${idx}])">
                    👨‍🍳 View &amp; Cook &rarr;
                </button>
                <button type="button" class="btn btn-icon btn-outline" title="Save to favorites" onclick="saveRecipeDirect(activeRecipes[${idx}], this)">
                    ❤️
                </button>
            </div>
        `;
        grid.appendChild(card);
    });

    document.getElementById('recipeResultsSection').style.display = 'block';
    document.getElementById('recipeResultsSection').scrollIntoView({ behavior: 'smooth' });
}

/* ==========================================================
   Recipe Detail Modal & Cooking Mode
   ========================================================== */
function viewRecipeDetailModal(recipe) {
    currentViewingRecipe = recipe;

    document.getElementById('modalRecipeTitle').innerText = recipe.title;
    document.getElementById('modalDifficultyBadge').innerText = recipe.difficulty || 'Easy';
    document.getElementById('modalCookingTime').innerText = `⏱️ Cooking Time: ${recipe.cooking_time || '20 mins'}`;
    
    // Rescued badges
    const rescuedContainer = document.getElementById('modalRescuedTags');
    rescuedContainer.innerText = (recipe.rescued_items || []).join(', ');

    // Ingredients checklist
    const ingList = document.getElementById('modalIngredientsList');
    ingList.innerHTML = '';

    (recipe.ingredients_have || []).forEach(ing => {
        const li = document.createElement('li');
        li.style.cssText = 'padding: 6px 0; border-bottom: 1px dashed var(--border-color); display: flex; align-items: center; gap: 8px; font-size: 14px;';
        li.innerHTML = `<span>✅</span> <strong>${ing}</strong> <span style="font-size: 11px; color: var(--forest-green);">(In Stock)</span>`;
        ingList.appendChild(li);
    });

    (recipe.ingredients_missing || []).forEach(ing => {
        const li = document.createElement('li');
        li.style.cssText = 'padding: 6px 0; border-bottom: 1px dashed var(--border-color); display: flex; align-items: center; gap: 8px; font-size: 14px;';
        li.innerHTML = `<span>🛒</span> <span>${ing}</span> <span style="font-size: 11px; color: var(--text-secondary);">(Pantry staple)</span>`;
        ingList.appendChild(li);
    });

    // Steps
    const stepsList = document.getElementById('modalStepsList');
    stepsList.innerHTML = '';

    (recipe.steps || []).forEach((step, idx) => {
        const stepDiv = document.createElement('div');
        stepDiv.className = 'step-item';
        stepDiv.innerHTML = `
            <input type="checkbox" id="step_cb_${idx}" onchange="this.parentElement.classList.toggle('checked', this.checked)">
            <label for="step_cb_${idx}" style="cursor: pointer;">
                <strong>Step ${idx + 1}:</strong> ${step}
            </label>
        `;
        stepsList.appendChild(stepDiv);
    });

    openModal('recipeDetailModal');
}

/* ==========================================================
   Mark as Cooked (Confetti Celebration + Waste Log)
   ========================================================== */
async function markRecipeCooked() {
    if (!currentViewingRecipe) return;

    const cookBtn = document.getElementById('modalMarkCookedBtn');
    cookBtn.disabled = true;
    cookBtn.innerText = 'Logging Savings...';

    const payload = {
        recipe_title: currentViewingRecipe.title,
        rescued_items: currentViewingRecipe.rescued_items || [],
        csrf_token: window.CSRF_TOKEN
    };

    try {
        const res = await fetch((window.APP_BASE_URL || '') + '/api/mark_cooked.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            closeModal('recipeDetailModal');

            // Trigger Fireworks Confetti
            if (typeof confetti === 'function') {
                confetti({
                    particleCount: 120,
                    spread: 80,
                    origin: { y: 0.6 }
                });
                setTimeout(() => {
                    confetti({
                        particleCount: 80,
                        angle: 60,
                        spread: 55,
                        origin: { x: 0 }
                    });
                    confetti({
                        particleCount: 80,
                        angle: 120,
                        spread: 55,
                        origin: { x: 1 }
                    });
                }, 300);
            }

            showToast(data.message || 'Recipe cooked and money saved!', 'success', 6000);
            setTimeout(() => {
                window.location.href = 'impact.php';
            }, 1800);
        } else {
            showToast(data.error || 'Failed to log cooked recipe', 'error');
            cookBtn.disabled = false;
            cookBtn.innerText = '🎉 Mark as Cooked & Rescued!';
        }
    } catch (err) {
        showToast('Connection error', 'error');
        cookBtn.disabled = false;
        cookBtn.innerText = '🎉 Mark as Cooked & Rescued!';
    }
}

/* ==========================================================
   Save Recipe to Favorites
   ========================================================== */
async function saveCurrentRecipe() {
    if (!currentViewingRecipe) return;
    await saveRecipeDirect(currentViewingRecipe);
}

async function saveRecipeDirect(recipe, btnElement = null) {
    if (btnElement) {
        btnElement.style.color = 'var(--tomato)';
    }

    const payload = {
        title: recipe.title,
        cooking_time: recipe.cooking_time,
        difficulty: recipe.difficulty,
        rescued_items: recipe.rescued_items,
        ingredients: (recipe.ingredients_have || []).concat(recipe.ingredients_missing || []),
        steps: recipe.steps || [],
        csrf_token: window.CSRF_TOKEN
    };

    try {
        const res = await fetch((window.APP_BASE_URL || '') + '/api/save_recipe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
        } else {
            showToast(data.error || 'Could not save recipe', 'error');
        }
    } catch (err) {
        showToast('Connection error while saving', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
