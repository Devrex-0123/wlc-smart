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
    $rlStmt = $db->prepare(
        'SELECT requisition_line_id, quantity, unit_type FROM requisition_line WHERE request_id = ?'
    );
    $rlStmt->execute([$requestId]);
    while ($rl = $rlStmt->fetch(PDO::FETCH_ASSOC)) {
        $lid = (int) ($rl['requisition_line_id'] ?? 0);
        if ($lid <= 0) {
            continue;
        }
        $lineQty[$lid] = max(1, (int) ($rl['quantity'] ?? 1));
        $lineUnit[$lid] = trim((string) ($rl['unit_type'] ?? 'unit')) ?: 'unit';
    }

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

    $prefPrices = [];
    $prefStmt = $db->prepare(
        'SELECT supplier_id, quoted_prices FROM requisition_preferred_suppliers WHERE request_id = ?'
    );
    $prefStmt->execute([$requestId]);
    while ($pref = $prefStmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int) ($pref['supplier_id'] ?? 0);
        if ($sid <= 0) {
            continue;
        }
        $decoded = json_decode((string) ($pref['quoted_prices'] ?? ''), true);
        $prefPrices[$sid] = is_array($decoded) ? $decoded : [];
    }

    $lines = [];
    $selectedCount = 0;
    $grandTotal = 0.0;

    foreach ($itemRows as $idx => $row) {
        $detailId = (int) ($row['canvass_detail_id'] ?? 0);
        $lineId = isset($row['requisition_line_id']) ? (int) $row['requisition_line_id'] : 0;
        $sortOrder = (int) ($row['sort_order'] ?? $idx);
        $qty = $lineId > 0 && isset($lineQty[$lineId]) ? $lineQty[$lineId] : 1;
        $unit = $lineId > 0 && isset($lineUnit[$lineId]) ? $lineUnit[$lineId] : 'unit';

        $sel = $selectionByDetail[$detailId] ?? null;
        $supplierId = $sel ? (int) ($sel['supplier_id'] ?? 0) : 0;
        $source = $sel ? (string) ($sel['selection_source'] ?? '') : '';
        if ($source !== 'preferred' && $source !== 'canvassed') {
            $source = null;
        }

        $unitPrice = null;
        $lineTotal = null;
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
            'unit_type' => $unit,
            'supplier_id' => $supplierId,
            'supplier_name' => $supplierName !== '' ? $supplierName : null,
            'selection_source' => $source,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ];
    }

    return [
        'lines' => $lines,
        'item_count' => count($lines),
        'selected_count' => $selectedCount,
        'grand_total' => round($grandTotal, 2),
        'currency' => $currency,
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
