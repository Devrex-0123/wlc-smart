<?php

declare(strict_types=1);

/**
 * Build GSD suggested-supplier pricing overview (per requisition line + grand total).
 *
 * All reads now use the canonical tables:
 *   - requisition_line       → line items with quantity/unit
 *   - requisition_line_quotes → canvassed/preferred unit prices per supplier
 *   - requisition_line_awards → GSD-selected supplier per line
 *
 * Old tables (requisition_canvass_detail, requisition_canvass_detail_supplier,
 * request_approval_suggested_supplier_item) are no longer read here.
 * They remain in the database and are preserved for Phase 4 cleanup.
 *
 * @return array{
 *     lines: list<array{
 *         item_index: int,
 *         requisition_line_id: int,
 *         canvass_detail_id: int,
 *         item_name: string,
 *         group_label: string,
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
 *     currency: string,
 *     show_discount_column: bool
 * }
 */
function cwirmsCanvassPricingOverviewForRequest(PDO $db, int $requestId): array
{
    require_once __DIR__ . '/../api/approval_tables.php';
    ensureRequisitionLineAwardsTable($db);

    $currency = 'PHP';
    $empty = [
        'lines'              => [],
        'item_count'         => 0,
        'selected_count'     => 0,
        'grand_total'        => 0.0,
        'currency'           => $currency,
        'show_discount_column' => false,
    ];
    if ($requestId <= 0) {
        return $empty;
    }

    // Fetch all lines that have at least one supplier quote.
    $lineStmt = $db->prepare(
        "SELECT rl.requisition_line_id, rl.item_name, rl.quantity, rl.unit_type,
                rl.sort_order, rl.group_label
         FROM requisition_line rl
         WHERE rl.request_id = ?
           AND (rl.deleted_at IS NULL OR rl.deleted_at = '')
           AND EXISTS (
               SELECT 1
               FROM requisition_line_quotes rlq
               WHERE rlq.requisition_line_id = rl.requisition_line_id
           )
         ORDER BY rl.sort_order ASC, rl.requisition_line_id ASC"
    );
    $lineStmt->execute([$requestId]);
    $lineRows = $lineStmt->fetchAll(PDO::FETCH_ASSOC);
    if ($lineRows === []) {
        return $empty;
    }

    // Fetch all GSD awards for this request, joining to the matching canvassed quote price.
    $awardStmt = $db->prepare(
        "SELECT rla.requisition_line_id,
                rla.supplier_id,
                rla.selection_source,
                rla.awarded_qty,
                s.supplier_name,
                rlq.quoted_unit_price,
                rlq.discount_percent
         FROM requisition_line_awards rla
         INNER JOIN requisition_line rl ON rl.requisition_line_id = rla.requisition_line_id
         LEFT  JOIN suppliers s ON s.supplier_id = rla.supplier_id
         LEFT  JOIN requisition_line_quotes rlq
               ON  rlq.requisition_line_id = rla.requisition_line_id
               AND rlq.supplier_id         = rla.supplier_id
               AND rlq.quote_type          = COALESCE(NULLIF(rla.selection_source, ''), 'canvassed')
         WHERE rl.request_id = ?"
    );
    $awardStmt->execute([$requestId]);
    $awardByLine = [];
    while ($award = $awardStmt->fetch(PDO::FETCH_ASSOC)) {
        $lid = (int) ($award['requisition_line_id'] ?? 0);
        if ($lid > 0) {
            $awardByLine[$lid] = $award;
        }
    }

    $lines         = [];
    $selectedCount = 0;
    $grandTotal    = 0.0;
    $hasDiscount   = false;

    foreach ($lineRows as $idx => $row) {
        $lineId = (int) ($row['requisition_line_id'] ?? 0);
        $qty    = max(1, (int) ($row['quantity'] ?? 1));
        $unit   = trim((string) ($row['unit_type'] ?? 'unit')) ?: 'unit';

        $award      = $awardByLine[$lineId] ?? null;
        $supplierId = $award ? (int) ($award['supplier_id'] ?? 0) : 0;
        $source     = $award ? strtolower(trim((string) ($award['selection_source'] ?? ''))) : null;
        if ($source !== 'preferred' && $source !== 'canvassed') {
            $source = $supplierId > 0 ? 'canvassed' : null;
        }

        $unitPrice       = null;
        $lineTotal       = null;
        $discountPercent = null;
        $discountLabel   = null;
        $supplierName    = $award ? (string) ($award['supplier_name'] ?? '') : null;

        if ($supplierId > 0) {
            $selectedCount++;
            $rawPrice = $award['quoted_unit_price'] ?? null;
            if ($rawPrice !== null && is_numeric($rawPrice) && (float) $rawPrice >= 0) {
                $unitPrice = round((float) $rawPrice, 2);
                $discountPercent = cwirmsNormalizeCanvassSupplierDiscountPercent($award['discount_percent'] ?? null);
                if ($discountPercent !== null) {
                    $hasDiscount = true;
                    $discountLabel = rtrim(rtrim(number_format($discountPercent, 2, '.', ''), '0'), '.') . '%';
                }
                $discountFactor = $discountPercent !== null ? (1 - $discountPercent / 100) : 1.0;
                $lineTotal = round($unitPrice * $qty * $discountFactor, 2);
                $grandTotal += $lineTotal;
            }
        } else {
            $supplierId = null;
        }

        $lines[] = [
            'item_index'          => $idx,
            // canvass_detail_id is aliased to requisition_line_id for downstream backward-compat
            // (comptroller overview, GSD validation, etc. still key on canvass_detail_id).
            'canvass_detail_id'   => $lineId,
            'requisition_line_id' => $lineId,
            'item_name'           => (string) ($row['item_name'] ?? ''),
            'group_label'         => (string) ($row['group_label'] ?? ''),
            'quantity'            => $qty,
            'qty_per_set'         => 1,
            'requisition_qty'     => $qty,
            'unit_type'           => $unit,
            'supplier_id'         => $supplierId,
            'supplier_name'       => ($supplierName !== '' ? $supplierName : null),
            'selection_source'    => $source,
            'unit_price'          => $unitPrice,
            'line_total'          => $lineTotal,
            'discount_percent'    => $discountPercent,
            'discount_label'      => $discountLabel,
        ];
    }

    return [
        'lines'              => $lines,
        'item_count'         => count($lines),
        'selected_count'     => $selectedCount,
        'grand_total'        => round($grandTotal, 2),
        'currency'           => $currency,
        'show_discount_column' => $hasDiscount,
    ];
}

