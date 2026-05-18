<?php
session_start();
require_once __DIR__ . '/partials/session_access_guard.php';

require_once __DIR__ . '/../../app/classes/db.php';

$db = Database::connect();
$stmt = $db->prepare('SELECT * FROM user WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

require __DIR__ . '/partials/gsd_guard.php';

$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'User';
}
$initials = strtoupper(substr($user['Email'] ?? 'G', 0, 1));
$gsdActive = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GSD Dashboard - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/gsd.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .card-value { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body>
<?php require __DIR__ . '/partials/gsd_sidebar.php'; ?>

<main class="main-content gsd-main">
    <div class="page-header">
        <div class="gsd-kicker"><i class="fas fa-building"></i> GSD workspace</div>
        <h1>Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Overview for General Services (UI preview — data hooks pending).</p>
    </div>

    <?php require __DIR__ . '/partials/dashboard_overview_charts.php'; ?>

    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <p class="card-title">Pending review</p>
                    <h3 class="card-value">—</h3>
                </div>
                <div class="card-icon"><i class="fas fa-hourglass-half"></i></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-file-signature"></i>
                <span>Awaiting GSD action</span>
            </div>
        </div>
        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <p class="card-title">In progress</p>
                    <h3 class="card-value">—</h3>
                </div>
                <div class="card-icon"><i class="fas fa-spinner"></i></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-tasks"></i>
                <span>Active requisitions</span>
            </div>
        </div>
        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <p class="card-title">Cleared (month)</p>
                    <h3 class="card-value">—</h3>
                </div>
                <div class="card-icon"><i class="fas fa-check-double"></i></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-calendar-check"></i>
                <span>Completed this month</span>
            </div>
        </div>
        <div class="dashboard-card">
            <div class="card-header">
                <div>
                    <p class="card-title">Flagged</p>
                    <h3 class="card-value">—</h3>
                </div>
                <div class="card-icon"><i class="fas fa-flag"></i></div>
            </div>
            <div class="card-footer">
                <i class="fas fa-exclamation-circle"></i>
                <span>Items needing attention</span>
            </div>
        </div>
    </div>

    <div class="page-header">
        <h2 style="font-size:1.35rem;font-weight:700;color:#1e293b;margin:0 0 0.5rem 0;">Quick links</h2>
        <p style="margin-bottom: 0;">
            Open <a href="gsd_request.php" style="color: #16a34a; font-weight: 600;">Request</a> for request queues,
            <a href="gsd_account_management.php" style="color: #16a34a; font-weight: 600;">Account Management</a> for users, or
            <a href="audit_trail.php" style="color: #16a34a; font-weight: 600;">Audit Trail</a> for login and activity logs.
        </p>
    </div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Menu"><i class="fas fa-bars"></i></button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/gsd_shell.js"></script>
<script src="../assets/js/gsd_dashboard.js"></script>
</body>
</html>
