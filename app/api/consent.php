<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/../models/user.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$version = trim((string)($_POST['consent_version'] ?? 'v1.0'));
if ($version === '') {
    $version = 'v1.0';
}

try {
    $userModel = new User();
    $userModel->updateConsent((int)$_SESSION['user_id'], $version);
    $_SESSION['consent_required'] = false;

    $db = Database::connect();
    $stmt = $db->prepare("INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?, 'Consent Accepted', ?, NOW())");
    $stmt->execute([(int)$_SESSION['user_id'], "Accepted consent version {$version}"]);

    echo json_encode(['success' => true, 'message' => 'Consent saved']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to save consent']);
}

