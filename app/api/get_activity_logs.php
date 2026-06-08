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
    $totalCount = (int) $db->query("SELECT COUNT(*) FROM user_activity")->fetchColumn();

    $stmt = $db->query("
        SELECT 
            a.activity_id,
            a.user_id,
            a.activity_type,
            a.description,
            a.created_at,
            u.Email AS user_email,
            u.full_name AS user_full_name,
            u.role AS user_role
        FROM user_activity a
        LEFT JOIN user u ON a.user_id = u.user_id
        ORDER BY a.created_at DESC, a.activity_id DESC
    ");

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = [];
    foreach ($records as $row) {
        $time = new DateTime($row['created_at']);
        $userLabel = trim((string)($row['user_email'] ?? ''));
        if ($userLabel === '') {
            $userLabel = trim((string)($row['user_full_name'] ?? ''));
        }
        if ($userLabel === '') {
            $userLabel = 'Unknown';
        }

        $formatted[] = [
            "activity_id" => $row['activity_id'],
            "user"        => $userLabel,
            "role"        => $row['user_role'] ?? 'N/A',
            "type"        => $row['activity_type'],
            "description" => $row['description'],
            "time"        => $time->format("M d, Y h:i A")
        ];
    }

    echo json_encode([
        "success" => true,
        "activities" => $formatted,
        "count" => count($formatted),
        "total_count" => $totalCount
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

exit;
