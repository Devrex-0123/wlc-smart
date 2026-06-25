
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
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'User';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WLC-SMART</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc37">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php $imActivePage = 'dashboard.php'; require __DIR__ . '/partials/inventory_manager_sidebar.php'; ?>

<main class="main-content dashboard-home">
    <section class="dashboard-welcome">
        <h1 class="dashboard-welcome__title">Welcome back, <span class="dashboard-welcome__name"><?= htmlspecialchars($username) ?></span>!</h1>
        <p class="dashboard-welcome__subtitle">Here's an overview of your school assets and requisitions today.</p>
    </section>

    <section class="dashboard-stats" aria-label="Dashboard summary">
        <article class="dashboard-stat-card dashboard-stat-card--assets">
            <div class="dashboard-stat-card__head">
                <span class="dashboard-stat-card__icon" aria-hidden="true"><i class="fas fa-cube"></i></span>
                <span class="dashboard-stat-card__label">Total Assets</span>
            </div>
            <p class="dashboard-stat-card__value" id="totalAssetsCount">0</p>
            <p class="dashboard-stat-card__meta" id="totalAssetsMeta">Across 0 departments</p>
        </article>

        <article class="dashboard-stat-card dashboard-stat-card--requests">
            <div class="dashboard-stat-card__head">
                <span class="dashboard-stat-card__icon" aria-hidden="true"><i class="fas fa-file-lines"></i></span>
                <span class="dashboard-stat-card__label">Active Requests</span>
            </div>
            <p class="dashboard-stat-card__value" id="activeRequestsCount">0</p>
            <p class="dashboard-stat-card__meta" id="activeRequestsMeta">0 awaiting validation</p>
        </article>

        <article class="dashboard-stat-card dashboard-stat-card--delivery">
            <div class="dashboard-stat-card__head">
                <span class="dashboard-stat-card__icon" aria-hidden="true"><i class="fas fa-box-open"></i></span>
                <span class="dashboard-stat-card__label">Awaiting Item Receipt</span>
            </div>
            <p class="dashboard-stat-card__value" id="awaitingReceiptCount">0</p>
            <p class="dashboard-stat-card__meta" id="awaitingReceiptMeta">0 purchase orders</p>
        </article>

        <article class="dashboard-stat-card dashboard-stat-card--depts">
            <div class="dashboard-stat-card__head">
                <span class="dashboard-stat-card__icon" aria-hidden="true"><i class="fas fa-building"></i></span>
                <span class="dashboard-stat-card__label">Department with Active Requests</span>
            </div>
            <p class="dashboard-stat-card__value" id="deptsActiveCount">0</p>
            <a href="requisition_management.php" class="dashboard-stat-card__link">View breakdown <i class="fas fa-arrow-down" aria-hidden="true"></i></a>
        </article>
    </section>

    <section class="dashboard-panel dashboard-panel--pipeline" aria-label="Procurement pipeline">
        <header class="dashboard-panel__head">
            <h2 class="dashboard-panel__title"><i class="fas fa-diagram-project" aria-hidden="true"></i> Procurement Pipeline</h2>
            <div class="dashboard-panel__legend" aria-hidden="true">
                <span class="dashboard-panel__legend-item"><span class="dashboard-panel__legend-dot dashboard-panel__legend-dot--submitted"></span> Submitted</span>
                <span class="dashboard-panel__legend-item"><span class="dashboard-panel__legend-dot dashboard-panel__legend-dot--awaiting"></span> Awaiting validation</span>
            </div>
        </header>

        <div class="dashboard-pipeline__stages">
            <article class="pipeline-stage pipeline-stage--request">
                <div class="pipeline-stage__head">
                    <span class="pipeline-stage__icon" aria-hidden="true"><i class="fas fa-file-circle-plus"></i></span>
                    <span class="pipeline-stage__label">Request</span>
                </div>
                <div class="pipeline-stage__metrics">
                    <div class="pipeline-stage__metric pipeline-stage__metric--primary">
                        <span class="pipeline-stage__metric-value" id="pipelineRequestSubmitted">0</span>
                        <span class="pipeline-stage__metric-label">submitted</span>
                    </div>
                    <div class="pipeline-stage__metric pipeline-stage__metric--accent">
                        <span class="pipeline-stage__metric-value" id="pipelineRequestAwaiting">0</span>
                        <span class="pipeline-stage__metric-label">awaiting validation</span>
                    </div>
                </div>
            </article>

            <article class="pipeline-stage pipeline-stage--canvass">
                <div class="pipeline-stage__head">
                    <span class="pipeline-stage__icon" aria-hidden="true"><i class="fas fa-table-list"></i></span>
                    <span class="pipeline-stage__label">Canvass</span>
                </div>
                <div class="pipeline-stage__metrics">
                    <div class="pipeline-stage__metric pipeline-stage__metric--primary">
                        <span class="pipeline-stage__metric-value" id="pipelineCanvassSubmitted">0</span>
                        <span class="pipeline-stage__metric-label">submitted</span>
                    </div>
                    <div class="pipeline-stage__metric pipeline-stage__metric--accent">
                        <span class="pipeline-stage__metric-value" id="pipelineCanvassAwaiting">0</span>
                        <span class="pipeline-stage__metric-label">awaiting validation</span>
                    </div>
                </div>
            </article>

            <article class="pipeline-stage pipeline-stage--pr">
                <div class="pipeline-stage__head">
                    <span class="pipeline-stage__icon" aria-hidden="true"><i class="fas fa-file-lines"></i></span>
                    <span class="pipeline-stage__label">PR</span>
                </div>
                <div class="pipeline-stage__metrics">
                    <div class="pipeline-stage__metric pipeline-stage__metric--primary">
                        <span class="pipeline-stage__metric-value" id="pipelinePrSubmitted">0</span>
                        <span class="pipeline-stage__metric-label">submitted</span>
                    </div>
                    <div class="pipeline-stage__metric pipeline-stage__metric--accent">
                        <span class="pipeline-stage__metric-value" id="pipelinePrAwaiting">0</span>
                        <span class="pipeline-stage__metric-label">awaiting validation</span>
                    </div>
                </div>
            </article>

            <article class="pipeline-stage pipeline-stage--po">
                <div class="pipeline-stage__head">
                    <span class="pipeline-stage__icon" aria-hidden="true"><i class="fas fa-cart-shopping"></i></span>
                    <span class="pipeline-stage__label">PO</span>
                </div>
                <div class="pipeline-stage__metrics">
                    <div class="pipeline-stage__metric pipeline-stage__metric--primary">
                        <span class="pipeline-stage__metric-value" id="pipelinePoSubmitted">0</span>
                        <span class="pipeline-stage__metric-label">submitted</span>
                    </div>
                    <div class="pipeline-stage__metric pipeline-stage__metric--accent">
                        <span class="pipeline-stage__metric-value" id="pipelinePoAwaiting">0</span>
                        <span class="pipeline-stage__metric-label">awaiting validation</span>
                    </div>
                </div>
            </article>

            <article class="pipeline-stage pipeline-stage--receipt">
                <div class="pipeline-stage__head">
                    <span class="pipeline-stage__icon" aria-hidden="true"><i class="fas fa-box-open"></i></span>
                    <span class="pipeline-stage__label">Receipt</span>
                </div>
                <div class="pipeline-stage__metrics">
                    <div class="pipeline-stage__metric pipeline-stage__metric--primary">
                        <span class="pipeline-stage__metric-value" id="pipelineReceiptReleased">0</span>
                        <span class="pipeline-stage__metric-label">released</span>
                    </div>
                    <div class="pipeline-stage__metric pipeline-stage__metric--accent">
                        <span class="pipeline-stage__metric-value" id="pipelineReceiptPending">0</span>
                        <span class="pipeline-stage__metric-label">pending receipt</span>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <div class="dashboard-tables-split">
        <section class="dashboard-panel-card dashboard-panel-card--recent" aria-label="Pending requisitions">
            <header class="dashboard-panel-card__header">
                <div class="dashboard-panel-card__header-main">
                    <span class="dashboard-panel-card__icon dashboard-panel-card__icon--recent" aria-hidden="true">
                        <i class="fas fa-clock"></i>
                    </span>
                    <div class="dashboard-panel-card__header-text">
                        <h2 class="dashboard-panel-card__title">Pending Requisitions</h2>
                        <p class="dashboard-panel-card__desc">Recent requisitions awaiting action or approval.</p>
                    </div>
                </div>
                <a href="requisition_management.php" class="dashboard-panel__action">View all</a>
            </header>
            <div class="dashboard-panel-card__body">
                <ul class="dashboard-recent__list" id="recentRequisitionsList">
                    <li class="dashboard-recent__empty">Loading recent requisitions…</li>
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
                <ul class="dashboard-recent__list" id="awaitingItemReceiptList">
                    <li class="dashboard-recent__empty">Loading…</li>
                </ul>
            </div>
        </section>
    </div>
</main>

<?php require __DIR__ . '/partials/inventory_manager_sidebar_scripts.php'; ?>

<script src="../assets/js/dashboard.js?v=wlc5"></script>
<script src="../assets/js/logout.js?v=wlc1"></script>
</body>
</html>
