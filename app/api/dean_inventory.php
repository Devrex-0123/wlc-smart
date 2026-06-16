<?php
/**
 * Dean — read-only inventory for the dean's office only.
 * list_facilities: rooms/labs under the dean's office_id
 * list_inventory: inventory in one facility (verified same office)
 * get_components: catalog parts for one inventory row (verified same office)
 */
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/../helpers/dean_office_context.php';

function sendJson(array $p): void
{
    echo json_encode($p);
    exit;
}

function assertDeanWithOffice(PDO $db): array
{
    $ctx = cwirms_dean_api_require_context($db);

    return [
        'user_id' => cwirms_dean_api_actor_user_id($ctx),
        'office_id' => (int) $ctx['office_id'],
        'is_department_login' => !empty($ctx['is_department_login']),
    ];
}

function facilityBelongsToOffice(PDO $db, int $facilityId, int $officeId): bool
{
    if ($facilityId <= 0) {
        return false;
    }
    $st = $db->prepare('SELECT 1 FROM facilities WHERE facility_id = ? AND office_id = ? LIMIT 1');
    $st->execute([$facilityId, $officeId]);

    return (bool) $st->fetchColumn();
}

function inventoryInDeanOffice(PDO $db, int $inventoryId, int $officeId): bool
{
    if ($inventoryId <= 0) {
        return false;
    }
    $st = $db->prepare(
        'SELECT 1 FROM inventory inv
         INNER JOIN facilities f ON f.facility_id = inv.facility_id
         WHERE inv.inventory_id = ? AND f.office_id = ? LIMIT 1'
    );
    $st->execute([$inventoryId, $officeId]);

    return (bool) $st->fetchColumn();
}

function roleIsLaboratoryManager(string $role): bool
{
    $r = strtolower(trim($role));

    return $r === 'laboratory manager' || $r === 'laboratory_manager';
}

