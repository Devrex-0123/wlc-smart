<?php
session_start();
header("Content-Type: application/json");
require_once __DIR__ . '/../classes/db.php';

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']);
        exit;
    }

    $db = Database::connect();
    $action = $_POST['action'] ?? '';

    function saveOfficePhoto(?array $file): ?string {
        if (!$file || !isset($file['tmp_name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmp = (string) $file['tmp_name'];
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            throw new RuntimeException('Office image must be less than 5MB.');
        }
        $mime = mime_content_type($tmp) ?: '';
        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        if (!isset($extMap[$mime])) {
            throw new RuntimeException('Office image must be JPG, PNG, GIF, or WEBP.');
        }
        $ext = $extMap[$mime];
        $relDir = 'uploads/offices';
        $absDir = __DIR__ . '/public/' . $relDir;
        if (!is_dir($absDir) && !mkdir($absDir, 0777, true) && !is_dir($absDir)) {
            throw new RuntimeException('Could not create office uploads directory.');
        }
        $fileName = 'dept_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $absPath = $absDir . '/' . $fileName;
        if (!move_uploaded_file($tmp, $absPath)) {
            throw new RuntimeException('Failed to save office image.');
        }
        return 'app/api/public/' . $relDir . '/' . $fileName;
    }

    // ---------------- Activity Logging Function ----------------
    function logActivity($user_id, $activity_type, $description) {
        global $db;
        $stmt = $db->prepare("INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $activity_type, $description]);
    }

    // List offices with counts (paginated; search + sort apply before LIMIT)
    if ($action === 'list_offices') {
        $page = max(1, (int)($_POST['page'] ?? 1));
        $per_page = min(50, max(1, (int)($_POST['per_page'] ?? 5)));
        $offset = ($page - 1) * $per_page;
        $q = trim($_POST['q'] ?? '');
        $sort = $_POST['sort'] ?? '';

        $allowedSort = [
            'name-asc' => 'd.`office_name` ASC',
            'name-desc' => 'd.`office_name` DESC',
            'labs-asc' => 'total_labs ASC, d.`office_name` ASC',
            'labs-desc' => 'total_labs DESC, d.`office_name` ASC',
            'rooms-asc' => 'total_rooms ASC, d.`office_name` ASC',
            'rooms-desc' => 'total_rooms DESC, d.`office_name` ASC',
            'total-asc' => '(COALESCE(total_labs,0) + COALESCE(total_rooms,0)) ASC, d.`office_name` ASC',
            'total-desc' => '(COALESCE(total_labs,0) + COALESCE(total_rooms,0)) DESC, d.`office_name` ASC',
        ];
        $orderSql = $allowedSort[$sort] ?? 'd.office_id ASC';

        if ($q === '') {
            $total = (int)$db->query("SELECT COUNT(*) FROM offices")->fetchColumn();
        } else {
            $cstmt = $db->prepare("SELECT COUNT(*) FROM offices d WHERE d.`office_name` LIKE ?");
            $cstmt->execute(['%' . $q . '%']);
            $total = (int)$cstmt->fetchColumn();
        }

        $whereSql = ($q === '') ? '1=1' : 'd.`office_name` LIKE ?';
        $params = [];
        if ($q !== '') {
            $params[] = '%' . $q . '%';
        }
        $lim = (int)$per_page;
        $off = (int)$offset;
        $sql = "SELECT d.office_id, d.office_name, d.type, d.photo_url,
            (SELECT COUNT(*) FROM facilities f WHERE f.office_id = d.office_id AND COALESCE(f.laboratory,'') != '') AS total_labs,
            (SELECT COUNT(*) FROM facilities f WHERE f.office_id = d.office_id AND COALESCE(f.room,'') != '') AS total_rooms,
            (SELECT COUNT(*) FROM inventory i JOIN facilities f2 ON i.facility_id = f2.facility_id WHERE f2.office_id = d.office_id) AS total_inventory
            FROM offices d
            WHERE $whereSql
            ORDER BY $orderSql
            LIMIT $lim OFFSET $off";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_pages = $total > 0 ? (int)ceil($total / $per_page) : 1;
        echo json_encode([
            'success' => true,
            'offices' => $offices,
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $total_pages,
        ]);
        exit;
    }

    // Get single office
    if ($action === 'get_office') {
        $dept_id = $_POST['office_id'] ?? 0;
        $stmt = $db->prepare("SELECT office_id, office_name, type, photo_url FROM offices WHERE office_id = ?");
        $stmt->execute([$dept_id]);
        $dept = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'office' => $dept]);
        exit;
    }

    // Add office
    if ($action === 'add_office') {
        $dept_name = trim($_POST['office_name'] ?? '');
        $office_type = trim($_POST['type'] ?? '');
        $allowedOfficeTypes = ['academic', 'administrative', 'executive'];

        if (!$dept_name) {
            echo json_encode(['success' => false, 'message' => 'Office name is required']);
            exit;
        }
        if (!in_array($office_type, $allowedOfficeTypes, true)) {
            echo json_encode(['success' => false, 'message' => 'Valid office type is required']);
            exit;
        }

        // Check for duplicates (case-insensitive)
        $stmtChk = $db->prepare("SELECT office_id FROM offices WHERE `office_name` = ? LIMIT 1");
        $stmtChk->execute([$dept_name]);
        $exists = $stmtChk->fetch(PDO::FETCH_ASSOC);
        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'Office already exists']);
            exit;
        }

        $photoUrl = saveOfficePhoto($_FILES['office_photo'] ?? null);
        $stmt = $db->prepare("INSERT INTO offices (office_name, type, photo_url) VALUES (?, ?, ?)");
        $stmt->execute([$dept_name, $office_type, $photoUrl]);

        logActivity($_SESSION['user_id'], 'Add Office', "Added office: $dept_name ($office_type)");

        echo json_encode(['success' => true, 'message' => 'Office added successfully']);
        exit;
    }

    // Edit office
    if ($action === 'edit_office') {
        $dept_id = $_POST['office_id'] ?? 0;
        $dept_name = trim($_POST['office_name'] ?? '');
        $office_type = trim($_POST['type'] ?? '');
        $allowedOfficeTypes = ['academic', 'administrative', 'executive'];

        if (!$dept_id || !$dept_name) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        if (!in_array($office_type, $allowedOfficeTypes, true)) {
            echo json_encode(['success' => false, 'message' => 'Valid office type is required']);
            exit;
        }

        // Check for duplicate name in other records
        $stmtDup = $db->prepare("SELECT office_id FROM offices WHERE `office_name` = ? AND office_id <> ? LIMIT 1");
        $stmtDup->execute([$dept_name, $dept_id]);
        $dup = $stmtDup->fetch(PDO::FETCH_ASSOC);
        if ($dup) {
            echo json_encode(['success' => false, 'message' => 'Another office already uses that name']);
            exit;
        }

        $stmtOld = $db->prepare("SELECT `office_name` AS office_name, photo_url FROM offices WHERE office_id = ?");
        $stmtOld->execute([$dept_id]);
        $old = $stmtOld->fetch(PDO::FETCH_ASSOC) ?: [];
        $oldName = $old['office_name'] ?? 'Unknown';
        $nextPhoto = $old['photo_url'] ?? null;
        $uploadedPhoto = saveOfficePhoto($_FILES['office_photo'] ?? null);
        if ($uploadedPhoto !== null) {
            $nextPhoto = $uploadedPhoto;
        }

        $stmt = $db->prepare("UPDATE offices SET office_name = ?, type = ?, photo_url = ? WHERE office_id = ?");
        $stmt->execute([$dept_name, $office_type, $nextPhoto, $dept_id]);

        logActivity($_SESSION['user_id'], 'Edit Office', "Updated office: $oldName → $dept_name ($office_type)");

        echo json_encode(['success' => true, 'message' => 'Office updated successfully']);
        exit;
    }

    // Delete office (prevent if facilities exist)
    if ($action === 'delete_office') {
        $dept_id = $_POST['office_id'] ?? 0;
        if (!$dept_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid office id']);
            exit;
        }

        $stmtChk = $db->prepare("SELECT COUNT(*) AS cnt FROM facilities WHERE office_id = ?");
        $stmtChk->execute([$dept_id]);
        $cnt = $stmtChk->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
        if ($cnt > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete office that has facilities. Remove facilities first.']);
            exit;
        }

        $stmtOld = $db->prepare("SELECT `office_name` AS office_name FROM offices WHERE office_id = ?");
        $stmtOld->execute([$dept_id]);
        $oldName = $stmtOld->fetch(PDO::FETCH_ASSOC)['office_name'] ?? 'Unknown';

        $stmt = $db->prepare("DELETE FROM offices WHERE office_id = ?");
        $stmt->execute([$dept_id]);

        logActivity($_SESSION['user_id'], 'Delete Office', "Deleted office: $oldName");

        echo json_encode(['success' => true, 'message' => 'Office deleted successfully']);
        exit;
    }

    // List facilities for a office
    if ($action === 'list_facilities') {
        $dept_id = $_POST['office_id'] ?? 0;
        $stmt = $db->prepare("SELECT f.facility_id, f.building, f.code, f.floor, f.laboratory, f.room, f.type, f.date_created,
            (SELECT COUNT(*) FROM inventory i WHERE i.facility_id = f.facility_id) AS total_inventory
            FROM facilities f WHERE f.office_id = ? ORDER BY f.facility_id ASC");
        $stmt->execute([$dept_id]);
        $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'facilities' => $facilities]);
        exit;
    }

    // Add facility
    if ($action === 'add_facility') {
        $dept_id = $_POST['office_id'] ?? 0;
        $building = trim($_POST['building'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $floor = trim($_POST['floor'] ?? '');
        $laboratory = trim($_POST['laboratory'] ?? '');
        $room = trim($_POST['room'] ?? '');
        $type = trim($_POST['type'] ?? '');

        if (!$dept_id) {
            echo json_encode(['success' => false, 'message' => 'Office required']);
            exit;
        }
        if ($type === '') {
            echo json_encode(['success' => false, 'message' => 'Facility type is required']);
            exit;
        }

        $date_created = date('Y-m-d H:i:s');

        try {
            $stmt = $db->prepare("INSERT INTO facilities (office_id, building, code, floor, laboratory, room, type, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$dept_id, $building, $code, $floor, $laboratory, $room, $type, $date_created]);

            logActivity($_SESSION['user_id'], 'Add Facility', "Added facility in dept {$dept_id}: {$building} / {$room} ({$code})");

            echo json_encode(['success' => true, 'message' => 'Facility added successfully']);
            exit;
        } catch (PDOException $e) {
            // Helpful message for common duplicate primary error (likely missing AUTO_INCREMENT)
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo json_encode(['success' => false, 'message' => 'Database error: duplicate primary key. Ensure `facilities.facility_id` is AUTO_INCREMENT. Run migration: app/migrations/20260129_fix_facilities_autoinc.sql']);
                exit;
            }
            throw $e; // rethrow other DB errors
        }
    }

    // Edit facility
    if ($action === 'edit_facility') {
        $facility_id = $_POST['facility_id'] ?? 0;
        $building = trim($_POST['building'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $floor = trim($_POST['floor'] ?? '');
        $laboratory = trim($_POST['laboratory'] ?? '');
        $room = trim($_POST['room'] ?? '');
        $type = trim($_POST['type'] ?? '');

        if (!$facility_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid facility id']);
            exit;
        }
        if ($type === '') {
            echo json_encode(['success' => false, 'message' => 'Facility type is required']);
            exit;
        }

        $stmtOld = $db->prepare("SELECT CONCAT_WS(' ', building, room) AS label FROM facilities WHERE facility_id = ?");
        $stmtOld->execute([$facility_id]);
        $oldLabel = $stmtOld->fetch(PDO::FETCH_ASSOC)['label'] ?? 'Unknown';

        $stmt = $db->prepare("UPDATE facilities SET building=?, code=?, floor=?, laboratory=?, room=?, type=? WHERE facility_id = ?");
        $stmt->execute([$building, $code, $floor, $laboratory, $room, $type, $facility_id]);

        logActivity($_SESSION['user_id'], 'Edit Facility', "Updated facility: {$oldLabel}");

        echo json_encode(['success' => true, 'message' => 'Facility updated successfully']);
        exit;
    }

    // Delete facility
    if ($action === 'delete_facility') {
        $facility_id = $_POST['facility_id'] ?? 0;
        if (!$facility_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid facility id']);
            exit;
        }

        $stmtOld = $db->prepare("SELECT CONCAT_WS(' ', building, room) AS label FROM facilities WHERE facility_id = ?");
        $stmtOld->execute([$facility_id]);
        $oldLabel = $stmtOld->fetch(PDO::FETCH_ASSOC)['label'] ?? 'Unknown';

        $stmt = $db->prepare("DELETE FROM facilities WHERE facility_id = ?");
        $stmt->execute([$facility_id]);

        logActivity($_SESSION['user_id'], 'Delete Facility', "Deleted facility: {$oldLabel}");

        echo json_encode(['success' => true, 'message' => 'Facility deleted successfully']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

