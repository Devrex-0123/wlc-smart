<?php
/**
 * Unified dean sidebar (nav sections, footer, logout).
 *
 * Optional: $deanActivePage — current script filename (e.g. 'dean_dashboard.php').
 *           Auto-detected from SCRIPT_NAME when omitted.
 * Requires: $user, $currentUser, or $sidebarUser (row with full_name, Email, role, photo_url).
 */
$deanUser = $user ?? $currentUser ?? $sidebarUser ?? [];
$deanActivePage = isset($deanActivePage) && is_string($deanActivePage) && $deanActivePage !== ''
    ? $deanActivePage
    : basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

if ($deanActivePage === 'dean_requisition_status_progress.php' || $deanActivePage === 'dean_requisition_status.php') {
    $deanActivePage = isset($_GET['from']) && $_GET['from'] === 'workflow'
        ? 'dean_approval_workflow.php'
        : 'dean_requisition_management.php';
}

if ($deanActivePage === 'department_requisition_status_progress.php') {
    $deanActivePage = isset($_GET['from']) && $_GET['from'] === 'workflow'
        ? 'department_approval_workflow.php'
        : 'department_requisition_management.php';
}

if ($deanActivePage === 'department_requisition_form.php') {
    $deanActivePage = 'department_requisition_management.php';
}

$isDepartmentSidebar = isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'department';

if ($isDepartmentSidebar) {
    $deanFooterName = strtoupper(trim((string)($_SESSION['department_abbreviation'] ?? ($deanUser['department_abbreviation'] ?? ''))));
    if ($deanFooterName === '') {
        $deanFooterName = 'DEPT';
    }
    $deanInitialSeed = $deanFooterName;
} else {
    $deanRoleNorm = strtolower(trim((string)($deanUser['role'] ?? '')));
    if ($deanRoleNorm === 'user') {
        $deanFooterName = strtoupper(trim((string)($deanUser['abbreviation'] ?? '')));
        if ($deanFooterName === '') {
            $deanFooterName = trim((string)($deanUser['full_name'] ?? ''));
        }
    } else {
        $deanFooterName = trim((string)($deanUser['full_name'] ?? ''));
    }
    if ($deanFooterName === '') {
        $deanFooterName = explode('@', (string)($deanUser['Email'] ?? ''))[0] ?? 'Dean';
    }
    $deanInitialSeed = $deanFooterName !== '' ? $deanFooterName : (string)($deanUser['Email'] ?? 'D');
}
$deanInitials = strtoupper(substr($deanInitialSeed, 0, 1));
$deanRoleLabel = ucfirst((string)($deanUser['role'] ?? 'Dean'));

$deanIsActive = static function (string $page) use ($deanActivePage): string {
    return $page === $deanActivePage ? ' active' : '';
};
?>
<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li class="sidebar-section">Overview</li>
            <li class="nav-item-gap">
                <?php if ($isDepartmentSidebar): ?>
                <a href="department_dashboard.php" class="internal-link<?php echo $deanIsActive('department_dashboard.php'); ?>">
                <?php else: ?>
                <a href="dean_dashboard.php" class="internal-link<?php echo $deanIsActive('dean_dashboard.php'); ?>">
                <?php endif; ?>
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
            </li>

            <?php if ($isDepartmentSidebar): ?>
            <li class="sidebar-section">Requisition Management</li>
            <li>
                <a href="department_requisition_management.php" class="internal-link<?php echo $deanIsActive('department_requisition_management.php'); ?>">
                    <i class="fas fa-file-signature"></i> <span>Requisition Management</span>
                </a>
            </li>
            <li>
                <a href="department_approval_workflow.php" class="internal-link<?php echo $deanIsActive('department_approval_workflow.php'); ?>">
                    <i class="fas fa-sitemap"></i> <span>Approval Workflow</span>
                </a>
            </li>
            <?php else: ?>
            <li class="sidebar-section">Requisition Management</li>
            <li>
                <a href="dean_requisition_management.php" class="internal-link<?php echo $deanIsActive('dean_requisition_management.php'); ?>" data-notification-key="inventory_review" data-notification-view-key="inventory_review">
                    <i class="fas fa-file-signature"></i> <span>Requisition Management</span>
                </a>
            </li>
            <li>
                <a href="dean_approval_workflow.php" class="internal-link<?php echo $deanIsActive('dean_approval_workflow.php'); ?>">
                    <i class="fas fa-sitemap"></i> <span>Approval Workflow</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="sidebar-section">Inventory</li>
            <li class="nav-gap-sm">
                <?php if ($isDepartmentSidebar): ?>
                <a href="department_inventory.php" class="internal-link<?php echo $deanIsActive('department_inventory.php'); ?>">
                <?php else: ?>
                <a href="dean_inventory.php" class="internal-link<?php echo $deanIsActive('dean_inventory.php'); ?>">
                <?php endif; ?>
                    <i class="fas fa-cubes"></i> <span>Inventory</span>
                </a>
            </li>

            <li class="sidebar-section">Account</li>
            <li>
                <?php if ($isDepartmentSidebar): ?>
                <a href="department_account_management.php" class="internal-link<?php echo $deanIsActive('department_account_management.php'); ?>">
                <?php else: ?>
                <a href="dean_account_management.php" class="internal-link<?php echo $deanIsActive('dean_account_management.php'); ?>">
                <?php endif; ?>
                    <i class="fas fa-users-cog"></i> <span>Account Management</span>
                </a>
            </li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <?php if (!empty($deanUser['photo_url'])): ?>
                    <img src="../<?php echo htmlspecialchars((string)$deanUser['photo_url']); ?>" alt="Profile Photo" class="user-avatar-img">
                <?php else: ?>
                    <div class="user-avatar-initials"><?php echo htmlspecialchars($deanInitials); ?></div>
                <?php endif; ?>
                <span class="status-dot" title="Online" aria-label="Online"></span>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($deanFooterName); ?></h4>
                <p class="user-role"><?php echo htmlspecialchars($deanRoleLabel); ?></p>
            </div>
        </div>
        <button type="button" id="logoutBtn" class="btn-logout-sidebar">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </div>
</aside>
<?php if ($isDepartmentSidebar): ?>
<script>document.body.dataset.loginType = 'department';</script>
<?php endif; ?>
