<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';

$db = Database::connect();

// Get current user (Dean)
$stmt = $db->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user is a Dean
if (strtolower($currentUser['role']) !== 'dean') {
    header("Location: dashboard.php");
    exit;
}

// Get dean's office
$deanOfficeId = $currentUser['office_id'];

if (!$deanOfficeId) {
    echo "Dean is not assigned to any office.";
    exit;
}

// Get office name
$stmt = $db->prepare("SELECT `office_name` FROM offices WHERE office_id = ?");
$stmt->execute([$deanOfficeId]);
$dept = $stmt->fetch(PDO::FETCH_ASSOC);
$deptName = $dept['office_name'] ?? 'Unknown Office';

$initials = strtoupper(substr($currentUser['Email'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Management - Dean Dashboard</title>
<link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
<link rel="stylesheet" href="../assets/css/dean_account_management.css">
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

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li><a href="dean_dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a href="dean_requisition_management.php"><i class="fas fa-file-signature"></i> <span>Requisition Management</span></a></li>
            <li><a href="dean_requisition_status.php"><i class="fas fa-bars-progress"></i> <span>Status</span></a></li>
            <li><a href="dean_inventory.php"><i class="fas fa-cubes"></i> <span>Inventory</span></a></li>
            <li><a href="dean_account_management.php" class="active"><i class="fas fa-users-cog"></i> <span>Account Management</span></a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <?php if (!empty($currentUser['photo_url'])): ?>
                    <img src="../<?php echo htmlspecialchars($currentUser['photo_url']); ?>" alt="Profile Photo" class="user-avatar-img">
                <?php else: ?>
                    <div class="user-avatar-initials"><?php echo $initials; ?></div>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars((string)($currentUser['full_name'] ?? '') !== '' ? (string)$currentUser['full_name'] : explode('@', (string)$currentUser['Email'])[0]); ?></h4>
                <p><?php echo htmlspecialchars($currentUser['role']); ?></p>
            </div>
        </div>
        <button id="logoutBtn" class="btn-logout-sidebar">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </div>
</aside>

<!-- Main Content -->
<main class="main-content dean-account-management-container">
    <div class="page-header">
        <h1>Account Management - <?php echo htmlspecialchars($deptName); ?></h1>
        <p>Manage users in your office. Add, edit, or remove office members as needed.</p>
    </div>

    <div class="filter-section">
        <h3>Office Users</h3>
        <div class="filter-controls">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by email or role..." class="search-input">
            </div>
            <select id="sortDropdown" class="sort-dropdown">
                <option value="">Sort By</option>
                <option value="email-asc">Email (A-Z)</option>
                <option value="email-desc">Email (Z-A)</option>
                <option value="role-asc">Role (A-Z)</option>
                <option value="role-desc">Role (Z-A)</option>
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
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr>
                        <td colspan="6" style="text-align:center;padding:50px;color:#64748b;">Loading users...</td>
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
                <input type="hidden" name="office_id" id="office_id" value="<?php echo htmlspecialchars($deanOfficeId); ?>">
                
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
                        <option value="Laboratory Manager">Laboratory Manager</option>
                        <option value="Employee">Employee</option>
                        <option value="User">User</option>
                    </select>
                    <small style="font-size:12px;color:#64748b;margin-top:0.5rem;">You can only assign these roles to office users.</small>
                </div>
                
                <button type="submit" class="btn-save" id="saveBtn">Save</button>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" style="display:none;">
    <i class="fas fa-bars"></i>
</button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/dean_account_management.js"></script>
</body>
</html>
