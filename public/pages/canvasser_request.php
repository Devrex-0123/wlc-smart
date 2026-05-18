<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';

$db = Database::connect();
$stmt = $db->prepare('SELECT u.*, d.`office_name` AS office_name FROM user u LEFT JOIN offices d ON d.office_id = u.office_id WHERE u.user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

require __DIR__ . '/partials/canvasser_guard.php';

$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'User';
}
$initials = strtoupper(substr($user['Email'] ?? 'C', 0, 1));
$cvActive = 'request';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request — Canvasser — IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/dean_requisition_management.css">
    <link rel="stylesheet" href="../assets/css/gsd.css">
    <link rel="stylesheet" href="../assets/css/president_verifier.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/partials/canvasser_sidebar.php'; ?>

<main class="main-content">
    <div class="page-header management-header">
        <div>
            <div class="gsd-kicker" style="margin-bottom: 0.75rem;"><i class="fas fa-clipboard-check"></i> Canvasser workspace</div>
            <h1>Request Management</h1>
            <p>Only requisitions <strong>assigned to you</strong> by GSD for canvassing appear here. Open <strong>View</strong> for the <strong>canvass sheet</strong> (abstract of quotation); use the link on that page if you need the requisition supplier matrix or <strong>Done</strong>.</p>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <p>Total Requests</p>
            <h3 id="cvTotalCount">0</h3>
        </div>
        <div class="summary-card">
            <p>Pending</p>
            <h3 id="cvPendingCount">0</h3>
        </div>
        <div class="summary-card">
            <p>Ongoing</p>
            <h3 id="cvOngoingCount">0</h3>
        </div>
        <div class="summary-card">
            <p>Completed</p>
            <h3 id="cvCompletedCount">0</h3>
        </div>
    </div>

    <section class="cv-requests-main-block">
    <div class="filter-section cv-requests-filter">
        <h3><i class="fas fa-user-check"></i> Assigned to you</h3>
        <div class="filter-controls">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="cvReqSearch" class="search-input" placeholder="Search request, requester, office, or item…">
            </div>
            <select id="cvReqStatus" class="sort-dropdown">
                <option value="all">All Status</option>
                <option value="Pending">Pending</option>
                <option value="Ongoing">Ongoing</option>
                <option value="Completed">Completed</option>
            </select>
            <select id="cvReqSort" class="sort-dropdown">
                <option value="">Sort By</option>
                <option value="entry-desc" selected>Entry No. (Newest First)</option>
                <option value="entry-asc">Entry No. (Oldest First)</option>
            </select>
        </div>
    </div>

    <div class="table-container cv-requests-table-container">
        <div class="table-wrapper">
            <table class="pv-data-table pv-requests-table">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Request No.</th>
                        <th scope="col">Date</th>
                        <th scope="col">Requester</th>
                        <th scope="col">Office</th>
                        <th scope="col">Items</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="cvRequestTableBody">
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2rem;color:#64748b;">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="pagination-controls">
            <button type="button" id="cvPrevReqBtn" class="pagination-btn" disabled>
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            <span id="cvReqPageInfo" class="page-info">Page 1</span>
            <button type="button" id="cvNextReqBtn" class="pagination-btn" disabled>
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
    </section>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Menu"><i class="fas fa-bars"></i></button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/president_shell.js"></script>
<script src="../assets/js/canvasser_requests.js"></script>
</body>
</html>
