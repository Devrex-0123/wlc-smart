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
    <title>Requisition Management - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
    <link rel="stylesheet" href="../assets/css/dean_requisition_management.css?v=wlc56">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php $imActivePage = 'requisition_management.php'; require __DIR__ . '/partials/inventory_manager_sidebar.php'; ?>

<main class="main-content requisition-management-container" data-req-scope="management">
    <div class="req-page-header">
        <div class="req-page-header__text">
            <h1 class="req-page-header__title">Requisition Management</h1>
            <p class="req-page-header__subtitle">View requisition status across all offices.</p>
        </div>
        <div class="req-page-header__actions">
            <div class="search-container req-page-header__search">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Search" aria-label="Search requisitions">
            </div>
        </div>
    </div>

    <section class="req-summary-stats" aria-label="Requisition summary">
        <article class="req-summary-card req-summary-card--total">
            <div class="req-summary-card__head">
                <span class="req-summary-card__badge" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                <span class="req-summary-card__label">Total Requests</span>
            </div>
            <p class="req-summary-card__value" id="totalCount">0</p>
            <p class="req-summary-card__meta">All requisition requests.</p>
        </article>
        <article class="req-summary-card req-summary-card--pending">
            <div class="req-summary-card__head">
                <span class="req-summary-card__badge" aria-hidden="true"><i class="fas fa-hourglass-half"></i></span>
                <span class="req-summary-card__label">Pending</span>
            </div>
            <p class="req-summary-card__value" id="pendingCount">0</p>
            <p class="req-summary-card__meta">Requests currently awaiting approval.</p>
        </article>
        <article class="req-summary-card req-summary-card--completed">
            <div class="req-summary-card__head">
                <span class="req-summary-card__badge" aria-hidden="true"><i class="fas fa-circle-check"></i></span>
                <span class="req-summary-card__label">Completed</span>
            </div>
            <p class="req-summary-card__value" id="completedCount">0</p>
            <p class="req-summary-card__meta">Requests that completed all workflow stages.</p>
        </article>
        <article class="req-summary-card req-summary-card--rejected">
            <div class="req-summary-card__head">
                <span class="req-summary-card__badge" aria-hidden="true"><i class="fas fa-circle-xmark"></i></span>
                <span class="req-summary-card__label">Rejected</span>
            </div>
            <p class="req-summary-card__value" id="rejectedCount">0</p>
            <p class="req-summary-card__meta">Requests that did not meet approval requirements.</p>
        </article>
    </section>

    <div class="table-container">
        <div class="table-wrapper">
            <table class="req-management-table">
                <colgroup>
                    <col style="width:4%">
                    <col style="width:32%">
                    <col style="width:18%">
                    <col style="width:13%">
                    <col style="width:11%">
                    <col style="width:10%">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Requisition</th>
                        <th scope="col">Requester</th>
                        <th scope="col">Status</th>
                        <th scope="col">Date</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody id="requestTableBody"></tbody>
            </table>
        </div>
        <footer class="table-panel-footer" id="reqPagination" aria-label="Requisition list pages">
            <p class="table-panel-footer__info" id="reqPageInfo">Showing 0 to 0 of 0 entries</p>
            <div class="table-panel-footer__pagination">
                <button type="button" class="table-panel-footer__page-btn" id="prevReqBtn" disabled aria-label="Previous page">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                </button>
                <span class="table-panel-footer__page-num" id="reqPageNum">1</span>
                <button type="button" class="table-panel-footer__page-btn" id="nextReqBtn" disabled aria-label="Next page">
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
        </footer>
    </div>
</main>

<div id="toastContainer"></div>

<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/requisition_management.js?v=wlc7"></script>
<?php require __DIR__ . '/partials/inventory_manager_sidebar_scripts.php'; ?>
</body>
</html>
