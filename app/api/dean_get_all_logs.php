<?php
session_start();
header("Content-Type: application/json");

require_once __DIR__ . '/../classes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$db = Database::connect();

// Get current user (Dean)
$stmt = $db->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify user is a Dean
if (strtolower($currentUser['role']) !== 'dean') {
    echo json_encode([
        "success" => false,
        "message" => "Only deans can access this endpoint"
    ]);
    exit;
}

$deanOfficeId = $currentUser['office_id'];

if (!$deanOfficeId) {
    echo json_encode([
        "success" => false,
        "message" => "Dean is not assigned to any office",
        "logs" => [],
        "count" => 0
    ]);
    exit;
}

try {
    // Get all logs for users in dean's office
    $stmt = $db->prepare("
        SELECT l.*, u.Email, u.role 
        FROM log_history l 
        LEFT JOIN user u ON l.user_id = u.user_id 
        WHERE u.office_id = ?
        ORDER BY l.time_in DESC
    ");
    $stmt->execute([$deanOfficeId]);
    $allLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format logs
    $formattedLogs = [];
    foreach ($allLogs as $log) {
        $timeIn = new DateTime($log['time_in']);
        $timeOut = new DateTime($log['time_out']);
        $duration = $timeIn->diff($timeOut);
        $isActive = $log['time_in'] == $log['time_out'];
        
        $formattedLogs[] = [
            'log_id' => $log['log_id'],
            'email' => $log['Email'] ?? 'Unknown',
            'role' => $log['role'] ?? 'N/A',
            'time_in' => $timeIn->format('M d, Y h:i A'),
            'time_out' => $isActive ? 'Active Session' : $timeOut->format('M d, Y h:i A'),
            'duration' => $isActive ? 'Ongoing' : sprintf('%02d:%02d:%02d', $duration->h, $duration->i, $duration->s),
            'status' => $isActive ? 'Active' : 'Completed',
            'is_active' => $isActive
        ];
    }
    
    echo json_encode([
        "success" => true,
        "logs" => $formattedLogs,
        "count" => count($formattedLogs)
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

exit;
?>
