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

require __DIR__ . '/partials/comptroller_guard.php';

$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'User';
}
$initials = strtoupper(substr($user['Email'] ?? 'C', 0, 1));
$comptrollerActive = 'requests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptroller Requests - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
    <link rel="stylesheet" href="../assets/css/dean_requisition_management.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/partials/comptroller_sidebar.php'; ?>

<main class="main-content">
    <div class="page-header management-header">
        <div>
            <h1>Requisition Management</h1>
            <p>Browse requisitions across all offices. Use <strong>Status</strong> to open workflow progress and review forms (canvass, purchase requisition, purchase order).</p>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <p>Total Requests</p>
            <h3 id="compTotalCount">0</h3>
        </div>
        <div class="summary-card">
            <p>Pending</p>
            <h3 id="compPendingCount">0</h3>
        </div>
        <div class="summary-card">
            <p>Ongoing</p>
            <h3 id="compOngoingCount">0</h3>
        </div>
        <div class="summary-card">
            <p>Completed</p>
            <h3 id="compCompletedCount">0</h3>
        </div>
    </div>

    <div class="filter-section">
        <h3>All Requisitions</h3>
        <div class="filter-controls">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="compReqSearch" class="search-input" placeholder="Search request, requester, office, or item…">
            </div>
            <select id="compReqStatus" class="sort-dropdown">
                <option value="all">All Status</option>
                <option value="Pending">Pending</option>
                <option value="Ongoing">Ongoing</option>
                <option value="Completed">Completed</option>
            </select>
            <select id="compReqSort" class="sort-dropdown">
                <option value="">Sort By</option>
                <option value="entry-desc" selected>Entry No. (Newest First)</option>
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
                        <th>Requester</th>
                        <th>Office</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="compRequestTableBody">
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2rem;color:#64748b;">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="pagination-controls">
            <button type="button" id="compPrevReqBtn" class="pagination-btn" disabled>
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            <span id="compReqPageInfo" class="page-info">Page 1</span>
            <button type="button" id="compNextReqBtn" class="pagination-btn" disabled>
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Menu"><i class="fas fa-bars"></i></button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/comptroller_shell.js"></script>
<script src="../assets/js/comptroller_requests.js"></script>
</body>
</html>
