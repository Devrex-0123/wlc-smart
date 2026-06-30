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
    <div class="module-page-header im-page-header">
        <div class="im-page-header__top">
            <div class="im-page-header__text">
                <h1 class="module-page-header__title">Inventory Management</h1>
                <p class="module-page-header__subtitle">Manage and track inventory items assigned to facilities.</p>
            </div>
            <div class="im-page-header__actions">
                <div class="offices-search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="officeSearch" placeholder="Search" class="offices-search-input" aria-label="Search offices">
                </div>
                <button type="button" class="im-add-inventory-btn hidden" id="addInventoryBtn"><i class="fas fa-plus"></i> Add Inventory</button>
            </div>
        </div>
    </div>

    <!-- Offices View -->
    <div id="officesView">
        <div class="offices-list-card">
            <div class="table-container">
                <h2 class="table-header-title">School Facilities</h2>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>School Facilities</th>
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
                <footer class="table-panel-footer" id="officePagination" aria-label="Office list pages">
                    <p class="table-panel-footer__info" id="deptPageInfo">Showing 0 to 0 of 0 offices</p>
                    <div class="table-panel-footer__pagination">
                        <button type="button" class="table-panel-footer__page-btn" id="deptPrevPageBtn" disabled aria-label="Previous page">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="table-panel-footer__page-num" id="deptPageNum">1</span>
                        <button type="button" class="table-panel-footer__page-btn" id="deptNextPageBtn" disabled aria-label="Next page">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </div>

    <!-- Facilities View -->
    <div id="facilitiesView" class="hidden">
        <div class="offices-list-card">
            <div class="offices-list-card__header">
                <h3 id="facilityViewTitle" class="offices-list-card__title">Rooms and Laboratory</h3>
                <button type="button" class="im-back-btn" id="facilityBackBtn">
                    <i class="fas fa-chevron-left"></i> Back to Offices
                </button>
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
                <footer class="table-panel-footer" id="facilityPagination" aria-label="Facility list pages">
                    <p class="table-panel-footer__info" id="facilityPageInfo">Showing 0 to 0 of 0 facilities</p>
                    <div class="table-panel-footer__pagination">
                        <button type="button" class="table-panel-footer__page-btn" id="facilityPrevPageBtn" disabled aria-label="Previous page">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="table-panel-footer__page-num" id="facilityPageNum">1</span>
                        <button type="button" class="table-panel-footer__page-btn" id="facilityNextPageBtn" disabled aria-label="Next page">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </div>

    <!-- Inventory View -->
    <div id="inventoryView" class="hidden">
        <div class="offices-list-card">
            <div class="offices-list-card__header">
                <h3 id="inventoryViewTitle" class="offices-list-card__title">Inventory</h3>
                <button type="button" class="im-back-btn" id="inventoryBackBtn">
                    <i class="fas fa-chevron-left"></i> Back to Rooms and Laboratory
                </button>
            </div>
            <div class="table-container">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Item Component</th>
                                <th>Item Code</th>
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
                <footer class="table-panel-footer" id="inventoryPagination" aria-label="Inventory list pages">
                    <p class="table-panel-footer__info" id="inventoryPageInfo">Showing 0 to 0 of 0 items</p>
                    <div class="table-panel-footer__pagination">
                        <button type="button" class="table-panel-footer__page-btn" id="inventoryPrevPageBtn" disabled aria-label="Previous page">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="table-panel-footer__page-num" id="inventoryPageNum">1</span>
                        <button type="button" class="table-panel-footer__page-btn" id="inventoryNextPageBtn" disabled aria-label="Next page">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </footer>
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

    <!-- Delete Confirmation Modal -->
    <div class="inv-delete-modal" id="invDeleteModal" aria-hidden="true">
        <div class="inv-delete-backdrop" id="invDeleteBackdrop"></div>
        <div class="inv-delete-card" role="dialog" aria-modal="true" aria-labelledby="invDeleteTitle" aria-describedby="invDeleteDesc">
            <button type="button" class="inv-delete-close" id="invDeleteClose" aria-label="Close">&times;</button>
            <div class="inv-delete-icon" aria-hidden="true">
                <i class="fas fa-trash-alt"></i>
            </div>
            <h3 id="invDeleteTitle" class="inv-delete-title">Delete this inventory item?</h3>
            <p id="invDeleteDesc" class="inv-delete-desc">This will permanently remove the inventory item and all its parts. This action cannot be undone.</p>
            <p class="inv-delete-name" id="invDeleteName"></p>
            <div class="inv-delete-actions">
                <button type="button" class="inv-delete-btn inv-delete-btn-cancel" id="invDeleteCancelBtn">Cancel</button>
                <button type="button" class="inv-delete-btn inv-delete-btn-delete" id="invDeleteConfirmBtn">
                    <i class="fas fa-trash-alt"></i> Delete item
                </button>
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