try {
    $db = Database::connect();
    $ctx = assertDeanWithOffice($db);
    $deptId = (int) $ctx['office_id'];
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

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

    $invSum = "(SELECT COALESCE(SUM(ic.quantity), 0) FROM inventory i2
        INNER JOIN item_components ic ON ic.parent_item_id = i2.inventory_id
        WHERE i2.facility_id = f.facility_id)";

    if ($action === 'get_lab_manager_settings') {
        $st = $db->prepare(
            'SELECT d.default_lab_manager_user_id, u.Email AS default_lab_manager_email
             FROM offices d
             LEFT JOIN user u ON u.user_id = d.default_lab_manager_user_id
             WHERE d.office_id = ?'
        );
        $st->execute([$deptId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $st2 = $db->prepare(
            "SELECT user_id, Email FROM user
             WHERE office_id = ?
             AND LOWER(TRIM(role)) IN ('laboratory manager', 'laboratory_manager')
             ORDER BY Email ASC"
        );
        $st2->execute([$deptId]);
        $candidates = $st2->fetchAll(PDO::FETCH_ASSOC);

        sendJson([
            'success' => true,
            'default_lab_manager_user_id' => isset($row['default_lab_manager_user_id']) && $row['default_lab_manager_user_id'] !== null
                ? (int) $row['default_lab_manager_user_id'] : null,
            'default_lab_manager_email' => $row['default_lab_manager_email'] ?? null,
            'lab_manager_candidates' => $candidates,
        ]);
    }

    if ($action === 'set_lab_manager_settings') {
        $raw = trim((string) ($_POST['lab_manager_user_id'] ?? ''));
        if ($raw === '' || $raw === '0') {
            $st = $db->prepare('UPDATE offices SET default_lab_manager_user_id = NULL WHERE office_id = ?');
            $st->execute([$deptId]);
            sendJson(['success' => true, 'message' => 'Default lab manager cleared.']);
        }
        $uid = (int) $raw;
        if ($uid <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid user']);
        }
        $st = $db->prepare('SELECT user_id, role, office_id FROM user WHERE user_id = ?');
        $st->execute([$uid]);
        $urow = $st->fetch(PDO::FETCH_ASSOC);
        if (!$urow || (int) ($urow['office_id'] ?? 0) !== $deptId) {
            sendJson(['success' => false, 'message' => 'That user is not in your office']);
        }
        if (!roleIsLaboratoryManager((string) ($urow['role'] ?? ''))) {
            sendJson(['success' => false, 'message' => 'Only Laboratory Manager accounts can be set as the default assignee']);
        }
        $up = $db->prepare('UPDATE offices SET default_lab_manager_user_id = ? WHERE office_id = ?');
        $up->execute([$uid, $deptId]);
        sendJson(['success' => true, 'message' => 'Default lab manager saved.']);
    }

    if ($action === 'list_facilities') {
        $stmt = $db->prepare("
            SELECT
                f.facility_id,
                d.`office_name` AS office_name,
                f.building,
                f.code,
                f.floor,
                f.laboratory,
                f.room,
                f.type,
                $invSum AS total_inventory
            FROM facilities f
            LEFT JOIN offices d ON f.office_id = d.office_id
            WHERE f.office_id = ?
            ORDER BY COALESCE(f.laboratory, f.room, f.building, d.`office_name`) ASC
        ");
        $stmt->execute([$deptId]);
        $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendJson(['success' => true, 'facilities' => $facilities, 'office_id' => $deptId]);
    }

    if ($action === 'list_inventory') {
        $facilityId = (int) ($_POST['facility_id'] ?? $_GET['facility_id'] ?? 0);
        if ($facilityId <= 0) {
            sendJson(['success' => false, 'message' => 'Facility is required']);
        }
        if (!facilityBelongsToOffice($db, $facilityId, $deptId)) {
            sendJson(['success' => false, 'message' => 'This facility is not in your office']);
        }

        $stmt = $db->prepare("
            SELECT
                inv.inventory_id,
                inv.name,
                inv.item_code,
                inv.facility_id,
                inv.acquisition_date,
                inv.remarks,
                inv.created_at,
                inv.user_id,
                fp.item_name,
                fp.quantity,
                fp.condition_status,
                fp.status,
                d.`office_name` AS office_name,
                u.Email AS assigned_user_email,
                f.laboratory,
                f.room,
                f.building,
                f.code AS facility_code
            FROM inventory inv
            $invFirstPartJoin
            LEFT JOIN facilities f ON inv.facility_id = f.facility_id
            LEFT JOIN offices d ON f.office_id = d.office_id
            LEFT JOIN user u ON inv.user_id = u.user_id
            WHERE inv.facility_id = ?
            ORDER BY inv.inventory_id DESC
        ");
        $stmt->execute([$facilityId]);
        $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendJson(['success' => true, 'inventory' => $inventory]);
    }

    if ($action === 'get_components') {
        $inventoryId = (int) ($_POST['inventory_id'] ?? $_GET['inventory_id'] ?? 0);
        if ($inventoryId <= 0) {
            sendJson(['success' => false, 'message' => 'Inventory is required']);
        }
        if (!inventoryInDeanOffice($db, $inventoryId, $deptId)) {
            sendJson(['success' => false, 'message' => 'This inventory is not in your office']);
        }

        $stmt = $db->prepare("
            SELECT
                ic.component_id,
                ic.component_item_id,
                ic.quantity,
                ic.condition_status,
                ic.status,
                ic.code,
                i.item_name
            FROM item_components ic
            LEFT JOIN items i ON i.item_id = ic.component_item_id
            WHERE ic.parent_item_id = ?
            ORDER BY ic.component_id ASC
        ");
        $stmt->execute([$inventoryId]);
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendJson(['success' => true, 'components' => $components]);
    }

    if ($action === 'facility_meta') {
        $facilityId = (int) ($_POST['facility_id'] ?? $_GET['facility_id'] ?? 0);
        if ($facilityId <= 0) {
            sendJson(['success' => false, 'message' => 'Facility is required']);
        }
        if (!facilityBelongsToOffice($db, $facilityId, $deptId)) {
            sendJson(['success' => false, 'message' => 'This facility is not in your office']);
        }
        $st = $db->prepare("
            SELECT f.facility_id, f.building, f.code, f.floor, f.laboratory, f.room, f.type,
                d.`office_name` AS office_name
            FROM facilities f
            LEFT JOIN offices d ON d.office_id = f.office_id
            WHERE f.facility_id = ?
        ");
        $st->execute([$facilityId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        sendJson(['success' => true, 'facility' => $row]);
    }

    sendJson(['success' => false, 'message' => 'Invalid action']);
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => 'Server error']);
}
