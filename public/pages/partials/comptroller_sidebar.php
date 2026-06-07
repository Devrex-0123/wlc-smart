<?php
/**
 * Comptroller area sidebar. Expects: $user (array), $username (string), $initials (string),
 * $comptrollerActive — one of: dashboard | requests | audit
 */
$comptrollerActive = $comptrollerActive ?? '';
$roleLabel = htmlspecialchars(ucfirst(trim((string) ($user['role'] ?? 'Comptroller'))));
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
                <?php if (!empty($user['photo_url'])): ?>
                    <img src="../<?php echo htmlspecialchars($user['photo_url']); ?>" alt="Profile Photo" class="user-avatar-img">
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
