<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';

$db = Database::connect();
$stmt = $db->prepare('SELECT * FROM user WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

require __DIR__ . '/partials/gsd_guard.php';

$gsdOfficeId = $user['office_id'] ?? null;
if (!$gsdOfficeId) {
    echo 'GSD officer is not assigned to any office.';
    exit;
}

$stmt = $db->prepare('SELECT `office_name` FROM offices WHERE office_id = ?');
$stmt->execute([$gsdOfficeId]);
$dept = $stmt->fetch(PDO::FETCH_ASSOC);
$deptName = $dept['office_name'] ?? 'Unknown Office';

$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'User';
}
$initials = strtoupper(substr($user['Email'] ?? 'G', 0, 1));
$gsdActive = 'accounts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management - GSD - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/gsd.css">
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
<?php require __DIR__ . '/partials/gsd_sidebar.php'; ?>

<main class="main-content gsd-main dean-account-management-container">
    <div class="page-header">
        <div class="gsd-kicker"><i class="fas fa-users-cog"></i> GSD workspace</div>
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
            <button class="btn-filter" id="addUserBtn" type="button"><i class="fas fa-plus"></i> Add User</button>
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

    <div class="modal" id="userModal">
        <div class="modal-content">
            <span class="close-modal" id="closeModal">&times;</span>
            <h2 id="modalTitle">Add User</h2>
            <form id="userForm" enctype="multipart/form-data">
                <input type="hidden" name="user_id" id="user_id">
                <input type="hidden" name="office_id" id="office_id" value="<?php echo htmlspecialchars((string) $gsdOfficeId); ?>">

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="email" required>
                </div>

                <div class="form-group">
                    <label>Profile Photo</label>
                    <div class="photo-upload-row">
                        <div class="photo-preview">
                            <img id="photoPreview" alt="Preview" />
                            <div id="photoPlaceholder" class="photo-placeholder"><?php echo htmlspecialchars($initials); ?></div>
                        </div>
                        <input type="file" name="photo" id="photo" accept="image/*">
                    </div>
                    <small style="font-size:12px;color:#64748b;">JPEG, PNG, GIF, or WEBP. Max ~2MB recommended.</small>
                </div>

                <div class="form-group password-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password" placeholder="e.g. SecureP@ssw0rd2026" required>
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

    <div id="toastContainer" class="toast-container"></div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Menu"><i class="fas fa-bars"></i></button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/gsd_shell.js"></script>
<script src="../assets/js/gsd_account_management.js"></script>
</body>
</html>
