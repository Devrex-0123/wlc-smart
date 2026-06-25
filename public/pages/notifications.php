<?php
session_start();
require_once __DIR__ . '/partials/session_access_guard.php';

require_once __DIR__ . '/../../app/classes/db.php';

$db = Database::connect();
$isDepartmentLogin = isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'department';

if ($isDepartmentLogin) {
    $stmt = $db->prepare('SELECT * FROM departments WHERE department_id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['department_id']]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);
    $user = [
        'full_name' => $department['department_name'] ?? 'Department',
        'department_abbreviation' => $department['department_abbreviation'] ?? '',
        'Email' => $department['department_username'] ?? '',
        'role' => 'Department',
        'photo_url' => $department['department_photo_url'] ?? null,
    ];
} else {
    $stmt = $db->prepare('SELECT * FROM user WHERE user_id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$roleLc = strtolower(trim((string) ($user['role'] ?? $_SESSION['user_role'] ?? '')));
$isDeanWorkspace = $isDepartmentLogin || in_array($roleLc, ['dean', 'department','user'], true);

if (!$isDeanWorkspace) {
    header('Location: employee_dashboard.php');
    exit;
}

$deanActivePage = 'notifications.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications · WLC-SMART</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc34">
    <link rel="stylesheet" href="../assets/css/dean_dashboard.css?v=13">
    <link rel="stylesheet" href="../assets/css/requester_notifications.css?v=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/partials/dean_sidebar.php'; ?>

<main class="main-content dean-dashboard-home">
    <section class="dashboard-welcome dashboard-welcome--with-bell">
        <div class="dashboard-welcome__row">
            <div class="dashboard-welcome__copy">
                <h1 class="dashboard-welcome__title">Notifications</h1>
                <p class="dashboard-welcome__subtitle">Updates about your purchase orders and requests.</p>
            </div>
            <?php require __DIR__ . '/partials/requester_notifications_bell.php'; ?>
        </div>
    </section>
    <div class="notifications-page-list" id="notificationsPageList">
        <p class="requester-notifications-empty">Loading notifications…</p>
    </div>
</main>

<?php require __DIR__ . '/partials/dean_sidebar_scripts.php'; ?>
<script src="../assets/js/logout.js?v=wlc2"></script>
<script src="../assets/js/requester_notifications.js?v=2"></script>
<script src="../assets/js/requester_notifications_page.js?v=2"></script>
</body>
</html>
