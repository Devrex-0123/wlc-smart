<?php
session_start();

require_once __DIR__ . '/partials/session_access_guard.php';

require_once __DIR__ . '/../../app/models/user.php';
require_once __DIR__ . '/../../app/classes/db.php';

$db = Database::connect();
$stmt = $db->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'User';
}
$initialSeed = $username !== '' ? $username : (string)($user['Email'] ?? 'U');
$initials = strtoupper(substr($initialSeed, 0, 1));

// Initial stats
$totalUsers = $db->query("SELECT COUNT(*) FROM user")->fetchColumn();
$totalLogs  = $db->query("SELECT COUNT(*) FROM log_history")->fetchColumn();
$activeSessions = $db->query("SELECT COUNT(*) FROM log_history WHERE time_out IS NULL OR time_out = '0000-00-00 00:00:00' OR time_out = time_in")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - IMRMS</title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/loading.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.card-value { font-variant-numeric: tabular-nums; }
.status-badge.active { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
.status-badge.inactive { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
</style>
</head>
<body>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a href="requisition_management.php" class="internal-link" data-notification-key="inventory_review"><i class="fas fa-file-signature"></i> <span>Requisition Management</span></a></li>
            <li><a href="requisition_status.php" class="internal-link" data-notification-key="requester_attention"><i class="fas fa-bars-progress"></i> <span>Status</span></a></li>
            <li><a href="audit_trail.php" class="internal-link"><i class="fas fa-shield-alt"></i> <span>Audit Trail</span></a></li>
            <li><a href="my_profile.php" class="internal-link"><i class="fas fa-user"></i><span>My Profile</span></a></li>
            <li><a href="account_management.php" class="internal-link"><i class="fas fa-users-cog"></i><span>Account Management</span></a></li>
            <li><a href="facility_management.php" class=""><i class="fas fa-building"></i> Facility Management</a></li>
            <li><a href="item_management.php" class=""><i class="fas fa-box"></i> Item Management</a></li>
            <li><a href="inventory_management.php" class=""><i class="fas fa-cubes"></i> Inventory Management</a></li>
            <li><a href="supplier_management.php" class=""><i class="fas fa-truck"></i> Supplier Management</a></li>
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
                <p><?php echo htmlspecialchars(ucfirst($user['role'])); ?></p>
            </div>
        </div>
    </div>
</aside>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Here's what's happening today.</p>
    </div>

    <?php require __DIR__ . '/partials/dashboard_overview_charts.php'; ?>

    <!-- Statistics Cards -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-header">
                <div><p class="card-title">Total Users</p><h3 class="card-value"><?php echo number_format($totalUsers); ?></h3></div>
                <div class="card-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="card-footer"><i class="fas fa-arrow-up"></i><span>All registered users</span></div>
        </div>
        <div class="dashboard-card">
            <div class="card-header">
                <div><p class="card-title">Total Logs</p><h3 class="card-value"><?php echo number_format($totalLogs); ?></h3></div>
                <div class="card-icon"><i class="fas fa-clipboard-list"></i></div>
            </div>
            <div class="card-footer"><i class="fas fa-chart-line"></i><span>All time records</span></div>
        </div>
        <div class="dashboard-card">
            <div class="card-header">
                <div><p class="card-title">Active Sessions</p><h3 class="card-value"><?php echo number_format($activeSessions); ?></h3></div>
                <div class="card-icon"><i class="fas fa-user-check"></i></div>
            </div>
            <div class="card-footer"><i class="fas fa-circle" style="color:#22c55e;"></i><span>Currently active</span></div>
        </div>
        <div class="dashboard-card">
            <div class="card-header">
                <div><p class="card-title">Your Role</p><h3 class="card-value" style="font-size:1.5rem;text-transform:capitalize;"><?php echo htmlspecialchars($user['role']); ?></h3></div>
                <div class="card-icon"><i class="fas fa-user-shield"></i></div>
            </div>
            <div class="card-footer"><i class="fas fa-info-circle"></i><span>Account permissions</span></div>
        </div>
    </div>

    
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>

<script>
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
mobileMenuBtn.addEventListener('click', e => { 
    e.stopPropagation(); 
    sidebar.classList.toggle('open'); 
});
document.addEventListener('click', e => {
    if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});

// -------- Sidebar Scroll Position Preservation --------
document.addEventListener('DOMContentLoaded', function() {
    const sidebarNav = document.querySelector('.sidebar-nav');
    const scrollPosKey = 'sidebarScrollPos';
    
    // Restore scroll position on page load
    const savedScrollPos = sessionStorage.getItem(scrollPosKey);
    if (savedScrollPos) {
        sidebarNav.scrollTop = parseInt(savedScrollPos);
    }
    
    // Save scroll position before navigation
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        link.addEventListener('click', function() {
            sessionStorage.setItem(scrollPosKey, sidebarNav.scrollTop);
        });
    });
});

</script>

<script src="../assets/js/logout.js?v=wlc1"></script>
</body>
</html>
