<?php
session_start();
header("Content-Type: application/json");

require_once __DIR__ . "/../classes/db.php";

try {
    // Must be logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized"
        ]);
        exit;
    }

    $db = Database::connect();

    // Join facilities to get office name and include photo_url
    $stmt = $db->prepare("
        SELECT 
            u.user_id,
            u.Email AS email,
            u.full_name,
            u.contact_number,
            u.role,
            u.account_status,
            u.last_login,
            u.has_consented,
            u.consent_version,
            u.consent_date,
            u.created_at,
            u.updated_at,
            u.office_id,
            u.photo_url,
            d.`office_name` AS office_name
        FROM user u
        LEFT JOIN offices d ON u.office_id = d.office_id
        WHERE u.deleted_at IS NULL
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();

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
