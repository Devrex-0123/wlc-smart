<?php
session_start();
header("Content-Type: application/json");

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/../models/user.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$db = Database::connect();
$userId = $_SESSION['user_id'];

try {
    // Update active log session if exists
    $stmt = $db->prepare("
        SELECT log_id FROM log_history 
        WHERE user_id = ? AND (time_out IS NULL OR time_out = time_in)
        ORDER BY time_in DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $activeLog = $stmt->fetch();

    if ($activeLog) {
        $updateStmt = $db->prepare("UPDATE log_history SET time_out = NOW() WHERE log_id = ?");
        $updateStmt->execute([$activeLog['log_id']]);
    }

    // Clear remember token on logout
    $userModel = new User();
    $userModel->clearRememberTokenByUserId((int)$userId);

    session_destroy();

    echo json_encode([
        "success" => true,
        "message" => "Logged out successfully"
    ]);
} catch (Exception $e) {
    session_destroy();
    echo json_encode([
        "success" => false,
        "message" => "Logout failed (time_out may not be recorded)"
    ]);
}
exit;
