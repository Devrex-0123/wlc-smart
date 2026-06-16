<?php
require_once __DIR__ . '/partials/dean_page_context.php';

if ($isDepartmentLogin) {
    header('Location: department_approval_workflow.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Workflow - Dean</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc34">
    <link rel="stylesheet" href="../assets/css/dean_requisition_management.css?v=wlc58">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/partials/dean_sidebar.php'; ?>

<main class="main-content requisition-management-container" data-req-scope="workflow" data-req-hide-delete="true" data-req-office="<?php echo htmlspecialchars($deptName); ?>">
    <div class="req-page-header">
        <div class="req-page-header__text">
            <h1 class="req-page-header__title">Approval Workflow</h1>
            <p class="req-page-header__subtitle">Track approved requests through the approval pipeline.</p>
        </div>
    </div>

    <div class="req-filter-bar">
        <div class="req-filter-chips-bar" role="tablist" aria-label="Filter requisitions">
            <button type="button" class="req-filter-chip is-active" data-filter="all" aria-pressed="true">All</button>
            <button type="button" class="req-filter-chip" data-filter="ongoing" aria-pressed="false">Ongoing</button>
            <button type="button" class="req-filter-chip" data-filter="rejected" aria-pressed="false">Rejected</button>
            <button type="button" class="req-filter-chip" data-filter="completed" aria-pressed="false">Completed</button>
        </div>
        <div class="search-container req-filter-bar__search">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Search" aria-label="Search requisitions">
        </div>
    </div>

    <div class="table-container">
        <div class="table-wrapper">
            <table class="req-management-table">
                <colgroup>
                    <col style="width:2%">
                    <col style="width:46%">
                    <col style="width:18%">
                    <col style="width:13%">
                    <col style="width:12%">
                    <col style="width:8%">
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

<?php require __DIR__ . '/partials/dean_sidebar_scripts.php'; ?>
<script src="../assets/js/logout.js?v=wlc2"></script>
<script src="../assets/js/dean_requisition_management.js?v=wlc57"></script>
</body>
</html>
