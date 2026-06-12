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
if ($role !== 'inventory manager' && $role !== 'inventory_manager') {
    header('Location: ../../index.php');
    exit;
}

$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'Admin';
}
$initials = strtoupper(substr($user['Email'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
    <link rel="stylesheet" href="../assets/css/dean_requisition_management.css">
    <link rel="stylesheet" href="../assets/css/dean_requisition_status.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="status-page">
<?php $imActivePage = 'requisition_management.php'; require __DIR__ . '/partials/inventory_manager_sidebar.php'; ?>

<main class="main-content">
    <div class="page-header management-header">
        <div>
            <h1>Status</h1>
            <p>Track requisition progress across all offices.</p>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-card-icon"><i class="fas fa-layer-group"></i></div>
            <p>Total Requests</p>
            <h3 id="totalCount">0</h3>
        </div>
        <div class="summary-card">
            <div class="summary-card-icon"><i class="fas fa-hourglass-half"></i></div>
            <p>Pending</p>
            <h3 id="pendingCount">0</h3>
        </div>
        <div class="summary-card">
            <div class="summary-card-icon"><i class="fas fa-spinner"></i></div>
            <p>Ongoing</p>
            <h3 id="ongoingCount">0</h3>
        </div>
        <div class="summary-card">
            <div class="summary-card-icon"><i class="fas fa-circle-check"></i></div>
            <p>Completed</p>
            <h3 id="completedCount">0</h3>
        </div>
    </div>

    <div class="filter-section">
        <h3>Track Status</h3>
        <div class="filter-controls">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Search request, requester, office, item, or supplier...">
            </div>
            <select id="statusFilter" class="sort-dropdown">
                <option value="all">All Status</option>
                <option value="Pending">Pending</option>
                <option value="Ongoing">Ongoing</option>
                <option value="Completed">Completed</option>
            </select>
            <select id="sortDropdown" class="sort-dropdown">
                <option value="">Sort By</option>
                <option value="entry-desc">Entry No. (Newest First)</option>
                <option value="entry-asc">Entry No. (Oldest First)</option>
            </select>
        </div>
    </div>

    <div class="status-board">
        <div id="statusCards" class="status-cards"></div>
        <div class="pagination-controls">
            <button id="prevReqBtn" class="pagination-btn" disabled>
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            <span id="reqPageInfo" class="page-info">Page 1</span>
            <button id="nextReqBtn" class="pagination-btn" disabled>
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</main>

<div id="toastContainer"></div>
<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<?php require __DIR__ . '/partials/inventory_manager_sidebar_scripts.php'; ?>
<script src="../assets/js/requisition_status.js"></script>
</body>
</html>
