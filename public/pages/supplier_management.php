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
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/supplier_management.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="requisition_management.php"><i class="fas fa-file-signature"></i> Requisition Management</a></li>
            <li><a href="requisition_status.php"><i class="fas fa-bars-progress"></i> Status</a></li>
            <li><a href="audit_trail.php"><i class="fas fa-shield-alt"></i> Audit Trail</a></li>
            <li><a href="account_management.php"><i class="fas fa-users-cog"></i> Account Management</a></li>
            <li><a href="facility_management.php"><i class="fas fa-building"></i> Facility Management</a></li>
            <li><a href="item_management.php"><i class="fas fa-box"></i> Item Management</a></li>
            <li><a href="inventory_management.php"><i class="fas fa-cubes"></i> Inventory Management</a></li>
            <li><a href="supplier_management.php" class="active"><i class="fas fa-truck"></i> Supplier Management</a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <?php if (!empty($user['photo_url'])): ?>
                    <img src="../<?php echo htmlspecialchars($user['photo_url']); ?>" alt="Profile Photo" class="user-avatar-img">
                <?php else: ?>
                    <div class="user-avatar-initials"><?php echo $initials; ?></div>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars((string)($user['full_name'] ?? '') !== '' ? (string)$user['full_name'] : explode('@', (string)$user['Email'])[0]); ?></h4>
                <p><?php echo htmlspecialchars($user['role']); ?></p>
            </div>
        </div>
        <button id="logoutBtn" class="btn-logout-sidebar">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </div>
</aside>

<main class="main-content supplier-management-container">
    <div class="page-header">
        <h1>Supplier Management</h1>
        <p>Manage supplier information, contacts, and details.</p>
    </div>

    <!-- Suppliers View -->
    <div id="suppliersView">
        <div class="filter-section">
            <h3>Suppliers</h3>
            <div class="filter-controls">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="supplierSearchInput" placeholder="Search by name, email, or city..." class="search-input">
                </div>
                <select id="supplierSortDropdown" class="sort-dropdown">
                    <option value="">Sort By</option>
                    <option value="name-asc">Name (A-Z)</option>
                    <option value="name-desc">Name (Z-A)</option>
                    <option value="email-asc">Email (A-Z)</option>
                    <option value="email-desc">Email (Z-A)</option>
                    <option value="city-asc">City (A-Z)</option>
                    <option value="city-desc">City (Z-A)</option>
                    <option value="status-asc">Status (A-Z)</option>
                    <option value="status-desc">Status (Z-A)</option>
                </select>
                <button class="btn-filter" id="addSupplierBtn"><i class="fas fa-plus"></i> Add Supplier</button>
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
    </div>

    <!-- Supplier Modal -->
    <div class="modal" id="supplierModal">
        <div class="modal-content">
            <span class="close-modal" id="closeSupplierModal">&times;</span>
            <h2 id="modalTitle">
                <i class="fas fa-plus-circle"></i> Add Supplier
            </h2>
            <form id="supplierForm" enctype="multipart/form-data">
                <input type="hidden" name="supplier_id" id="supplier_id">

                <!-- Image Preview Section -->
                <div class="form-group image-upload-group">
                    <label>Supplier Image</label>
                    <div class="image-preview-container">
                        <div class="image-preview" id="imagePreview">
                            <i class="fas fa-image"></i>
                            <p>No image selected</p>
                        </div>
                    </div>
                    <input type="file" name="supplier_image" id="supplier_image" accept="image/*" class="file-input">
                    <label for="supplier_image" class="file-input-label">
                        <i class="fas fa-cloud-upload-alt"></i> Choose Image
                    </label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Supplier Name <span class="required">*</span></label>
                        <input type="text" name="supplier_name" id="supplier_name" placeholder="e.g. ABC Supplies Inc." required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="supplier_status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Person</label>
                        <input type="text" name="contact_person" id="contact_person" placeholder="e.g. John Doe">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="supplier_email" placeholder="e.g. supplier@example.com">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone_number" id="phone_number" placeholder="e.g. +1 (555) 123-4567">
                    </div>
                    <div class="form-group">
                        <label>Postal Code</label>
                        <input type="text" name="postal_code" id="postal_code" placeholder="e.g. 12345">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" id="supplier_city" placeholder="e.g. New York">
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" name="country" id="supplier_country" placeholder="e.g. United States">
                    </div>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="supplier_address" placeholder="Enter full address" rows="3"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-check-circle"></i> Save Supplier
                    </button>
                    <button type="button" class="btn-cancel" id="cancelBtn">
                        <i class="fas fa-times-circle"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteConfirmModal">
        <div class="modal-content delete-modal">
            <div class="delete-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2>Delete Supplier</h2>
            <p>Are you sure you want to delete <strong id="deleteSupplierName">this supplier</strong>?</p>
            <p class="delete-warning">This action cannot be undone.</p>
            <div class="delete-actions">
                <button class="btn-delete-confirm" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button class="btn-delete-cancel" id="cancelDeleteBtn">
                    <i class="fas fa-times"></i> Cancel
                </button>
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

<script>
// -------- Sidebar Scroll Position Preservation --------
document.addEventListener('DOMContentLoaded', function() {
    const sidebarNav = document.querySelector('.sidebar-nav');
    const scrollPosKey = 'sidebarScrollPos';
    
    // Restore scroll position on page load
    const savedScrollPos = sessionStorage.getItem(scrollPosKey);
    if (savedScrollPos) {
        sidebarNav.scrollTop = parseInt(savedScrollPos);
    }
    
    // Save scroll position before navigation
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        link.addEventListener('click', function() {
            sessionStorage.setItem(scrollPosKey, sidebarNav.scrollTop);
        });
    });
});

document.getElementById('mobileMenuBtn').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
});
</script>
</body>
</html>
