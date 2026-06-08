<?php
session_start();
header("Content-Type: application/json");

require_once __DIR__ . '/../classes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$db = Database::connect();

try {
    $todaysActivities = (int) $db->query("
        SELECT COUNT(*)
        FROM user_activity
        WHERE DATE(created_at) = CURDATE()
    ")->fetchColumn();

    $activeUsers = (int) $db->query("
        SELECT COUNT(DISTINCT user_id)
        FROM log_history
        WHERE DATE(time_in) = CURDATE()
    ")->fetchColumn();

    $failedLoginAttempts = (int) $db->query("
        SELECT COUNT(*)
        FROM user_activity
        WHERE DATE(created_at) = CURDATE()
          AND activity_type = 'Failed Login'
    ")->fetchColumn();

    $totalAuditRecords = (int) $db->query("
        SELECT
            (SELECT COUNT(*) FROM user_activity) +
            (SELECT COUNT(*) FROM log_history)
    ")->fetchColumn();

    echo json_encode([
        "success" => true,
        "summary" => [
            "todays_activities" => $todaysActivities,
            "active_users" => $activeUsers,
            "failed_login_attempts" => $failedLoginAttempts,
            "total_audit_records" => $totalAuditRecords,
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

exit;
