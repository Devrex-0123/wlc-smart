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
    <title>Supplier Management - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
    <link rel="stylesheet" href="../assets/css/supplier_management.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<?php $imActivePage = 'supplier_management.php'; require __DIR__ . '/partials/inventory_manager_sidebar.php'; ?>

<main class="main-content supplier-management-container">
  <div class="module-page-header">
    <h1 class="module-page-header__title">Supplier Management</h1>
    <p class="module-page-header__subtitle">Manage supplier information, contacts, and details.</p>
  </div>

    <!-- Suppliers View -->
    <div id="suppliersView">
      <div class="suppliers-list-card">
        <div class="filter-section suppliers-filter-bar">
          <h2 class="suppliers-list-card__heading">Suppliers List</h2>
          <div class="filter-controls">
            <div class="search-container">
              <i class="fas fa-search"></i>
              <input type="text" id="supplierSearchInput" placeholder="Search" class="search-input">
            </div>
            <button class="btn-filter" id="addSupplierBtn" type="button"><i class="fas fa-plus"></i> Add Supplier</button>
          </div>
        </div>

        <div class="table-container">
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th class="col-image">Image</th>
                  <th class="col-name">Supplier Name</th>
                  <th class="col-contact">Contact Person</th>
                  <th class="col-email">Email</th>
                  <th class="col-phone">Phone</th>
                  <th class="col-city">City</th>
                  <th class="col-status">Status</th>
                  <th class="col-action">Action</th>
                </tr>
              </thead>
              <tbody id="supplierTableBody">
                <tr>
                  <td colspan="9" class="loading-cell">Loading Suppliers...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="pagination-controls">
          <button type="button" class="pagination-btn pagination-btn--prev" id="prevSupplierBtn" disabled><i class="fas fa-chevron-left"></i> Previous</button>
          <span id="supplierPageInfo" class="page-info">Page 1 of 1 (0 records)</span>
          <button type="button" class="pagination-btn pagination-btn--next" id="nextSupplierBtn" disabled>Next <i class="fas fa-chevron-right"></i></button>
        </div>
      </div>
    </div>

    <!-- Supplier Modal -->
    <div class="modal" id="supplierModal">
      <div class="modal-content supplier-modal-content">
        <div class="modal-header">
          <h2 id="modalTitle">Add Supplier</h2>
          <button type="button" class="close-modal" id="closeSupplierModal" aria-label="Close modal">&times;</button>
        </div>

        <form id="supplierForm" class="supplier-modal-form" enctype="multipart/form-data">
          <input type="hidden" name="supplier_id" id="supplier_id">

          <div class="supplier-avatar-section">
            <div class="supplier-avatar-preview" id="imagePreview">
              <img id="supplierImagePreview" alt="Supplier preview" hidden>
              <div id="supplierImagePlaceholder" class="supplier-avatar-placeholder">
                <i class="fas fa-store" aria-hidden="true"></i>
              </div>
            </div>
            <div class="supplier-avatar-actions">
              <label for="supplier_image" class="btn-upload-label">Choose Image</label>
              <input type="file" name="supplier_image" id="supplier_image" accept="image/*" class="file-input-hidden">
              <small class="input-hint">JPEG, PNG, GIF, or WEBP. Max ~2MB.</small>
            </div>
          </div>

          <hr class="form-divider">

          <p class="form-section-label">Basic Information</p>
          <div class="form-grid">
            <div class="form-group">
              <label for="supplier_name">Supplier Name <span class="required">*</span></label>
              <input type="text" name="supplier_name" id="supplier_name" placeholder="e.g. ABC Supplies Inc." required>
            </div>
            <div class="form-group">
              <label for="supplier_status">Status <span class="required">*</span></label>
              <select name="status" id="supplier_status" required>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
          </div>

          <hr class="form-divider">

          <p class="form-section-label">Contact Details</p>
          <div class="form-grid">
            <div class="form-group">
              <label for="contact_person">Contact Person <span class="required">*</span></label>
              <input type="text" name="contact_person" id="contact_person" placeholder="e.g. John Doe" required>
            </div>
            <div class="form-group">
              <label for="supplier_email">Email <span class="required">*</span></label>
              <input type="email" name="email" id="supplier_email" placeholder="e.g. supplier@example.com" required>
            </div>
            <div class="form-group">
              <label for="phone_number">Phone Number <span class="required">*</span></label>
              <input type="tel" name="phone_number" id="phone_number" placeholder="e.g. 09123456789" inputmode="numeric" maxlength="11" pattern="\d{11}" required>
            </div>
            <div class="form-group">
              <label for="supplier_tin">TIN <span class="optional-label">(optional)</span></label>
              <input type="text" name="tin" id="supplier_tin" maxlength="20" placeholder="e.g. 123-456-789-000" autocomplete="off">
              <p class="field-hint">Leave blank for online shops without a BIR TIN.</p>
            </div>
          </div>

          <hr class="form-divider">

          <p class="form-section-label">Address</p>
          <div class="form-grid">
            <div class="form-group form-grid__full">
              <label for="supplier_address">Street Address <span class="required">*</span></label>
              <textarea name="address" id="supplier_address" placeholder="Enter full street address" rows="3" required></textarea>
            </div>
            <div class="form-group">
              <label for="supplier_city">City <span class="required">*</span></label>
              <input type="text" name="city" id="supplier_city" placeholder="e.g. Ormoc City" required>
            </div>
            <div class="form-group">
              <label for="supplier_country">Country <span class="required">*</span></label>
              <input type="text" name="country" id="supplier_country" placeholder="e.g. Philippines" required>
            </div>
            <div class="form-group">
              <label for="postal_code">Postal Code <span class="optional-label">(optional)</span></label>
              <input type="text" name="postal_code" id="postal_code" placeholder="e.g. 6541">
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-cancel" id="cancelBtn">Cancel</button>
            <button type="submit" class="btn-save" id="saveSupplierBtn">Save Supplier</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="supplier-delete-modal" id="deleteConfirmModal" aria-hidden="true">
        <div class="supplier-delete-backdrop" id="deleteConfirmBackdrop"></div>
        <div class="supplier-delete-card" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle" aria-describedby="deleteConfirmDesc">
            <button type="button" class="supplier-delete-close" id="closeDeleteModal" aria-label="Close">&times;</button>
            <div class="supplier-delete-icon" aria-hidden="true">
                <i class="fas fa-trash-alt"></i>
            </div>
            <h3 id="deleteConfirmTitle" class="supplier-delete-title">Delete this supplier?</h3>
            <p id="deleteConfirmDesc" class="supplier-delete-desc">This will permanently remove the supplier from your list. This action cannot be undone.</p>
            <p class="supplier-delete-name" id="deleteSupplierName"></p>
            <div class="supplier-delete-actions">
                <button type="button" class="supplier-delete-btn supplier-delete-btn-cancel" id="cancelDeleteBtn">Cancel</button>
                <button type="button" class="supplier-delete-btn supplier-delete-btn-delete" id="confirmDeleteBtn">
                    <i class="fas fa-trash-alt"></i> Delete supplier
                </button>
            </div>
        </div>
    </div>

    <!-- Success Confirmation Modal -->
    <div class="supplier-success-modal" id="supplierSuccessModal" aria-hidden="true">
        <div class="supplier-success-backdrop" id="supplierSuccessBackdrop"></div>
        <div class="supplier-success-card" role="dialog" aria-modal="true" aria-labelledby="supplierSuccessTitle" aria-describedby="supplierSuccessMessage">
            <div class="supplier-success-icon" aria-hidden="true">
                <i class="fas fa-check"></i>
            </div>
            <h3 id="supplierSuccessTitle" class="supplier-success-title">Success</h3>
            <p id="supplierSuccessMessage" class="supplier-success-desc"></p>
            <div class="supplier-success-actions">
                <button type="button" class="supplier-success-btn" id="supplierSuccessOk">OK</button>
            </div>
        </div>
    </div>

</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" style="display:none;">
    <i class="fas fa-bars"></i>
</button>

<div id="toastContainer"></div>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/supplier_management.js"></script>

<?php require __DIR__ . '/partials/inventory_manager_sidebar_scripts.php'; ?>
</body>
</html>
