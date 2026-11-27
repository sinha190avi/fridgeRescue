<?php
$pageTitle = 'Scan Groceries — FridgeRescue';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$autoSample = $_GET['sample'] ?? ($_GET['demo'] ?? '' ? 'sample_fridge_1.jpg' : '');
?>

<main class="main-content">
    <div style="max-width: 900px; margin: 0 auto;">
        <!-- Header -->
        <div style="margin-bottom: 28px; text-align: center;">
            <div class="produce-sticker" style="margin-bottom: 12px;">
                👁️ Google Gemini 2.5 Flash Vision
            </div>
            <h1 style="font-size: 34px; margin-bottom: 8px;">Scan Your Fridge or Groceries</h1>
            <p style="color: var(--text-secondary); font-size: 15px; max-width: 600px; margin: 0 auto;">
                Take a quick snapshot of your fridge shelves, crisper drawer, or grocery haul. Gemini AI detects the ingredients, estimates shelf-life, and enters them into your pantry.
            </p>
        </div>

        <!-- Quick 1-Click Sample Selectors -->
        <div style="display: flex; justify-content: center; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;">
            <button type="button" class="btn btn-outline btn-sm" onclick="loadSampleImage('sample_fridge_1.jpg')">
                📸 Try Sample Fridge 1
            </button>
            <button type="button" class="btn btn-outline btn-sm" onclick="loadSampleImage('sample_groceries_2.jpg')">
                🛒 Try Sample Haul 2
            </button>
        </div>

        <!-- Upload & Scan Box -->
        <div class="scanner-container" id="scannerContainer">
            <!-- Dropzone State -->
            <div class="upload-dropzone" id="dropzoneArea" onclick="document.getElementById('fridgePhotoInput').click()">
                <div class="dropzone-icon">📷</div>
                <h3 style="font-size: 20px; margin-bottom: 8px;">Drag &amp; drop a fridge photo here</h3>
                <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 20px;">
                    or click to browse from device (JPG, PNG, WEBP up to 5 MB)
                </p>
                <div style="display: flex; justify-content: center; gap: 12px;">
                    <button type="button" class="btn btn-primary" onclick="event.stopPropagation(); document.getElementById('fridgePhotoInput').click()">
                        📁 Browse Photos
                    </button>
                    <button type="button" class="btn btn-outline" onclick="event.stopPropagation(); document.getElementById('cameraInput').click()">
                        📸 Take Photo (Camera)
                    </button>
                </div>
            </div>

            <!-- Preview & Scanning State -->
            <div class="preview-wrapper" id="previewWrapper">
                <img id="previewImage" class="preview-img" alt="Fridge Preview">
                <!-- Laser line -->
                <div class="scanner-laser" id="scannerLaser"></div>
                <!-- Bounding boxes container -->
                <div id="detectionOverlay"></div>
            </div>

            <!-- Hidden File Inputs -->
            <input type="file" id="fridgePhotoInput" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="handleFileSelect(event)">
            <input type="file" id="cameraInput" accept="image/jpeg,image/png,image/webp" capture="environment" style="display: none;" onchange="handleFileSelect(event)">
        </div>

        <!-- Status / Processing Banner -->
        <div id="scanningStatus" style="display: none; text-align: center; margin-bottom: 28px;">
            <div style="display: inline-flex; align-items: center; gap: 10px; background: var(--surface-card); padding: 12px 24px; border-radius: var(--radius-full); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);">
                <span class="ring-pulse" style="font-size: 20px;">🤖</span>
                <span style="font-weight: 600; font-size: 14px;" id="scanningStatusText">Gemini 2.5 Flash is detecting vegetables, dairy &amp; shelf-life...</span>
            </div>
        </div>

        <!-- Editable Results Table -->
        <div class="results-card" id="resultsSection">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 22px; margin-bottom: 4px;">Detected Food Items (<span id="detectedCount">0</span>)</h2>
                    <p style="color: var(--text-secondary); font-size: 13px;">Review, edit names, or adjust expiry dates before saving.</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="addNewEmptyRow()">
                        + Add Row
                    </button>
                    <button type="button" class="btn btn-accent btn-sm" onclick="resetScanner()">
                        🔄 Rescan Photo
                    </button>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="editable-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Emoji</th>
                            <th>Item Name</th>
                            <th style="width: 140px;">Category</th>
                            <th style="width: 130px;">Quantity</th>
                            <th style="width: 160px;">Expiry Date</th>
                            <th style="width: 100px;">Price (<?= e($currentUser['currency'] ?? '₹') ?>)</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="resultsTableBody">
                        <!-- Populated dynamically by JS -->
                    </tbody>
                </table>
            </div>

            <!-- Disclaimer note -->
            <div style="margin: 20px 0; padding: 14px 18px; border-radius: var(--radius-md); background: var(--surface-card-subtle); border-left: 4px solid var(--warning); font-size: 13px; color: var(--text-secondary); display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">ℹ️</span>
                <span><strong>Safety note:</strong> Expiry dates are AI estimates. Check smell and packaging before eating.</span>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <button type="button" class="btn btn-outline" onclick="resetScanner()">Cancel</button>
                <button type="button" class="btn btn-primary btn-lg" id="saveAllBtn" onclick="saveAllScannedItems()">
                    💾 Stock My Fridge (<span id="saveCountBadge">0</span> Items)
                </button>
            </div>
        </div>
    </div>
