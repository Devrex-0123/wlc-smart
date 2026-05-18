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
        "activities" => [],
        "count" => 0
    ]);
    exit;
}

try {
    // Get activity logs for users in dean's office
    $stmt = $db->prepare("
        SELECT 
            a.activity_id,
            a.activity_type,
            a.description,
            a.created_at,
            u.Email AS user_email,
            u.role AS user_role
        FROM user_activity a
        LEFT JOIN user u ON a.user_id = u.user_id
        WHERE u.office_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$deanOfficeId]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = [];
    foreach ($records as $row) {
        $time = new DateTime($row['created_at']);
        $formatted[] = [
            "activity_id" => $row['activity_id'],
            "user"        => $row['user_email'] ?? 'Unknown',
            "role"        => $row['user_role'] ?? 'N/A',
            "type"        => $row['activity_type'],
            "description" => $row['description'],
            "time"        => $time->format("M d, Y h:i A")
        ];
    }

    echo json_encode([
        "success" => true,
        "activities" => $formatted,
        "count" => count($formatted)
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

exit;
?>
