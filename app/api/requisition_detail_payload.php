<?php

declare(strict_types=1);

/**
 * Bind nullable ints for SQL NULL-safe comparisons (avoid (int)null → 0 breaking WHERE col = ?).
 *
 * @param mixed $v
 */
function requisitionNullableInt($v): ?int
{
    if ($v === null || $v === '' || $v === false) {
        return null;
    }

    return (int) $v;
}

/**
 * SELECT fragment: aggregate item names, supplier names, and min quoted price for list UIs.
 * Expects main table alias `r` for `requisition_item`.
 */
function requisitionSqlSelectListAggregates(): string
{
    // suppliers_concat: awarded suppliers from requisition_line_awards (canonical award table).
    // list_min_price:   cheapest canvassed quote from requisition_line_quotes (canonical quote table).
    // items_concat:     top-level lines only (group_label IS NULL = department-submitted lines).
    return '(SELECT GROUP_CONCAT(rl.item_name ORDER BY rl.sort_order ASC, rl.requisition_line_id ASC SEPARATOR \'||\')
        FROM requisition_line rl
        WHERE rl.request_id = r.request_id
          AND (rl.deleted_at IS NULL OR rl.deleted_at = \'\')) AS items_concat,
        (SELECT GROUP_CONCAT(
            COALESCE(NULLIF(TRIM(s.supplier_name), \'\'), \'—\')
            ORDER BY rla.award_id ASC
            SEPARATOR \'||\')
        FROM requisition_line_awards rla
        INNER JOIN requisition_line rl2 ON rl2.requisition_line_id = rla.requisition_line_id
        LEFT JOIN suppliers s ON s.supplier_id = rla.supplier_id
        WHERE rl2.request_id = r.request_id) AS suppliers_concat,
        (SELECT MIN(rlq.quoted_unit_price)
        FROM requisition_line_quotes rlq
        INNER JOIN requisition_line rl3 ON rl3.requisition_line_id = rlq.requisition_line_id
        WHERE rl3.request_id = r.request_id AND rlq.quoted_unit_price IS NOT NULL) AS list_min_price';
}

/**
 * SELECT fragment for history rows: human-readable item list. Alias `r` = requisition_item.
 */
function requisitionSqlHistoryItemsLabel(): string
{
    return 'COALESCE((SELECT GROUP_CONCAT(rl.item_name ORDER BY rl.sort_order ASC, rl.requisition_line_id ASC SEPARATOR \', \')
        FROM requisition_line rl WHERE rl.request_id = r.request_id), \'—\') AS item_name';
}

/**
 * @return list<string>
 */
function requisitionExplodePipeOrDefault(?string $concat, string $ifEmpty): array
{
    $parts = array_values(array_filter(explode('||', (string) $concat), static function ($x) {
        return $x !== '';
    }));

    return $parts !== [] ? $parts : [$ifEmpty];
}

/**
 * True after canvasser or G.S.D. / comptroller / president records accept or reject (requester must not edit requisition body; canvass saves also blocked).
 *
 * @param array<string, mixed>|null $approval request_approval-shaped row or payload['approval']
 */
function requisitionVerifierChainLocked(?array $approval): bool
{
    if (!$approval) {
        return false;
    }
    foreach (['canvas_status', 'gsd_status', 'comp_status', 'pres_status'] as $key) {
        $v = strtolower(trim((string) ($approval[$key] ?? '')));
        if ($v === 'accept' || $v === 'reject') {
            return true;
        }
    }

    return false;
}

/**
 * True when the canvass sheet has been verified by G.S.D., Comptroller, and President.
 * Purchase requisition is available only after the full canvass verification chain completes.
 */
function requisitionCanvassFormAcceptedForRequest(PDO $db, int $requestId): bool
{
    if ($requestId <= 0) {
        return false;
    }
    try {
        require_once __DIR__ . '/approval_tables.php';
        if (!cwirmsApprovalTableExists($db, 'canvass_verification_approval')) {
            return false;
        }
        $stmt = $db->prepare(
            'SELECT LOWER(TRIM(COALESCE(gsd_status, \'\'))),
                    LOWER(TRIM(COALESCE(comp_status, \'\'))),
                    LOWER(TRIM(COALESCE(pres_status, \'\')))
             FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if (!$row) {
            return false;
        }
        $gsd = strtolower(trim((string) ($row[0] ?? '')));
        $comp = strtolower(trim((string) ($row[1] ?? '')));
        $pres = strtolower(trim((string) ($row[2] ?? '')));
    } catch (Throwable $e) {
        return false;
    }

    return $gsd === 'accept' && $comp === 'accept' && $pres === 'accept';
}

/**
 * Load first request_approval row and check verifier chain lock.
 */
function requisitionVerifierChainLockedForRequest(PDO $db, int $requestId): bool
{
    if ($requestId <= 0) {
        return false;
    }
    try {
        require_once __DIR__ . '/approval_tables.php';
        if (!cwirmsApprovalTableExists($db, 'canvass_verification_approval')) {
            return false;
        }
        $stmt = $db->prepare(
            'SELECT canvas_status, gsd_status, comp_status, pres_status
             FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }

    return $row ? requisitionVerifierChainLocked($row) : false;
}

/**
 * Distinct supplier IDs already present on the canvass matrix for a request.
 *
 * @return list<int>
 */
function cwirmsDistinctSupplierIdsForRequest(PDO $db, int $requestId): array
{
    $stmt = $db->prepare(
        'SELECT DISTINCT rlq.supplier_id
         FROM requisition_line_quotes rlq
         INNER JOIN requisition_line rl ON rl.requisition_line_id = rlq.requisition_line_id
         WHERE rl.request_id = ?'
    );
    $stmt->execute([$requestId]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));

    return array_values(
        array_filter($ids, static function ($n) {
            return (int) $n > 0;
        })
    );
}

function requisitionInsertHeader(PDO $db, int $userId, int $officeId, int $facilityId, string $createdAtSql, ?string $message): int
{
    $stmt = $db->prepare("INSERT INTO requisition_item (user_id, office_id, facility_id, status, created_at, message) VALUES (?, ?, ?, 'Pending', ?, ?)");
    $stmt->execute([$userId, $officeId, $facilityId, $createdAtSql, $message]);

    return (int) $db->lastInsertId();
}

/**
 * @param array<int, mixed> $items
 * @param array<int, mixed> $suppliers
 */
function requisitionInsertLinesForRequest(PDO $db, int $requestId, array $items, array $suppliers): void
{
    // group_label is NULL for department-submitted lines (top-level); canvasser-created
    // component lines set group_label to the parent line's item_name.
    $lineStmt = $db->prepare(
        'INSERT INTO requisition_line
             (request_id, sort_order, item_id, item_name, item_brand, model, specification, item_category, photo_url, quantity, unit_type, group_label)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'\', ?, ?, ?)'
    );
    $sortOrder = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemName      = trim((string) ($item['name'] ?? ''));
        if ($itemName === '') {
            continue;
        }
        $itemBrand     = trim((string) ($item['brand'] ?? ''));
        $model         = trim((string) ($item['model'] ?? ''));
        $specification = trim((string) ($item['specification'] ?? ''));
        $itemCategory  = trim((string) ($item['category'] ?? ''));
        $groupLabel    = trim((string) ($item['group_label'] ?? ''));
        $itemId        = isset($item['item_id']) ? (int) $item['item_id'] : null;
        $quantity      = max(1, (int) ($item['quantity'] ?? 1));
        $unitTypeRaw   = strtolower(trim((string) ($item['unit_type'] ?? 'piece')));
        $unitType      = $unitTypeRaw !== '' ? $unitTypeRaw : 'piece';

        $lineStmt->execute([
            $requestId,
            $sortOrder,
            ($itemId > 0) ? $itemId : null,
            $itemName,
            $itemBrand  !== '' ? $itemBrand  : null,
            $model      !== '' ? $model      : null,
            $specification !== '' ? $specification : null,
            $itemCategory  !== '' ? $itemCategory  : null,
            $quantity,
            $unitType,
            $groupLabel !== '' ? $groupLabel : null,
        ]);
        ++$sortOrder;
    }
}

/**
 * One row per (line × supplier row); supplier columns null when line has no quotes yet.
 *
 * @return array<int, array<string, mixed>>
 */
function requisitionFetchDetailMatrixRows(PDO $db, int $requestId): array
{
    // Joins directly to requisition_line_quotes (canonical quote table).
    // Includes model, specification, and group_label so callers can render
    // grouped / component lines without extra queries.
    $sql = 'SELECT
                rl.requisition_line_id,
                rl.item_name,
                rl.item_brand,
                rl.model,
                rl.specification,
                rl.item_category,
                rl.group_label,
                rl.quantity,
                rl.unit_type,
                rl.item_id,
                rlq.supplier_id,
                rlq.quoted_unit_price AS price,
                rlq.quote_type,
                rlq.benefits,
                s.supplier_name,
                s.supplier_image
            FROM requisition_line rl
            LEFT JOIN requisition_line_quotes rlq ON rlq.requisition_line_id = rl.requisition_line_id
            LEFT JOIN suppliers s ON s.supplier_id = rlq.supplier_id
            WHERE rl.request_id = ?
              AND rl.deleted_at IS NULL
            ORDER BY rl.sort_order ASC, rl.requisition_line_id ASC, rlq.supplier_id ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute([$requestId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Build requisition detail payload from sibling rows (shared shape for edit / read-only view).
 *
 * @param array<string, mixed> $anchor
 * @param array<int, array<string, mixed>> $rows
 * @return array<string, mixed>|null
 */
function buildRequisitionDetailPayload(array $anchor, array $rows, int $requestIdForEdit): ?array
{
    if (count($rows) === 0) {
        return null;
    }

    $priceKey = null;
    if (count($rows) > 0 && array_key_exists('price', $rows[0])) {
        $priceKey = 'price';
    } else {
        foreach ($rows as $r0) {
            foreach (['quoted_price', 'unit_price', 'item_price'] as $pk) {
                if (array_key_exists($pk, $r0)) {
                    $priceKey = $pk;
                    break 2;
                }
            }
        }
    }

    $itemKeyToIndex = [];
    $itemsOut = [];
    foreach ($rows as $row) {
        // Use requisition_line_id as the unique key so items with identical names
        // but different groups/models/specs are never merged into one entry.
        $ikey = (string) ($row['requisition_line_id'] ?? '');
        if ($ikey === '') {
            $ikey = ($row['item_name'] ?? '') . "\0" . ($row['item_brand'] ?? '') . "\0" . ($row['item_category'] ?? '') . "\0" . (string) max(1, (int) ($row['quantity'] ?? 1)) . "\0" . (string) ($row['unit_type'] ?? 'unit');
        }
        if (!isset($itemKeyToIndex[$ikey])) {
            $itemKeyToIndex[$ikey] = count($itemsOut);
            $itemsOut[] = [
                'item_id'       => !empty($row['item_id']) ? (int) $row['item_id'] : null,
                'name'          => (string) ($row['item_name'] ?? ''),
                'brand'         => (string) ($row['item_brand'] ?? ''),
                'model'         => (string) ($row['model'] ?? ''),
                'specification' => (string) ($row['specification'] ?? ''),
                'category'      => (string) ($row['item_category'] ?? ''),
                'group_label'   => (string) ($row['group_label'] ?? ''),
                'quantity'      => max(1, (int) ($row['quantity'] ?? 1)),
                'unit_type'     => (string) ($row['unit_type'] ?? 'unit'),
            ];
        }
    }

    $supplierIdToIndex = [];
    $suppliersOut = [];
    foreach ($rows as $row) {
        $sid = $row['supplier_id'] ?? null;
        if ($sid === null || $sid === '') {
            continue;
        }
        $sid = (int) $sid;
        if ($sid <= 0) {
            continue;
        }
        if (!isset($supplierIdToIndex[$sid])) {
            $supplierIdToIndex[$sid] = count($suppliersOut);
            $suppliersOut[] = [
                'supplier_id' => $sid,
                'supplier_name' => (string) ($row['supplier_name'] ?? ''),
                'supplier_image' => (string) ($row['supplier_image'] ?? ''),
                'prices' => [],
            ];
        }
    }

    foreach ($rows as $row) {
        $ikey = (string) ($row['requisition_line_id'] ?? '');
        if ($ikey === '') {
            $ikey = ($row['item_name'] ?? '') . "\0" . ($row['item_brand'] ?? '') . "\0" . ($row['item_category'] ?? '') . "\0" . (string) max(1, (int) ($row['quantity'] ?? 1)) . "\0" . (string) ($row['unit_type'] ?? 'unit');
        }
        if (!isset($itemKeyToIndex[$ikey])) {
            continue;
        }
        $itemIdx = $itemKeyToIndex[$ikey];
        $sid = isset($row['supplier_id']) ? (int) $row['supplier_id'] : 0;
        if ($sid <= 0 || !isset($supplierIdToIndex[$sid])) {
            continue;
        }
        $supIdx = $supplierIdToIndex[$sid];
        $pv = '';
        if ($priceKey !== null && array_key_exists($priceKey, $row) && $row[$priceKey] !== null && $row[$priceKey] !== '') {
            $pv = is_numeric($row[$priceKey]) ? (string) (float) $row[$priceKey] : (string) $row[$priceKey];
        }
        $suppliersOut[$supIdx]['prices'][$itemIdx] = $pv;
    }

    $dateStr = $anchor['created_at'] ?? '';
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', (string) $dateStr, $m)) {
        $requestDate = $m[1];
    } else {
        $requestDate = date('Y-m-d', strtotime((string) $dateStr));
    }

    return [
        'success' => true,
        'edit_request_id' => $requestIdForEdit,
        'office_id' => (int) $anchor['office_id'],
        'facility_id' => (int) $anchor['facility_id'],
        'request_date' => $requestDate,
        'message' => (string) ($anchor['message'] ?? ''),
        'purpose' => (string) ($anchor['purpose'] ?? ''),
        'urgent_note' => (string) ($anchor['urgent_note'] ?? ''),
        'items' => $itemsOut,
        'suppliers' => $suppliersOut,
        'status' => (string) ($anchor['status'] ?? 'Pending'),
    ];
}

/**
 * Attach approval flags from requisition_form_approval + canvass_verification_approval.
 *
 * @param array<string, mixed> $payload
 */
function requisitionAttachApprovalToPayload(PDO $db, int $requestId, array &$payload): void
{
    $payload['approval'] = [
        'requisition_status' => null,
        'requisition_note' => null,
        'requisition_reviewed_by' => null,
        'requisition_reviewed_at' => null,
        'canvas_status' => null,
        'canvassed_by' => null,
        'suggested_supplier_id' => null,
        'suggested_supplier_name' => null,
        'gsd_status' => null,
        'comp_status' => null,
        'pres_status' => null,
        'canvas_submitted_at' => null,
    ];
    require_once __DIR__ . '/approval_tables.php';
    try {
        if (cwirmsApprovalTableExists($db, 'requisition_form_approval')) {
            $rfa = $db->prepare(
                'SELECT requisition_status, requisition_note, requisition_reviewed_by, requisition_reviewed_at
                 FROM requisition_form_approval WHERE request_id = ? LIMIT 1'
            );
            $rfa->execute([$requestId]);
            $r = $rfa->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $payload['approval']['requisition_status'] = $r['requisition_status'] ?? null;
                $payload['approval']['requisition_note'] = $r['requisition_note'] ?? null;
                $payload['approval']['requisition_reviewed_by'] = $r['requisition_reviewed_by'] ?? null;
                $payload['approval']['requisition_reviewed_at'] = $r['requisition_reviewed_at'] ?? null;
            }
        }
        if (cwirmsApprovalTableExists($db, 'canvass_verification_approval')) {
            $cva = $db->prepare(
                'SELECT canvas_status, canvassed_by, suggested_supplier_id, suggested_supplier_name, gsd_status, comp_status, pres_status, canvas_submitted_at
                 FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
            );
            $cva->execute([$requestId]);
            $c = $cva->fetch(PDO::FETCH_ASSOC);
            if ($c) {
                $payload['approval']['canvas_status'] = $c['canvas_status'] ?? null;
                $payload['approval']['canvassed_by'] = $c['canvassed_by'] ?? null;
                if (array_key_exists('suggested_supplier_id', $c)) {
                    $payload['approval']['suggested_supplier_id'] = $c['suggested_supplier_id'] !== null ? (int) $c['suggested_supplier_id'] : null;
                }
                $payload['approval']['suggested_supplier_name'] = $c['suggested_supplier_name'] ?? null;
                $payload['approval']['gsd_status'] = $c['gsd_status'] ?? null;
                $payload['approval']['comp_status'] = $c['comp_status'] ?? null;
                $payload['approval']['pres_status'] = $c['pres_status'] ?? null;
                $payload['approval']['canvas_submitted_at'] = $c['canvas_submitted_at'] ?? null;
            }
        }
    } catch (Throwable $e) {
        // leave defaults
    }
}
