<?php
/**
 * President verifier workspace sidebar.
 * Expects: $user, $username, $initials, $pvActive — dashboard | request | accounts | audit
 */
$pvActive = $pvActive ?? '';
$roleLabel = 'President verifier';
?>
<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li>
                <a href="president_dashboard.php" class="<?php echo $pvActive === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="president_request.php" class="<?php echo $pvActive === 'request' ? 'active' : ''; ?>" data-notification-key="president_pending">
                    <i class="fas fa-file-signature"></i> <span>Request</span>
                </a>
            </li>
            <li>
                <a href="president_account_management.php" class="<?php echo $pvActive === 'accounts' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i> <span>Account Management</span>
                </a>
            </li>
            <li>
                <a href="audit_trail.php" class="<?php echo $pvActive === 'audit' ? 'active' : ''; ?>">
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
