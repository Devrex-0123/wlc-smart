<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$isUserLogin = !empty($_SESSION['user_id']);
$isDepartmentLogin = isset($_SESSION['login_type'])
    && $_SESSION['login_type'] === 'department'
    && !empty($_SESSION['department_id']);

if (!$isUserLogin && !$isDepartmentLogin) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    if ($isUserLogin) {
        require_once __DIR__ . '/../classes/db.php';
        require_once __DIR__ . '/../models/user.php';

        $db = Database::connect();
        $userId = (int) $_SESSION['user_id'];

        $stmt = $db->prepare('
            SELECT log_id FROM log_history
            WHERE user_id = ? AND (time_out IS NULL OR time_out = time_in)
            ORDER BY time_in DESC
            LIMIT 1
        ');
        $stmt->execute([$userId]);
        $activeLog = $stmt->fetch();

        if ($activeLog) {
            $updateStmt = $db->prepare('UPDATE log_history SET time_out = NOW() WHERE log_id = ?');
            $updateStmt->execute([$activeLog['log_id']]);
        }

        $userModel = new User();
        $userModel->clearRememberTokenByUserId($userId);
    }

    session_destroy();

    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully',
    ]);
} catch (Exception $e) {
    session_destroy();
    echo json_encode([
        'success' => false,
        'message' => 'Logout failed (time_out may not be recorded)',
    ]);
}
exit;
