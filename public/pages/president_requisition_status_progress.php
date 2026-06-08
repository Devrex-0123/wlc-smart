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
$pvActive = 'request';
$rspProgressBackHref = 'president_request.php';
$rspBackAriaLabel = 'Back to Requisition Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requisition progress — President — IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/requisition_status_progress.css">
    <link rel="stylesheet" href="../assets/css/president_verifier.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/partials/president_verifier_sidebar.php'; ?>

<main class="main-content">
    <a href="<?php echo htmlspecialchars($rspProgressBackHref, ENT_QUOTES, 'UTF-8'); ?>" class="rsp-back rsp-back-upper-left" aria-label="<?php echo htmlspecialchars($rspBackAriaLabel, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-arrow-left"></i> Back</a>
    <div class="page-header management-header" style="margin-bottom: 1rem;">
        <div>
            <h1>Requisition progress</h1>
            <p>Workflow overview for this request. Open forms below to review canvass, purchase requisition, or purchase order.</p>
        </div>
    </div>
    <div id="rspRoot" data-readonly="1" data-viewer="president" data-back-href="<?php echo htmlspecialchars($rspProgressBackHref, ENT_QUOTES, 'UTF-8'); ?>"></div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Menu"><i class="fas fa-bars"></i></button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/president_shell.js"></script>
<script src="../assets/js/requisition_status_progress.js"></script>
</body>
</html>
