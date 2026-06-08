<?php
session_start();
require_once __DIR__ . '/partials/session_access_guard.php';

require_once __DIR__ . '/../../app/classes/db.php';

$db = Database::connect();
$stmt = $db->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'Dean';
}
$initials = strtoupper(substr($user['Email'], 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dean Dashboard - IMRMS</title>
<link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
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

<!-- Sidebar - EXACT same as your dashboard.php -->
<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li><a href="dean_dashboard.php" class="active"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li><a href="dean_requisition_management.php" data-notification-key="inventory_review"><i class="fas fa-file-signature"></i> <span>Requisition Management</span></a></li>
            <li><a href="dean_requisition_status.php" data-notification-key="requester_attention"><i class="fas fa-bars-progress"></i> <span>Status</span></a></li>
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
    </div>
</aside>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
        <div>
            <h1>Dean Dashboard</h1>
            <p>Welcome back, Dean <?php echo htmlspecialchars($username); ?>! Here's your office overview.</p>
        </div>
        <button id="requestItemBtn" class="btn-add" style="padding: 0.65rem 1rem; font-size: 1rem; border: none; border-radius: 6px; background-color: #16a34a; color: #fff; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.15);" onclick="window.location.href='dean_requisition_form.php?from=dashboard';">
            <i class="fas fa-plus" style="margin-right: 0.35rem;"></i> Request Item
        </button>
    </div>

    <?php require __DIR__ . '/partials/dashboard_overview_charts.php'; ?>

    <!-- Statistics Cards - Hardcoded Beautiful Numbers -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <p class="card-title">Pending Requests</p>
                    <h3 class="card-value">18</h3>
                </div>
                <div class="card-icon"><i class="fas fa-clock"></i></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i>
                <span>Requires your approval</span>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <p class="card-title">Total Inventory Items</p>
                    <h3 class="card-value">342</h3>
                </div>
                <div class="card-icon"><i class="fas fa-boxes"></i></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-layer-group"></i>
                <span>Available in your office</span>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <p class="card-title">Active Borrowers</p>
                    <h3 class="card-value">47</h3>
                </div>
                <div class="card-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-user-check" style="color:#22c55e;"></i>
                <span>Currently using items</span>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <p class="card-title">Your Authority</p>
                    <h3 class="card-value" style="font-size:1.5rem;text-transform:capitalize;">Dean</h3>
                </div>
                <div class="card-icon"><i class="fas fa-crown"></i></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-shield-alt" style="color:#8b5cf6;"></i>
                <span>Full office control</span>
            </div>
        </div>
    </div>

    <!-- Optional: Recent Activity (Hardcoded Preview) -->
    <div class="table-container" style="margin-top: 2rem;">
        <div class="table-header">
            <h2>Recent Requests</h2>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Item</th>
                        <th>Requested</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>j.doe</td>
                        <td>MacBook Pro 16"</td>
                        <td>2 hours ago</td>
                        <td><span class="status-badge active">Pending</span></td>
                    </tr>
                    <tr>
                        <td>m.santos</td>
                        <td>Projector HD</td>
                        <td>5 hours ago</td>
                        <td><span class="status-badge active">Pending</span></td>
                    </tr>
                    <tr>
                        <td>a.reyes</td>
                        <td>Microphone Set</td>
                        <td>1 day ago</td>
                        <td><span class="status-badge" style="background:#dbeafe;color:#1d4ed8;">Approved</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>

<!-- Toast Container -->
<div id="toastContainer"></div>

<!-- Mobile Menu Script (Same as your dashboard.php) -->
<script>
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');

mobileMenuBtn.addEventListener('click', e => { 
    e.stopPropagation(); 
    sidebar.classList.toggle('open'); 
});

document.addEventListener('click', e => {
    if (window.innerWidth <= 768 && 
        sidebar.classList.contains('open') && 
        !sidebar.contains(e.target) && 
        !mobileMenuBtn.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});
</script>

<!-- Logout Script -->
<script src="../assets/js/logout.js?v=wlc1"></script>
</body>
</html>