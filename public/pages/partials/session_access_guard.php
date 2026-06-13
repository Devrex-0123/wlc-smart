<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$isDepartmentLogin = isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'department';
$isUserLogin = isset($_SESSION['user_id']);

if (!$isUserLogin && !($isDepartmentLogin && !empty($_SESSION['department_id']))) {
    header("Location: ../../index.php");
    exit;
}

require_once __DIR__ . '/../../../app/classes/db.php';
$db = Database::connect();

if ($isDepartmentLogin) {
    $stmt = $db->prepare("
        SELECT department_id, department_status
        FROM departments
        WHERE department_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $_SESSION['department_id']]);
    $guardDepartment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$guardDepartment || strcasecmp((string) ($guardDepartment['department_status'] ?? ''), 'Active') !== 0) {
        session_destroy();
        header("Location: ../../index.php");
        exit;
    }

    return;
}

$stmt = $db->prepare("SELECT user_id, account_status, deleted_at FROM user WHERE user_id = ? LIMIT 1");
$stmt->execute([(int) $_SESSION['user_id']]);
$guardUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guardUser || !empty($guardUser['deleted_at'])) {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

$guardStatus = strtolower(trim((string) ($guardUser['account_status'] ?? 'active')));
if ($guardStatus === 'disabled' || $guardStatus === 'locked') {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}
