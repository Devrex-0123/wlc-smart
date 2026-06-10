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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dean Requisition Management</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc34">
    <link rel="stylesheet" href="../assets/css/dean_requisition_management.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/partials/dean_sidebar.php'; ?>

<main class="main-content">
    <div class="page-header management-header">
        <div>
            <h1>Requisition Management</h1>
            <p>Track your requests and manage them.</p>
        </div>
        <button class="btn-green" onclick="window.location.href='dean_requisition_form.php?from=requisition';">
            <i class="fas fa-plus"></i> New Requisition
        </button>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <p>Total Requests</p>
            <h3 id="totalCount">0</h3>
        </div>
        <div class="summary-card">
            <p>Pending</p>
            <h3 id="pendingCount">0</h3>
        </div>
        <div class="summary-card">
            <p>Ongoing</p>
            <h3 id="ongoingCount">0</h3>
        </div>
        <div class="summary-card">
            <p>Completed</p>
            <h3 id="completedCount">0</h3>
        </div>
    </div>

    <div class="filter-section">
        <h3>Requisition Requests</h3>
        <div class="filter-controls">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Search request no, item, or supplier...">
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

    <div class="table-container">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Request No.</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Suppliers</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="requestTableBody"></tbody>
            </table>
        </div>
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

<div id="confirmModal" class="confirm-modal" style="display:none;">
    <div class="confirm-backdrop"></div>
    <div class="confirm-card">
        <h4 id="confirmTitle">Confirm Action</h4>
        <p id="confirmText">Are you sure?</p>
        <div class="confirm-actions">
            <button type="button" id="confirmCancel" class="btn-muted">Cancel</button>
            <button type="button" id="confirmOk" class="btn-danger">Delete</button>
        </div>
    </div>
</div>

<div id="toastContainer"></div>

<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>

<?php require __DIR__ . '/partials/dean_sidebar_scripts.php'; ?>
<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/dean_requisition_management.js"></script>
</body>
</html>
