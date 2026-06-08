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
<link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
<link rel="stylesheet" href="../assets/css/account_management.css?v=wlc31">
<link rel="stylesheet" href="../assets/css/loading.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
<?php $imActivePage = 'account_management.php'; require __DIR__ . '/partials/inventory_manager_sidebar.php'; ?>

<main class="main-content account-management-container">
    <div class="module-page-header">
        <h1 class="module-page-header__title">Account Management</h1>
        <p class="module-page-header__subtitle">Manage user accounts, roles, permissions, and system access securely.</p>
    </div>

    <div class="users-list-card">
        <div class="filter-section account-management-filter-bar">
            <h2 class="users-list-card__heading">User List</h2>
            <div class="filter-controls">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search" class="search-input" aria-label="Search users">
                </div>
                <button class="btn-filter" id="addUserBtn" type="button"><i class="fas fa-plus"></i> Add User</button>
            </div>
        </div>

        <div class="users-tables-split" id="usersTablesSplit">
            <div class="users-table-panel users-table-panel--admin">
                <h3 class="users-table-panel__title users-table-panel__title--admin">Administrative Users</h3>
                <div class="table-container">
                    <div class="table-wrapper">
                        <table class="users-management-table users-management-table--admin">
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
                            <tbody id="adminUsersTableBody">
                                <tr>
                                    <td colspan="8" class="users-table-loading">Loading users...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="users-table-panel users-table-panel--dept">
                <h3 class="users-table-panel__title users-table-panel__title--dept">Department Heads</h3>
                <div class="table-container">
                    <div class="table-wrapper">
                        <table class="users-management-table users-management-table--dept">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Photo</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Office</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="deptUsersTableBody">
                                <tr>
                                    <td colspan="7" class="users-table-loading">Loading users...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add User</h2>
                <button type="button" class="close-modal" id="closeModal" aria-label="Close modal">&times;</button>
            </div>

            <form id="userForm" enctype="multipart/form-data" class="modal-form">
                <input type="hidden" name="user_id" id="user_id">

                <div class="profile-avatar-section">
                    <div class="avatar-preview-container">
                        <img id="photoPreview" alt="Preview" style="display:none;">
                        <div id="photoPlaceholder" class="photo-placeholder"><?php echo htmlspecialchars($initials); ?></div>
                    </div>
                    <div class="avatar-action-container">
                        <label for="photo" class="btn-upload-label">Choose Photo</label>
                        <input type="file" name="photo" id="photo" accept="image/*" class="file-input-hidden">
                        <small class="input-hint">JPEG, PNG, GIF, or WEBP. Max ~2MB.</small>
                    </div>
                </div>

                <hr class="form-divider">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" name="full_name" id="full_name" placeholder="e.g. Juan Dela Cruz" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="tel" name="contact_number" id="contact_number" placeholder="09XX XXX XXXX" inputmode="numeric" maxlength="11" pattern="\d{11}" title="Enter exactly 11 digits" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" id="email" required placeholder="name@domain.com">
                    </div>
                    <div class="form-group">
                        <label for="account_status">Account Status</label>
                        <select name="account_status" id="account_status" required>
                            <option value="active">Active</option>
                            <option value="disabled">Disabled</option>
                            <option value="locked">Locked</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="role">Assigned Role</label>
                        <select name="role" id="role" required>
                            <option value="">Select Role</option>
                            <option value="Dean">Dean</option>
                            <option value="Laboratory Manager">Laboratory Manager</option>
                            <option value="Comptroller">Comptroller</option>
                            <option value="President">President</option>
                            <option value="GSD officer">GSD officer</option>
                            <option value="Canvasser">Canvasser</option>
                            <option value="Employee">Employee</option>
                            <option value="User">User</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="office_id">Office / Department</label>
                        <select name="office_id" id="office_id" required>
                            <option value="">Select Office</option>
                        </select>
                    </div>
                </div>

                <hr class="form-divider">

                <h4 id="changePasswordHeading" class="change-password-heading" hidden>Change Password</h4>
                <p id="changePasswordHint" class="change-password-hint" hidden>Leave blank to keep current password</p>

                <div class="form-grid">
                    <div class="form-group password-group">
                        <label for="password">Password</label>
                        <div class="input-icon-wrapper">
                            <input type="password" name="password" id="password" placeholder="e.g. SecureP@ssw0rd2025" required>
                            <i class="fas fa-eye toggle-password" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="form-group password-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-icon-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-type password" required>
                            <i class="fas fa-eye toggle-password" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>

                <div class="password-feedback-container">
                    <div class="password-requirements" id="passwordRequirements">
                        <div id="length" class="requirement-item">• At least 8 characters</div>
                        <div id="uppercase" class="requirement-item">• One uppercase letter</div>
                        <div id="lowercase" class="requirement-item">• One lowercase letter</div>
                        <div id="number" class="requirement-item">• One number</div>
                        <div id="special" class="requirement-item">• One special char (@$!%*?&#-_.)</div>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                    <div class="password-match-status" id="passwordMatchStatus" aria-live="polite"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="cancelModalBtn">Cancel</button>
                    <button type="submit" class="btn-save" id="saveBtn">Save</button>
                </div>
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
            <div class="view-user-header-text">
                <i class="fas fa-user-circle" aria-hidden="true"></i>
                <h3 id="viewUserTitle">User Account Details</h3>
            </div>
            <button type="button" id="viewUserCloseBtn" class="view-user-close-btn" aria-label="Close">&times;</button>
        </div>

        <div class="view-user-hero">
            <div class="view-user-avatar">
                <img id="view_photo" class="view-user-avatar-img" alt="" hidden>
                <div id="view_photo_placeholder" class="view-user-avatar-placeholder">U</div>
            </div>
            <div class="view-user-hero-meta">
                <p id="view_hero_name" class="view-user-hero-name">—</p>
                <p id="view_hero_email" class="view-user-hero-email">—</p>
                <span id="view_hero_role" class="view-user-role-badge">—</span>
            </div>
        </div>

        <div class="view-user-body">
            <section class="view-user-section">
                <h4 class="view-user-section-title"><i class="fas fa-building" aria-hidden="true"></i> Organization</h4>
                <div class="view-user-grid">
                    <div class="view-user-field">
                        <span class="view-user-label"><i class="fas fa-phone" aria-hidden="true"></i> Contact</span>
                        <p id="view_contact_number" class="view-user-value">—</p>
                    </div>
                    <div class="view-user-field">
                        <span class="view-user-label"><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Office</span>
                        <p id="view_office" class="view-user-value">—</p>
                    </div>
                </div>
            </section>

            <section class="view-user-section">
                <h4 class="view-user-section-title"><i class="fas fa-shield-alt" aria-hidden="true"></i> Account &amp; Access</h4>
                <div class="view-user-grid">
                    <div class="view-user-field">
                        <span class="view-user-label"><i class="fas fa-toggle-on" aria-hidden="true"></i> Status</span>
                        <p id="view_status" class="view-user-value view-user-value--status">—</p>
                    </div>
                    <div class="view-user-field">
                        <span class="view-user-label"><i class="fas fa-file-signature" aria-hidden="true"></i> Consent</span>
                        <p id="view_consent" class="view-user-value">—</p>
                    </div>
                </div>
            </section>

            <section class="view-user-section">
                <h4 class="view-user-section-title"><i class="fas fa-clock" aria-hidden="true"></i> Activity</h4>
                <div class="view-user-grid">
                    <div class="view-user-field">
                        <span class="view-user-label"><i class="fas fa-right-to-bracket" aria-hidden="true"></i> Last Login</span>
                        <p id="view_last_login" class="view-user-value">—</p>
                    </div>
                    <div class="view-user-field">
                        <span class="view-user-label"><i class="fas fa-calendar-plus" aria-hidden="true"></i> Created At</span>
                        <p id="view_created_at" class="view-user-value">—</p>
                    </div>
                </div>
            </section>
        </div>

        <div class="view-user-footer">
            <button type="button" class="btn-cancel" id="viewUserCloseFooterBtn">Close</button>
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

<!-- Enable account confirmation -->
<div id="enableConfirmModal" class="delete-confirm-modal" aria-hidden="true">
    <div class="delete-confirm-backdrop" id="enableConfirmBackdrop"></div>
    <div class="delete-confirm-card" role="dialog" aria-modal="true" aria-labelledby="enableConfirmTitle" aria-describedby="enableConfirmDesc">
        <div class="delete-confirm-icon delete-confirm-icon--enable" aria-hidden="true">
            <i class="fas fa-user-check"></i>
        </div>
        <h3 id="enableConfirmTitle" class="delete-confirm-title">Enable this account?</h3>
        <p id="enableConfirmDesc" class="delete-confirm-desc">The user will be able to sign in again.</p>
        <p class="delete-confirm-email" id="enableConfirmEmail" hidden></p>
        <div class="delete-confirm-actions">
            <button type="button" class="delete-confirm-btn delete-confirm-btn-cancel" id="enableConfirmCancel">Cancel</button>
            <button type="button" class="delete-confirm-btn delete-confirm-btn-save" id="enableConfirmOk">
                <i class="fas fa-user-check"></i> Enable account
            </button>
        </div>
    </div>
</div>

<!-- Save edit confirmation -->
<div id="saveConfirmModal" class="delete-confirm-modal" aria-hidden="true">
    <div class="delete-confirm-backdrop" id="saveConfirmBackdrop"></div>
    <div class="delete-confirm-card" role="dialog" aria-modal="true" aria-labelledby="saveConfirmTitle" aria-describedby="saveConfirmDesc">
        <div class="delete-confirm-icon delete-confirm-icon--save" aria-hidden="true">
            <i class="fas fa-save"></i>
        </div>
        <h3 id="saveConfirmTitle" class="delete-confirm-title">Save changes?</h3>
        <p id="saveConfirmDesc" class="delete-confirm-desc">Are you sure you want to apply these updates?</p>
        <div class="delete-confirm-actions">
            <button type="button" class="delete-confirm-btn delete-confirm-btn-cancel" id="saveConfirmCancel">Cancel</button>
            <button type="button" class="delete-confirm-btn delete-confirm-btn-save" id="saveConfirmOk">
                <i class="fas fa-check"></i> Continue
            </button>
        </div>
    </div>
</div>

<!-- Success popup -->
<div id="accountSuccessModal" class="delete-confirm-modal" aria-hidden="true">
    <div class="delete-confirm-backdrop" id="accountSuccessBackdrop"></div>
    <div class="delete-confirm-card" role="dialog" aria-modal="true" aria-labelledby="accountSuccessTitle" aria-describedby="accountSuccessMessage">
        <div class="delete-confirm-icon delete-confirm-icon--success" aria-hidden="true">
            <i class="fas fa-check"></i>
        </div>
        <h3 id="accountSuccessTitle" class="delete-confirm-title">Success</h3>
        <p id="accountSuccessMessage" class="delete-confirm-desc"></p>
        <div class="delete-confirm-actions delete-confirm-actions--single">
            <button type="button" class="delete-confirm-btn delete-confirm-btn-save" id="accountSuccessOk">OK</button>
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
<script src="../assets/js/account_management.js?v=wlc31"></script>

<?php require __DIR__ . '/partials/inventory_manager_sidebar_scripts.php'; ?>
</body>
</html>