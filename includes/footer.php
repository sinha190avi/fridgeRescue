<?php
$currentPageFooter = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
?>
    <!-- Mobile Bottom Navigation Bar -->
    <nav class="mobile-nav">
        <a href="dashboard.php" class="mobile-nav-item <?= $currentPageFooter === 'dashboard.php' ? 'active' : '' ?>">
            <span style="font-size: 20px;">❄️</span>
            <span>Fridge</span>
        </a>
        <a href="scan.php" class="mobile-nav-item <?= $currentPageFooter === 'scan.php' ? 'active' : '' ?>">
            <span style="font-size: 20px;">📸</span>
            <span>Scan</span>
        </a>
        <a href="recipes.php" class="mobile-nav-item <?= $currentPageFooter === 'recipes.php' ? 'active' : '' ?>">
            <span style="font-size: 20px;">🍳</span>
            <span>Recipes</span>
        </a>
        <a href="impact.php" class="mobile-nav-item <?= $currentPageFooter === 'impact.php' ? 'active' : '' ?>">
            <span style="font-size: 20px;">📊</span>
            <span>Impact</span>
        </a>
        <a href="settings.php" class="mobile-nav-item <?= $currentPageFooter === 'settings.php' ? 'active' : '' ?>">
            <span style="font-size: 20px;">⚙️</span>
            <span>Settings</span>
        </a>
    </nav>

    </div><!-- /.app-container -->

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Quick Add Item Modal -->
    <div class="modal-backdrop" id="addItemModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 style="font-size: 20px;">🥦 Add Food to Fridge</h3>
                <button type="button" class="btn btn-icon btn-outline" onclick="closeModal('addItemModal')">&times;</button>
            </div>
            <form id="addItemForm" onsubmit="handleManualAddItem(event)">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 80px 1fr; gap: 12px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Emoji</label>
                            <input type="text" id="addEmoji" class="form-control" value="🥦" maxlength="4" style="text-align: center; font-size: 20px;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Item Name</label>
                            <input type="text" id="addName" class="form-control" placeholder="e.g. Fresh Spinach" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Category</label>
                            <select id="addCategory" class="form-control" onchange="autoUpdateEmoji(this.value)">
                                <option value="vegetable">Vegetable</option>
                                <option value="fruit">Fruit</option>
                                <option value="dairy">Dairy</option>
                                <option value="leftover">Leftover</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Estimated Price (<?= e($currentUser['currency'] ?? '₹') ?>)</label>
                            <input type="number" id="addPrice" class="form-control" value="50" min="0" step="5">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Quantity</label>
                            <input type="number" id="addQty" class="form-control" value="1" min="0.1" step="any">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Unit</label>
                            <select id="addUnit" class="form-control">
                                <option value="pcs">pcs</option>
                                <option value="g">grams (g)</option>
                                <option value="kg">kg</option>
                                <option value="bunch">bunch</option>
                                <option value="pack">pack</option>
                                <option value="L">liters (L)</option>
                                <option value="bowl">bowl</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" id="addExpiryDate" class="form-control" value="<?= date('Y-m-d', strtotime('+4 days')) ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addItemModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveAddBtn">Save to Fridge</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal-backdrop" id="editItemModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 style="font-size: 20px;">✏️ Edit Food Item</h3>
                <button type="button" class="btn btn-icon btn-outline" onclick="closeModal('editItemModal')">&times;</button>
            </div>
            <form id="editItemForm" onsubmit="handleEditItemSubmit(event)">
                <input type="hidden" id="editItemId">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 80px 1fr; gap: 12px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Emoji</label>
                            <input type="text" id="editEmoji" class="form-control" maxlength="4" style="text-align: center; font-size: 20px;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Item Name</label>
                            <input type="text" id="editName" class="form-control" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Category</label>
                            <select id="editCategory" class="form-control">
                                <option value="vegetable">Vegetable</option>
                                <option value="fruit">Fruit</option>
                                <option value="dairy">Dairy</option>
                                <option value="leftover">Leftover</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Estimated Price</label>
                            <input type="number" id="editPrice" class="form-control" min="0" step="1">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Quantity</label>
                            <input type="number" id="editQty" class="form-control" min="0.1" step="any">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Unit</label>
                            <input type="text" id="editUnit" class="form-control">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" id="editExpiryDate" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editItemModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveEditBtn">Update Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div class="modal-backdrop" id="deleteConfirmModal">
        <div class="modal-dialog" style="max-width: 440px;">
            <div class="modal-header">
                <h3 style="font-size: 20px; color: var(--tomato);">🗑️ Remove Item?</h3>
                <button type="button" class="btn btn-icon btn-outline" onclick="closeModal('deleteConfirmModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmPrompt" style="color: var(--text-secondary); line-height: 1.6;">
                    Are you sure you want to remove this item?
                </p>
                <div style="margin-top: 16px; display: flex; gap: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                        <input type="radio" name="delete_reason" value="wasted" checked>
                        <span>Mark as spoiled / wasted (tracks loss)</span>
                    </label>
                </div>
                <div style="margin-top: 8px; display: flex; gap: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                        <input type="radio" name="delete_reason" value="used">
                        <span>Ate or cooked it (no recipe)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteConfirmModal')">Keep Item</button>
                <button type="button" class="btn btn-rescue" id="confirmDeleteBtn">Remove</button>
            </div>
        </div>
    </div>

    <script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
