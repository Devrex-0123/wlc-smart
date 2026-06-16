<?php
session_start();
header("Content-Type: application/json");

require_once __DIR__ . "/../classes/db.php";
require_once __DIR__ . '/../helpers/dean_office_context.php';

try {
    $db = Database::connect();
    $deanApiCtx = cwirms_dean_api_require_context($db);
    $deanOfficeId = (int) $deanApiCtx['office_id'];
    $excludeUserId = !empty($deanApiCtx['is_department_login'])
        ? cwirms_dean_api_actor_user_id($deanApiCtx)
        : (int) ($_SESSION['user_id'] ?? 0);

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
    $stmt->execute([$deanOfficeId, $excludeUserId]);

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