</main>

<script>
let currentSelectedFile = null;
let currentSampleName = null;

// Initialize drag & drop
const container = document.getElementById('scannerContainer');
['dragenter', 'dragover'].forEach(name => {
    container.addEventListener(name, (e) => { e.preventDefault(); container.classList.add('dragover'); });
});
['dragleave', 'drop'].forEach(name => {
    container.addEventListener(name, (e) => { e.preventDefault(); container.classList.remove('dragover'); });
});
container.addEventListener('drop', (e) => {
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        processSelectedImage(e.dataTransfer.files[0]);
    }
});

function handleFileSelect(e) {
    if (e.target.files && e.target.files[0]) {
        processSelectedImage(e.target.files[0]);
    }
}

function loadSampleImage(sampleFileName) {
    currentSampleName = sampleFileName;
    currentSelectedFile = null;
    const previewImg = document.getElementById('previewImage');
    const sampleBase = "<?= asset_url('assets/img/samples/') ?>/";
    previewImg.src = `${sampleBase}${sampleFileName}`;
    
    document.getElementById('dropzoneArea').style.display = 'none';
    document.getElementById('previewWrapper').style.display = 'block';
    
    startScanAnimation();
    sendScanRequest({ sample: sampleFileName });
}

function processSelectedImage(file) {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        showToast('Please upload a JPG, PNG, or WEBP image', 'error');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        showToast('File size exceeds 5MB limit', 'error');
        return;
    }

    currentSelectedFile = file;
    currentSampleName = null;

    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById('previewImage').src = e.target.result;
        document.getElementById('dropzoneArea').style.display = 'none';
        document.getElementById('previewWrapper').style.display = 'block';
        startScanAnimation();
        
        const formData = new FormData();
        formData.append('image', file);
        formData.append('csrf_token', window.CSRF_TOKEN);
        sendScanRequest(formData, true);
    };
    reader.readAsDataURL(file);
}

function startScanAnimation() {
    document.getElementById('scannerLaser').style.display = 'block';
    document.getElementById('scanningStatus').style.display = 'block';
    document.getElementById('resultsSection').style.display = 'none';
    
    // Simulate animated recognition bounding tags while waiting
    const overlay = document.getElementById('detectionOverlay');
    overlay.innerHTML = '';
    
    setTimeout(() => {
        addMockBoundingTag(overlay, '🥬 Leafy Greens', '20%', '15%', '130px', '110px');
    }, 600);
    setTimeout(() => {
        addMockBoundingTag(overlay, '🍅 Tomatoes', '25%', '55%', '140px', '120px');
    }, 1200);
    setTimeout(() => {
        addMockBoundingTag(overlay, '🥛 Dairy Product', '60%', '30%', '110px', '140px');
    }, 1800);
}

