<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/approval_tables.php';
require_once __DIR__ . '/requisition_detail_payload.php';

function sendJson(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function assertSessionUserId(): int
{
    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Unauthorized']);
    }

    return (int) $_SESSION['user_id'];
}

function viewerRoleLc(PDO $db, int $userId): string
{
    $stmt = $db->prepare('SELECT role FROM user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $role = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));

    return $role;
}

function assertInventoryManagerPr(PDO $db): void
{
    $role = viewerRoleLc($db, assertSessionUserId());
    if ($role !== 'inventory manager' && $role !== 'inventory_manager') {
        sendJson(['success' => false, 'message' => 'Forbidden']);
    }
}

function assertPresidentPr(PDO $db): void
{
    $role = viewerRoleLc($db, assertSessionUserId());
    $allowed = ['president', 'president verifier', 'verifier president', 'president_verifier'];
    if (!in_array($role, $allowed, true)) {
        sendJson(['success' => false, 'message' => 'Forbidden']);
    }
}

function requestHasPurchaseRequisitionLines(PDO $db, int $requestId): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM request_approval_suggested_supplier_item WHERE request_id = ?');
    $stmt->execute([$requestId]);

    return (int) $stmt->fetchColumn() > 0;
}

function requestGsdCanvassAccepted(PDO $db, int $requestId): bool
{
    return requisitionCanvassFormAcceptedForRequest($db, $requestId);
}

function canViewPurchaseRequisition(PDO $db, int $userId, int $requestId): bool
{
    $stmt = $db->prepare('SELECT role FROM user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $role = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));

    $allowedRoles = [
        'inventory manager',
        'inventory_manager',
        'president',
        'president verifier',
        'verifier president',
        'president_verifier',
    ];
    if (in_array($role, $allowedRoles, true)) {
        return true;
    }

    $own = $db->prepare('SELECT 1 FROM requisition_item WHERE request_id = ? AND user_id = ? LIMIT 1');
    $own->execute([$requestId, $userId]);

    return (bool) $own->fetchColumn();
}

function tableHasColumn(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1'
    );
    $stmt->execute([$table, $column]);

    return (bool) $stmt->fetchColumn();
}

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
         LIMIT 1'
    );
    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
}

/**
 * @param array<int, array<string, mixed>> $items
 */
