<?php

declare(strict_types=1);

/**
 * Catalog item id for a canvass row: use requisition_line.item_id when set,
 * otherwise exact case-insensitive match on items.item_name vs. canvass label.
 */
function cwirmsResolveCatalogItemIdForCanvassRow(
    PDO $db,
    int $requestId,
    ?int $requisitionLineId,
    string $componentLabel
): ?int {
    if ($requisitionLineId !== null && $requisitionLineId > 0) {
        $st = $db->prepare(
            'SELECT item_id FROM requisition_line WHERE requisition_line_id = ? AND request_id = ? LIMIT 1'
        );
        $st->execute([$requisitionLineId, $requestId]);
        $cid = $st->fetchColumn();
        if ($cid !== false && $cid !== null && (int) $cid > 0) {
            return (int) $cid;
        }
    }

    $name = trim($componentLabel);
    if ($name === '') {
        return null;
    }

    $st = $db->prepare(
        'SELECT item_id FROM items WHERE LOWER(TRIM(item_name)) = LOWER(TRIM(?)) LIMIT 1'
    );
    $st->execute([$name]);
    $cid = $st->fetchColumn();
    if ($cid !== false && $cid !== null) {
        return (int) $cid;
    }

    return null;
}

/**
 * Ordered supplier ids for suggestions (junction + legacy items.supplier_id).
 *
 * @return list<int>
 */
function cwirmsSuggestedSupplierIdsForCatalogItem(PDO $db, ?int $catalogItemId): array
{
    if ($catalogItemId === null || $catalogItemId <= 0) {
        return [];
    }

    $ids = [];
    try {
        $st = $db->prepare(
            'SELECT supplier_id FROM item_supplier WHERE item_id = ? ORDER BY sort_order ASC, supplier_id ASC'
        );
        $st->execute([$catalogItemId]);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $ids[] = (int) $row['supplier_id'];
        }
    } catch (Throwable $e) {
        $ids = [];
    }

    try {
        $st = $db->prepare(
            'SELECT supplier_id FROM items WHERE item_id = ? AND supplier_id IS NOT NULL LIMIT 1'
        );
        $st->execute([$catalogItemId]);
        $pid = $st->fetchColumn();
        if ($pid !== false && $pid !== null) {
            $p = (int) $pid;
            if (!in_array($p, $ids, true)) {
                array_unshift($ids, $p);
            }
        }
    } catch (Throwable $e) {
    }

    return $ids;
}

/**
 * @return list<int>
 */
function cwirmsCanvassRowSuggestedSupplierIds(
    PDO $db,
    int $requestId,
    ?int $requisitionLineId,
    string $componentLabel
): array {
    $cid = cwirmsResolveCatalogItemIdForCanvassRow($db, $requestId, $requisitionLineId, $componentLabel);

    return cwirmsSuggestedSupplierIdsForCatalogItem($db, $cid);
}