/**
 * Look up the canvassed unit price for a specific requisition line + supplier.
 * Reads from the canonical requisition_line_quotes table.
 *
 * @param int $lineId     requisition_line_id
 * @param int $supplierId supplier_id
 */
function cwirmsPricingOverviewCanvassedUnitPrice(PDO $db, int $lineId, int $supplierId): ?float
{
    $stmt = $db->prepare(
        "SELECT quoted_unit_price
         FROM requisition_line_quotes
         WHERE requisition_line_id = ? AND supplier_id = ? AND quote_type = 'canvassed'
         LIMIT 1"
    );
    $stmt->execute([$lineId, $supplierId]);
    $raw = $stmt->fetchColumn();
    if ($raw === false || $raw === null || !is_numeric($raw)) {
        return null;
    }
    $price = round((float) $raw, 2);

    return $price >= 0 ? $price : null;
}

// ---------------------------------------------------------------------------
// Discount helpers
// These functions still read from canvass_supplier_discounts via the legacy
// requisition_canvass_detail_supplier join. Since no new data flows to that
// table in Phase 2+, they return empty maps (show_discount_column stays false).
// Full discount migration is tracked for Phase 3.
// ---------------------------------------------------------------------------

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
 *
 * NOTE: Returns an empty map until the discount system is migrated to Phase 3.
 *       The legacy join through requisition_canvass_detail_supplier receives no
 *       new data, so this is intentionally a no-op in the new schema.
 */
function cwirmsCanvassSupplierDiscountPercentsMapForRequest(PDO $db, int $requestId): array
{
    return [];
}

/**
 * @return array<int, list<array{id: int, label: ?string, discount_percent: float}>>
 *
 * NOTE: Stub — see cwirmsCanvassSupplierDiscountPercentsMapForRequest().
 */
function cwirmsCanvassSupplierDiscountsBySupplierForRequest(PDO $db, int $requestId): array
{
    return [];
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
            'label'            => $label !== '' ? substr($label, 0, 100) : null,
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
 * NOTE: Stub — no new data flows to canvass_supplier_discounts in Phase 2+.
 * Full discount migration is deferred to Phase 3.
 *
 * @param list<array<string, mixed>> $suppliers
 */
function cwirmsPersistCanvassSupplierDiscountsForRequest(PDO $db, int $requestId, array $suppliers): void
{
    // Phase 3: migrate discount persistence to a new table keyed on (request_id, supplier_id).
}
