<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

require_once __DIR__ . '/../../../app/classes/db.php';
$db = Database::connect();
$stmt = $db->prepare("SELECT user_id, account_status, deleted_at FROM user WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$_SESSION['user_id']]);
$guardUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guardUser || !empty($guardUser['deleted_at'])) {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

$guardStatus = strtolower(trim((string)($guardUser['account_status'] ?? 'active')));
if ($guardStatus === 'disabled' || $guardStatus === 'locked') {
    session_destroy();
    header("Location: ../../index.php");
    exit;
}

