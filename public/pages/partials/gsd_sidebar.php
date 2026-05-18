<?php
/**
 * GSD area sidebar. Expects: $user, $username, $initials,
 * $gsdActive — one of: dashboard | request | accounts | audit
 */
$gsdActive = $gsdActive ?? '';
$roleLabel = htmlspecialchars(ucfirst(trim((string) ($user['role'] ?? 'GSD officer'))));
?>
<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li>
                <a href="gsd_dashboard.php" class="<?php echo $gsdActive === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="gsd_request.php" class="<?php echo $gsdActive === 'request' ? 'active' : ''; ?>">
                    <i class="fas fa-file-signature"></i> <span>Request</span>
                </a>
            </li>
            <li>
                <a href="gsd_account_management.php" class="<?php echo $gsdActive === 'accounts' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i> <span>Account Management</span>
                </a>
            </li>
            <li>
                <a href="audit_trail.php" class="<?php echo $gsdActive === 'audit' ? 'active' : ''; ?>">
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
