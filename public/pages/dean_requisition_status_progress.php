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

$username = trim((string)($user['full_name'] ?? ''));
if ($username === '') {
    $username = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'Dean';
}
$initials = strtoupper(substr($user['Email'] ?? 'D', 0, 1));
$progressPageFrom = $_GET['from'] ?? '';
$rspProgressBackHref = ($progressPageFrom === 'status') ? 'dean_requisition_status.php' : 'dean_requisition_management.php';
$rspBackAriaLabel = ($progressPageFrom === 'status') ? 'Back to Status list' : 'Back to Requisition Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requisition Progress - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc44">
    <link rel="stylesheet" href="../assets/css/requisition_status_progress.css?v=wlc10">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/partials/dean_sidebar.php'; ?>

<main class="main-content">
    <div id="rspRoot" data-readonly="1" data-dean-flow="1" data-back-href="<?php echo htmlspecialchars($rspProgressBackHref, ENT_QUOTES, 'UTF-8'); ?>" data-back-aria-label="<?php echo htmlspecialchars($rspBackAriaLabel, ENT_QUOTES, 'UTF-8'); ?>" data-progress-from="<?php echo htmlspecialchars($progressPageFrom, ENT_QUOTES, 'UTF-8'); ?>"></div>
</main>

<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>

<?php require __DIR__ . '/partials/dean_sidebar_scripts.php'; ?>
<script src="../assets/js/logout.js?v=wlc1"></script>
<script src="../assets/js/requisition_status_progress.js?v=wlc10"></script>
</body>
</html>
