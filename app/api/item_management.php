<?php
session_start();
header("Content-Type: application/json");
require_once __DIR__ . '/../classes/db.php';

/**
 * @return array<int, true>
 */
function itemManagementSupplierIdSet(PDO $db): array
{
    $valid = [];
    $catStmt = $db->query('SELECT supplier_id FROM suppliers');
    while ($sid = $catStmt->fetchColumn()) {
        $valid[(int) $sid] = true;
    }

    return $valid;
}

/**
 * @param array<int|string> $rawIds
 * @param array<int, true> $valid
 * @return list<int>
 */
function itemManagementNormalizeSupplierIds(array $rawIds, array $valid): array
{
    $out = [];
    foreach ($rawIds as $x) {
        $i = (int) $x;
        if ($i > 0 && isset($valid[$i])) {
            $out[] = $i;
        }
    }

    return array_values(array_unique($out));
}

function itemManagementSyncJunction(PDO $db, int $itemId, array $supplierIds): void
{
    try {
        $db->prepare('DELETE FROM item_supplier WHERE item_id = ?')->execute([$itemId]);
        if ($supplierIds === []) {
            return;
        }
        $ins = $db->prepare('INSERT INTO item_supplier (item_id, supplier_id, sort_order) VALUES (?, ?, ?)');
        foreach ($supplierIds as $ord => $sid) {
            $ins->execute([$itemId, $sid, $ord]);
        }
    } catch (Throwable $e) {
        // Migration not applied yet; ignore junction sync.
    }
}

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']);
        exit;
    }

    $db = Database::connect();
    $action = $_POST['action'] ?? '';

    // ---------------- Activity Logging Function ----------------
    function logActivity($user_id, $activity_type, $description) {
        global $db;
        $stmt = $db->prepare("INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $activity_type, $description]);
    }

    if($action === 'add' || $action === 'edit'){
        $item_name = trim($_POST['item_name'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');
        $item_id = $_POST['item_id'] ?? null;
        $validSuppliers = itemManagementSupplierIdSet($db);
        $rawSupplierIds = isset($_POST['supplier_ids']) && is_array($_POST['supplier_ids'])
            ? $_POST['supplier_ids']
            : [];
        $supplierIdsOrdered = itemManagementNormalizeSupplierIds($rawSupplierIds, $validSuppliers);
        $supplier_id = $supplierIdsOrdered[0] ?? null;

        if(!$item_name){
            echo json_encode(['success'=>false,'message'=>'Item name is required']);
            exit;
        }

        // Handle photo upload
        $photoPath = null;
        if ($action === 'edit' && $item_id) {
            // Get existing photo for edit
            $stmtPhoto = $db->prepare("SELECT photo_url FROM items WHERE item_id=?");
            $stmtPhoto->execute([$item_id]);
            $existingItem = $stmtPhoto->fetch(PDO::FETCH_ASSOC);
            $photoPath = $existingItem['photo_url'] ?? null;
        }

        // Handle new photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/inventory/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileTmp  = $_FILES['photo']['tmp_name'];
            $fileName = $_FILES['photo']['name'];
            $fileType = mime_content_type($fileTmp);

            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($fileType, $allowed)) {
                $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                $safeName = 'item_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetPath = $uploadDir . $safeName;

                if (move_uploaded_file($fileTmp, $targetPath)) {
                    // Remove old photo if exists (edit mode)
                    if ($action === 'edit' && $photoPath) {
                        $oldPath = __DIR__ . '/../../public/' . ltrim($photoPath, '/');
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    $photoPath = 'uploads/inventory/' . $safeName;
                }
            }
        }

        if($action === 'add'){
            $stmt = $db->prepare("INSERT INTO items (item_name, brand, model, description, category, unit, status, supplier_id, photo_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$item_name, $brand, $model, $description, $category, $unit, $status, $supplier_id, $photoPath]);

            $newItemId = (int) $db->lastInsertId();
            itemManagementSyncJunction($db, $newItemId, $supplierIdsOrdered);

            // Log activity
            logActivity($_SESSION['user_id'], 'Add Item', "Added item: $item_name");

            echo json_encode(['success'=>true,'message'=>'Item added successfully']);
        } else {
            // Get old item name for logging
            $stmtOld = $db->prepare("SELECT item_name FROM items WHERE item_id=?");
            $stmtOld->execute([$item_id]);
            $oldItem = $stmtOld->fetch(PDO::FETCH_ASSOC)['item_name'] ?? "Unknown";

            $stmt = $db->prepare("UPDATE items SET item_name=?, brand=?, model=?, description=?, category=?, unit=?, status=?, supplier_id=?, photo_url=? WHERE item_id=?");
            $stmt->execute([$item_name, $brand, $model, $description, $category, $unit, $status, $supplier_id, $photoPath, $item_id]);

            itemManagementSyncJunction($db, (int) $item_id, $supplierIdsOrdered);

            // Log activity
            logActivity($_SESSION['user_id'], 'Edit Item', "Updated item: $oldItem → $item_name");

            echo json_encode(['success'=>true,'message'=>'Item updated successfully']);
        }
    }
    else if($action === 'delete'){
        $item_id = $_POST['item_id'] ?? '';
        if(!$item_id){
            echo json_encode(['success'=>false,'message'=>'Item ID is required']);
            exit;
        }

        // Get item name for logging
        $stmtOld = $db->prepare("SELECT item_name FROM items WHERE item_id=?");
        $stmtOld->execute([$item_id]);
        $oldItem = $stmtOld->fetch(PDO::FETCH_ASSOC)['item_name'] ?? "Unknown";

        $stmt = $db->prepare("DELETE FROM items WHERE item_id=?");
        $stmt->execute([$item_id]);

        // Log activity
        logActivity($_SESSION['user_id'], 'Delete Item', "Deleted item: $oldItem");

        echo json_encode(['success'=>true,'message'=>'Item deleted successfully']);
    }
    else if($action === 'list'){
        $stmt = $db->prepare("SELECT item_id, item_name, brand, model, description, category, unit, status, supplier_id, photo_url, created_at FROM items ORDER BY item_id ASC"); 
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $junction = [];
        try {
            $jst = $db->query(
                'SELECT item_id, supplier_id FROM item_supplier ORDER BY item_id ASC, sort_order ASC, supplier_id ASC'
            );
            while ($row = $jst->fetch(PDO::FETCH_ASSOC)) {
                $iid = (int) $row['item_id'];
                $junction[$iid][] = (int) $row['supplier_id'];
            }
        } catch (Throwable $e) {
            $junction = [];
        }
        foreach ($items as &$it) {
            $iid = (int) $it['item_id'];
            $list = $junction[$iid] ?? [];
            $legacy = $it['supplier_id'] !== null && $it['supplier_id'] !== '' ? (int) $it['supplier_id'] : 0;
            if ($legacy > 0 && !in_array($legacy, $list, true)) {
                array_unshift($list, $legacy);
            }
            if ($list === [] && $legacy > 0) {
                $list = [$legacy];
            }
            $it['supplier_ids'] = $list;
        }
        unset($it);
        echo json_encode(['success'=>true,'items'=>$items]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Invalid action']);
    }

} catch(Exception $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
