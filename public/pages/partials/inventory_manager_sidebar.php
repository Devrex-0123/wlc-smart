<?php
/**
 * Unified inventory manager sidebar (nav sections, footer, logout).
 *
 * Optional: $imActivePage — current script filename (e.g. 'dashboard.php').
 *           Auto-detected from SCRIPT_NAME when omitted.
 * Requires: $user or $sidebarUser (user row with full_name, Email, role, photo_url).
 */
$imUser = $user ?? $sidebarUser ?? [];
$imActivePage = isset($imActivePage) && is_string($imActivePage) && $imActivePage !== ''
    ? $imActivePage
    : basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

$imDisplayName = trim((string)($imUser['full_name'] ?? ''));
if ($imDisplayName === '') {
    $imDisplayName = explode('@', (string)($imUser['Email'] ?? ''))[0] ?? 'User';
}
$imInitialSeed = $imDisplayName !== '' ? $imDisplayName : (string)($imUser['Email'] ?? 'U');
$imInitials = strtoupper(substr($imInitialSeed, 0, 1));
$imRoleLabel = ucfirst((string)($imUser['role'] ?? 'User'));

$imIsActive = static function (string $page) use ($imActivePage): string {
    return $page === $imActivePage ? ' active' : '';
};
?>
<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li class="sidebar-section">Overview</li>
            <li>
                <a href="dashboard.php" class="internal-link<?php echo $imIsActive('dashboard.php'); ?>">
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
            </li>

            <li class="sidebar-section">Requisition Management</li>
            <li>
                <a href="requisition_management.php" class="internal-link<?php echo $imIsActive('requisition_management.php'); ?>" data-notification-key="inventory_review">
                    <i class="fas fa-file-signature"></i> <span>Requisition Management</span>
                </a>
            </li>
            <li>
                <a href="approval_workflow.php" class="internal-link<?php echo $imIsActive('approval_workflow.php'); ?>">
                    <i class="fas fa-sitemap"></i> <span>Approval Workflow</span>
                </a>
            </li>

            <li class="sidebar-section">Inventory</li>
            <li>
                <a href="item_management.php" class="internal-link<?php echo $imIsActive('item_management.php'); ?>">
                    <i class="fas fa-box"></i> <span>Item Management</span>
                </a>
            </li>
            <li>
                <a href="inventory_management.php" class="internal-link<?php echo $imIsActive('inventory_management.php'); ?>">
                    <i class="fas fa-cubes"></i> <span>Inventory Management</span>
                </a>
            </li>
            <li>
                <a href="supplier_management.php" class="internal-link<?php echo $imIsActive('supplier_management.php'); ?>">
                    <i class="fas fa-truck"></i> <span>Supplier Management</span>
                </a>
            </li>
            <li>
                <a href="facility_management.php" class="internal-link<?php echo $imIsActive('facility_management.php'); ?>">
                    <i class="fas fa-building"></i> <span>Facility Management</span>
                </a>
            </li>

            <li class="sidebar-section">Monitoring</li>
            <li>
                <a href="audit_trail.php" class="internal-link<?php echo $imIsActive('audit_trail.php'); ?>">
                    <i class="fas fa-shield-alt"></i> <span>Audit Trail</span>
                </a>
            </li>
            <li>
                <a href="account_management.php" class="internal-link<?php echo $imIsActive('account_management.php'); ?>">
                    <i class="fas fa-users-cog"></i> <span>Account Management</span>
                </a>
            </li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <?php if (!empty($imUser['photo_url'])): ?>
                    <img src="../<?php echo htmlspecialchars((string)$imUser['photo_url']); ?>" alt="Profile Photo" class="user-avatar-img">
                <?php else: ?>
                    <div class="user-avatar-initials"><?php echo htmlspecialchars($imInitials); ?></div>
                <?php endif; ?>
                <span class="status-dot" title="Online" aria-label="Online"></span>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($imDisplayName); ?></h4>
                <p class="user-role"><?php echo htmlspecialchars($imRoleLabel); ?></p>
            </div>
        </div>
        <button type="button" id="logoutBtn" class="btn-logout-sidebar">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </div>
</aside>
