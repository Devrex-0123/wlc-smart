<?php
session_start();
header("Content-Type: application/json");
require_once __DIR__ . '/../classes/db.php';

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $db = Database::connect();
    $action = $_POST['action'] ?? '';

    function logActivity($user_id, $activity_type, $description) {
        global $db;
        $stmt = $db->prepare("INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $activity_type, $description]);
    }

    // List all units
    if ($action === 'list') {
        $stmt = $db->prepare("SELECT unit_id, unit_name, unit_abbreviation, unit_description, created_at FROM units ORDER BY unit_name ASC");
        $stmt->execute();
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'units' => $units]);
        exit;
    }

    // Get single unit
    if ($action === 'get') {
        $unit_id = (int)($_POST['unit_id'] ?? 0);
        if ($unit_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid unit ID.']);
            exit;
        }
        $stmt = $db->prepare("SELECT unit_id, unit_name, unit_abbreviation, unit_description FROM units WHERE unit_id = ?");
        $stmt->execute([$unit_id]);
        $unit = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$unit) {
            echo json_encode(['success' => false, 'message' => 'Unit not found.']);
            exit;
        }
        echo json_encode(['success' => true, 'unit' => $unit]);
        exit;
    }

    // Add unit
    if ($action === 'add') {
        $unit_name        = trim($_POST['unit_name'] ?? '');
        $unit_abbreviation = strtolower(trim($_POST['unit_abbreviation'] ?? ''));
        $unit_description  = trim($_POST['unit_description'] ?? '');

        if ($unit_name === '') {
            echo json_encode(['success' => false, 'message' => 'Unit name is required.']);
            exit;
        }
        if (strlen($unit_name) > 50) {
            echo json_encode(['success' => false, 'message' => 'Unit name must not exceed 50 characters.']);
            exit;
        }
        if ($unit_abbreviation === '') {
            echo json_encode(['success' => false, 'message' => 'Abbreviation is required.']);
            exit;
        }
        if (strlen($unit_abbreviation) > 20) {
            echo json_encode(['success' => false, 'message' => 'Abbreviation must not exceed 20 characters.']);
            exit;
        }
        if (strlen($unit_description) > 50) {
            echo json_encode(['success' => false, 'message' => 'Description must not exceed 50 characters.']);
            exit;
        }

        // Duplicate check on name
        $stmtChk = $db->prepare("SELECT unit_id FROM units WHERE LOWER(unit_name) = LOWER(?) LIMIT 1");
        $stmtChk->execute([$unit_name]);
        if ($stmtChk->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'A unit with that name already exists.']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO units (unit_name, unit_abbreviation, unit_description) VALUES (?, ?, ?)");
        $stmt->execute([
            $unit_name,
            $unit_abbreviation,
            $unit_description !== '' ? $unit_description : null,
        ]);

        logActivity($_SESSION['user_id'], 'Add Unit', "Added unit: $unit_name ($unit_abbreviation)");
        echo json_encode(['success' => true, 'message' => "Unit \"$unit_name\" added successfully."]);
        exit;
    }

    // Edit unit
    if ($action === 'edit') {
        $unit_id           = (int)($_POST['unit_id'] ?? 0);
        $unit_name         = trim($_POST['unit_name'] ?? '');
        $unit_abbreviation = strtolower(trim($_POST['unit_abbreviation'] ?? ''));
        $unit_description  = trim($_POST['unit_description'] ?? '');

        if ($unit_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid unit ID.']);
            exit;
        }
        if ($unit_name === '') {
            echo json_encode(['success' => false, 'message' => 'Unit name is required.']);
            exit;
        }
        if (strlen($unit_name) > 50) {
            echo json_encode(['success' => false, 'message' => 'Unit name must not exceed 50 characters.']);
            exit;
        }
        if ($unit_abbreviation === '') {
            echo json_encode(['success' => false, 'message' => 'Abbreviation is required.']);
            exit;
        }
        if (strlen($unit_abbreviation) > 20) {
            echo json_encode(['success' => false, 'message' => 'Abbreviation must not exceed 20 characters.']);
            exit;
        }
        if (strlen($unit_description) > 50) {
            echo json_encode(['success' => false, 'message' => 'Description must not exceed 50 characters.']);
            exit;
        }

        // Duplicate check excluding self
        $stmtChk = $db->prepare("SELECT unit_id FROM units WHERE LOWER(unit_name) = LOWER(?) AND unit_id != ? LIMIT 1");
        $stmtChk->execute([$unit_name, $unit_id]);
        if ($stmtChk->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'Another unit with that name already exists.']);
            exit;
        }

        $stmt = $db->prepare("UPDATE units SET unit_name = ?, unit_abbreviation = ?, unit_description = ?, updated_at = NOW() WHERE unit_id = ?");
        $stmt->execute([
            $unit_name,
            $unit_abbreviation,
            $unit_description !== '' ? $unit_description : null,
            $unit_id,
        ]);

        logActivity($_SESSION['user_id'], 'Edit Unit', "Updated unit ID $unit_id: $unit_name ($unit_abbreviation)");
        echo json_encode(['success' => true, 'message' => "Unit \"$unit_name\" updated successfully."]);
        exit;
    }

    // Delete unit
    if ($action === 'delete') {
        $unit_id = (int)($_POST['unit_id'] ?? 0);
        if ($unit_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid unit ID.']);
            exit;
        }

        // Guard: unit referenced in canvass records
        $stmtRef = $db->prepare("SELECT canvass_detail_id FROM requisition_canvass_detail WHERE unit_id = ? LIMIT 1");
        $stmtRef->execute([$unit_id]);
        if ($stmtRef->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete: this unit is used in existing canvass records.']);
            exit;
        }

        // Fetch name for log before deleting
        $stmtName = $db->prepare("SELECT unit_name FROM units WHERE unit_id = ?");
        $stmtName->execute([$unit_id]);
        $row = $stmtName->fetch(PDO::FETCH_ASSOC);
        $unit_name = $row['unit_name'] ?? "ID $unit_id";

        $stmt = $db->prepare("DELETE FROM units WHERE unit_id = ?");
        $stmt->execute([$unit_id]);

        logActivity($_SESSION['user_id'], 'Delete Unit', "Deleted unit: $unit_name (ID $unit_id)");
        echo json_encode(['success' => true, 'message' => "Unit \"$unit_name\" deleted successfully."]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
