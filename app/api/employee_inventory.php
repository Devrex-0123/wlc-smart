<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/db.php';

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $db = Database::connect();
    $user_id = $_SESSION['user_id'];

    $invFirstPartJoin = "
            LEFT JOIN (
                SELECT ic.parent_item_id AS _fp_pid, ic.component_item_id, ic.quantity, ic.condition_status, ic.status, ic.photo_url, i.item_name
                FROM item_components ic
                INNER JOIN items i ON i.item_id = ic.component_item_id
                INNER JOIN (
                    SELECT parent_item_id, MIN(component_id) AS _fcid
                    FROM item_components
                    GROUP BY parent_item_id
                ) z ON z.parent_item_id = ic.parent_item_id AND z._fcid = ic.component_id
            ) fp ON fp._fp_pid = inv.inventory_id
    ";

    $stmt = $db->prepare("
        SELECT 
            inv.inventory_id,
            inv.name,
            inv.item_code,
            inv.facility_id,
            inv.acquisition_date,
            inv.remarks,
            inv.created_at,
            fp.component_item_id AS item_id,
            fp.item_name,
            fp.quantity,
            fp.condition_status,
            fp.status,
            fp.photo_url,
            d.`office_name` AS office_name
        FROM inventory inv
        $invFirstPartJoin
        LEFT JOIN facilities f ON inv.facility_id = f.facility_id
        LEFT JOIN offices d ON f.office_id = d.office_id
        WHERE inv.user_id = ?
        ORDER BY inv.inventory_id DESC
    ");
    $stmt->execute([$user_id]);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'inventory' => $inventory,
        'total' => count($inventory)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
