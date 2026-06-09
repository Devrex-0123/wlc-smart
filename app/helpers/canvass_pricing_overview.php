<?php

declare(strict_types=1);

/**
 * Build GSD suggested-supplier pricing overview (per canvass line + grand total).
 *
 * @return array{
 *     lines: list<array{
 *         item_index: int,
 *         canvass_detail_id: int,
 *         item_name: string,
 *         quantity: int,
 *         unit_type: string,
 *         supplier_id: int|null,
 *         supplier_name: string|null,
 *         selection_source: string|null,
 *         unit_price: float|null,
 *         line_total: float|null
 *     }>,
 *     item_count: int,
 *     selected_count: int,
 *     grand_total: float,
 *     currency: string
 * }
 */
function cwirmsRequestProcurementBaselineFromLines(array $requisitionLines): array
{
    if ($requisitionLines === []) {
        return ['quantity' => 1, 'unit_type' => 'unit'];
    }

    $setRows = [];
    foreach ($requisitionLines as $row) {
        if (strtolower(trim((string) ($row['unit_type'] ?? ''))) === 'set') {
            $setRows[] = $row;
        }
    }

    if ($setRows !== []) {
        $best = $setRows[0];
        foreach ($setRows as $row) {
            if ((int) ($row['quantity'] ?? 0) > (int) ($best['quantity'] ?? 0)) {
                $best = $row;
            }
        }

        return [
            'quantity' => max(1, (int) ($best['quantity'] ?? 1)),
            'unit_type' => 'set',
        ];
    }

    if (count($requisitionLines) === 1) {
        $only = $requisitionLines[0];

        return [
            'quantity' => max(1, (int) ($only['quantity'] ?? 1)),
            'unit_type' => trim((string) ($only['unit_type'] ?? 'unit')) ?: 'unit',
        ];
    }

    return ['quantity' => 1, 'unit_type' => 'unit'];
}

/**
 * @param array<int, int>    $lineQty
 * @param array<int, string> $lineUnit
 * @return array{quantity: int, unit_type: string, qty_per_set: int, requisition_qty: int}
 */
function cwirmsPricingQuantityForCanvassDetail(
    int $lineId,
    array $lineQty,
    array $lineUnit,
    array $baseline
): array {
    $qtyPerSet = 1;

    if ($lineId > 0 && isset($lineQty[$lineId])) {
        $rlQty = max(1, (int) $lineQty[$lineId]);
        $rlUnit = $lineUnit[$lineId] ?? 'unit';
        if ($rlUnit === 'set' || $rlQty > 1) {
            return [
                'quantity' => $rlQty,
                'unit_type' => $rlUnit,
                'qty_per_set' => $qtyPerSet,
                'requisition_qty' => $rlQty,
            ];
        }
    }

    return [
        'quantity' => max(1, (int) ($baseline['quantity'] ?? 1)),
        'unit_type' => (string) ($baseline['unit_type'] ?? 'unit'),
        'qty_per_set' => $qtyPerSet,
        'requisition_qty' => max(1, (int) ($baseline['quantity'] ?? 1)),
    ];
}