function addMockBoundingTag(parent, label, top, left, width, height) {
    const tag = document.createElement('div');
    tag.className = 'detection-tag';
    tag.setAttribute('data-label', label);
    tag.style.top = top;
    tag.style.left = left;
    tag.style.width = width;
    tag.style.height = height;
    parent.appendChild(tag);
}

async function sendScanRequest(payload, isFormData = false) {
    try {
        let options = {
            method: 'POST'
        };

        if (isFormData) {
            options.body = payload;
        } else {
            const formData = new FormData();
            formData.append('sample', payload.sample || '');
            formData.append('csrf_token', window.CSRF_TOKEN);
            options.body = formData;
        }

        const res = await fetch((window.APP_BASE_URL || '') + '/api/scan_fridge.php', options);
        const data = await res.json();

        // Stop scanning animations
        document.getElementById('scannerLaser').style.display = 'none';
        document.getElementById('scanningStatus').style.display = 'none';

        if (data.success && data.items && data.items.length > 0) {
            showToast(`Gemini recognized ${data.count} items!`, 'success');
            renderResultsTable(data.items);
        } else {
            showToast(data.error || 'No items could be recognized in this photo', 'error');
            resetScanner();
        }
    } catch (err) {
        document.getElementById('scannerLaser').style.display = 'none';
        document.getElementById('scanningStatus').style.display = 'none';
        showToast('Connection error during AI scanning', 'error');
        resetScanner();
    }
}

function renderResultsTable(items) {
    const tbody = document.getElementById('resultsTableBody');
    tbody.innerHTML = '';

    items.forEach((item, index) => {
        const row = createTableRow(item, index);
        tbody.appendChild(row);
    });

    document.getElementById('detectedCount').innerText = items.length;
    document.getElementById('saveCountBadge').innerText = items.length;
    document.getElementById('resultsSection').style.display = 'block';

    // Scroll smoothly to results
    document.getElementById('resultsSection').scrollIntoView({ behavior: 'smooth' });
}

function createTableRow(item, idx) {
    const tr = document.createElement('tr');
    tr.className = 'item-row';
    tr.innerHTML = `
        <td>
            <input type="text" class="table-input row-emoji" value="${item.emoji || '🥦'}" style="text-align: center; font-size: 18px;" maxlength="4">
        </td>
        <td>
            <input type="text" class="table-input row-name" value="${item.name || ''}" placeholder="Item name" required>
        </td>
        <td>
            <select class="table-input row-category" onchange="updateRowEmoji(this)">
                <option value="vegetable" ${item.category === 'vegetable' ? 'selected' : ''}>Vegetable</option>
                <option value="fruit" ${item.category === 'fruit' ? 'selected' : ''}>Fruit</option>
                <option value="dairy" ${item.category === 'dairy' ? 'selected' : ''}>Dairy</option>
                <option value="leftover" ${item.category === 'leftover' ? 'selected' : ''}>Leftover</option>
                <option value="other" ${item.category === 'other' ? 'selected' : ''}>Other</option>
            </select>
        </td>
        <td>
            <div style="display: flex; gap: 4px;">
                <input type="number" class="table-input row-qty" value="${item.quantity || 1}" min="0.1" step="any" style="width: 60px;">
                <input type="text" class="table-input row-unit" value="${item.unit || 'pcs'}" style="width: 60px;">
            </div>
        </td>
        <td>
            <input type="date" class="table-input row-expiry" value="${item.expiry_date || ''}" required>
        </td>
        <td>
            <input type="number" class="table-input row-price" value="${item.estimated_price || 40}" min="0" step="5">
        </td>
        <td>
            <button type="button" class="btn btn-icon btn-outline" style="width: 28px; height: 28px; color: var(--tomato);" onclick="removeTableRow(this)" title="Delete row">
                &times;
            </button>
        </td>
    `;
    return tr;
}

