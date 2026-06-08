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
<title>Inventory Management - IMRMS</title>
<link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
<link rel="stylesheet" href="../assets/css/inventory_management.css">
<link rel="stylesheet" href="../assets/css/loading.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<?php $imActivePage = 'inventory_management.php'; require __DIR__ . '/partials/inventory_manager_sidebar.php'; ?>

<main class="main-content inventory-management-container">
    <div class="page-header">
        <h1>Inventory Management</h1>
        <p>Manage and track inventory items assigned to facilities. The office list is ordered by total inventory and paginated five per page.</p>
    </div>

    <!-- Breadcrumb Navigation -->
    <div class="breadcrumb-nav" id="breadcrumb">
        <div class="breadcrumb-item active" id="breadcrumb-home">
            <i class="fas fa-home"></i> Offices
        </div>
        <div class="breadcrumb-item hidden" id="breadcrumb-facility">
            <i class="fas fa-chevron-right"></i> <span id="breadcrumb-facility-text"></span>
        </div>
        <div class="breadcrumb-item hidden" id="breadcrumb-inventory">
            <i class="fas fa-chevron-right"></i> Inventory
        </div>
    </div>

    <!-- Offices View -->
    <div id="officesView">
        <div class="filter-section">
            <div class="filter-section-lead">
                <h3>Offices</h3>
                <p class="filter-hint">Offices are ranked by total inventory (highest first). Five per page; use Previous / Next to browse.</p>
            </div>
            <div class="filter-controls">
                <input type="text" id="officeSearch" placeholder="Search offices..." aria-label="Search offices" />
                <button class="btn-filter" id="officeSearchBtn"><i class="fas fa-search"></i> Search</button>
                <button class="btn-filter" id="officeSortBtn" data-sort="total-desc">Sort: TOTAL ▼</button>
            </div>
        </div>
        <div class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Office Name</th>
                            <th>Total Labs</th>
                            <th>Total Rooms</th>
                            <th>TOTAL</th>
                            <th>Total Inventory</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="officeTableBody">
                        <tr>
                            <td colspan="7" class="loading-cell">Loading Offices...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination-controls" id="officePagination" aria-label="Office list pages">
                <button type="button" id="deptPrevPageBtn" class="pagination-btn" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span id="deptPageInfo" class="page-info">Page 1</span>
                <button type="button" id="deptNextPageBtn" class="pagination-btn" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Facilities View -->
    <div id="facilitiesView" class="hidden">
        <div class="filter-section">
            <h3 id="facilityViewTitle">Rooms and Labs</h3>
        </div>
        <div class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Building</th>
                            <th>Code</th>
                            <th>Floor</th>
                            <th>Laboratory</th>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Total Inventory</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="facilityTableBody">
                        <tr>
                            <td colspan="9" class="loading-cell">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Inventory View -->
    <div id="inventoryView" class="hidden">
        <div class="filter-section">
            <h3>Inventory in CICTE</h3>
            <button class="btn-filter" id="addInventoryBtn"><i class="fas fa-plus"></i> Add Inventory</button>
        </div>
        <div class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Item component</th>
                            <th>Item code</th>
                            <th>Quantity</th>
                            <th>Condition</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <tr>
                            <td colspan="8" class="loading-cell">Loading Inventory...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Inventory Modal -->
    <div class="modal" id="inventoryModal">
        <div class="modal-content modal-scrollable">
            <span class="close-modal" id="closeInventoryModal">&times;</span>
            <h2 id="modalTitle">Add Inventory</h2>
            <form id="inventoryForm" enctype="multipart/form-data">
                <input type="hidden" name="inventory_id" id="inventory_id">
                <input type="hidden" name="request_id" id="request_id" value="0">

                <div class="form-group">
                    <label>Inventory Name <span class="required-indicator">*</span></label>
                    <input type="text" name="name" id="name" placeholder="e.g. Computer Set 1, Conference Table A" required>
                    <small class="text-sm text-secondary">A friendly label for this inventory record. Catalog items (monitor, CPU, etc.) are added as parts below.</small>
                </div>

                <div class="form-row facility-assignment-row">
                    <div class="form-group facility-select-group">
                        <label>Facility <span class="required-indicator">*</span></label>
                        <select name="facility_id" id="facility_id" required>
                            <option value="">Select Facility</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Assigned Personnel</label>
                        <select name="assigned_user_id" id="assigned_user_id">
                            <option value="">Office default (if dean set one)</option>
                            <option value="0">No assignee</option>
                        </select>
                        <small class="text-sm text-secondary">Leave on office default to use the dean&rsquo;s lab manager for new items, or pick a person. Choose &ldquo;No assignee&rdquo; to keep the row unassigned even when a default exists.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Inventory item code</label>
                    <div id="item_code_display" class="code-display">-</div>
                    <input type="hidden" name="item_code" id="item_code">
                    <small class="text-sm text-secondary">Generated from the facility. Part rows use this code unless you override in the part dialog.</small>
                </div>

                <div class="components-section components-section--primary">
                    <div class="components-header">
                        <h3 class="m-0">Catalog parts</h3>
                        <button type="button" id="addComponentBtn" class="btn-add-component">
                            <i class="fas fa-plus"></i> Add part
                        </button>
                    </div>
                    <p class="text-sm text-secondary components-help">Add each piece from the item catalog. Set quantity, condition, and availability per part. At least one part is required when creating inventory.</p>
                    <div id="componentsList" class="components-container">
                        <p class="text-center text-muted components-empty-msg">No parts added yet</p>
                    </div>
                </div>

                <div class="form-group">
                    <label>Acquisition Date</label>
                    <input type="date" name="acquisition_date" id="acquisition_date">
                </div>

                <div class="form-group">
                    <label>Remarks</label>
                    <textarea name="remarks" id="remarks" placeholder="Additional notes or remarks..."></textarea>
                </div>

                <button type="button" class="btn-save" id="saveInventoryBtn">Save Inventory</button>
            </form>
        </div>
    </div>

    <!-- Component Modal -->
    <div class="modal" id="componentModal">
        <div class="modal-content">
            <span class="close-modal" id="closeComponentModal">&times;</span>
            <h2 id="componentModalTitle">Add catalog part</h2>
            <form id="componentForm" enctype="multipart/form-data">
                <input type="hidden" name="component_index" id="component_index">
                
                <div class="form-group">
                    <label>Catalog item <span class="required-indicator">*</span></label>
                    <select name="component_item_id" id="component_item_id" required>
                        <option value="">Select from item catalog</option>
                    </select>
                </div>

                <input type="hidden" name="component_code" id="component_code">

                <div class="form-group">
                    <label>Quantity <span class="required-indicator">*</span></label>
                    <input type="number" name="component_quantity" id="component_quantity" min="1" value="1" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Condition</label>
                        <select name="component_condition" id="component_condition">
                            <option value="">—</option>
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="component_status" id="component_status">
                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Component Photo</label>
                    <div class="photo-upload-row">
                        <div class="photo-preview">
                            <img id="componentPhotoPreview" alt="Preview" class="photo-preview-hidden" />
                            <div id="componentPhotoPlaceholder" class="photo-placeholder">IMG</div>
                        </div>
                        <input type="file" name="component_photo" id="component_photo" accept="image/*">
                    </div>
                    <small class="text-sm text-secondary">JPEG, PNG, GIF, or WEBP. Max ~2MB recommended.</small>
                </div>

                <button type="submit" class="btn-save">Save part</button>
            </form>
        </div>
    </div>

    <!-- Detail View Modal -->
    <div class="modal" id="detailModal">
        <div class="modal-content modal-scrollable">
            <span class="close-modal" id="closeDetailModal">&times;</span>
            <h2>Inventory details</h2>
            
            <div class="detail-section">
                <div class="detail-header detail-header--text-only">
                    <div class="detail-info">
                        <h3 id="detailName" class="detail-title">Name</h3>
                        <p id="detailLocation" class="detail-subtitle">Location</p>
                    </div>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Item Code</span>
                        <span class="detail-value" id="detailItemCode">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Acquisition Date</span>
                        <span class="detail-value" id="detailDate">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Assigned Personnel</span>
                        <span class="detail-value" id="detailAssignedUser">-</span>
                    </div>
                </div>

                <div class="detail-remarks">
                    <span class="detail-label">Remarks</span>
                    <p id="detailRemarks" class="detail-remarks-text">-</p>
                </div>
            </div>

            <div class="components-section">
                <div class="components-header">
                    <h3 class="m-0">Catalog parts</h3>
                </div>
                <p class="text-sm text-secondary components-help">Each piece from the item catalog with its own code, quantity, condition, and status (same layout as when editing).</p>
                <div id="detailComponentsList" class="components-container">
                </div>
            </div>
        </div>
    </div>
</main>

<div id="imageLightbox" class="image-lightbox hidden" role="dialog" aria-modal="true" aria-label="Enlarged image">
    <button type="button" class="image-lightbox-close" id="imageLightboxClose" aria-label="Close preview">&times;</button>
    <img id="imageLightboxImg" class="image-lightbox-img" src="" alt="Enlarged part photo" />
</div>

<button class="mobile-menu-btn hidden" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>

<div id="toastContainer"></div>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/inventory_hierarchy.js"></script>
<script src="../assets/js/inventory_management.js"></script>

<?php require __DIR__ . '/partials/inventory_manager_sidebar_scripts.php'; ?>
</body>
</html>