function cwirmsCanvassPricingOverviewForRequest(PDO $db, int $requestId): array
{
    $currency = 'PHP';
    $empty = [
        'lines' => [],
        'item_count' => 0,
        'selected_count' => 0,
        'grand_total' => 0.0,
        'currency' => $currency,
    ];
    if ($requestId <= 0) {
        return $empty;
    }

    require_once __DIR__ . '/../api/approval_tables.php';
    ensureRequisitionPreferredQuoteColumns($db);
    ensureSuggestedSupplierSelectionSourceColumn($db);
    ensureCanvassSupplierNotesColumns($db);
    ensureCanvassSupplierDiscountsTable($db);
    $canvassDiscountBySupplier = cwirmsCanvassSupplierDiscountPercentsMapForRequest($db, $requestId);

    $itemStmt = $db->prepare(
        'SELECT canvass_detail_id, requisition_line_id, component_label, sort_order
         FROM requisition_canvass_detail
         WHERE request_id = ?
         ORDER BY sort_order ASC, canvass_detail_id ASC'
    );
    $itemStmt->execute([$requestId]);
    $itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    if ($itemRows === []) {
        return $empty;
    }

    $lineQty = [];
    $lineUnit = [];
    $requisitionLines = [];
    $rlStmt = $db->prepare(
        'SELECT requisition_line_id, quantity, unit_type FROM requisition_line WHERE request_id = ?'
    );
    $rlStmt->execute([$requestId]);
    while ($rl = $rlStmt->fetch(PDO::FETCH_ASSOC)) {
        $requisitionLines[] = $rl;
        $lid = (int) ($rl['requisition_line_id'] ?? 0);
        if ($lid <= 0) {
            continue;
        }
        $lineQty[$lid] = max(1, (int) ($rl['quantity'] ?? 1));
        $lineUnit[$lid] = trim((string) ($rl['unit_type'] ?? 'unit')) ?: 'unit';
    }
    $procurementBaseline = cwirmsRequestProcurementBaselineFromLines($requisitionLines);

    $selStmt = $db->prepare(
        'SELECT rassi.canvass_detail_id, rassi.supplier_id, rassi.selection_source, s.supplier_name
         FROM request_approval_suggested_supplier_item rassi
         LEFT JOIN suppliers s ON s.supplier_id = rassi.supplier_id
         WHERE rassi.request_id = ?'
    );
    $selStmt->execute([$requestId]);
    $selectionByDetail = [];
    while ($sel = $selStmt->fetch(PDO::FETCH_ASSOC)) {
        $cid = (int) ($sel['canvass_detail_id'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        $selectionByDetail[$cid] = [
            'supplier_id' => (int) ($sel['supplier_id'] ?? 0),
            'supplier_name' => (string) ($sel['supplier_name'] ?? ''),
            'selection_source' => strtolower(trim((string) ($sel['selection_source'] ?? ''))),
        ];
    }

    require_once __DIR__ . '/../api/approval_tables.php';
    $prefPrices = [];
    $quoteMaps = cwirmsLoadPreferredSupplierQuoteMapsForRequest($db, $requestId);
    foreach ($quoteMaps as $sid => $entry) {
        $prefPrices[(int) $sid] = $entry['prices'];
    }

    $lines = [];
    $selectedCount = 0;
    $grandTotal = 0.0;

    foreach ($itemRows as $idx => $row) {
        $detailId = (int) ($row['canvass_detail_id'] ?? 0);
        $lineId = isset($row['requisition_line_id']) ? (int) $row['requisition_line_id'] : 0;
        $sortOrder = (int) ($row['sort_order'] ?? $idx);
        $qtyMeta = cwirmsPricingQuantityForCanvassDetail($lineId, $lineQty, $lineUnit, $procurementBaseline);
        $qty = (int) $qtyMeta['quantity'];
        $unit = (string) $qtyMeta['unit_type'];
        $qtyPerSet = (int) $qtyMeta['qty_per_set'];
        $requisitionQty = (int) $qtyMeta['requisition_qty'];

        $sel = $selectionByDetail[$detailId] ?? null;
        $supplierId = $sel ? (int) ($sel['supplier_id'] ?? 0) : 0;
        $source = $sel ? (string) ($sel['selection_source'] ?? '') : '';
        if ($source !== 'preferred' && $source !== 'canvassed') {
            $source = null;
        }

        $unitPrice = null;
        $lineTotal = null;
        $discountLabel = null;
        $supplierName = $sel ? (string) ($sel['supplier_name'] ?? '') : null;

        if ($supplierId > 0) {
            $selectedCount++;
            if ($source === 'preferred') {
                $unitPrice = cwirmsPricingOverviewPreferredUnitPrice($prefPrices, $supplierId, $sortOrder, $idx);
            } else {
                $unitPrice = cwirmsPricingOverviewCanvassedUnitPrice($db, $detailId, $supplierId);
                if ($source === null) {
                    $source = 'canvassed';
                }
            }
            if ($unitPrice !== null) {
                $discountLabel = null;
                if ($source === 'canvassed' && $supplierId > 0) {
                    $discountPercents = $canvassDiscountBySupplier[$supplierId] ?? [];
                    if ($discountPercents !== []) {
                        $unitPrice = cwirmsApplyCompoundedCanvassDiscounts($unitPrice, $discountPercents);
                        $discountLabel = cwirmsFormatCompoundedDiscountLabel($discountPercents);
                    }
                }
                $lineTotal = round($unitPrice * $qty, 2);
                $grandTotal += $lineTotal;
            }
        } else {
            $supplierId = null;
        }

        $lines[] = [
            'item_index' => $idx,
            'canvass_detail_id' => $detailId,
            'item_name' => (string) ($row['component_label'] ?? ''),
            'quantity' => $qty,
            'qty_per_set' => $qtyPerSet,
            'requisition_qty' => $requisitionQty,
            'unit_type' => $unit,
            'supplier_id' => $supplierId,
            'supplier_name' => $supplierName !== '' ? $supplierName : null,
            'selection_source' => $source,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'discount_label' => $discountLabel ?? null,
        ];
    }

    return [
        'lines' => $lines,
        'item_count' => count($lines),
        'selected_count' => $selectedCount,
        'grand_total' => round($grandTotal, 2),
        'currency' => $currency,
        'show_discount_column' => $canvassDiscountBySupplier !== [],
    ];
}

function cwirmsPricingOverviewPreferredUnitPrice(array $prefPrices, int $supplierId, int $sortOrder, int $itemIndex): ?float
{
    $map = $prefPrices[$supplierId] ?? [];
    if (!is_array($map) || $map === []) {
        return null;
    }
    $raw = $map[$sortOrder] ?? $map[(string) $sortOrder] ?? $map[$itemIndex] ?? $map[(string) $itemIndex] ?? null;
    if ($raw === null || $raw === '' || !is_numeric($raw)) {
        return null;
    }
    $price = round((float) $raw, 2);

    return $price >= 0 ? $price : null;
}

function cwirmsPricingOverviewCanvassedUnitPrice(PDO $db, int $canvassDetailId, int $supplierId): ?float
{
    $stmt = $db->prepare(
        'SELECT price FROM requisition_canvass_detail_supplier
         WHERE canvass_detail_id = ? AND supplier_id = ? LIMIT 1'
    );
    $stmt->execute([$canvassDetailId, $supplierId]);
    $raw = $stmt->fetchColumn();
    if ($raw === false || $raw === null || !is_numeric($raw)) {
        return null;
    }
    $price = round((float) $raw, 2);

    return $price >= 0 ? $price : null;
}

function cwirmsNormalizeCanvassSupplierDiscountPercent(mixed $raw): ?float
{
    if ($raw === null || $raw === '') {
        return null;
    }
    if (!is_numeric($raw)) {
        return null;
    }
    $value = round((float) $raw, 2);
    if ($value <= 0) {
        return null;
    }
    if ($value > 100) {
        return 100.0;
    }

    return $value;
}

function cwirmsApplyCompoundedCanvassDiscounts(float $total, array $discountPercents): float
{
    $result = $total;
    foreach ($discountPercents as $percent) {
        $normalized = cwirmsNormalizeCanvassSupplierDiscountPercent($percent);
        if ($normalized !== null) {
            $result *= (1 - $normalized / 100);
        }
    }

    return round($result, 2);
}

function cwirmsEffectiveCompoundedDiscountPercent(array $discountPercents): ?float
{
    if ($discountPercents === []) {
        return null;
    }
    $factor = 1.0;
    foreach ($discountPercents as $percent) {
        $normalized = cwirmsNormalizeCanvassSupplierDiscountPercent($percent);
        if ($normalized !== null) {
            $factor *= (1 - $normalized / 100);
        }
    }
    $effective = round((1 - $factor) * 10000) / 100;

    return $effective > 0 ? $effective : null;
}

function cwirmsFormatCompoundedDiscountLabel(array $discountPercents): ?string
{
    $effective = cwirmsEffectiveCompoundedDiscountPercent($discountPercents);
    if ($effective === null) {
        return null;
    }
    $label = fmod($effective, 1.0) === 0.0
        ? (string) (int) $effective
        : rtrim(rtrim(number_format($effective, 2, '.', ''), '0'), '.');

    return $label . '%';
}

/**
 * @return array<int, list<float>> supplier_id => discount percents (ordered)
 */
function cwirmsCanvassSupplierDiscountPercentsMapForRequest(PDO $db, int $requestId): array
{
    $bySupplier = cwirmsCanvassSupplierDiscountsBySupplierForRequest($db, $requestId);
    $map = [];
    foreach ($bySupplier as $sid => $rows) {
        $percents = [];
        foreach ($rows as $row) {
            $pct = cwirmsNormalizeCanvassSupplierDiscountPercent($row['discount_percent'] ?? null);
            if ($pct !== null) {
                $percents[] = $pct;
            }
        }
        if ($percents !== []) {
            $map[$sid] = $percents;
        }
    }

    return $map;
}

/**
 * @return array<int, list<array{id: int, label: ?string, discount_percent: float}>>
 */
function cwirmsCanvassSupplierDiscountsBySupplierForRequest(PDO $db, int $requestId): array
{
    require_once __DIR__ . '/../api/approval_tables.php';
    ensureCanvassSupplierDiscountsTable($db);

    $stmt = $db->prepare(
        'SELECT cds.supplier_id, csd.id, csd.label, csd.discount_percent
         FROM canvass_supplier_discounts csd
         INNER JOIN requisition_canvass_detail_supplier cds
            ON cds.canvass_detail_supplier_id = csd.canvass_supplier_id
         INNER JOIN requisition_canvass_detail cd ON cd.canvass_detail_id = cds.canvass_detail_id
         WHERE cd.request_id = ?
         ORDER BY cds.supplier_id ASC, csd.id ASC'
    );
    $stmt->execute([$requestId]);
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int) ($row['supplier_id'] ?? 0);
        if ($sid <= 0) {
            continue;
        }
        $pct = cwirmsNormalizeCanvassSupplierDiscountPercent($row['discount_percent'] ?? null);
        if ($pct === null) {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        if (!isset($map[$sid])) {
            $map[$sid] = [];
        }
        $map[$sid][] = [
            'id' => (int) ($row['id'] ?? 0),
            'label' => $label !== '' ? $label : null,
            'discount_percent' => $pct,
        ];
    }

    return $map;
}

/**
 * @return list<array{label: ?string, discount_percent: float}>
 */
function cwirmsNormalizeCanvassSupplierDiscountRows(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $rows = [];
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $pct = cwirmsNormalizeCanvassSupplierDiscountPercent($entry['discount_percent'] ?? null);
        if ($pct === null) {
            continue;
        }
        $label = trim((string) ($entry['label'] ?? ''));
        $rows[] = [
            'label' => $label !== '' ? substr($label, 0, 100) : null,
            'discount_percent' => $pct,
        ];
    }

    return $rows;
}