function updateRowEmoji(selectElement) {
    const row = selectElement.closest('tr');
    const emojiInput = row.querySelector('.row-emoji');
    const categoryMap = { vegetable: '🥦', fruit: '🍎', dairy: '🥛', leftover: '🍲', other: '🥫' };
    if (emojiInput && categoryMap[selectElement.value]) {
        emojiInput.value = categoryMap[selectElement.value];
    }
}

function removeTableRow(btn) {
    const row = btn.closest('tr');
    row.remove();
    updateRowCount();
}

function addNewEmptyRow() {
    const tbody = document.getElementById('resultsTableBody');
    const defaultExp = new Date();
    defaultExp.setDate(defaultExp.getDate() + 4);
    const expStr = defaultExp.toISOString().split('T')[0];

    const emptyItem = {
        emoji: '🥦',
        name: '',
        category: 'vegetable',
        quantity: 1,
        unit: 'pcs',
        expiry_date: expStr,
        estimated_price: 50
    };
    tbody.appendChild(createTableRow(emptyItem, tbody.children.length));
    updateRowCount();
}

function updateRowCount() {
    const count = document.querySelectorAll('#resultsTableBody tr').length;
    document.getElementById('detectedCount').innerText = count;
    document.getElementById('saveCountBadge').innerText = count;
}

function resetScanner() {
    document.getElementById('dropzoneArea').style.display = 'block';
    document.getElementById('previewWrapper').style.display = 'none';
    document.getElementById('scannerLaser').style.display = 'none';
    document.getElementById('scanningStatus').style.display = 'none';
    document.getElementById('detectionOverlay').innerHTML = '';
    document.getElementById('resultsSection').style.display = 'none';
    document.getElementById('fridgePhotoInput').value = '';
    document.getElementById('cameraInput').value = '';
    currentSelectedFile = null;
    currentSampleName = null;
}

/* ==========================================================
   Save All Scanned Items to DB
   ========================================================== */
async function saveAllScannedItems() {
    const rows = document.querySelectorAll('#resultsTableBody tr');
    if (rows.length === 0) {
        showToast('No items to save', 'warning');
        return;
    }

    const items = [];
    rows.forEach(r => {
        const name = r.querySelector('.row-name')?.value.trim();
        if (name) {
            items.push({
                emoji: r.querySelector('.row-emoji')?.value.trim() || '🥦',
                name: name,
                category: r.querySelector('.row-category')?.value || 'vegetable',
                quantity: parseFloat(r.querySelector('.row-qty')?.value) || 1,
                unit: r.querySelector('.row-unit')?.value.trim() || 'pcs',
                expiry_date: r.querySelector('.row-expiry')?.value,
                estimated_price: parseFloat(r.querySelector('.row-price')?.value) || 0
            });
        }
    });

    if (items.length === 0) {
        showToast('Please enter at least one item name', 'error');
        return;
    }

    const saveBtn = document.getElementById('saveAllBtn');
    saveBtn.disabled = true;
    saveBtn.innerText = 'Saving Groceries...';

    try {
        const res = await fetch((window.APP_BASE_URL || '') + '/api/save_items.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                items: items,
                csrf_token: window.CSRF_TOKEN
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 800);
        } else {
            showToast(data.error || 'Failed to stock fridge', 'error');
            saveBtn.disabled = false;
            saveBtn.innerText = `💾 Stock My Fridge (${items.length} Items)`;
        }
    } catch (err) {
        showToast('Connection error while saving', 'error');
        saveBtn.disabled = false;
        saveBtn.innerText = `💾 Stock My Fridge (${items.length} Items)`;
    }
}

// Auto-run sample if query param present
window.addEventListener('DOMContentLoaded', () => {
    const auto = "<?= e($autoSample) ?>";
    if (auto) {
        setTimeout(() => loadSampleImage(auto), 300);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
