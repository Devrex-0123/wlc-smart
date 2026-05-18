<?php
session_start();
header("Content-Type: application/json");

require_once __DIR__ . '/../classes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$db = Database::connect();
$userId = $_SESSION['user_id'];

try {
    // Check if there's already an active session (time_in = time_out) for this user
    $checkStmt = $db->prepare("
        SELECT log_id FROM log_history 
        WHERE user_id = ? AND time_in = time_out 
        ORDER BY time_in DESC LIMIT 1
    ");
    $checkStmt->execute([$userId]);
    $existingLog = $checkStmt->fetch();
    
    if ($existingLog) {
        // Already have an active session, don't create duplicate
        $stmt = $db->prepare("SELECT * FROM log_history WHERE log_id = ?");
        $stmt->execute([$existingLog['log_id']]);
        $log = $stmt->fetch();
        
        echo json_encode([
            "success" => true,
            "message" => "Active session already exists",
            "log_id" => $existingLog['log_id'],
            "time_in" => $log['time_in'],
            "duplicate" => true
        ]);
    } else {
        // Insert new time_in record
        $stmt = $db->prepare("INSERT INTO log_history (user_id, time_in, time_out) VALUES (?, NOW(), NOW())");
        $stmt->execute([$userId]);
        
        $logId = $db->lastInsertId();
        
        // Get the inserted record
        $stmt = $db->prepare("SELECT * FROM log_history WHERE log_id = ?");
        $stmt->execute([$logId]);
        $log = $stmt->fetch();
        
        echo json_encode([
            "success" => true,
            "message" => "Time in recorded",
            "log_id" => $logId,
            "time_in" => $log['time_in']
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
exit;