function cwirmsValidateCanvassSupplierDiscountPayload(mixed $discounts): ?string
{
    if ($discounts === null) {
        return null;
    }
    if (!is_array($discounts)) {
        return 'Supplier discounts must be a list.';
    }
    foreach ($discounts as $entry) {
        if (!is_array($entry)) {
            return 'Invalid supplier discount row.';
        }
        $pctRaw = $entry['discount_percent'] ?? null;
        if ($pctRaw === null || $pctRaw === '') {
            continue;
        }
        if (!is_numeric($pctRaw)) {
            return 'Each supplier discount must be a number between 0 and 100.';
        }
        $pct = (float) $pctRaw;
        if ($pct < 0 || $pct > 100) {
            return 'Each supplier discount must be between 0 and 100.';
        }
    }

    return null;
}

/**
 * @param list<array<string, mixed>> $suppliers
 */
function cwirmsPersistCanvassSupplierDiscountsForRequest(PDO $db, int $requestId, array $suppliers): void
{
    require_once __DIR__ . '/../api/approval_tables.php';
    ensureCanvassSupplierDiscountsTable($db);

    $del = $db->prepare(
        'DELETE csd FROM canvass_supplier_discounts csd
         INNER JOIN requisition_canvass_detail_supplier cds
            ON cds.canvass_detail_supplier_id = csd.canvass_supplier_id
         INNER JOIN requisition_canvass_detail cd ON cd.canvass_detail_id = cds.canvass_detail_id
         WHERE cd.request_id = ?'
    );
    $del->execute([$requestId]);

    $anchorStmt = $db->prepare(
        'SELECT cds.supplier_id, MIN(cds.canvass_detail_supplier_id) AS anchor_id
         FROM requisition_canvass_detail_supplier cds
         INNER JOIN requisition_canvass_detail cd ON cd.canvass_detail_id = cds.canvass_detail_id
         WHERE cd.request_id = ?
         GROUP BY cds.supplier_id'
    );
    $anchorStmt->execute([$requestId]);
    $anchors = [];
    while ($row = $anchorStmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int) ($row['supplier_id'] ?? 0);
        $anchorId = (int) ($row['anchor_id'] ?? 0);
        if ($sid > 0 && $anchorId > 0) {
            $anchors[$sid] = $anchorId;
        }
    }

    if ($anchors === []) {
        return;
    }

    $ins = $db->prepare(
        'INSERT INTO canvass_supplier_discounts (canvass_supplier_id, label, discount_percent) VALUES (?, ?, ?)'
    );

    foreach ($suppliers as $supplier) {
        if (!is_array($supplier)) {
            continue;
        }
        $sid = (int) ($supplier['supplier_id'] ?? 0);
        if ($sid <= 0 || !isset($anchors[$sid])) {
            continue;
        }
        $rows = cwirmsNormalizeCanvassSupplierDiscountRows($supplier['discounts'] ?? []);
        if ($rows === []) {
            continue;
        }
        foreach ($rows as $row) {
            $ins->execute([$anchors[$sid], $row['label'], $row['discount_percent']]);
        }
    }
}
