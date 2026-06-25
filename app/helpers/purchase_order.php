<?php

declare(strict_types=1);

require_once __DIR__ . '/comptroller_qty_approval.php';
require_once __DIR__ . '/supplier.php';
require_once __DIR__ . '/../api/approval_tables.php';

function cwirmsResolvePoModeOfPayment(float $totalAmount): string
{
    return round($totalAmount, 2) <= 1500.0 ? 'cash' : 'cheque';
}

function cwirmsFormatPoModeOfPaymentLabel(string $mode): string
{
    return strtolower(trim($mode)) === 'cheque' ? 'Cheque' : 'Cash';
}

function cwirmsGenerateNextPoNumber(PDO $db): string
{
    ensurePurchaseOrderTables($db);
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

function cwirmsPurchaseOrderIdForRequest(PDO $db, int $requestId): ?int
{
    if ($requestId <= 0) {
        return null;
    }
    ensurePurchaseOrderTables($db);
    $stmt = $db->prepare(
        'SELECT id FROM purchase_orders WHERE requisition_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$requestId]);
    $id = (int) ($stmt->fetchColumn() ?: 0);

    return $id > 0 ? $id : null;
}

/**
 * @return array<int, array<string, string>>
 */
function cwirmsPurchaseOrderDescriptionsByCanvassDetail(PDO $db, int $requestId): array
{
    $colCheck = static function (string $column) use ($db): bool {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'requisition_canvass_detail'
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$column]);

        return ((int) $stmt->fetchColumn()) > 0;
    };

    $hasModel = $colCheck('model');
    $hasName = $colCheck('item_name');
    $hasBrand = $colCheck('brand');

    $nameExpr = $hasName ? "NULLIF(TRIM(cd.item_name), '')" : 'NULL';
    $brandExpr = $hasBrand ? "NULLIF(TRIM(cd.brand), '')" : 'NULL';
    $modelExpr = $hasModel ? "NULLIF(TRIM(cd.model), '')" : 'NULL';

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
 * @return array{
 *   header: array<string, mixed>,
 *   lines: list<array{description: string, sub_description: ?string, quantity: int, unit_price: float, amount: float}>,
 *   total_amount: float,
 *   supplier_name: string,
 *   supplier_id: ?int,
 *   supplier_tin: ?string,
 *   requested_by_name: string,
 *   requested_by_user_id: int,
 *   facility_id: ?int,
 *   location_facility: string,
 *   purpose_of_request: string
 * }|null
 */
function cwirmsBuildPurchaseOrderDraftFromRequisition(PDO $db, int $requestId): ?array
{
    if ($requestId <= 0) {
        return null;
    }

    $headerStmt = $db->prepare(
        'SELECT r.request_id, r.purpose, r.user_id, r.facility_id, u.full_name, u.Email,
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
    if ($pricingLines === []) {
        return null;
    }

    $descByDetail = cwirmsPurchaseOrderDescriptionsByCanvassDetail($db, $requestId);
    $lines = [];
    $totalAmount = 0.0;
    $primarySupplierName = '';

    foreach ($pricingLines as $line) {
        $detailId = (int) ($line['canvass_detail_id'] ?? 0);
        $desc = $descByDetail[$detailId] ?? [];
        $qty = max(0, (int) ($line['accepted_qty'] ?? $line['requested_qty'] ?? $line['quantity'] ?? 0));
        if ($qty <= 0) {
            continue;
        }
        $unitPrice = isset($line['unit_price']) && is_numeric($line['unit_price'])
            ? round((float) $line['unit_price'], 2)
            : 0.0;
        $discountPercent = cwirmsNormalizeCanvassSupplierDiscountPercent($line['discount_percent'] ?? null);
        $discountFactor = $discountPercent !== null ? (1 - $discountPercent / 100) : 1.0;
        $effectiveUnitPrice = round($unitPrice * $discountFactor, 2);
        if (isset($line['approved_line_total']) && is_numeric($line['approved_line_total'])) {
            $amount = round((float) $line['approved_line_total'], 2);
        } else {
            $amount = round($qty * $effectiveUnitPrice, 2);
        }
        $totalAmount += $amount;

        $itemName = trim((string) ($desc['item_name'] ?? $line['item_name'] ?? ''));
        $brand = trim((string) ($desc['item_brand'] ?? ''));
        $model = trim((string) ($desc['item_model'] ?? ''));
        $spec = trim((string) ($desc['item_specification'] ?? ''));
        $subParts = array_values(array_filter([$brand, $model], static fn ($v) => $v !== ''));
        $subDescription = $subParts !== [] ? implode(' · ', $subParts) : ($spec !== '' ? $spec : null);

        $supplierName = trim((string) ($line['supplier_name'] ?? ''));
        if ($primarySupplierName === '' && $supplierName !== '') {
            $primarySupplierName = $supplierName;
        }

        $lines[] = [
            'description' => $itemName !== '' ? $itemName : '—',
            'sub_description' => $subDescription,
            'quantity' => $qty,
            'unit_price' => $effectiveUnitPrice,
            'amount' => $amount,
        ];
    }

    if ($lines === []) {
        return null;
    }

    $requesterUserId = (int) ($header['user_id'] ?? 0);
    $fullName = trim((string) ($header['full_name'] ?? ''));
    $email = (string) ($header['Email'] ?? '');
    $requesterDisplay = $fullName !== ''
        ? $fullName
        : ($email !== '' ? (explode('@', $email)[0] ?? $email) : 'Requester');

    $room = trim((string) ($header['room'] ?? ''));
    $lab = trim((string) ($header['laboratory'] ?? ''));
    $building = trim((string) ($header['building'] ?? ''));
    $roomOrLab = $room !== '' ? $room : $lab;
    $locationLabel = $roomOrLab !== '' && $building !== ''
        ? ($roomOrLab . ' · ' . $building)
        : ($roomOrLab !== '' ? $roomOrLab : ($building !== '' ? $building : '—'));

    $supplierId = null;
    $supplierTin = null;
    if ($primarySupplierName !== '') {
        ensureSupplierTinColumn($db);
        $supStmt = $db->prepare(
            'SELECT supplier_id, tin FROM suppliers WHERE supplier_name = ? ORDER BY supplier_id ASC LIMIT 1'
        );
        $supStmt->execute([$primarySupplierName]);
        $supRow = $supStmt->fetch(PDO::FETCH_ASSOC);
        if ($supRow) {
            $supplierId = (int) ($supRow['supplier_id'] ?? 0) ?: null;
            $supplierTin = cwirmsNormalizeSupplierTin($supRow['tin'] ?? null);
        }
    }

    return [
        'header' => $header,
        'lines' => $lines,
        'total_amount' => round($totalAmount, 2),
        'supplier_name' => $primarySupplierName !== '' ? $primarySupplierName : '—',
        'supplier_id' => $supplierId,
        'supplier_tin' => $supplierTin,
        'requested_by_name' => $requesterDisplay,
        'requested_by_user_id' => $requesterUserId,
        'facility_id' => !empty($header['facility_id']) ? (int) $header['facility_id'] : null,
        'location_facility' => $locationLabel,
        'purpose_of_request' => trim((string) ($header['purpose'] ?? '')),
    ];
}

function cwirmsPurchaseRequisitionFullyAccepted(PDO $db, int $requestId): bool
{
    if ($requestId <= 0 || !cwirmsApprovalTableExists($db, 'purchase_requisition_approval')) {
        return false;
    }
    $stmt = $db->prepare(
        'SELECT LOWER(TRIM(COALESCE(pr_inv_status, \'pending\'))) AS inv,
                LOWER(TRIM(COALESCE(pr_pres_status, \'pending\'))) AS pres
         FROM purchase_requisition_approval WHERE request_id = ? LIMIT 1'
    );
    $stmt->execute([$requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return strtolower(trim((string) ($row['inv'] ?? ''))) === 'accept'
        && strtolower(trim((string) ($row['pres'] ?? ''))) === 'accept';
}

/**
 * Create a purchase order from an accepted purchase requisition when none exists yet.
 */
function cwirmsEnsurePurchaseOrderFromRequisition(PDO $db, int $requestId, int $generatedByUserId): ?int
{
    ensurePurchaseOrderTables($db);

    $existingId = cwirmsPurchaseOrderIdForRequest($db, $requestId);
    if ($existingId !== null) {
        return $existingId;
    }

    if (!cwirmsPurchaseRequisitionFullyAccepted($db, $requestId)) {
        return null;
    }

    $draft = cwirmsBuildPurchaseOrderDraftFromRequisition($db, $requestId);
    if ($draft === null || $draft['lines'] === []) {
        return null;
    }

    $db->beginTransaction();
    try {
        $poNumber = cwirmsGenerateNextPoNumber($db);
        $modeOfPayment = cwirmsResolvePoModeOfPayment((float) $draft['total_amount']);
        $insert = $db->prepare(
            'INSERT INTO purchase_orders
             (po_number, requisition_id, requested_by_user_id, requested_by_name, facility_id,
              location_facility, supplier_id, supplier_name, supplier_tin,
              mode_of_payment, purpose_of_request, total_amount, status, created_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?)'
        );
        $insert->execute([
            $poNumber,
            $requestId,
            $draft['requested_by_user_id'] > 0 ? $draft['requested_by_user_id'] : null,
            $draft['requested_by_name'],
            $draft['facility_id'],
            $draft['location_facility'],
            $draft['supplier_id'],
            $draft['supplier_name'],
            $draft['supplier_tin'],
            $modeOfPayment,
            $draft['purpose_of_request'] !== '' ? $draft['purpose_of_request'] : null,
            $draft['total_amount'],
            $generatedByUserId > 0 ? $generatedByUserId : null,
        ]);
        $poId = (int) $db->lastInsertId();

        $lineInsert = $db->prepare(
            'INSERT INTO purchase_order_lines
             (purchase_order_id, description, sub_description, quantity, unit_price, amount, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($draft['lines'] as $sort => $line) {
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

        return $poId;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        return null;
    }
}
