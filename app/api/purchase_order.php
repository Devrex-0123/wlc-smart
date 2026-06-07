<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/approval_tables.php';
require_once __DIR__ . '/../helpers/supplier.php';
require_once __DIR__ . '/../helpers/purchase_order.php';

function poSendJson(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function poAssertSessionUserId(): int
{
    if (!isset($_SESSION['user_id'])) {
        poSendJson(['success' => false, 'message' => 'Unauthorized']);
    }

    return (int) $_SESSION['user_id'];
}

function poViewerRoleLc(PDO $db, int $userId): string
{
    $stmt = $db->prepare('SELECT role FROM user WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$userId]);

    return strtolower(trim((string) ($stmt->fetchColumn() ?: '')));
}

function poIsPresidentRole(string $roleLc): bool
{
    return in_array($roleLc, ['president', 'president verifier', 'verifier president', 'president_verifier'], true);
}

function poSanitizeString(mixed $value, int $maxLen): string
{
    $text = trim((string) ($value ?? ''));

    return mb_substr($text, 0, $maxLen);
}

function poResolveModeOfPaymentFromTotal(float $totalAmount): string
{
    return cwirmsResolvePoModeOfPayment($totalAmount);
}

function poSyncModeOfPaymentFromTotal(PDO $db, int $poId, float $totalAmount, array $header): void
{
    if ($poId <= 0) {
        return;
    }
    $computed = poResolveModeOfPaymentFromTotal($totalAmount);
    $stored = strtolower(trim((string) ($header['mode_of_payment'] ?? '')));
    if ($stored === $computed) {
        return;
    }
    $upd = $db->prepare(
        'UPDATE purchase_orders SET mode_of_payment = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL'
    );
    $upd->execute([$computed, $poId]);
    $header['mode_of_payment'] = $computed;
}

function poEnrichSupplierTin(PDO $db, array $header): string
{
    $tin = trim((string) ($header['supplier_tin'] ?? ''));
    if ($tin !== '') {
        $normalized = cwirmsNormalizeSupplierTin($tin);

        return $normalized ?? $tin;
    }

    ensureSupplierTinColumn($db);
    $supplierId = (int) ($header['supplier_id'] ?? 0);
    if ($supplierId > 0) {
        $stmt = $db->prepare('SELECT tin FROM suppliers WHERE supplier_id = ? LIMIT 1');
        $stmt->execute([$supplierId]);
        $raw = $stmt->fetchColumn();
        if ($raw !== false && $raw !== null && trim((string) $raw) !== '') {
            return cwirmsNormalizeSupplierTin($raw) ?? trim((string) $raw);
        }
    }

    $supplierName = trim((string) ($header['supplier_name'] ?? ''));
    if ($supplierName !== '') {
        $stmt = $db->prepare('SELECT tin FROM suppliers WHERE supplier_name = ? ORDER BY supplier_id ASC LIMIT 1');
        $stmt->execute([$supplierName]);
        $raw = $stmt->fetchColumn();
        if ($raw !== false && $raw !== null && trim((string) $raw) !== '') {
            return cwirmsNormalizeSupplierTin($raw) ?? trim((string) $raw);
        }
    }

    return '';
}

/**
 * @return array{quantity: int, unit_price: float, amount: float, description: string, sub_description: ?string}
 */
function poNormalizeLineRow(mixed $row, int $index): array
{
    if (!is_array($row)) {
        poSendJson(['success' => false, 'message' => 'Invalid line item at row ' . ($index + 1)]);
    }

    $description = poSanitizeString($row['description'] ?? '', 255);
    if ($description === '') {
        poSendJson(['success' => false, 'message' => 'Line ' . ($index + 1) . ': description is required.']);
    }

    $subDescription = poSanitizeString($row['sub_description'] ?? '', 255);
    $quantity = (int) ($row['quantity'] ?? 0);
    if ($quantity < 1) {
        poSendJson(['success' => false, 'message' => 'Line ' . ($index + 1) . ': quantity must be at least 1.']);
    }

    $unitPriceRaw = $row['unit_price'] ?? null;
    if ($unitPriceRaw === null || $unitPriceRaw === '' || !is_numeric($unitPriceRaw)) {
        poSendJson(['success' => false, 'message' => 'Line ' . ($index + 1) . ': unit price is required.']);
    }
    $unitPrice = round((float) $unitPriceRaw, 2);
    if ($unitPrice < 0) {
        poSendJson(['success' => false, 'message' => 'Line ' . ($index + 1) . ': unit price cannot be negative.']);
    }

    $amount = round($quantity * $unitPrice, 2);

    return [
        'description' => $description,
        'sub_description' => $subDescription !== '' ? $subDescription : null,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'amount' => $amount,
    ];
}

function poGenerateNextNumber(PDO $db): string
{
    $stmt = $db->query(
        "SELECT po_number FROM purchase_orders
         WHERE po_number REGEXP '^PO-[0-9]+$'
         ORDER BY CAST(SUBSTRING(po_number, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $last = (string) ($stmt->fetchColumn() ?: '');
    $next = 1;
    if ($last !== '' && preg_match('/^PO-(\d+)$/i', $last, $matches)) {
        $next = (int) $matches[1] + 1;
    }

    return 'PO-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

function poFetchById(PDO $db, int $poId, bool $includeDeleted = false): ?array
{
    $sql = 'SELECT * FROM purchase_orders WHERE id = ?';
    if (!$includeDeleted) {
        $sql .= ' AND deleted_at IS NULL';
    }
    $sql .= ' LIMIT 1';

    $stmt = $db->prepare($sql);
    $stmt->execute([$poId]);
    $header = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$header) {
        return null;
    }

    $lineStmt = $db->prepare(
        'SELECT id, description, sub_description, quantity, unit_price, amount, sort_order
         FROM purchase_order_lines
         WHERE purchase_order_id = ?
         ORDER BY sort_order ASC, id ASC'
    );
    $lineStmt->execute([$poId]);
    $lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'header' => $header,
        'lines' => $lines,
    ];
}

function poCanView(PDO $db, int $userId, array $header): bool
{
    $role = poViewerRoleLc($db, $userId);
    if (
        poIsPresidentRole($role)
        || $role === 'inventory manager'
        || $role === 'inventory_manager'
        || $role === 'comptroller'
    ) {
        return true;
    }

    $createdBy = (int) ($header['created_by_user_id'] ?? 0);
    $requestedBy = (int) ($header['requested_by_user_id'] ?? 0);
    if ($userId === $createdBy || $userId === $requestedBy) {
        return true;
    }

    $requisitionId = (int) ($header['requisition_id'] ?? 0);
    if ($requisitionId > 0) {
        $own = $db->prepare('SELECT 1 FROM requisition_item WHERE request_id = ? AND user_id = ? LIMIT 1');
        $own->execute([$requisitionId, $userId]);

        return (bool) $own->fetchColumn();
    }

    return false;
}

function poFormatRecord(PDO $db, array $header, array $lines): array
{
    $dateIssued = '';
    if (!empty($header['created_at'])) {
        $ts = strtotime((string) $header['created_at']);
        $dateIssued = $ts ? date('F j, Y', $ts) : (string) $header['created_at'];
    }

    $totalAmount = round((float) ($header['total_amount'] ?? 0), 2);
    $modeOfPayment = poResolveModeOfPaymentFromTotal($totalAmount);
    $supplierTin = poEnrichSupplierTin($db, $header);

    return [
        'id' => (int) ($header['id'] ?? 0),
        'po_number' => (string) ($header['po_number'] ?? ''),
        'requisition_id' => $header['requisition_id'] !== null ? (int) $header['requisition_id'] : null,
        'requested_by_user_id' => $header['requested_by_user_id'] !== null ? (int) $header['requested_by_user_id'] : null,
        'requested_by' => (string) ($header['requested_by_name'] ?? ''),
        'facility_id' => $header['facility_id'] !== null ? (int) $header['facility_id'] : null,
        'location_facility' => (string) ($header['location_facility'] ?? ''),
        'supplier_id' => $header['supplier_id'] !== null ? (int) $header['supplier_id'] : null,
        'supplier_name' => (string) ($header['supplier_name'] ?? ''),
        'supplier_tin' => $supplierTin,
        'mode_of_payment' => $modeOfPayment,
        'mode_of_payment_label' => cwirmsFormatPoModeOfPaymentLabel($modeOfPayment),
        'purpose_of_request' => (string) ($header['purpose_of_request'] ?? ''),
        'total_amount' => $totalAmount,
        'status' => (string) ($header['status'] ?? 'pending'),
        'approved_by_president' => (int) ($header['approved_by_president'] ?? 0) === 1,
        'approved_at' => $header['approved_at'] ?? null,
        'date_issued' => $dateIssued,
        'created_at' => $header['created_at'] ?? null,
        'lines' => array_map(static function (array $line): array {
            return [
                'id' => (int) ($line['id'] ?? 0),
                'description' => (string) ($line['description'] ?? ''),
                'sub_description' => (string) ($line['sub_description'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'unit_price' => round((float) ($line['unit_price'] ?? 0), 2),
                'amount' => round((float) ($line['amount'] ?? 0), 2),
                'sort_order' => (int) ($line['sort_order'] ?? 0),
            ];
        }, $lines),
    ];
}

$db = Database::connect();
ensurePurchaseOrderTables($db);
ensureSupplierTinColumn($db);

$userId = poAssertSessionUserId();
$roleLc = poViewerRoleLc($db, $userId);
$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));

if ($action === 'create') {
    $linesRaw = $_POST['lines'] ?? null;
    if (is_string($linesRaw)) {
        $decoded = json_decode($linesRaw, true);
        $linesRaw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($linesRaw) || $linesRaw === []) {
        poSendJson(['success' => false, 'message' => 'At least one line item is required.']);
    }

    $normalizedLines = [];
    $totalAmount = 0.0;
    foreach ($linesRaw as $idx => $row) {
        $line = poNormalizeLineRow($row, (int) $idx);
        $normalizedLines[] = $line;
        $totalAmount += $line['amount'];
    }
    $totalAmount = round($totalAmount, 2);

    $requisitionId = (int) ($_POST['requisition_id'] ?? 0);
    if ($requisitionId > 0) {
        $reqCheck = $db->prepare('SELECT 1 FROM requisition_item WHERE request_id = ? LIMIT 1');
        $reqCheck->execute([$requisitionId]);
        if (!$reqCheck->fetchColumn()) {
            $requisitionId = 0;
        }
    } else {
        $requisitionId = 0;
    }

    $supplierId = (int) ($_POST['supplier_id'] ?? 0);
    $supplierName = poSanitizeString($_POST['supplier_name'] ?? '', 100);
    $supplierTin = cwirmsNormalizeSupplierTin($_POST['supplier_tin'] ?? null);
    if ($supplierId > 0) {
        $supStmt = $db->prepare('SELECT supplier_name, tin FROM suppliers WHERE supplier_id = ? LIMIT 1');
        $supStmt->execute([$supplierId]);
        $supRow = $supStmt->fetch(PDO::FETCH_ASSOC);
        if ($supRow) {
            if ($supplierName === '') {
                $supplierName = poSanitizeString($supRow['supplier_name'] ?? '', 100);
            }
            if ($supplierTin === null && !empty($supRow['tin'])) {
                $supplierTin = cwirmsNormalizeSupplierTin($supRow['tin']);
            }
        } else {
            $supplierId = 0;
        }
    }

    if ($supplierName === '') {
        poSendJson(['success' => false, 'message' => 'Supplier name is required.']);
    }

    $facilityId = (int) ($_POST['facility_id'] ?? 0);
    if ($facilityId > 0) {
        $facCheck = $db->prepare('SELECT 1 FROM facilities WHERE facility_id = ? LIMIT 1');
        $facCheck->execute([$facilityId]);
        if (!$facCheck->fetchColumn()) {
            $facilityId = 0;
        }
    } else {
        $facilityId = 0;
    }

    $mode = poResolveModeOfPaymentFromTotal($totalAmount);

    $requestedByName = poSanitizeString($_POST['requested_by'] ?? '', 150);
    if ($requestedByName === '') {
        poSendJson(['success' => false, 'message' => 'Requested by is required.']);
    }

    $locationFacility = poSanitizeString($_POST['location_facility'] ?? '', 255);
    if ($locationFacility === '') {
        poSendJson(['success' => false, 'message' => 'Location / facility is required.']);
    }

    $purpose = poSanitizeString($_POST['purpose_of_request'] ?? '', 65535);

    $db->beginTransaction();
    try {
        $poNumber = poGenerateNextNumber($db);
        $insert = $db->prepare(
            'INSERT INTO purchase_orders
             (po_number, requisition_id, requested_by_user_id, requested_by_name, facility_id,
              location_facility, supplier_id, supplier_name, supplier_tin,
              mode_of_payment, purpose_of_request, total_amount, status, created_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?)'
        );
        $insert->execute([
            $poNumber,
            $requisitionId > 0 ? $requisitionId : null,
            $userId,
            $requestedByName,
            $facilityId > 0 ? $facilityId : null,
            $locationFacility,
            $supplierId > 0 ? $supplierId : null,
            $supplierName,
            $supplierTin,
            $mode,
            $purpose !== '' ? $purpose : null,
            $totalAmount,
            $userId,
        ]);
        $poId = (int) $db->lastInsertId();

        $lineInsert = $db->prepare(
            'INSERT INTO purchase_order_lines
             (purchase_order_id, description, sub_description, quantity, unit_price, amount, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($normalizedLines as $sort => $line) {
            $lineInsert->execute([
                $poId,
                $line['description'],
                $line['sub_description'],
                $line['quantity'],
                $line['unit_price'],
                $line['amount'],
                $sort,
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        poSendJson(['success' => false, 'message' => 'Failed to create purchase order.']);
    }

    $record = poFetchById($db, $poId);
    poSendJson([
        'success' => true,
        'message' => 'Purchase order created successfully.',
        'data' => poFormatRecord($db, $record['header'], $record['lines']),
    ]);
}

if ($action === 'fetch') {
    $poId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Purchase order id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    if (!poCanView($db, $userId, $record['header'])) {
        poSendJson(['success' => false, 'message' => 'Forbidden']);
    }

    $header = $record['header'];
    $totalAmount = round((float) ($header['total_amount'] ?? 0), 2);
    poSyncModeOfPaymentFromTotal($db, $poId, $totalAmount, $header);

    poSendJson([
        'success' => true,
        'data' => poFormatRecord($db, $header, $record['lines']),
    ]);
}

if ($action === 'list') {
    $page = max(1, (int) ($_POST['page'] ?? $_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int) ($_POST['per_page'] ?? $_GET['per_page'] ?? 10)));
    $offset = ($page - 1) * $perPage;

    $where = 'WHERE deleted_at IS NULL';
    $params = [];
    if (!poIsPresidentRole($roleLc) && $roleLc !== 'inventory manager' && $roleLc !== 'inventory_manager') {
        $where .= ' AND (created_by_user_id = ? OR requested_by_user_id = ?)';
        $params[] = $userId;
        $params[] = $userId;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM purchase_orders {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $lim = (int) $perPage;
    $off = (int) $offset;
    $listStmt = $db->prepare(
        "SELECT id, po_number, requested_by_name, supplier_name, total_amount, status,
                approved_by_president, created_at
         FROM purchase_orders
         {$where}
         ORDER BY created_at DESC, id DESC
         LIMIT {$lim} OFFSET {$off}"
    );
    $listStmt->execute($params);
    $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    poSendJson([
        'success' => true,
        'data' => [
            'items' => array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'po_number' => (string) ($row['po_number'] ?? ''),
                    'requested_by' => (string) ($row['requested_by_name'] ?? ''),
                    'supplier_name' => (string) ($row['supplier_name'] ?? ''),
                    'total_amount' => round((float) ($row['total_amount'] ?? 0), 2),
                    'status' => (string) ($row['status'] ?? 'pending'),
                    'approved_by_president' => (int) ($row['approved_by_president'] ?? 0) === 1,
                    'created_at' => $row['created_at'] ?? null,
                ];
            }, $rows),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
        ],
    ]);
}

if ($action === 'approve' || $action === 'reject' || $action === 'undo') {
    if (!poIsPresidentRole($roleLc)) {
        poSendJson(['success' => false, 'message' => 'Only the President may perform this action.']);
    }

    $poId = (int) ($_POST['id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Purchase order id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    $status = strtolower(trim((string) ($record['header']['status'] ?? 'pending')));

    if ($action === 'approve') {
        if ($status !== 'pending') {
            poSendJson(['success' => false, 'message' => 'Only pending purchase orders can be approved.']);
        }
        $upd = $db->prepare(
            'UPDATE purchase_orders
             SET status = \'approved\', approved_by_president = 1, approved_at = NOW(), updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL'
        );
        $upd->execute([$poId]);
        poSendJson(['success' => true, 'message' => 'Purchase order approved.']);
    }

    if ($action === 'reject') {
        if ($status !== 'pending') {
            poSendJson(['success' => false, 'message' => 'Only pending purchase orders can be rejected.']);
        }
        $upd = $db->prepare(
            'UPDATE purchase_orders
             SET status = \'rejected\', approved_by_president = 0, approved_at = NULL, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL'
        );
        $upd->execute([$poId]);
        poSendJson(['success' => true, 'message' => 'Purchase order rejected.']);
    }

    if ($action === 'undo') {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            poSendJson(['success' => false, 'message' => 'Only approved or rejected purchase orders can be undone.']);
        }
        $upd = $db->prepare(
            'UPDATE purchase_orders
             SET status = \'pending\', approved_by_president = 0, approved_at = NULL, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL'
        );
        $upd->execute([$poId]);
        poSendJson(['success' => true, 'message' => 'Purchase order decision reset to pending.']);
    }
}

if ($action === 'ensure_for_request') {
    require_once __DIR__ . '/../helpers/purchase_order.php';

    $requestId = (int) ($_POST['request_id'] ?? $_GET['request_id'] ?? 0);
    if ($requestId <= 0) {
        poSendJson(['success' => false, 'message' => 'Request id is required.']);
    }

    $reqCheck = $db->prepare('SELECT user_id FROM requisition_item WHERE request_id = ? LIMIT 1');
    $reqCheck->execute([$requestId]);
    $reqRow = $reqCheck->fetch(PDO::FETCH_ASSOC);
    if (!$reqRow) {
        poSendJson(['success' => false, 'message' => 'Request not found.']);
    }

    $role = poViewerRoleLc($db, $userId);
    $reqOwnerId = (int) ($reqRow['user_id'] ?? 0);
    $allowed = poIsPresidentRole($role)
        || $role === 'inventory manager'
        || $role === 'inventory_manager'
        || $role === 'comptroller'
        || $userId === $reqOwnerId;
    if (!$allowed) {
        poSendJson(['success' => false, 'message' => 'Forbidden']);
    }

    if (!cwirmsPurchaseRequisitionFullyAccepted($db, $requestId)) {
        poSendJson(['success' => false, 'message' => 'Purchase requisition must be fully verified before generating a purchase order.']);
    }

    $poId = cwirmsEnsurePurchaseOrderFromRequisition($db, $requestId, $userId);
    if ($poId === null || $poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Unable to generate purchase order for this request.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order was created but could not be loaded.']);
    }

    $header = $record['header'];
    $totalAmount = round((float) ($header['total_amount'] ?? 0), 2);
    poSyncModeOfPaymentFromTotal($db, $poId, $totalAmount, $header);

    poSendJson([
        'success' => true,
        'message' => 'Purchase order is ready.',
        'data' => poFormatRecord($db, $header, $record['lines']),
    ]);
}

if ($action === 'delete') {
    $poId = (int) ($_POST['id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Purchase order id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    if (!poCanView($db, $userId, $record['header']) && !poIsPresidentRole($roleLc)) {
        poSendJson(['success' => false, 'message' => 'Forbidden']);
    }

    $upd = $db->prepare(
        'UPDATE purchase_orders SET deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND deleted_at IS NULL'
    );
    $upd->execute([$poId]);
    poSendJson(['success' => true, 'message' => 'Purchase order deleted.']);
}

poSendJson(['success' => false, 'message' => 'Unknown action.']);
