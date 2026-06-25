<?php
session_start();
header("Content-Type: application/json");
require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/../helpers/supplier.php';

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $db = Database::connect();
    ensureSupplierTinColumn($db);
    $action = $_POST['action'] ?? '';

    // Activity Logging Function
    function logActivity($user_id, $activity_type, $description) {
        global $db;
        $stmt = $db->prepare("INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $activity_type, $description]);
    }

    // List all suppliers
    if ($action === 'list_suppliers') {
        $stmt = $db->prepare("SELECT * FROM suppliers ORDER BY date_added DESC");
        $stmt->execute();
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'suppliers' => $suppliers]);
        exit;
    }

    // Get single supplier
    if ($action === 'get_supplier') {
        $supplier_id = $_POST['supplier_id'] ?? 0;
        $stmt = $db->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplier_id]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'supplier' => $supplier]);
        exit;
    }

    // Add supplier
    if ($action === 'add_supplier') {
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        $tin = cwirmsNormalizeSupplierTin($_POST['tin'] ?? null);
        $phone_number = cwirmsNormalizeSupplierPhone($phone_number);

        $validationError = cwirmsValidateSupplierFormData([
            'supplier_name' => $supplier_name,
            'contact_person' => $contact_person,
            'email' => $email,
            'phone_number' => $phone_number,
            'address' => $address,
            'city' => $city,
            'country' => $country,
            'status' => $status,
        ]);
        if ($validationError !== null) {
            echo json_encode(['success' => false, 'message' => $validationError]);
            exit;
        }

        // Check for duplicates (case-insensitive)
        $stmtChk = $db->prepare("SELECT supplier_id FROM suppliers WHERE LOWER(supplier_name) = LOWER(?) LIMIT 1");
        $stmtChk->execute([$supplier_name]);
        $exists = $stmtChk->fetch(PDO::FETCH_ASSOC);
        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'Supplier already exists']);
            exit;
        }

        // Handle image upload
        $supplier_image = null;
        if (isset($_FILES['supplier_image']) && $_FILES['supplier_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/suppliers/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = time() . '_' . bin2hex(random_bytes(5)) . '_' . basename($_FILES['supplier_image']['name']);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['supplier_image']['tmp_name'], $filePath)) {
                $supplier_image = 'uploads/suppliers/' . $fileName;
            }
        }

        $vat_registered = isset($_POST['vat_registered']) && $_POST['vat_registered'] ? 1 : 0;

        $stmt = $db->prepare(
            'INSERT INTO suppliers (supplier_name, contact_person, phone_number, email, address, city, country, postal_code, tin, vat_registered, status, supplier_image)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $supplier_name,
            $contact_person,
            $phone_number,
            $email,
            $address,
            $city,
            $country,
            $postal_code,
            $tin,
            $vat_registered,
            $status,
            $supplier_image,
        ]);

        logActivity($_SESSION['user_id'], 'Add Supplier', "Added supplier: $supplier_name");

        echo json_encode(['success' => true, 'message' => 'Supplier added successfully']);
        exit;
    }

    // Edit supplier
    if ($action === 'edit_supplier') {
        $supplier_id = $_POST['supplier_id'] ?? 0;
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        $tin = cwirmsNormalizeSupplierTin($_POST['tin'] ?? null);
        $phone_number = cwirmsNormalizeSupplierPhone($phone_number);
        $vat_registered = isset($_POST['vat_registered']) && $_POST['vat_registered'] ? 1 : 0;

        if (!$supplier_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid supplier ID']);
            exit;
        }

        $validationError = cwirmsValidateSupplierFormData([
            'supplier_name' => $supplier_name,
            'contact_person' => $contact_person,
            'email' => $email,
            'phone_number' => $phone_number,
            'address' => $address,
            'city' => $city,
            'country' => $country,
            'status' => $status,
        ]);
        if ($validationError !== null) {
            echo json_encode(['success' => false, 'message' => $validationError]);
            exit;
        }

        // Check for duplicate name in other records
        $stmtDup = $db->prepare("SELECT supplier_id FROM suppliers WHERE LOWER(supplier_name) = LOWER(?) AND supplier_id <> ? LIMIT 1");
        $stmtDup->execute([$supplier_name, $supplier_id]);
        $dup = $stmtDup->fetch(PDO::FETCH_ASSOC);
        if ($dup) {
            echo json_encode(['success' => false, 'message' => 'Another supplier already uses that name']);
            exit;
        }

        // Get old supplier data
        $stmtOld = $db->prepare("SELECT supplier_name, supplier_image FROM suppliers WHERE supplier_id = ?");
        $stmtOld->execute([$supplier_id]);
        $oldSupplier = $stmtOld->fetch(PDO::FETCH_ASSOC);

        // Handle image upload
        $supplier_image = $oldSupplier['supplier_image'];
        if (isset($_FILES['supplier_image']) && $_FILES['supplier_image']['error'] === UPLOAD_ERR_OK) {
            // Delete old image if exists
            if ($oldSupplier['supplier_image']) {
                $oldImagePath = __DIR__ . '/../../public/' . $oldSupplier['supplier_image'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            $uploadDir = __DIR__ . '/../../public/uploads/suppliers/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = time() . '_' . bin2hex(random_bytes(5)) . '_' . basename($_FILES['supplier_image']['name']);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['supplier_image']['tmp_name'], $filePath)) {
                $supplier_image = 'uploads/suppliers/' . $fileName;
            }
        }

        $stmt = $db->prepare(
            'UPDATE suppliers
             SET supplier_name = ?, contact_person = ?, phone_number = ?, email = ?, address = ?, city = ?, country = ?, postal_code = ?, tin = ?, vat_registered = ?, status = ?, supplier_image = ?
             WHERE supplier_id = ?'
        );
        $stmt->execute([
            $supplier_name,
            $contact_person,
            $phone_number,
            $email,
            $address,
            $city,
            $country,
            $postal_code,
            $tin,
            $vat_registered,
            $status,
            $supplier_image,
            $supplier_id,
        ]);

        logActivity($_SESSION['user_id'], 'Edit Supplier', "Updated supplier: {$oldSupplier['supplier_name']} → $supplier_name");

        echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
        exit;
    }

    // Delete supplier
    if ($action === 'delete_supplier') {
        $supplier_id = $_POST['supplier_id'] ?? 0;

        if (!$supplier_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid supplier ID']);
            exit;
        }

        $stmtOld = $db->prepare("SELECT supplier_name, supplier_image FROM suppliers WHERE supplier_id = ?");
        $stmtOld->execute([$supplier_id]);
        $supplier = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$supplier) {
            echo json_encode(['success' => false, 'message' => 'Supplier not found']);
            exit;
        }

        // Delete image if exists
        if ($supplier['supplier_image']) {
            $imagePath = __DIR__ . '/../../public/' . $supplier['supplier_image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $stmt = $db->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplier_id]);

        logActivity($_SESSION['user_id'], 'Delete Supplier', "Deleted supplier: {$supplier['supplier_name']}");

        echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
