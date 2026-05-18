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

    // ==================== FILE UPLOAD FUNCTION ====================
    function uploadFile($file, $upload_dir = null) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        if ($upload_dir === null) {
            $upload_dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'inventory' . DIRECTORY_SEPARATOR;
        }

        // Ensure upload directory exists
        if (!is_dir($upload_dir)) {
            if (!@mkdir($upload_dir, 0755, true)) {
                error_log("Failed to create upload directory: $upload_dir");
                return null;
            }
        }

        $filename = time() . '_' . uniqid() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "", basename($file['name']));
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Ensure file is readable
            @chmod($filepath, 0644);
            // Return relative path for web access (without public/ prefix)
            return 'uploads/inventory/' . $filename;
        }
        
        error_log("Failed to move uploaded file to: $filepath");
        return null;
    }

    // ==================== ACTIVITY LOGGING FUNCTION ====================
    function logActivity($user_id, $activity_type, $description) {
        global $db;
        try {
            $stmt = $db->prepare("INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user_id, $activity_type, $description]);
        } catch (Throwable $e) {
            error_log('inventory_management logActivity: ' . $e->getMessage());
        }
    }

    // First catalog part per inventory (for list/detail summary columns)
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

    if($action === 'add' || $action === 'edit'){
        $name = trim($_POST['name'] ?? '');
        $item_code = trim($_POST['item_code'] ?? '');
        $facility_id = trim($_POST['facility_id'] ?? '');
        $acquisition_date = trim($_POST['acquisition_date'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $inventory_id = $_POST['inventory_id'] ?? null;
        $request_id = trim($_POST['request_id'] ?? '0');
        if ($request_id === '') {
            $request_id = '0';
        }
        $components = $_POST['components'] ?? '[]';
        $components_data = json_decode($components, true);
        if (!is_array($components_data)) {
            $components_data = [];
        }

        if(!$name || !$facility_id || !$item_code){
            echo json_encode(['success'=>false,'message'=>'Name, facility, and item code are required']);
            exit;
        }

        if ($action === 'add' && count($components_data) < 1) {
            echo json_encode(['success'=>false,'message'=>'Add at least one catalog item (part) for this inventory.']);
            exit;
        }

        if ($action === 'add') {
            $has_part_id = false;
            foreach ($components_data as $row) {
                if (!empty($row['item_id'])) {
                    $has_part_id = true;
                    break;
                }
            }
            if (!$has_part_id) {
                echo json_encode(['success'=>false,'message'=>'Each part must use a catalog item from the list.']);
                exit;
            }
        }

        if($action === 'add'){
            $seen_component_items = [];
            foreach ($components_data as $row) {
                $cid = isset($row['item_id']) ? trim((string)$row['item_id']) : '';
                if ($cid === '') {
                    continue;
                }
                if (isset($seen_component_items[$cid])) {
                    echo json_encode(['success'=>false,'message'=>'Each catalog item can only appear once per inventory. Increase quantity if you need more of the same part.']);
                    exit;
                }
                $seen_component_items[$cid] = true;
            }
        }

        $rawAssign = trim((string) ($_POST['assigned_user_id'] ?? ''));
        if ($rawAssign === '0') {
            $user_id_value = null;
        } elseif ($rawAssign !== '') {
            $user_id_value = $rawAssign;
        } elseif ($action === 'add') {
            $stDef = $db->prepare('SELECT d.default_lab_manager_user_id FROM facilities f LEFT JOIN offices d ON d.office_id = f.office_id WHERE f.facility_id = ? LIMIT 1');
            $stDef->execute([(int) $facility_id]);
            $defRow = $stDef->fetch(PDO::FETCH_ASSOC);
            $defUid = (int) ($defRow['default_lab_manager_user_id'] ?? 0);
            $user_id_value = $defUid > 0 ? (string) $defUid : null;
        } else {
            $user_id_value = null;
        }

        if($action === 'add'){
            $stmt = $db->prepare("INSERT INTO inventory (name, item_code, facility_id, acquisition_date, remarks, user_id, request_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $item_code, $facility_id, $acquisition_date ?: null, $remarks, $user_id_value, $request_id]);

            $new_inventory_id = $db->lastInsertId();

            foreach ($components_data as $idx => $comp) {
                $comp_photo_url = '';
                $comp_item_id = $comp['item_id'] ?? null;
                $comp_code = $comp['code'] ?? '';
                $comp_qty = isset($comp['quantity']) ? (int)$comp['quantity'] : 1;
                $comp_cond = trim($comp['condition_status'] ?? '');
                $comp_stat = trim($comp['status'] ?? '') ?: 'Available';

                if(isset($_FILES["component_photo_$idx"]) && $_FILES["component_photo_$idx"]['error'] === UPLOAD_ERR_OK) {
                    $uploaded = uploadFile($_FILES["component_photo_$idx"]);
                    if($uploaded) {
                        $comp_photo_url = $uploaded;
                    }
                }

                if($comp_item_id) {
                    $comp_stmt = $db->prepare("INSERT INTO item_components (parent_item_id, component_item_id, quantity, condition_status, status, code, photo_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $comp_stmt->execute([$new_inventory_id, $comp_item_id, $comp_qty, $comp_cond !== '' ? $comp_cond : null, $comp_stat, $comp_code, $comp_photo_url !== '' ? $comp_photo_url : '']);
                }
            }

            logActivity($_SESSION['user_id'], 'Add Inventory', "Added inventory: $name");
            echo json_encode(['success'=>true,'message'=>'Inventory added successfully', 'inventory_id'=>$new_inventory_id]);
        } 
        else {
            $stmt = $db->prepare("UPDATE inventory SET name=?, item_code=?, facility_id=?, acquisition_date=?, remarks=?, user_id=?, request_id=? WHERE inventory_id=?");
            $stmt->execute([$name, $item_code, $facility_id, $acquisition_date ?: null, $remarks, $user_id_value, $request_id, $inventory_id]);

            logActivity($_SESSION['user_id'], 'Edit Inventory', "Updated inventory: $name");
            echo json_encode(['success'=>true,'message'=>'Inventory updated successfully']);
        }
    }
    else if($action === 'delete'){
        $inventory_id = $_POST['inventory_id'] ?? '';
        if(!$inventory_id){
            echo json_encode(['success'=>false,'message'=>'Inventory ID is required']);
            exit;
        }

        // Delete components first
        $comp_stmt = $db->prepare("DELETE FROM item_components WHERE parent_item_id = ?");
        $comp_stmt->execute([$inventory_id]);

        // Then delete inventory
        $stmt = $db->prepare("DELETE FROM inventory WHERE inventory_id=?");
        $stmt->execute([$inventory_id]);

        logActivity($_SESSION['user_id'], 'Delete Inventory', "Deleted inventory item");
        echo json_encode(['success'=>true,'message'=>'Inventory deleted successfully']);
    }
    else if($action === 'list'){
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
                inv.request_id,
                fp.component_item_id AS item_id,
                fp.item_name,
                fp.quantity,
                fp.condition_status,
                fp.status,
                fp.photo_url,
                d.`office_name` AS office_name,
                u.Email as assigned_user_email
            FROM inventory inv
            $invFirstPartJoin
            LEFT JOIN facilities f ON inv.facility_id = f.facility_id
            LEFT JOIN offices d ON f.office_id = d.office_id
            LEFT JOIN user u ON inv.user_id = u.user_id
            ORDER BY inv.inventory_id DESC
        ");
        $stmt->execute();
        $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'inventory'=>$inventory]);
    }
    else if($action === 'get_items'){
        $stmt = $db->prepare("SELECT item_id, item_name FROM items WHERE status = 'Active' ORDER BY item_name ASC");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'items'=>$items]);
    }
    else if($action === 'get_facilities'){
        $stmt = $db->prepare("SELECT f.facility_id, f.office_id, d.`office_name` AS office_name, d.default_lab_manager_user_id, f.building, f.code, f.floor, f.laboratory, f.room, f.type FROM facilities f LEFT JOIN offices d ON f.office_id = d.office_id ORDER BY d.`office_name` ASC, f.building ASC");
        $stmt->execute();
        $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'facilities'=>$facilities]);
    }
    else if($action === 'get_users'){
        $facility_id = $_POST['facility_id'] ?? null;
        
        if($facility_id) {
            // Get users assigned to a specific office
            $stmt = $db->prepare("
                SELECT u.user_id, u.Email, u.role, u.office_id, d.`office_name` AS office_name
                FROM user u
                LEFT JOIN offices d ON u.office_id = d.office_id
                WHERE u.office_id = ?
                ORDER BY u.Email ASC
            ");
            $stmt->execute([$facility_id]);
        } else {
            // Get all users with their office info
            $stmt = $db->prepare("
                SELECT u.user_id, u.Email, u.role, u.office_id, d.`office_name` AS office_name
                FROM user u
                LEFT JOIN offices d ON u.office_id = d.office_id
                ORDER BY u.Email ASC
            ");
            $stmt->execute();
        }
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'users'=>$users]);
    }
    else if($action === 'get_components'){
        $inventory_id = $_POST['inventory_id'] ?? '';
        if(!$inventory_id){
            echo json_encode(['success'=>false,'message'=>'Inventory ID is required']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT 
                ic.component_id,
                ic.parent_item_id,
                ic.component_item_id,
                ic.quantity,
                ic.condition_status,
                ic.status,
                ic.code,
                ic.photo_url,
                i.item_name
            FROM item_components ic
            LEFT JOIN items i ON ic.component_item_id = i.item_id
            WHERE ic.parent_item_id = ?
            ORDER BY ic.component_id ASC
        ");
        $stmt->execute([$inventory_id]);
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success'=>true,'components'=>$components]);
    }
    else if($action === 'add_component'){
        $inventory_id = $_POST['inventory_id'] ?? '';
        $component_item_id = $_POST['component_item_id'] ?? '';
        $code = $_POST['code'] ?? '';
        $quantity = $_POST['quantity'] ?? 1;
        $condition_status = trim($_POST['condition_status'] ?? '');
        $status = trim($_POST['status'] ?? '') ?: 'Available';

        if(!$inventory_id || !$component_item_id){
            echo json_encode(['success'=>false,'message'=>'Inventory and Component Item are required']);
            exit;
        }

        $dupChk = $db->prepare("SELECT 1 FROM item_components WHERE parent_item_id = ? AND component_item_id = ? LIMIT 1");
        $dupChk->execute([$inventory_id, $component_item_id]);
        if ($dupChk->fetch()) {
            echo json_encode(['success'=>false,'message'=>'That catalog item is already in this inventory.']);
            exit;
        }

        $photo_url = '';
        if(isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photo_url = uploadFile($_FILES['photo']);
        }

        $stmt = $db->prepare("INSERT INTO item_components (parent_item_id, component_item_id, quantity, condition_status, status, code, photo_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$inventory_id, $component_item_id, $quantity, $condition_status !== '' ? $condition_status : null, $status, $code, $photo_url !== '' && $photo_url !== null ? $photo_url : '']);

        logActivity($_SESSION['user_id'], 'Add Component', "Added component to inventory: $inventory_id");
        echo json_encode(['success'=>true,'message'=>'Component added successfully']);
    }
    else if($action === 'update_component'){
        $component_id = $_POST['component_id'] ?? '';
        $component_item_id = $_POST['component_item_id'] ?? '';
        $code = $_POST['code'] ?? '';
        $quantity = $_POST['quantity'] ?? 1;
        $condition_status = trim($_POST['condition_status'] ?? '');
        $status = trim($_POST['status'] ?? '') ?: 'Available';

        if(!$component_id || !$component_item_id){
            echo json_encode(['success'=>false,'message'=>'Component ID and Item are required']);
            exit;
        }

        $stmtPid = $db->prepare("SELECT parent_item_id FROM item_components WHERE component_id = ?");
        $stmtPid->execute([$component_id]);
        $pidRow = $stmtPid->fetch(PDO::FETCH_ASSOC);
        if (!$pidRow) {
            echo json_encode(['success'=>false,'message'=>'Component not found']);
            exit;
        }
        $parent_item_id = $pidRow['parent_item_id'];
        $dupChk = $db->prepare("SELECT 1 FROM item_components WHERE parent_item_id = ? AND component_item_id = ? AND component_id != ? LIMIT 1");
        $dupChk->execute([$parent_item_id, $component_item_id, $component_id]);
        if ($dupChk->fetch()) {
            echo json_encode(['success'=>false,'message'=>'That catalog item is already in this inventory.']);
            exit;
        }

        // Get existing component to preserve photo if not updated
        $stmt_photo = $db->prepare("SELECT photo_url FROM item_components WHERE component_id = ?");
        $stmt_photo->execute([$component_id]);
        $existing = $stmt_photo->fetch();
        $photo_url = $existing['photo_url'] ?? '';

        if(isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photo_url = uploadFile($_FILES['photo']);
        }

        $stmt = $db->prepare("UPDATE item_components SET component_item_id=?, code=?, quantity=?, condition_status=?, status=?, photo_url=? WHERE component_id=?");
        $stmt->execute([$component_item_id, $code, $quantity, $condition_status !== '' ? $condition_status : null, $status, $photo_url, $component_id]);

        logActivity($_SESSION['user_id'], 'Update Component', "Updated component: $component_id");
        echo json_encode(['success'=>true,'message'=>'Component updated successfully']);
    }
    else if($action === 'delete_component'){
        $component_id = $_POST['component_id'] ?? '';
        if(!$component_id){
            echo json_encode(['success'=>false,'message'=>'Component ID is required']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM item_components WHERE component_id=?");
        $stmt->execute([$component_id]);

        logActivity($_SESSION['user_id'], 'Delete Component', "Deleted component: $component_id");
        echo json_encode(['success'=>true,'message'=>'Component deleted successfully']);
    }
    else if($action === 'get_offices'){
        $page = max(1, (int)($_POST['page'] ?? 1));
        $per_page = min(50, max(1, (int)($_POST['per_page'] ?? 5)));
        $offset = ($page - 1) * $per_page;
        $q = trim($_POST['q'] ?? '');
        $sort = $_POST['sort'] ?? 'total-desc';
        $allowedSort = [
            'name-asc' => 'd.`office_name` ASC',
            'name-desc' => 'd.`office_name` DESC',
            'total-asc' => 'total_inventory ASC, d.`office_name` ASC',
            'total-desc' => 'total_inventory DESC, d.`office_name` ASC',
        ];
        $orderSql = $allowedSort[$sort] ?? $allowedSort['total-desc'];

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
        $sql = "
            SELECT
                d.office_id,
                d.`office_name` AS office_name,
                COUNT(DISTINCT CASE WHEN f.laboratory IS NOT NULL AND f.laboratory != '' THEN f.facility_id END) as lab_count,
                COUNT(DISTINCT CASE WHEN f.room IS NOT NULL AND f.room != '' THEN f.facility_id END) as room_count,
                (SELECT COALESCE(SUM(ic.quantity), 0) FROM inventory i2
                 INNER JOIN item_components ic ON ic.parent_item_id = i2.inventory_id
                 WHERE i2.facility_id IN (
                    SELECT f2.facility_id FROM facilities f2 
                    WHERE f2.office_id = d.office_id
                 )) as total_inventory
            FROM offices d
            LEFT JOIN facilities f ON f.office_id = d.office_id
            WHERE $whereSql
            GROUP BY d.office_id, d.`office_name`
            ORDER BY $orderSql
            LIMIT $lim OFFSET $off
        ";
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
    }
    else if($action === 'get_facilities_by_office'){
        $office_id = isset($_POST['office_id']) ? trim((string)$_POST['office_id']) : '';
        $office_name = trim($_POST['office_name'] ?? '');

        if($office_id === '' && $office_name === ''){
            echo json_encode(['success'=>false,'message'=>'Office id or name is required']);
            exit;
        }

        $invSum = "(SELECT COALESCE(SUM(ic.quantity), 0) FROM inventory i2
            INNER JOIN item_components ic ON ic.parent_item_id = i2.inventory_id
            WHERE i2.facility_id = f.facility_id)";

        if($office_id !== ''){
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
                    $invSum as total_inventory
                FROM facilities f
                LEFT JOIN offices d ON f.office_id = d.office_id
                WHERE f.office_id = ?
                ORDER BY COALESCE(f.laboratory, f.room, d.`office_name`) ASC
            ");
            $stmt->execute([(int)$office_id]);
        } else {
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
                    $invSum as total_inventory
                FROM facilities f
                LEFT JOIN offices d ON f.office_id = d.office_id
                WHERE TRIM(d.`office_name`) = TRIM(?)
                ORDER BY COALESCE(f.laboratory, f.room, d.`office_name`) ASC
            ");
            $stmt->execute([$office_name]);
        }
        $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'facilities'=>$facilities]);
    }
    else if($action === 'get_inventory_by_facility'){
        $facility_id = $_POST['facility_id'] ?? '';
        if(!$facility_id){
            echo json_encode(['success'=>false,'message'=>'Facility ID is required']);
            exit;
        }

        // Get inventory items for a specific facility
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
                inv.request_id,
                fp.component_item_id AS item_id,
                fp.item_name,
                fp.quantity,
                fp.condition_status,
                fp.status,
                fp.photo_url,
                d.`office_name` AS office_name,
                u.Email as assigned_user_email
            FROM inventory inv
            $invFirstPartJoin
            LEFT JOIN facilities f ON inv.facility_id = f.facility_id
            LEFT JOIN offices d ON f.office_id = d.office_id
            LEFT JOIN user u ON inv.user_id = u.user_id
            WHERE inv.facility_id = ?
            ORDER BY inv.inventory_id DESC
        ");
        $stmt->execute([$facility_id]);
        $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'inventory'=>$inventory]);
    }
    else if($action === 'get_inventory_count_by_facility'){
        $facility_id = $_POST['facility_id'] ?? '';
        if(!$facility_id){
            echo json_encode(['success'=>false,'message'=>'Facility ID is required']);
            exit;
        }

        // Count existing inventory items in this facility
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM inventory 
            WHERE facility_id = ?
        ");
        $stmt->execute([$facility_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['count'] ?? 0;
        echo json_encode(['success'=>true,'count'=>$count]);
    }
    else {
        echo json_encode(['success'=>false,'message'=>'Invalid action']);
    }

} catch(Exception $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
