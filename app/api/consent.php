<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/consent.php';
require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../models/department.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$isDepartmentLogin = isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'department' && !empty($_SESSION['department_id']);
$isUserLogin = !empty($_SESSION['user_id']);

if (!$isUserLogin && !$isDepartmentLogin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$version = trim((string) ($_POST['consent_version'] ?? CONSENT_VERSION));
if ($version === '') {
    $version = CONSENT_VERSION;
}

try {
    if ($isDepartmentLogin) {
        $departmentModel = new Department();
        $departmentModel->updateConsent((int) $_SESSION['department_id'], $version);
    } else {
        $userModel = new User();
        $userModel->updateConsent((int) $_SESSION['user_id'], $version);

        $db = Database::connect();
        $stmt = $db->prepare(
            "INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?, 'Consent Accepted', ?, NOW())"
        );
        $stmt->execute([(int) $_SESSION['user_id'], "Accepted consent version {$version}"]);
    }

    $_SESSION['consent_required'] = false;
    $_SESSION['has_consented'] = true;
    $_SESSION['consent_version_current'] = $version;

    echo json_encode(['success' => true, 'message' => 'Consent saved']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to save consent']);
}
