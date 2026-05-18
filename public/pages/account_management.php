<?php
session_start();
require_once __DIR__ . '/partials/session_access_guard.php';

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
<title>Account Management - IMRMS</title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/account_management.css">
<link rel="stylesheet" href="../assets/css/loading.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .password-requirements {
        font-size: 12px;
        color: #64748b;
        margin-top: 5px;
        line-height: 1.4;
    }
    .requirement-met { color: #10b981 !important; }
    .requirement-not-met { color: #ef4444; }
    .password-strength {
        margin-top: 8px;
        font-size: 13px;
        font-weight: 500;
    }
</style>
</head>
<body>
<!-- [Same sidebar and HTML as before] -->
<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="requisition_management.php"><i class="fas fa-file-signature"></i> Requisition Management</a></li>
            <li><a href="requisition_status.php"><i class="fas fa-bars-progress"></i> Status</a></li>
            <li><a href="audit_trail.php"><i class="fas fa-shield-alt"></i> Audit Trail</a></li>
            <li><a href="my_profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            <li><a href="account_management.php" class="active"><i class="fas fa-users-cog"></i> Account Management</a></li>
            <li><a href="facility_management.php"><i class="fas fa-building"></i> Facility Management</a></li>
            <li><a href="item_management.php"><i class="fas fa-box"></i> Item Management</a></li>
            <li><a href="inventory_management.php"><i class="fas fa-cubes"></i> Inventory Management</a></li>
            <li><a href="supplier_management.php"><i class="fas fa-truck"></i> Supplier Management</a></li>
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
    </div>
</aside>

<main class="main-content account-management-container">
    <div class="page-header">
        <h1>Account Management</h1>
        <p>Manage users in the system. Add, edit, or remove users as needed.</p>
    </div>

    <div class="filter-section">
        <h3>Users List</h3>
        <div class="filter-controls">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by email, role, or office..." class="search-input">
            </div>
            <select id="sortDropdown" class="sort-dropdown">
                <option value="">Sort By</option>
                <option value="email-asc">Email (A-Z)</option>
                <option value="email-desc">Email (Z-A)</option>
                <option value="role-asc">Role (A-Z)</option>
                <option value="role-desc">Role (Z-A)</option>
                <option value="office-asc">Office (A-Z)</option>
                <option value="office-desc">Office (Z-A)</option>
                <option value="date-asc">Date (Oldest First)</option>
                <option value="date-desc">Date (Newest First)</option>
            </select>
            <button class="btn-filter" id="addUserBtn"><i class="fas fa-plus"></i> Add User</button>
        </div>
    </div>

    <div class="table-container">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Photo</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Office</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr>
                        <td colspan="8" style="text-align:center;padding:50px;color:#64748b;">Loading users...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <span class="close-modal" id="closeModal">&times;</span>
            <h2 id="modalTitle">Add User</h2>
            <form id="userForm" enctype="multipart/form-data">
                <input type="hidden" name="user_id" id="user_id">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" id="full_name" placeholder="e.g. Juan Dela Cruz">
                </div>
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" id="contact_number" placeholder="+63...">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="email" required>
                </div>
                <div class="form-group">
                    <label>Profile Photo</label>
                    <div class="photo-upload-row">
                        <div class="photo-preview">
                            <img id="photoPreview" alt="Preview" />
                            <div id="photoPlaceholder" class="photo-placeholder"><?php echo $initials; ?></div>
                        </div>
                        <input type="file" name="photo" id="photo" accept="image/*">
                    </div>
                    <small style="font-size:12px;color:#64748b;">JPEG, PNG, GIF, or WEBP. Max ~2MB recommended.</small>
                </div>
                <div class="form-group password-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password" placeholder="e.g. SecureP@ssw0rd2025" required>
                    <i class="fas fa-eye toggle-password"></i>
                </div>
                <div class="password-requirements" id="passwordRequirements">
                    <div><span id="length">• At least 8 characters</span></div>
                    <div><span id="uppercase">• One uppercase letter</span></div>
                    <div><span id="lowercase">• One lowercase letter</span></div>
                    <div><span id="number">• One number</span></div>
                    <div><span id="special">• One special char (@$!%*?&#-_.)</span></div>
                </div>
                <div class="password-strength" id="passwordStrength"></div>

                <div class="form-group password-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-type password" required>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="role" required>
                        <option value="">Select Role</option>
                        <option value="Inventory Manager">Inventory Manager</option>
                        <option value="Dean">Dean</option>
                        <option value="Laboratory Manager">Laboratory Manager</option>
                        <option value="Comptroller">Comptroller</option>
                        <option value="President">President</option>
                        <option value="GSD officer">GSD officer</option>
                        <option value="Employee">Employee</option>
                        <option value="User">User</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Office</label>
                    <select name="office_id" id="office_id">
                        <option value="">Loading offices...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Account Status</label>
                    <select name="account_status" id="account_status" required>
                        <option value="active">Active</option>
                        <option value="disabled">Disabled</option>
                        <option value="locked">Locked</option>
                    </select>
                </div>
                <button type="submit" class="btn-save" id="saveBtn">Save</button>
            </form>
        </div>
    </div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" style="display:none;">
    <i class="fas fa-bars"></i>
</button>

<div id="toastContainer"></div>

<!-- View user details modal -->
<div id="viewUserModal" class="view-user-modal" aria-hidden="true">
    <div class="view-user-backdrop" id="viewUserBackdrop"></div>
    <div class="view-user-card" role="dialog" aria-modal="true" aria-labelledby="viewUserTitle">
        <div class="view-user-header">
            <h3 id="viewUserTitle">User Account Details</h3>
            <button type="button" id="viewUserCloseBtn" class="view-user-close-btn">&times;</button>
        </div>
        <div class="view-user-grid">
            <div><span class="view-user-label">Full Name</span><p id="view_full_name">—</p></div>
            <div><span class="view-user-label">Email</span><p id="view_email">—</p></div>
            <div><span class="view-user-label">Role</span><p id="view_role">—</p></div>
            <div><span class="view-user-label">Status</span><p id="view_status">—</p></div>
            <div><span class="view-user-label">Office</span><p id="view_office">—</p></div>
            <div><span class="view-user-label">Consent</span><p id="view_consent">—</p></div>
            <div><span class="view-user-label">Last Login</span><p id="view_last_login">—</p></div>
            <div><span class="view-user-label">Created At</span><p id="view_created_at">—</p></div>
        </div>
    </div>
</div>

<!-- Delete user confirmation -->
<div id="deleteConfirmModal" class="delete-confirm-modal" aria-hidden="true">
    <div class="delete-confirm-backdrop" id="deleteConfirmBackdrop"></div>
    <div class="delete-confirm-card" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle" aria-describedby="deleteConfirmDesc">
        <div class="delete-confirm-icon" aria-hidden="true">
            <i class="fas fa-user-slash"></i>
        </div>
        <h3 id="deleteConfirmTitle" class="delete-confirm-title">Delete this user?</h3>
        <p id="deleteConfirmDesc" class="delete-confirm-desc">This will soft-delete the account (can be restored from database records if needed).</p>
        <p class="delete-confirm-email" id="deleteConfirmEmail" hidden></p>
        <div class="delete-confirm-actions">
            <button type="button" class="delete-confirm-btn delete-confirm-btn-cancel" id="deleteConfirmCancel">Cancel</button>
            <button type="button" class="delete-confirm-btn delete-confirm-btn-delete" id="deleteConfirmOk">
                <i class="fas fa-trash-alt"></i> Delete user
            </button>
        </div>
    </div>
</div>

<!-- Disable account confirmation (same pattern as delete confirmation) -->
<div id="disableConfirmModal" class="delete-confirm-modal" aria-hidden="true">
    <div class="delete-confirm-backdrop" id="disableConfirmBackdrop"></div>
    <div class="delete-confirm-card" role="dialog" aria-modal="true" aria-labelledby="disableConfirmTitle" aria-describedby="disableConfirmDesc">
        <div class="delete-confirm-icon" aria-hidden="true">
            <i class="fas fa-user-lock"></i>
        </div>
        <h3 id="disableConfirmTitle" class="delete-confirm-title">Disable this account?</h3>
        <p id="disableConfirmDesc" class="delete-confirm-desc">The user will not be able to sign in until the account is enabled again.</p>
        <p class="delete-confirm-email" id="disableConfirmEmail" hidden></p>
        <div class="delete-confirm-actions">
            <button type="button" class="delete-confirm-btn delete-confirm-btn-cancel" id="disableConfirmCancel">Cancel</button>
            <button type="button" class="delete-confirm-btn delete-confirm-btn-delete" id="disableConfirmOk">
                <i class="fas fa-user-lock"></i> Disable account
            </button>
        </div>
    </div>
</div>

<!-- Photo lightbox preview -->
<div id="photoLightbox" class="photo-lightbox">
    <div class="photo-lightbox-content">
        <button type="button" class="photo-lightbox-close">&times;</button>
        <img id="photoLightboxImg" class="photo-lightbox-img" alt="User photo preview">
        <div id="photoLightboxMeta" class="photo-lightbox-meta"></div>
    </div>
</div>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/account_management.js"></script>

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
</script>
</body>
</html>