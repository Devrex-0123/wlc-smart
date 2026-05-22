<?php
session_start();
require_once __DIR__ . '/partials/session_access_guard.php';

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
$comptrollerActive = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptroller Dashboard - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .card-value { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body>
<?php require __DIR__ . '/partials/comptroller_sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Overview of requisition lines across IMRMS.</p>
    </div>

    <?php require __DIR__ . '/partials/dashboard_overview_charts.php'; ?>

    <div class="dashboard-grid">
        <div class="dashboard-card" data-notification-key="comptroller_pending">
            <div class="card-header">
                <div>
                    <p class="card-title">Pending</p>
                    <h3 class="card-value" id="compStatPending">—</h3>
                </div>
                <div class="card-icon"><i class="fas fa-hourglass-half"></i></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-file-signature"></i>
                <span>Status: Pending</span>
            </div>
        </div>
        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <p class="card-title">Ongoing</p>
                    <h3 class="card-value" id="compStatOngoing">—</h3>
                </div>
                <div class="card-icon"><i class="fas fa-spinner"></i></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-clipboard-list"></i>
                <span>In progress with inventory</span>
            </div>
        </div>
        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <p class="card-title">Cleared this month</p>
                    <h3 class="card-value" id="compStatClearedMonth">—</h3>
                </div>
                <div class="card-icon"><i class="fas fa-check-double"></i></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-calendar-check"></i>
                <span>Completed (created this month)</span>
            </div>
        </div>
        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <p class="card-title">Flagged (pending + note)</p>
                    <h3 class="card-value" id="compStatFlagged">—</h3>
                </div>
                <div class="card-icon"><i class="fas fa-flag"></i></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-exclamation-circle"></i>
                <span>Pending rows with a message</span>
            </div>
        </div>
    </div>

    <div class="page-header">
        <h2 style="font-size:1.35rem;font-weight:700;color:#1e293b;margin:0 0 0.5rem 0;">Snapshot</h2>
        <p style="margin-bottom: 0;">
            <strong id="compStatTotalLines">—</strong> requisition line(s) in the system;
            <strong id="compStatCompletedAll">—</strong> marked completed (all time).
            Open <a href="comptroller_requests.php" style="color: #16a34a; font-weight: 600;">Requests</a> to browse lines or
            <a href="audit_trail.php" style="color: #16a34a; font-weight: 600;">Audit Trail</a> for login and activity logs.
        </p>
    </div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Menu"><i class="fas fa-bars"></i></button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/comptroller_shell.js"></script>
<script src="../assets/js/comptroller_dashboard.js"></script>
</body>
</html>
