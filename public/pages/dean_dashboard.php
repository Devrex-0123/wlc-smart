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
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'Dean';
}
$initials = strtoupper(substr($user['Email'], 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dean Dashboard - IMRMS</title>
<link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc34">
<link rel="stylesheet" href="../assets/css/dean_dashboard.css?v=12">
<link rel="stylesheet" href="../assets/css/loading.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<?php require __DIR__ . '/partials/dean_sidebar.php'; ?>

<!-- Main Content -->
<main class="main-content dean-dashboard-home">
    <section class="dashboard-welcome">
        <h1 class="dashboard-welcome__title">Welcome back!</h1>
        <p class="dashboard-welcome__subtitle">Manage and request resources for your department.</p>
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

    <div class="dean-dashboard-panels" aria-label="Requests and pipeline">
        <section class="dashboard-panel dashboard-panel--recent dean-recent-panel" aria-label="Recent requests">
            <header class="dashboard-panel__head dashboard-panel__head--split dean-recent-panel__head">
                <h2 class="dashboard-panel__title"><i class="fas fa-clock" aria-hidden="true"></i> Recent Requests</h2>
                <button id="requestItemBtn" class="dean-request-btn" type="button" onclick="window.location.href='dean_requisition_form.php?from=dashboard';">
                    <i class="fas fa-plus" aria-hidden="true"></i> Request Item
                </button>
            </header>
        </section>

        <section class="dashboard-panel dashboard-panel--pipeline dean-pipeline-panel" aria-label="Procurement pipeline">
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
                            <span class="pipeline-stage__metric-value" id="deanPipelineRequestSubmitted">0</span>
                            <span class="pipeline-stage__metric-label">submitted</span>
                        </div>
                        <div class="pipeline-stage__metric pipeline-stage__metric--accent">
                            <span class="pipeline-stage__metric-value" id="deanPipelineRequestAwaiting">0</span>
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
                            <span class="pipeline-stage__metric-value" id="deanPipelineCanvassSubmitted">0</span>
                            <span class="pipeline-stage__metric-label">submitted</span>
                        </div>
                        <div class="pipeline-stage__metric pipeline-stage__metric--accent">
                            <span class="pipeline-stage__metric-value" id="deanPipelineCanvassAwaiting">0</span>
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
                            <span class="pipeline-stage__metric-value" id="deanPipelinePrSubmitted">0</span>
                            <span class="pipeline-stage__metric-label">submitted</span>
                        </div>
                        <div class="pipeline-stage__metric pipeline-stage__metric--accent">
                            <span class="pipeline-stage__metric-value" id="deanPipelinePrAwaiting">0</span>
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
                            <span class="pipeline-stage__metric-value" id="deanPipelinePoSubmitted">0</span>
                            <span class="pipeline-stage__metric-label">submitted</span>
                        </div>
                        <div class="pipeline-stage__metric pipeline-stage__metric--accent">
                            <span class="pipeline-stage__metric-value" id="deanPipelinePoAwaiting">0</span>
                            <span class="pipeline-stage__metric-label">awaiting validation</span>
                        </div>
                    </div>
                </article>

                <article class="pipeline-stage pipeline-stage--delivery">
                    <div class="pipeline-stage__head">
                        <span class="pipeline-stage__icon" aria-hidden="true"><i class="fas fa-truck"></i></span>
                        <span class="pipeline-stage__label">Delivery</span>
                    </div>
                    <div class="pipeline-stage__metrics">
                        <div class="pipeline-stage__metric pipeline-stage__metric--primary">
                            <span class="pipeline-stage__metric-value" id="deanPipelineDeliveryTransit">0</span>
                            <span class="pipeline-stage__metric-label">in transit</span>
                        </div>
                        <div class="pipeline-stage__metric pipeline-stage__metric--accent">
                            <span class="pipeline-stage__metric-value" id="deanPipelineDeliveryReceiving">0</span>
                            <span class="pipeline-stage__metric-label">pending receiving</span>
                        </div>
                    </div>
                </article>
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
<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/dean_dashboard.js?v=4"></script>
</body>
</html>