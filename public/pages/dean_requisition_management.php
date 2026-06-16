<?php
require_once __DIR__ . '/partials/dean_page_context.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requisition Management - Dean</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc34">
    <link rel="stylesheet" href="../assets/css/dean_requisition_management.css?v=wlc56">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/partials/dean_sidebar.php'; ?>

<main class="main-content requisition-management-container" data-req-scope="management">
    <div class="req-page-header">
        <div class="req-page-header__text">
            <h1 class="req-page-header__title">Requisition Management</h1>
            <p class="req-page-header__subtitle">Track your requests and manage them.</p>
        </div>
        <div class="req-page-header__actions">
            <div class="search-container req-page-header__search">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Search" aria-label="Search requisitions">
            </div>
            <button type="button" class="btn-green" onclick="window.location.href='dean_requisition_form.php?from=requisition';">
                <i class="fas fa-plus"></i> New Requisition
            </button>
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
                <span class="req-summary-card__badge" aria-hidden="true"><i class="fas fa-spinner"></i></span>
                <span class="req-summary-card__label">Ongoing</span>
            </div>
            <p class="req-summary-card__value" id="ongoingCount">0</p>
            <p class="req-summary-card__meta">Requests currently in progress.</p>
        </article>
        <article class="req-summary-card req-summary-card--rejected">
            <div class="req-summary-card__head">
                <span class="req-summary-card__badge" aria-hidden="true"><i class="fas fa-circle-check"></i></span>
                <span class="req-summary-card__label">Completed</span>
            </div>
            <p class="req-summary-card__value" id="completedCount">0</p>
            <p class="req-summary-card__meta">Requests that completed all workflow stages.</p>
        </article>
    </section>

    <div class="table-container">
<div class="table-wrapper">
            <table class="req-management-table">
                <colgroup>
                    <col style="width:4%">
                    <col style="width:14%">
                    <col style="width:11%">
                    <col style="width:26%">
                    <col style="width:10%">
                    <col style="width:11%">
                    <col style="width:24%">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Request No.</th>
                        <th scope="col">Date</th>
                        <th scope="col">Items</th>
                        <th scope="col">Suppliers</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
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
<script src="../assets/js/logout.js?v=wlc2"></script>
<script src="../assets/js/dean_requisition_management.js?v=wlc57"></script>
</body>
</html>
