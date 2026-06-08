<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';
$db = Database::connect();
$stmt = $db->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$initials = strtoupper(substr($user['Email'], 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Item Management - IMRMS</title>
<link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
<link rel="stylesheet" href="../assets/css/item_management.css">
<link rel="stylesheet" href="../assets/css/loading.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<?php $imActivePage = 'item_management.php'; require __DIR__ . '/partials/inventory_manager_sidebar.php'; ?>

<main class="main-content item-management-container">
    <div class="page-header">
        <h1>Item Management</h1>
        <p>Manage inventory items in the system.</p>
    </div>

    <div class="filter-section">
        <h3>Items List</h3>
        <div class="filter-controls">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="itemSearchInput" placeholder="Search by name, brand, or category..." class="search-input">
            </div>
            <select id="itemSortDropdown" class="sort-dropdown">
                <option value="">Sort By</option>
                <option value="name-asc">Name (A-Z)</option>
                <option value="name-desc">Name (Z-A)</option>
                <option value="brand-asc">Brand (A-Z)</option>
                <option value="brand-desc">Brand (Z-A)</option>
                <option value="category-asc">Category (A-Z)</option>
                <option value="category-desc">Category (Z-A)</option>
                <option value="status-asc">Status (A-Z)</option>
                <option value="status-desc">Status (Z-A)</option>
            </select>
            <button class="btn-filter" id="addItemBtn"><i class="fas fa-plus"></i> Add Item</button>
        </div>
    </div>

    <div class="table-container">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Photo</th>
                        <th>Item Name</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">
                    <tr>
                        <td colspan="10" style="text-align:center;padding:50px;color:#64748b;">Loading Items...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Item Modal -->
    <div class="modal" id="itemModal">
        <div class="modal-content">
            <span class="close-modal" id="closeItemModal">&times;</span>
            <h2 id="modalTitle">Add Item</h2>
            <form id="itemForm" enctype="multipart/form-data">
                <input type="hidden" name="item_id" id="item_id">
                
                <div class="form-group">
                    <label>Item Name <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="item_name" id="item_name" placeholder="e.g. Laptop" required>
                </div>

                <div class="form-group">
                    <label>Photo</label>
                    <div class="photo-upload-row">
                        <div class="photo-preview">
                            <img id="photoPreview" alt="Preview" />
                            <div id="photoPlaceholder" class="photo-placeholder">+</div>
                        </div>
                        <input type="file" name="photo" id="photo" accept="image/*">
                    </div>
                    <small style="font-size:12px;color:#64748b;">JPEG, PNG, GIF, or WEBP. Max ~2MB recommended.</small>
                </div>

                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" name="brand" id="brand" placeholder="e.g. Dell, HP, Lenovo">
                </div>

                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" id="model" placeholder="e.g. XPS 13, EliteBook">
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" id="category" placeholder="e.g. Electronics, Furniture">
                </div>

                <div class="form-group">
                    <label>Suppliers (canvass suggestions)</label>
                    <div class="item-supplier-picker-row">
                        <select id="supplier_pick_add" class="item-supplier-dropdown" aria-label="Add supplier">
                            <option value="">Select a supplier to add…</option>
                        </select>
                        <button type="button" class="item-supplier-add-btn" id="supplier_add_btn">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                    <div id="supplier_chips" class="item-supplier-chips" aria-live="polite"></div>
                    <small class="item-supplier-hint">Add one or more vendors from the list. Order matters for canvass suggestions. These are only hints when a request matches this catalog item.</small>
                </div>

                <div class="form-group">
                    <label>Unit</label>
                    <input type="text" name="unit" id="unit" placeholder="e.g. Piece, Box, Set">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="description" placeholder="Enter item description..."></textarea>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="status">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn-save">Save Item</button>
            </form>
        </div>
    </div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" style="display:none;">
    <i class="fas fa-bars"></i>
</button>

<div id="toastContainer"></div>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/item_management.js"></script>

<?php require __DIR__ . '/partials/inventory_manager_sidebar_scripts.php'; ?>
</body>
</html>
