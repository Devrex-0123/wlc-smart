<?php
/**
 * Comptroller area sidebar. Expects: $user (array), optional $username (string), $initials (string),
 * $comptrollerActive — one of: dashboard | requests | audit
 */
$comptrollerActive = $comptrollerActive ?? '';
$sidebarUser = $user ?? [];
if (!isset($username) || !is_string($username)) {
    $username = trim((string)($sidebarUser['full_name'] ?? ''));
    if ($username === '') {
        $username = explode('@', (string)($sidebarUser['Email'] ?? ''))[0] ?? 'User';
    }
}
if (!isset($initials) || !is_string($initials) || $initials === '') {
    $initialSeed = $username !== '' ? $username : (string)($sidebarUser['Email'] ?? 'C');
    $initials = strtoupper(substr($initialSeed, 0, 1));
}
$roleLabel = htmlspecialchars(ucfirst(trim((string) ($sidebarUser['role'] ?? 'Comptroller'))));
?>
<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li>
                <a href="comptroller_dashboard.php" class="<?php echo $comptrollerActive === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="comptroller_requests.php" class="<?php echo $comptrollerActive === 'requests' ? 'active' : ''; ?>" data-notification-key="comptroller_pending">
                    <i class="fas fa-file-signature"></i> <span>Requisition Management</span>
                </a>
            </li>
            <li>
                <a href="audit_trail.php" class="<?php echo $comptrollerActive === 'audit' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i> <span>Audit Trail</span>
                </a>
            </li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <?php if (!empty($sidebarUser['photo_url'])): ?>
                    <img src="../<?php echo htmlspecialchars($sidebarUser['photo_url']); ?>" alt="Profile Photo" class="user-avatar-img">
                <?php else: ?>
                    <div class="user-avatar-initials"><?php echo htmlspecialchars($initials); ?></div>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($username); ?></h4>
                <p><?php echo $roleLabel; ?></p>
            </div>
        </div>
    </div>
</aside>
