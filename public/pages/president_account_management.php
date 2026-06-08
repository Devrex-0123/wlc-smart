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

require __DIR__ . '/partials/president_verifier_guard.php';

$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'User';
}
$initials = strtoupper(substr($user['Email'] ?? 'P', 0, 1));
$pvActive = 'accounts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management — President Verifier — IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
    <link rel="stylesheet" href="../assets/css/gsd.css">
    <link rel="stylesheet" href="../assets/css/president_verifier.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/partials/president_verifier_sidebar.php'; ?>

<main class="main-content gsd-main">
    <div class="page-header">
        <div class="gsd-kicker"><i class="fas fa-users-cog"></i> President verifier workspace</div>
        <h1>Account Management</h1>
        <p>Manage accounts in scope for presidential verification (UI shell — API routes to be added).</p>
    </div>

    <div style="display:flex;justify-content:center;padding:1rem 0;">
        <div class="pv-placeholder">
            <div class="pv-placeholder-icon"><i class="fas fa-user-shield"></i></div>
            <h2>Accounts module</h2>
            <p>List, search, and edit users will appear here with the same table and modal patterns as the main admin area once backend endpoints are wired.</p>
        </div>
    </div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Menu"><i class="fas fa-bars"></i></button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/president_shell.js"></script>
</body>
</html>
