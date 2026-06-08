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

$role = strtolower(trim($user['role'] ?? ''));
$isComptroller = ($role === 'comptroller');
$isInventoryManager = ($role === 'inventory manager' || $role === 'inventory_manager');

if (!$isComptroller && !$isInventoryManager) {
    header('Location: ../../index.php');
    exit;
}

$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'User';
}
$initials = strtoupper(substr($user['Email'] ?? 'A', 0, 1));

$rspReadonly = '1';
$rspViewer = $isComptroller ? 'comptroller' : 'inventory';
$rspBackHref = $isComptroller ? 'comptroller_requests.php' : 'requisition_management.php';
$progressPageFrom = $_GET['from'] ?? '';
$rspProgressBackHref = ($progressPageFrom === 'status') ? 'requisition_status.php' : $rspBackHref;
$rspBackAriaLabel = ($progressPageFrom === 'status')
    ? 'Back to Status list'
    : ($isComptroller ? 'Back to Requisition Management' : 'Back to Requisition Management');

if ($isComptroller) {
    $comptrollerActive = 'requests';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requisition progress - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
    <link rel="stylesheet" href="../assets/css/requisition_status_progress.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php if ($isComptroller): ?>
    <?php require __DIR__ . '/partials/comptroller_sidebar.php'; ?>
<?php else: ?>
<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a href="requisition_management.php"><i class="fas fa-file-signature"></i> <span>Requisition Management</span></a></li>
            <li><a href="requisition_status.php" class="active" data-notification-key="requester_attention" data-notification-view-key="requester_attention"><i class="fas fa-bars-progress"></i> <span>Status</span></a></li>
            <li><a href="audit_trail.php"><i class="fas fa-shield-alt"></i> <span>Audit Trail</span></a></li>
            <li><a href="account_management.php"><i class="fas fa-users-cog"></i> <span>Account Management</span></a></li>
            <li><a href="facility_management.php"><i class="fas fa-building"></i> <span>Facility Management</span></a></li>
            <li><a href="item_management.php"><i class="fas fa-box"></i> <span>Item Management</span></a></li>
            <li><a href="inventory_management.php"><i class="fas fa-cubes"></i> <span>Inventory Management</span></a></li>
            <li><a href="supplier_management.php"><i class="fas fa-truck"></i> <span>Supplier Management</span></a></li>
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
                <p><?php echo htmlspecialchars($user['role'] ?? ''); ?></p>
            </div>
        </div>
        <button id="logoutBtn" class="btn-logout-sidebar">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </div>
</aside>
<?php endif; ?>

<main class="main-content">
    <a href="<?php echo htmlspecialchars($rspProgressBackHref, ENT_QUOTES, 'UTF-8'); ?>" class="rsp-back rsp-back-upper-left" aria-label="<?php echo htmlspecialchars($rspBackAriaLabel, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-arrow-left"></i> Back</a>
    <div class="page-header management-header" style="margin-bottom: 1rem;">
        <div>
            <h1>Requisition progress</h1>
            <p>Workflow overview for this request. Open forms below to review canvass, purchase requisition, or purchase order.</p>
        </div>
    </div>
    <div id="rspRoot" data-readonly="<?php echo htmlspecialchars($rspReadonly, ENT_QUOTES, 'UTF-8'); ?>" data-viewer="<?php echo htmlspecialchars($rspViewer, ENT_QUOTES, 'UTF-8'); ?>" data-back-href="<?php echo htmlspecialchars($rspProgressBackHref, ENT_QUOTES, 'UTF-8'); ?>" data-progress-from="<?php echo htmlspecialchars($progressPageFrom, ENT_QUOTES, 'UTF-8'); ?>"></div>
</main>

<button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu"><i class="fas fa-bars"></i></button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<?php if ($isComptroller): ?>
<script src="../assets/js/comptroller_shell.js"></script>
<?php endif; ?>
<script src="../assets/js/requisition_status_progress.js"></script>
</body>
</html>
