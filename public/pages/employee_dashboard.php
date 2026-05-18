<?php
session_start();
require_once __DIR__ . '/partials/session_access_guard.php';

// Check if user is logged in and is an employee
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header("Location: ../../index.php");
    exit;
}

// Redirect non-employees to appropriate dashboard
$role = strtolower(trim($_SESSION['user_role']));
if ($role === 'dean') {
    header("Location: dean_dashboard.php");
    exit;
} elseif ($role !== 'employee' && $role !== 'user' && $role !== 'laboratory manager') {
    header("Location: dashboard.php");
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';
$db = Database::connect();
$stmt = $db->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$initials = strtoupper(substr($user['Email'], 0, 1));
$userName = trim((string)($user['full_name'] ?? ''));
if ($userName === '') {
    $userName = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'Employee';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/employee_dashboard.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<aside class="sidebar" id="sidebar">
    <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
    <nav>
        <ul class="sidebar-nav">
            <li><a href="employee_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="audit_trail.php"><i class="fas fa-shield-alt"></i> Audit Trail</a></li>
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
                <h4><?php echo htmlspecialchars($userName); ?></h4>
                <p><?php echo htmlspecialchars($user['role']); ?></p>
            </div>
        </div>
    </div>
</aside>

<main class="main-content">
    <div class="page-header">
        <h1>Welcome, <?php echo htmlspecialchars($userName); ?>!</h1>
        <p>Manage and track your assigned inventory items.</p>
    </div>

    <?php require __DIR__ . '/partials/dashboard_overview_charts.php'; ?>

    <!-- Statistics Cards -->
    <div class="cards-grid">
        <div class="card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-cube"></i>
                </div>
                <h3 class="card-title">Assigned Items</h3>
            </div>
            <div class="card-value" id="assignedCount">0</div>
            <p class="card-description">Total inventory items assigned to you</p>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="card-title">Good Condition</h3>
            </div>
            <div class="card-value" id="goodCount">0</div>
            <p class="card-description">Items in good condition</p>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 class="card-title">Fair Condition</h3>
            </div>
            <div class="card-value" id="fairCount">0</div>
            <p class="card-description">Items that need attention</p>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3 class="card-title">Poor Condition</h3>
            </div>
            <div class="card-value" id="poorCount">0</div>
            <p class="card-description">Items needing repair/replacement</p>
        </div>
    </div>

    <!-- Inventory List -->
    <div class="inventory-section">
        <div class="section-header">
            <h2>My Assigned Inventory</h2>
        </div>

        <div class="table-container">
            <table class="table-wrapper">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Item Code</th>
                        <th>Facility</th>
                        <th>Quantity</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="inventoryTableBody">
                    <tr>
                        <td colspan="8" style="text-align:center;padding:50px;color:#64748b;">Loading inventory...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal" id="detailModal" style="display: none;">
        <div class="modal-content" style="max-height: 90vh; overflow-y: auto;">
            <span class="close-modal" id="closeDetailModal" style="cursor: pointer; font-size: 2rem; position: absolute; right: 1rem; top: 1rem;">&times;</span>
            <h2>Inventory Details</h2>
            
            <div class="detail-section">
                <div class="detail-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
                    <div class="detail-info">
                        <h3 id="detailName" style="margin: 0 0 0.5rem 0;">Item Name</h3>
                        <p id="detailLocation" style="margin: 0; color: #64748b; font-size: 0.9rem;">Location</p>
                    </div>
                    <div id="detailPhotoContainer" class="detail-photo">
                        <img id="detailPhoto" alt="Item Photo" style="width: 120px; height: 120px; border-radius: 8px; object-fit: cover; display: none;" />
                        <div id="detailPhotoPlaceholder" style="width: 120px; height: 120px; border-radius: 8px; background: linear-gradient(135deg, #3b82f6, #60a5fa); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">IMG</div>
                    </div>
                </div>

                <div class="detail-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="detail-item">
                        <span class="detail-label" style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Item Code</span>
                        <span class="detail-value" id="detailItemCode" style="display: block; margin-top: 0.5rem; color: #1e293b; font-weight: 500;">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label" style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Item Type</span>
                        <span class="detail-value" id="detailItemType" style="display: block; margin-top: 0.5rem; color: #1e293b; font-weight: 500;">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label" style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Quantity</span>
                        <span class="detail-value" id="detailQuantity" style="display: block; margin-top: 0.5rem; color: #1e293b; font-weight: 500;">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label" style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Condition</span>
                        <span class="detail-value" id="detailCondition" style="display: block; margin-top: 0.5rem; color: #1e293b; font-weight: 500;">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label" style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Status</span>
                        <span class="detail-value" id="detailStatus" style="display: block; margin-top: 0.5rem; color: #1e293b; font-weight: 500;">-</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label" style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Acquisition Date</span>
                        <span class="detail-value" id="detailDate" style="display: block; margin-top: 0.5rem; color: #1e293b; font-weight: 500;">-</span>
                    </div>
                </div>

                <div class="detail-remarks">
                    <span class="detail-label" style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Remarks</span>
                    <p id="detailRemarks" style="margin: 0.5rem 0 0 0; color: #475569; font-size: 0.9rem;">-</p>
                </div>
            </div>

            <!-- Components Display -->
            <div style="margin-top: 2rem;">
                <h3 style="margin: 0 0 1.5rem 0;">Components</h3>
                <div id="detailComponentsList" style="display: grid; gap: 1.5rem;">
                    <!-- Components will be rendered here -->
                </div>
            </div>
        </div>
    </div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" style="display:none;">
    <i class="fas fa-bars"></i>
</button>

<div id="toastContainer"></div>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/employee_dashboard.js"></script>

<script>
// Sidebar scroll position preservation
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

// Mobile menu toggle
document.getElementById('mobileMenuBtn').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
});

// Close modal when clicking X
document.getElementById('closeDetailModal').addEventListener('click', () => {
    document.getElementById('detailModal').style.display = 'none';
});

// Close modal when clicking outside
window.addEventListener('click', (e) => {
    const modal = document.getElementById('detailModal');
    if (e.target === modal) {
        modal.style.display = 'none';
    }
});
</script>
</body>
</html>
