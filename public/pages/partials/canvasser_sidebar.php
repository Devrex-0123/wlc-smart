<?php
/**
 * Canvasser workspace sidebar.
 * Expects: $user, $username, $initials, $cvActive — dashboard | request | audit
 */
$cvActive = $cvActive ?? '';
$roleLabel = 'Canvasser';
?>
<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li>
                <a href="canvasser_dashboard.php" class="<?php echo $cvActive === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="canvasser_request.php" class="<?php echo $cvActive === 'request' ? 'active' : ''; ?>" data-notification-key="canvasser_assigned">
                    <i class="fas fa-file-signature"></i> <span>Request</span>
                </a>
            </li>
            <li>
                <a href="audit_trail.php" class="<?php echo $cvActive === 'audit' ? 'active' : ''; ?>">
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
                <p><?php echo htmlspecialchars($roleLabel); ?></p>
            </div>
        </div>
    </div>
</aside>
