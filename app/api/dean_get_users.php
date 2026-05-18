<?php
session_start();
header("Content-Type: application/json");

require_once __DIR__ . "/../classes/db.php";

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized"
        ]);
        exit;
    }

    $db = Database::connect();

    // Get current user
    $stmt = $db->prepare("SELECT * FROM user WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // Deans and GSD officers may list office users (same office scope)
    $roleLc = strtolower(trim((string) ($currentUser['role'] ?? '')));
    if ($roleLc !== 'dean' && $roleLc !== 'gsd officer') {
        echo json_encode([
            "success" => false,
            "message" => "You do not have access to this endpoint"
        ]);
        exit;
    }

    $deanOfficeId = $currentUser['office_id'];

    if (!$deanOfficeId) {
        echo json_encode([
            "success" => false,
            "message" => "You are not assigned to any office",
            "users" => []
        ]);
        exit;
    }

    // Get all users in dean's office
    $stmt = $db->prepare("
        SELECT 
            u.user_id,
            u.Email AS email,
            u.role,
            u.created_at,
            u.photo_url
        FROM user u
        WHERE u.office_id = ?
        AND u.user_id != ?
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$deanOfficeId, $_SESSION['user_id']]);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "users" => $users
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
