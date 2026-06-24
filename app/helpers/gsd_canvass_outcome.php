<?php

declare(strict_types=1);

require_once __DIR__ . '/supplier.php';
require_once __DIR__ . '/../api/approval_tables.php';

/**
 * @return list<array<string, mixed>>
 */
function cwirmsFetchRequisitionLinesForRequest(PDO $db, int $requestId): array
{
    if ($requestId <= 0) {
        return [];
    }

    $sqlWithDeleted = "SELECT requisition_line_id, item_name, item_brand, model, specification,
                quantity, unit_type, group_label, line_status, estimated_unit_cost
         FROM requisition_line
         WHERE request_id = ? AND (deleted_at IS NULL OR deleted_at = '')
         ORDER BY sort_order ASC, requisition_line_id ASC";
    $sqlPlain = "SELECT requisition_line_id, item_name, item_brand, model, specification,
                quantity, unit_type, group_label, line_status, estimated_unit_cost
         FROM requisition_line
         WHERE request_id = ?
         ORDER BY sort_order ASC, requisition_line_id ASC";

    try {
        $stmt = $db->prepare($sqlWithDeleted);
        $stmt->execute([$requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $stmt = $db->prepare($sqlPlain);
        $stmt->execute([$requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * True when at least one GSD canvassed quote with a unit price exists on this request.
 */
function cwirmsRequestHasGsdCanvassedQuoteData(PDO $db, int $requestId): bool
{
    if ($requestId <= 0) {
        return false;
    }

    $sqlWithDeleted = "SELECT 1
         FROM requisition_line_quotes rlq
         INNER JOIN requisition_line rl ON rl.requisition_line_id = rlq.requisition_line_id
         WHERE rl.request_id = ?
           AND (rl.deleted_at IS NULL OR rl.deleted_at = '')
           AND rlq.quote_type = 'canvassed'
           AND rlq.quoted_unit_price IS NOT NULL
           AND rlq.quoted_unit_price >= 0
         LIMIT 1";
    $sqlPlain = "SELECT 1
         FROM requisition_line_quotes rlq
         INNER JOIN requisition_line rl ON rl.requisition_line_id = rlq.requisition_line_id
         WHERE rl.request_id = ?
           AND rlq.quote_type = 'canvassed'
           AND rlq.quoted_unit_price IS NOT NULL
           AND rlq.quoted_unit_price >= 0
         LIMIT 1";

    try {
        $stmt = $db->prepare($sqlWithDeleted);
        $stmt->execute([$requestId]);
    } catch (Throwable $e) {
        $stmt = $db->prepare($sqlPlain);
        $stmt->execute([$requestId]);
    }

    return (bool) $stmt->fetchColumn();
}

/**
 * Build the GSD canvass outcome payload (lines, suppliers, header, approval).
 *
 * @return array<string, mixed>
 */
function cwirmsBuildGsdCanvassOutcomeView(PDO $db, int $requestId): array
{
    ensureSupplierTinColumn($db);
    ensureRequisitionLineQuotesTable($db);
    ensureRequisitionLineQuotesGsdColumns($db);
    ensureRequisitionLineAwardsTable($db);

    $hStmt = $db->prepare(
        'SELECT ri.created_at, ri.purpose, d.office_name, f.building, f.room, f.laboratory
         FROM requisition_item ri
         LEFT JOIN offices d ON d.office_id = ri.office_id
         LEFT JOIN facilities f ON f.facility_id = ri.facility_id
         WHERE ri.request_id = ? LIMIT 1'
    );
    $hStmt->execute([$requestId]);
    $hRow = $hStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $facilityLabel = '—';
    if ($hRow) {
        $roomLab = trim((string) ($hRow['room'] ?? ''));
        if ($roomLab === '') {
            $roomLab = trim((string) ($hRow['laboratory'] ?? ''));
        }
        $building = trim((string) ($hRow['building'] ?? ''));
        if ($roomLab !== '' && $building !== '') {
            $facilityLabel = $roomLab . ' · ' . $building;
        } elseif ($roomLab !== '') {
            $facilityLabel = $roomLab;
        } elseif ($building !== '') {
            $facilityLabel = $building;
        }
    }

    $lineStmt = $db->prepare(
        "SELECT requisition_line_id, item_name, item_brand, model, specification,
                quantity, unit_type, group_label, line_status, estimated_unit_cost
         FROM requisition_line
         WHERE request_id = ? AND (deleted_at IS NULL OR deleted_at = '')
         ORDER BY sort_order ASC, requisition_line_id ASC"
    );
    try {
        $lineStmt->execute([$requestId]);
        $lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $lines = cwirmsFetchRequisitionLinesForRequest($db, $requestId);
    }
    $lineIds = array_map('intval', array_column($lines, 'requisition_line_id'));

    $quotesByLine = [];
    $prefQuotesByLine = [];
    if ($lineIds !== []) {
        $ph = implode(',', array_fill(0, count($lineIds), '?'));
        $qStmt = $db->prepare(
            "SELECT rlq.requisition_line_id, rlq.supplier_id, rlq.quoted_unit_price,
                    rlq.benefits, rlq.quote_type, rlq.canvasser_name, rlq.discount_percent,
                    s.supplier_name, s.supplier_image
             FROM requisition_line_quotes rlq
             INNER JOIN suppliers s ON s.supplier_id = rlq.supplier_id
             WHERE rlq.requisition_line_id IN ($ph)
               AND rlq.quote_type IN ('canvassed', 'preferred')
             ORDER BY rlq.quote_type ASC, s.supplier_name ASC"
        );
        $qStmt->execute($lineIds);
        while ($q = $qStmt->fetch(PDO::FETCH_ASSOC)) {
            $lid = (int) $q['requisition_line_id'];
            $entry = [
                'supplier_id'       => (int) $q['supplier_id'],
                'supplier_name'     => (string) $q['supplier_name'],
                'supplier_image'    => (string) ($q['supplier_image'] ?? ''),
                'quoted_unit_price' => $q['quoted_unit_price'],
                'benefits'          => $q['benefits'],
                'quote_type'        => (string) $q['quote_type'],
                'canvasser_name'    => $q['canvasser_name'],
                'discount_percent'  => $q['discount_percent'],
            ];
            if ($q['quote_type'] === 'canvassed') {
                $quotesByLine[$lid][] = $entry;
            } else {
                $prefQuotesByLine[$lid][] = $entry;
            }
        }
    }

    $awardByLine = [];
    if ($lineIds !== []) {
        $ph = implode(',', array_fill(0, count($lineIds), '?'));
        $aStmt = $db->prepare(
            "SELECT rla.requisition_line_id, rla.supplier_id, rla.selection_source,
                    s.supplier_name
             FROM requisition_line_awards rla
             INNER JOIN suppliers s ON s.supplier_id = rla.supplier_id
             WHERE rla.requisition_line_id IN ($ph)"
        );
        $aStmt->execute($lineIds);
        while ($a = $aStmt->fetch(PDO::FETCH_ASSOC)) {
            $lid = (int) ($a['requisition_line_id'] ?? 0);
            if ($lid > 0) {
                $awardByLine[$lid] = [
                    'supplier_id'      => (int) ($a['supplier_id'] ?? 0),
                    'supplier_name'    => (string) ($a['supplier_name'] ?? ''),
                    'selection_source' => strtolower(trim((string) ($a['selection_source'] ?? 'canvassed'))) ?: 'canvassed',
                ];
            }
        }
    }

    $result = [];
    foreach ($lines as $line) {
        $lid = (int) $line['requisition_line_id'];
        $result[] = [
            'requisition_line_id' => $lid,
            'item_name'           => (string) ($line['item_name'] ?? ''),
            'brand'               => (string) ($line['item_brand'] ?? ''),
            'model'               => (string) ($line['model'] ?? ''),
            'specification'       => (string) ($line['specification'] ?? ''),
            'quantity'            => (int) ($line['quantity'] ?? 1),
            'unit_type'           => (string) ($line['unit_type'] ?? 'unit'),
            'group_label'         => (string) ($line['group_label'] ?? ''),
            'line_status'         => (string) ($line['line_status'] ?? ''),
            'estimated_unit_cost' => $line['estimated_unit_cost'],
            'canvassed_quotes'    => $quotesByLine[$lid] ?? [],
            'preferred_quotes'    => $prefQuotesByLine[$lid] ?? [],
            'award'               => $awardByLine[$lid] ?? null,
        ];
    }

    $appStmt = $db->prepare(
        'SELECT canvas_status, canvassed_by, canvassed_at, canvas_assignee_user_id,
                gsd_status, comp_status, pres_status, suggested_supplier_id, suggested_supplier_name
         FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
    );
    $appStmt->execute([$requestId]);
    $appRow = $appStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $suppStmt = $db->query(
        'SELECT supplier_id, supplier_name, supplier_image, tin, address, contact_person, phone_number FROM suppliers ORDER BY supplier_name ASC'
    );
    $suppliers = $suppStmt ? $suppStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    return [
        'lines'     => $result,
        'suppliers' => $suppliers,
        'header'    => [
            'request_date'   => $hRow['created_at'] ?? '',
            'purpose'        => $hRow['purpose'] ?? '',
            'office_name'    => $hRow['office_name'] ?? '—',
            'facility_label' => $facilityLabel,
        ],
        'approval' => [
            'canvas_status'           => $appRow['canvas_status'] ?? null,
            'canvassed_by'            => $appRow['canvassed_by'] ?? null,
            'canvassed_at'            => $appRow['canvassed_at'] ?? null,
            'canvas_assignee_user_id' => isset($appRow['canvas_assignee_user_id']) ? (int) $appRow['canvas_assignee_user_id'] : null,
            'gsd_status'              => $appRow['gsd_status'] ?? null,
            'comp_status'             => $appRow['comp_status'] ?? null,
            'pres_status'             => $appRow['pres_status'] ?? null,
            'suggested_supplier_id'   => isset($appRow['suggested_supplier_id']) ? (int) $appRow['suggested_supplier_id'] : null,
            'suggested_supplier_name' => $appRow['suggested_supplier_name'] ?? null,
        ],
    ];
}
