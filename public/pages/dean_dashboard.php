<?php
require_once __DIR__ . '/partials/dean_page_context.php';

$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'Dean';
}
$initials = strtoupper(substr((string)($user['Email'] ?? 'D'), 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dean Dashboard - IMRMS</title>
<link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc38">
<link rel="stylesheet" href="../assets/css/dean_dashboard.css?v=14">
<link rel="stylesheet" href="../assets/css/requester_notifications.css?v=1">
<link rel="stylesheet" href="../assets/css/loading.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<?php require __DIR__ . '/partials/dean_sidebar.php'; ?>

<!-- Main Content -->
<main class="main-content dean-dashboard-home">
    <section class="dashboard-welcome dashboard-welcome--with-bell">
        <div class="dashboard-welcome__row">
            <div class="dashboard-welcome__copy">
                <h1 class="dashboard-welcome__title">Welcome back!</h1>
                <p class="dashboard-welcome__subtitle">Manage and request resources for your department.</p>
            </div>
            <?php require __DIR__ . '/partials/requester_notifications_bell.php'; ?>
        </div>
    </section>

    <section class="dean-summary-grid" aria-label="Request summary">
        <article class="dean-summary-card dean-summary-card--total">
            <div class="dean-summary-card__head">
                <span class="dean-summary-card__icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                <p class="dean-summary-card__label">Total requests</p>
            </div>
            <p class="dean-summary-card__value" id="deanTotalRequests">0</p>
            <p class="dean-summary-card__meta">All requisitions you've submitted</p>
        </article>
        <article class="dean-summary-card dean-summary-card--progress">
            <div class="dean-summary-card__head">
                <span class="dean-summary-card__icon" aria-hidden="true"><i class="fas fa-spinner"></i></span>
                <p class="dean-summary-card__label">In progress</p>
            </div>
            <p class="dean-summary-card__value" id="deanInProgressCount">0</p>
            <p class="dean-summary-card__meta">Pending approval or processing</p>
        </article>
        <article class="dean-summary-card dean-summary-card--completed">
            <div class="dean-summary-card__head">
                <span class="dean-summary-card__icon" aria-hidden="true"><i class="fas fa-circle-check"></i></span>
                <p class="dean-summary-card__label">Completed</p>
            </div>
            <p class="dean-summary-card__value" id="deanCompletedCount">0</p>
            <p class="dean-summary-card__meta">Successfully fulfilled requests</p>
        </article>
        <article class="dean-summary-card dean-summary-card--rejected">
            <div class="dean-summary-card__head">
                <span class="dean-summary-card__icon" aria-hidden="true"><i class="fas fa-circle-xmark"></i></span>
                <p class="dean-summary-card__label">Rejected</p>
            </div>
            <p class="dean-summary-card__value" id="deanRejectedCount">0</p>
            <p class="dean-summary-card__meta">Returned for revision</p>
        </article>
    </section>

    <div class="dashboard-tables-split">
        <section class="dashboard-panel-card dashboard-panel-card--recent" aria-label="Pending requests">
            <header class="dashboard-panel-card__header">
                <div class="dashboard-panel-card__header-main">
                    <span class="dashboard-panel-card__icon dashboard-panel-card__icon--recent" aria-hidden="true">
                        <i class="fas fa-clock"></i>
                    </span>
                    <div class="dashboard-panel-card__header-text">
                        <h2 class="dashboard-panel-card__title">Pending Requests</h2>
                        <p class="dashboard-panel-card__desc">Recent requisitions awaiting action or approval.</p>
                    </div>
                </div>
                <button id="requestItemBtn" class="dean-request-btn" type="button" onclick="window.location.href='<?php echo $isDepartmentLogin ? 'department_requisition_form.php' : 'dean_requisition_form.php'; ?>?from=dashboard';">
                    <i class="fas fa-plus" aria-hidden="true"></i> Request Item
                </button>
            </header>
            <div class="dashboard-panel-card__body">
                <ul class="dashboard-recent__list" id="deanPendingList">
                    <li class="dashboard-recent__empty">Loading…</li>
                </ul>
            </div>
        </section>

        <section class="dashboard-panel-card dashboard-panel-card--awaiting" aria-label="Awaiting item receipt">
            <header class="dashboard-panel-card__header">
                <div class="dashboard-panel-card__header-main">
                    <span class="dashboard-panel-card__icon dashboard-panel-card__icon--awaiting" aria-hidden="true">
                        <i class="fas fa-box-open"></i>
                    </span>
                    <div class="dashboard-panel-card__header-text">
                        <h2 class="dashboard-panel-card__title">Awaiting Item Receipt</h2>
                        <p class="dashboard-panel-card__desc">Purchase orders with payment released, pending physical receipt.</p>
                    </div>
                </div>
            </header>
            <div class="dashboard-panel-card__body">
                <ul class="dashboard-recent__list" id="deanAwaitingReceiptList">
                    <li class="dashboard-recent__empty">Loading…</li>
                </ul>
            </div>
        </section>
    </div>
</main>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>

<!-- Toast Container -->
<div id="toastContainer"></div>

<?php require __DIR__ . '/partials/dean_sidebar_scripts.php'; ?>

<!-- Logout Script -->
<script src="../assets/js/logout.js?v=wlc2"></script>
<script src="../assets/js/requester_notifications.js?v=2"></script>
<script src="../assets/js/dean_dashboard.js?v=5"></script>
</body>
</html>