function savePurchaseAuditSnapshot(
    PDO $db,
    int $requestId,
    int $generatedByUserId,
    string $requesterName,
    string $purpose,
    float $grandTotal,
    array $items
): void {
    if (!tableExists($db, 'purchase_requisition_audit') || !tableExists($db, 'purchase_requisition_audit_item')) {
        return;
    }

    $db->beginTransaction();
    try {
        $hdr = $db->prepare(
            'INSERT INTO purchase_requisition_audit
             (request_id, generated_by_user_id, requester_name, purpose, grand_total)
             VALUES (?, ?, ?, ?, ?)'
        );
        $hdr->execute([
            $requestId,
            $generatedByUserId,
            $requesterName !== '' ? $requesterName : null,
            $purpose !== '' ? $purpose : null,
            round($grandTotal, 2),
        ]);
        $auditId = (int) $db->lastInsertId();

        $line = $db->prepare(
            'INSERT INTO purchase_requisition_audit_item
             (purchase_audit_id, line_no, description_name, description_brand, description_model, description_specification, qty, supplier_name, unit_price, amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $lineNo = 1;
        foreach ($items as $it) {
            $desc = is_array($it['description'] ?? null) ? $it['description'] : [];
            $line->execute([
                $auditId,
                $lineNo,
                (string) ($desc['name'] ?? '—'),
                ($desc['brand'] ?? '') !== '' ? (string) $desc['brand'] : null,
                ($desc['model'] ?? '') !== '' ? (string) $desc['model'] : null,
                ($desc['specification'] ?? '') !== '' ? (string) $desc['specification'] : null,
                max(1, (int) ($it['qty'] ?? 1)),
                (string) ($it['supplier_name'] ?? '—'),
                round((float) ($it['unit_price'] ?? 0), 2),
                round((float) ($it['amount'] ?? 0), 2),
            ]);
            $lineNo += 1;
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
}

/**
 * @return array<int, array<string, string>>
 */
function purchaseRequisitionDescriptionsByCanvassDetail(PDO $db, int $requestId): array
{
    $hasModelInCanvass = tableHasColumn($db, 'requisition_canvass_detail', 'model');
    $hasNameInCanvass = tableHasColumn($db, 'requisition_canvass_detail', 'item_name');
    $hasBrandInCanvass = tableHasColumn($db, 'requisition_canvass_detail', 'brand');

    $nameExpr = $hasNameInCanvass ? 'NULLIF(TRIM(cd.item_name), \'\')' : 'NULL';
    $brandExpr = $hasBrandInCanvass ? 'NULLIF(TRIM(cd.brand), \'\')' : 'NULL';
    $modelExpr = $hasModelInCanvass ? 'NULLIF(TRIM(cd.model), \'\')' : 'NULL';

    $stmt = $db->prepare(
        "SELECT
            cd.canvass_detail_id,
            COALESCE($nameExpr, NULLIF(TRIM(rl.item_name), ''), NULLIF(TRIM(cd.component_label), ''), '—') AS item_name,
            COALESCE($brandExpr, NULLIF(TRIM(rl.item_brand), ''), '') AS item_brand,
            COALESCE($modelExpr, '') AS item_model,
            COALESCE(NULLIF(TRIM(cd.specification), ''), NULLIF(TRIM(rl.item_category), ''), '') AS item_specification
         FROM requisition_canvass_detail cd
         LEFT JOIN requisition_line rl
            ON rl.requisition_line_id = cd.requisition_line_id
           AND rl.request_id = cd.request_id
         WHERE cd.request_id = ?
         ORDER BY cd.sort_order ASC, cd.canvass_detail_id ASC"
    );
    $stmt->execute([$requestId]);
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $detailId = (int) ($row['canvass_detail_id'] ?? 0);
        if ($detailId > 0) {
            $map[$detailId] = $row;
        }
    }

    return $map;
}

/**
 * Header, line items, and display fields for purchase requisition (snapshot / GET payload).
 *
 * @return array{
 *   header: array<string, mixed>,
 *   items: array<int, array<string, mixed>>,
 *   grand_total: float,
 *   requester_display: string,
 *   purpose: string,
 *   location_label: string
 * }|null
 */
function loadPurchaseRequisitionSnapshotData(PDO $db, int $requestId): ?array
{
    require_once __DIR__ . '/../helpers/comptroller_qty_approval.php';

    $headerStmt = $db->prepare(
        'SELECT r.request_id, r.created_at, r.purpose, r.user_id, u.Email,
                f.room, f.laboratory, f.building
         FROM requisition_item r
         LEFT JOIN user u ON u.user_id = r.user_id
         LEFT JOIN facilities f ON f.facility_id = r.facility_id
         WHERE r.request_id = ?
         LIMIT 1'
    );
    $headerStmt->execute([$requestId]);
    $header = $headerStmt->fetch(PDO::FETCH_ASSOC);
    if (!$header) {
        return null;
    }

    $overview = cwirmsComptrollerPricingOverviewForRequest($db, $requestId);
    $pricingLines = $overview['lines'] ?? [];
    $descByDetail = purchaseRequisitionDescriptionsByCanvassDetail($db, $requestId);

    $items = [];
    $grandTotal = 0.0;
    foreach ($pricingLines as $line) {
        $detailId = (int) ($line['canvass_detail_id'] ?? 0);
        $desc = $descByDetail[$detailId] ?? [];

        $qty = max(0, (int) ($line['accepted_qty'] ?? $line['requested_qty'] ?? $line['quantity'] ?? 0));
        $unitPrice = isset($line['unit_price']) && is_numeric($line['unit_price'])
            ? (float) $line['unit_price']
            : 0.0;
        $amount = round($qty * $unitPrice, 2);
        $grandTotal += $amount;

        $supplierName = trim((string) ($line['supplier_name'] ?? ''));
        $itemName = trim((string) ($desc['item_name'] ?? $line['item_name'] ?? ''));

        $items[] = [
            'description' => [
                'name' => $itemName !== '' ? $itemName : '—',
                'brand' => (string) ($desc['item_brand'] ?? ''),
                'model' => (string) ($desc['item_model'] ?? ''),
                'specification' => (string) ($desc['item_specification'] ?? ''),
            ],
            'qty' => $qty,
            'supplier_name' => $supplierName !== '' ? $supplierName : '—',
            'unit_price' => $unitPrice,
            'amount' => $amount,
        ];
    }

    $requesterEmail = (string) ($header['Email'] ?? '');
    $requesterDisplay = $requesterEmail !== '' ? (explode('@', $requesterEmail)[0] ?? $requesterEmail) : '—';
    $purpose = (string) ($header['purpose'] ?? '');
    $room = trim((string) ($header['room'] ?? ''));
    $lab = trim((string) ($header['laboratory'] ?? ''));
    $building = trim((string) ($header['building'] ?? ''));
    $roomOrLab = $room !== '' ? $room : $lab;
    $locationLabel = $roomOrLab !== '' && $building !== ''
        ? ($roomOrLab . ' · ' . $building)
        : ($roomOrLab !== '' ? $roomOrLab : ($building !== '' ? $building : '—'));

    return [
        'header' => $header,
        'items' => $items,
        'grand_total' => $grandTotal,
        'requester_display' => $requesterDisplay,
        'purpose' => $purpose,
        'location_label' => $locationLabel,
    ];
}

try {
    $db = Database::connect();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($action === 'set_pr_verification') {
        $userId = assertSessionUserId();
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $verifier = strtolower(trim((string) ($_POST['verifier'] ?? '')));
        $prStatus = strtolower(trim((string) ($_POST['pr_status'] ?? '')));
        $note = trim((string) ($_POST['pr_note'] ?? ''));

        if ($requestId <= 0 || !in_array($verifier, ['inventory', 'president'], true) || !in_array($prStatus, ['accept', 'reject', 'pending'], true)) {
            sendJson(['success' => false, 'message' => 'Invalid verification payload.']);
        }

        if (!cwirmsApprovalTableExists($db, 'purchase_requisition_approval')) {
            sendJson(['success' => false, 'message' => 'Purchase requisition verification is not installed. Run migration 20260529_split_request_approval_tables.sql (or restore purchase_requisition_approval from cwirms.sql).']);
        }

        if ($verifier === 'inventory') {
            assertInventoryManagerPr($db);
        } else {
            assertPresidentPr($db);
        }

        if (!canViewPurchaseRequisition($db, $userId, $requestId)) {
            sendJson(['success' => false, 'message' => 'Forbidden']);
        }

        $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $chk->execute([$requestId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        if ($prStatus !== 'pending') {
            if (!requestHasPurchaseRequisitionLines($db, $requestId)) {
                sendJson(['success' => false, 'message' => 'No purchase requisition lines yet (suggested suppliers required).']);
            }
            if (!requestGsdCanvassAccepted($db, $requestId)) {
                sendJson(['success' => false, 'message' => 'G.S.D., Comptroller, and President must verify the canvass form before purchase requisition verification.']);
            }
        }

        if ($verifier === 'president' && $prStatus === 'accept') {
            $invStmt = $db->prepare(
                'SELECT LOWER(TRIM(COALESCE(pr_inv_status, \'\'))) FROM purchase_requisition_approval WHERE request_id = ? LIMIT 1'
            );
            $invStmt->execute([$requestId]);
            $invSt = strtolower(trim((string) ($invStmt->fetchColumn() ?: '')));
            if ($invSt !== 'accept') {
                sendJson(['success' => false, 'message' => 'Inventory must verify the purchase requisition before presidential approval.']);
            }
        }

        if ($prStatus === 'reject' && $note === '') {
            sendJson(['success' => false, 'message' => 'Please add a rejection note.']);
        }

        ensurePurchaseRequisitionApprovalRow($db, $requestId);

        if ($verifier === 'inventory') {
            if ($prStatus === 'pending') {
                $up = $db->prepare(
                    'UPDATE purchase_requisition_approval SET pr_inv_status = ?, pr_inv_note = NULL, pr_inv_at = NULL WHERE request_id = ?'
                );
                $up->execute([$prStatus, $requestId]);
            } else {
                $up = $db->prepare(
                    'UPDATE purchase_requisition_approval SET pr_inv_status = ?, pr_inv_note = ?, pr_inv_at = NOW() WHERE request_id = ?'
                );
                $up->execute([$prStatus, $note !== '' ? $note : null, $requestId]);
            }
        } elseif ($prStatus === 'pending') {
            $up = $db->prepare(
                'UPDATE purchase_requisition_approval SET pr_pres_status = ?, pr_pres_note = NULL, pr_pres_at = NULL WHERE request_id = ?'
            );
            $up->execute([$prStatus, $requestId]);
        } else {
            $up = $db->prepare(
                'UPDATE purchase_requisition_approval SET pr_pres_status = ?, pr_pres_note = ?, pr_pres_at = NOW() WHERE request_id = ?'
            );
            $up->execute([$prStatus, $note !== '' ? $note : null, $requestId]);
        }

        if ($prStatus === 'accept') {
            $snap = loadPurchaseRequisitionSnapshotData($db, $requestId);
            if ($snap) {
                savePurchaseAuditSnapshot(
                    $db,
                    $requestId,
                    $userId,
                    $snap['requester_display'],
                    $snap['purpose'],
                    $snap['grand_total'],
                    $snap['items']
                );
            }
        }

        sendJson(['success' => true, 'message' => 'Purchase requisition verification saved.']);
    }

    if ($action !== 'get') {
        sendJson(['success' => false, 'message' => 'Invalid action.']);
    }

    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Unauthorized']);
    }

    $requestId = (int) ($_GET['request_id'] ?? 0);
    if ($requestId <= 0) {
        sendJson(['success' => false, 'message' => 'Invalid request id.']);
    }

    $userId = (int) $_SESSION['user_id'];
    if (!canViewPurchaseRequisition($db, $userId, $requestId)) {
        sendJson(['success' => false, 'message' => 'Forbidden']);
    }

    if (!requisitionCanvassFormAcceptedForRequest($db, $requestId)) {
        sendJson(['success' => false, 'message' => 'Purchase requisition is available only after G.S.D., Comptroller, and President verify the canvass form.']);
    }

    $snap = loadPurchaseRequisitionSnapshotData($db, $requestId);
    if (!$snap) {
        sendJson(['success' => false, 'message' => 'Request not found.']);
    }
    $header = $snap['header'];
    $items = $snap['items'];
    $grandTotal = $snap['grand_total'];
    $requesterDisplay = $snap['requester_display'];
    $purpose = $snap['purpose'];
    $locationLabel = $snap['location_label'];

    if (cwirmsApprovalTableExists($db, 'purchase_requisition_approval')) {
        ensurePurchaseRequisitionApprovalRow($db, $requestId);
        $approvalStmt = $db->prepare(
            'SELECT pr_inv_status, pr_pres_status FROM purchase_requisition_approval WHERE request_id = ? LIMIT 1'
        );
        $approvalStmt->execute([$requestId]);
        $approval = $approvalStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } else {
        $approval = ['pr_inv_status' => 'pending', 'pr_pres_status' => 'pending'];
    }

    sendJson([
        'success' => true,
        'request_id' => (int) $header['request_id'],
        'requested_at' => (string) ($header['created_at'] ?? ''),
        'purpose' => $purpose,
        'requester' => $requesterDisplay,
        'location_label' => $locationLabel,
        'items' => $items,
        'grand_total' => $grandTotal,
        'approval_summary' => [
            'inventory_status' => (string) ($approval['pr_inv_status'] ?? 'pending'),
            'president_status' => (string) ($approval['pr_pres_status'] ?? 'pending'),
        ],
    ]);
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

