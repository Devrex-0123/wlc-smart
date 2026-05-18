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

$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'Dean';
}
$initials = strtoupper(substr($user['Email'] ?? 'D', 0, 1));
$progressPageFrom = $_GET['from'] ?? '';
$rspProgressBackHref = ($progressPageFrom === 'status') ? 'dean_requisition_status.php' : 'dean_requisition_management.php';
$rspBackAriaLabel = ($progressPageFrom === 'status') ? 'Back to Status list' : 'Back to Requisition Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requisition progress - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/requisition_status_progress.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li><a href="dean_dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a href="dean_requisition_management.php"><i class="fas fa-file-signature"></i> <span>Requisition Management</span></a></li>
            <li><a href="dean_requisition_status.php" class="active"><i class="fas fa-bars-progress"></i> <span>Status</span></a></li>
            <li><a href="dean_inventory.php"><i class="fas fa-cubes"></i> <span>Inventory</span></a></li>
            <li><a href="dean_account_management.php"><i class="fas fa-users-cog"></i> <span>Account Management</span></a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <?php if (!empty($user['photo_url'])): ?>
                    <img src="../<?php echo htmlspecialchars($user['photo_url']); ?>" alt="Profile Photo" class="user-avatar-img">
                <?php else: ?>
                    <div class="user-avatar-initials"><?php echo $initials; ?></div>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($username); ?></h4>
                <p>Dean</p>
            </div>
        </div>
        <button id="logoutBtn" class="btn-logout-sidebar">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </div>
</aside>

<main class="main-content">
    <a href="<?php echo htmlspecialchars($rspProgressBackHref, ENT_QUOTES, 'UTF-8'); ?>" class="rsp-back rsp-back-upper-left" aria-label="<?php echo htmlspecialchars($rspBackAriaLabel, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-arrow-left"></i> Back</a>
    <div class="page-header management-header" style="margin-bottom: 1rem;">
        <div>
            <h1>Requisition progress</h1>
            <p>View-only workflow for your request (you cannot change status here).</p>
        </div>
    </div>
    <div id="rspRoot" data-readonly="1" data-dean-flow="1" data-back-href="<?php echo htmlspecialchars($rspProgressBackHref, ENT_QUOTES, 'UTF-8'); ?>" data-progress-from="<?php echo htmlspecialchars($progressPageFrom, ENT_QUOTES, 'UTF-8'); ?>"></div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/requisition_status_progress.js"></script>
</body>
</html>
