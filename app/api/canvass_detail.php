<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/requisition_detail_payload.php';
require_once __DIR__ . '/item_supplier_helpers.php';

function sendJson(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function assertLoggedIn(): void
{
    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Unauthorized']);
    }
}

/**
 * @return array<string, mixed>
 */
function loadOwnedRequest(PDO $db, int $requestId, int $userId): array
{
    $stmt = $db->prepare(
        'SELECT r.*, d.`office_name` AS office_name,
                f.building, f.room, f.laboratory
         FROM requisition_item r
         LEFT JOIN offices d ON d.office_id = r.office_id
         LEFT JOIN facilities f ON f.facility_id = r.facility_id
         WHERE r.request_id = ? AND r.user_id = ?'
    );
    $stmt->execute([$requestId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        sendJson(['success' => false, 'message' => 'Request not found.']);
    }

    return $row;
}

function userIsGsdOfficer(PDO $db, int $userId): bool
{
    $stmt = $db->prepare('SELECT LOWER(TRIM(COALESCE(role, \'\'))) FROM user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $r = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));

    return $r === 'gsd officer';
}

function userIsComptroller(PDO $db, int $userId): bool
{
    $stmt = $db->prepare('SELECT LOWER(TRIM(COALESCE(role, \'\'))) FROM user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $r = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));

    return $r === 'comptroller';
}

function userIsInventoryManager(PDO $db, int $userId): bool
{
    $stmt = $db->prepare('SELECT LOWER(TRIM(COALESCE(role, \'\'))) FROM user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $r = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));

    return $r === 'inventory manager' || $r === 'inventory_manager';
}

function userIsPresidentVerifier(PDO $db, int $userId): bool
{
    $stmt = $db->prepare('SELECT LOWER(TRIM(COALESCE(role, \'\'))) FROM user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $r = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));
    $allowed = ['president', 'president verifier', 'verifier president', 'president_verifier'];

    return in_array($r, $allowed, true);
}

function canvasserEmailLocalPartForCanvassApi(PDO $db, int $userId): string
{
    $stmt = $db->prepare('SELECT Email FROM user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $email = (string) ($row['Email'] ?? '');

    return $email !== '' ? strtolower((string) (explode('@', $email)[0] ?? $email)) : '';
}

/**
 * GSD-assigned canvasser (matches canvasser_requests.php).
 *
 * @param array<string, mixed>|null $existing canvass_verification_approval row
 */
function userMayActAsCanvasAssigneeForCanvassApi(PDO $db, ?array $existing, int $sessionUid): bool
{
    if (!$existing) {
        return false;
    }
    $aid = (int) ($existing['canvas_assignee_user_id'] ?? 0);
    if ($aid > 0) {
        return $aid === $sessionUid;
    }
    $local = canvasserEmailLocalPartForCanvassApi($db, $sessionUid);
    $by = strtolower(trim((string) ($existing['canvassed_by'] ?? '')));

    return $local !== '' && $by === $local;
}

/**
 * @return array<string, mixed>|null
 */
function loadRequestApprovalCanvasRow(PDO $db, int $requestId): ?array
{
    $stmt = $db->prepare(
        'SELECT canvas_assignee_user_id, canvassed_by, canvas_status FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
    );
    $stmt->execute([$requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * Request owner (dean) or GSD officer — for read-only canvass payload (GET / suggest_suppliers).
 *
 * @return array<string, mixed>
 */
function loadCanvassGetRequest(PDO $db, int $requestId, int $userId): array
{
    $stmt = $db->prepare(
        'SELECT r.*, d.`office_name` AS office_name,
                f.building, f.room, f.laboratory
         FROM requisition_item r
         LEFT JOIN offices d ON d.office_id = r.office_id
         LEFT JOIN facilities f ON f.facility_id = r.facility_id
         WHERE r.request_id = ? AND r.user_id = ?'
    );
    $stmt->execute([$requestId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }
    if (
        userIsInventoryManager($db, $userId)
        || userIsGsdOfficer($db, $userId)
        || userIsComptroller($db, $userId)
        || userIsPresidentVerifier($db, $userId)
    ) {
        // For verifiers, check if canvas_submission_status is 'submitted'
        $statusCheckStmt = $db->prepare(
            'SELECT LOWER(TRIM(COALESCE(canvas_submission_status, \'draft\'))) as status
             FROM canvass_verification_approval
             WHERE request_id = ?'
        );
        $statusCheckStmt->execute([$requestId]);
        $statusRow = $statusCheckStmt->fetch(PDO::FETCH_ASSOC);
        $canvasStatus = $statusRow ? $statusRow['status'] : 'draft';
        
        // Only allow verifiers to see submitted canvass forms
        if ($canvasStatus !== 'submitted') {
            sendJson(['success' => false, 'message' => 'The canvass form is still in draft. Only the requester can view draft forms.']);
        }
        
        $stmt2 = $db->prepare(
            'SELECT r.*, d.`office_name` AS office_name,
                    f.building, f.room, f.laboratory
             FROM requisition_item r
             LEFT JOIN offices d ON d.office_id = r.office_id
             LEFT JOIN facilities f ON f.facility_id = r.facility_id
             WHERE r.request_id = ?'
        );
        $stmt2->execute([$requestId]);
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($row2) {
            return $row2;
        }
    }

    $stmt3 = $db->prepare(
        'SELECT r.*, d.`office_name` AS office_name,
                f.building, f.room, f.laboratory
         FROM requisition_item r
         LEFT JOIN offices d ON d.office_id = r.office_id
         LEFT JOIN facilities f ON f.facility_id = r.facility_id
         WHERE r.request_id = ?'
    );
    $stmt3->execute([$requestId]);
    $row3 = $stmt3->fetch(PDO::FETCH_ASSOC);
    if ($row3) {
        $ra = loadRequestApprovalCanvasRow($db, $requestId);
        if ($ra && userMayActAsCanvasAssigneeForCanvassApi($db, $ra, $userId)) {
            // Check if canvas is submitted before allowing assignee to see it
            $statusCheckStmt = $db->prepare(
                'SELECT LOWER(TRIM(COALESCE(canvas_submission_status, \'draft\'))) as status
                 FROM canvass_verification_approval
                 WHERE request_id = ?'
            );
            $statusCheckStmt->execute([$requestId]);
            $statusRow = $statusCheckStmt->fetch(PDO::FETCH_ASSOC);
            $canvasStatus = $statusRow ? $statusRow['status'] : 'draft';
            
            // Canvass assignees can only see submitted forms
            if ($canvasStatus !== 'submitted') {
                sendJson(['success' => false, 'message' => 'The canvass form is still in draft. Only the requester can view draft forms.']);
            }
            
            // Allow GET after canvass is finalized so the assignee can see the sheet, undo, and refresh.
            // Saves while finalized are rejected in the save handler (code canvas_finalized).
            return $row3;
        }
    }
    sendJson(['success' => false, 'message' => 'Request not found.']);
}

function requisitionInventoryAccepted(PDO $db, int $requestId): bool
{
    $stmt = $db->prepare(
        'SELECT LOWER(TRIM(COALESCE(requisition_status, \'\'))) FROM requisition_form_approval WHERE request_id = ? LIMIT 1'
    );
    $stmt->execute([$requestId]);
    $v = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));

    return $v === 'accept';
}

/**
 * Existing canvass lines from DB (assignee saves may only update supplier prices, not these rows).
 *
 * @param array<int, true> $allowedSet
 *
 * @return list<array{requisition_line_id: int|null, component_label: string, brand: ?string, model: ?string, specification: ?string, sort_order: int}>
 */
function cwirmsNormalizedCanvassItemsFromDb(PDO $db, int $requestId, array $allowedSet): array
{
    $detailRows = [];
    try {
        $detStmt = $db->prepare(
            'SELECT requisition_line_id, component_label, brand, model, specification, sort_order
             FROM requisition_canvass_detail WHERE request_id = ?
             ORDER BY sort_order ASC, canvass_detail_id ASC'
        );
        $detStmt->execute([$requestId]);
        $detailRows = $detStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $detStmt = $db->prepare(
            'SELECT requisition_line_id, component_label, specification, sort_order
             FROM requisition_canvass_detail WHERE request_id = ?
             ORDER BY sort_order ASC, canvass_detail_id ASC'
        );
        $detStmt->execute([$requestId]);
        foreach ($detStmt->fetchAll(PDO::FETCH_ASSOC) as $legacyRow) {
            $legacyRow['brand'] = null;
            $legacyRow['model'] = null;
            $detailRows[] = $legacyRow;
        }
    }

    $normalizedItems = [];
    $sort = 0;
    foreach ($detailRows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $name = trim((string) ($r['component_label'] ?? ''));
        if ($name === '') {
            continue;
        }
        $spec = trim((string) ($r['specification'] ?? ''));
        $brand = isset($r['brand']) ? trim((string) $r['brand']) : '';
        $model = isset($r['model']) ? trim((string) $r['model']) : '';
        $lineId = null;
        if (isset($r['requisition_line_id']) && $r['requisition_line_id'] !== '' && $r['requisition_line_id'] !== null) {
            $lineId = (int) $r['requisition_line_id'];
            if ($lineId > 0 && !isset($allowedSet[$lineId])) {
                $lineId = null;
            }
            if ($lineId <= 0) {
                $lineId = null;
            }
        }
        $normalizedItems[] = [
            'requisition_line_id' => $lineId,
            'component_label' => substr($name, 0, 150),
            'brand' => $brand !== '' ? substr($brand, 0, 100) : null,
            'model' => $model !== '' ? substr($model, 0, 100) : null,
            'specification' => $spec !== '' ? substr($spec, 0, 500) : null,
            'sort_order' => $sort,
        ];
        $sort++;
    }

    return $normalizedItems;
}

/**
 * @param array<string, mixed> $anchor
 */
function buildFacilityLabel(array $anchor): string
{
    $roomLab = trim((string) ($anchor['room'] ?? ''));
    if ($roomLab === '') {
        $roomLab = trim((string) ($anchor['laboratory'] ?? ''));
    }
    $building = trim((string) ($anchor['building'] ?? ''));
    if ($roomLab !== '' && $building !== '') {
        return $roomLab . ' · ' . $building;
    }
    if ($roomLab !== '') {
        return $roomLab;
    }
    if ($building !== '') {
        return $building;
    }

    return '—';
}

/**
 * @return list<array<string, mixed>>
 */
function fetchSupplierCatalog(PDO $db): array
{
    $stmt = $db->query(
        'SELECT supplier_id, supplier_name, supplier_image, contact_person, phone_number, email, address, city, country, postal_code
         FROM suppliers ORDER BY supplier_name ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Catalog rows for canvass item name suggestions (items table).
 *
 * @return list<array<string, mixed>>
 */
function fetchItemCatalogForCanvass(PDO $db): array
{
    try {
        $stmt = $db->query(
            'SELECT item_id, item_name, brand, model, category FROM items ORDER BY item_name ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        try {
            $stmt = $db->query(
                'SELECT item_id, item_name, brand, category FROM items ORDER BY item_name ASC'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['model'] = null;
            }
            unset($row);

            return $rows;
        } catch (Throwable $e2) {
            return [];
        }
    }
}

try {
    assertLoggedIn();
    $db = Database::connect();
    $uid = (int) $_SESSION['user_id'];
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'get') {
        $requestId = (int) ($_GET['request_id'] ?? 0);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }
        $anchor = loadCanvassGetRequest($db, $requestId, $uid);
        if (!requisitionInventoryAccepted($db, $requestId)) {
            sendJson(['success' => false, 'message' => 'Open this form after the inventory manager accepts your requisition.']);
        }

        $supplierCatalog = fetchSupplierCatalog($db);
        $catalogById = [];
        foreach ($supplierCatalog as $s) {
            $catalogById[(int) $s['supplier_id']] = $s;
        }

        $detailRows = [];
        try {
            $detStmt = $db->prepare(
                'SELECT canvass_detail_id, requisition_line_id, component_label, brand, model, specification, sort_order
                 FROM requisition_canvass_detail WHERE request_id = ?
                 ORDER BY sort_order ASC, canvass_detail_id ASC'
            );
            $detStmt->execute([$requestId]);
            $detailRows = $detStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $detStmt = $db->prepare(
                'SELECT canvass_detail_id, requisition_line_id, component_label, specification, sort_order
                 FROM requisition_canvass_detail WHERE request_id = ?
                 ORDER BY sort_order ASC, canvass_detail_id ASC'
            );
            $detStmt->execute([$requestId]);
            foreach ($detStmt->fetchAll(PDO::FETCH_ASSOC) as $legacyRow) {
                $legacyRow['brand'] = null;
                $legacyRow['model'] = null;
                $detailRows[] = $legacyRow;
            }
        }

        $items = [];
        $idxByDetailId = [];
        foreach ($detailRows as $i => $r) {
            $cid = (int) $r['canvass_detail_id'];
            $idxByDetailId[$cid] = $i;
            $lineId = $r['requisition_line_id'];
            $lineIdInt = $lineId !== null && $lineId !== '' ? (int) $lineId : null;
            $label = (string) $r['component_label'];
            $suggestedIds = cwirmsCanvassRowSuggestedSupplierIds($db, $requestId, $lineIdInt, $label);
            $items[] = [
                'canvass_detail_id' => $cid,
                'requisition_line_id' => $lineIdInt,
                'item_name' => $label,
                'brand' => isset($r['brand']) && $r['brand'] !== null ? (string) $r['brand'] : '',
                'model' => isset($r['model']) && $r['model'] !== null ? (string) $r['model'] : '',
                'specification' => $r['specification'] !== null ? (string) $r['specification'] : '',
                'suggested_supplier_ids' => $suggestedIds,
                'selected_supplier_id' => null,
            ];
        }

        if ($idxByDetailId !== []) {
            $selStmt = $db->prepare(
                'SELECT canvass_detail_id, supplier_id
                 FROM request_approval_suggested_supplier_item
                 WHERE request_id = ?'
            );
            $selStmt->execute([$requestId]);
            while ($sel = $selStmt->fetch(PDO::FETCH_ASSOC)) {
                $cidSel = (int) ($sel['canvass_detail_id'] ?? 0);
                $sidSel = (int) ($sel['supplier_id'] ?? 0);
                if ($cidSel <= 0 || $sidSel <= 0) {
                    continue;
                }
                $idx = $idxByDetailId[$cidSel] ?? null;
                if ($idx === null || !isset($items[$idx])) {
                    continue;
                }
                $items[$idx]['selected_supplier_id'] = $sidSel;
            }
        }

        $supplierPricesBySid = [];
        if ($idxByDetailId !== []) {
            $ids = array_keys($idxByDetailId);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $cj = $db->prepare(
                "SELECT canvass_detail_id, supplier_id, price FROM requisition_canvass_detail_supplier WHERE canvass_detail_id IN ($placeholders)"
            );
            $cj->execute($ids);
            while ($row = $cj->fetch(PDO::FETCH_ASSOC)) {
                $cid = (int) $row['canvass_detail_id'];
                $sid = (int) $row['supplier_id'];
                $idx = $idxByDetailId[$cid] ?? null;
                if ($idx === null) {
                    continue;
                }
                if (!isset($supplierPricesBySid[$sid])) {
                    $supplierPricesBySid[$sid] = [];
                }
                $supplierPricesBySid[$sid][$idx] = $row['price'] !== null ? (string) $row['price'] : '';
            }
        }

        $matrixSuppliers = [];
        foreach ($supplierPricesBySid as $sid => $prices) {
            $s = $catalogById[$sid] ?? null;
            if (!$s) {
                continue;
            }
            $matrixSuppliers[] = [
                'supplier_id' => $sid,
                'supplier_name' => (string) ($s['supplier_name'] ?? ''),
                'supplier_image' => (string) ($s['supplier_image'] ?? ''),
                'prices' => $prices,
            ];
        }
        usort(
            $matrixSuppliers,
            static fn ($a, $b) => strcasecmp((string) $a['supplier_name'], (string) $b['supplier_name'])
        );

        $dateStr = '';
        if (!empty($anchor['created_at'])) {
            $dateStr = date('Y-m-d', strtotime((string) $anchor['created_at']));
        }

        $approvalWrap = ['approval' => []];
        requisitionAttachApprovalToPayload($db, $requestId, $approvalWrap);

        $itemCatalog = fetchItemCatalogForCanvass($db);

        $requisitionRequestedItems = [];
        $rlRows = [];
        try {
            $rlStmt = $db->prepare(
                'SELECT requisition_line_id, item_name, item_brand, item_category, quantity, unit_type
                 FROM requisition_line WHERE request_id = ?
                 ORDER BY sort_order ASC, requisition_line_id ASC'
            );
            $rlStmt->execute([$requestId]);
            $rlRows = $rlStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            try {
                $rlStmt = $db->prepare(
                    'SELECT requisition_line_id, item_name, item_brand, item_category, quantity
                     FROM requisition_line WHERE request_id = ?
                     ORDER BY sort_order ASC, requisition_line_id ASC'
                );
                $rlStmt->execute([$requestId]);
                $rlRows = $rlStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e2) {
                $rlRows = [];
            }
        }
        foreach ($rlRows as $rlRow) {
            $requisitionRequestedItems[] = [
                'requisition_line_id' => (int) $rlRow['requisition_line_id'],
                'item_name' => (string) ($rlRow['item_name'] ?? ''),
                'item_brand' => $rlRow['item_brand'] !== null ? (string) $rlRow['item_brand'] : '',
                'item_category' => $rlRow['item_category'] !== null ? (string) $rlRow['item_category'] : '',
                'quantity' => isset($rlRow['quantity']) ? (int) $rlRow['quantity'] : 1,
                'unit_type' => isset($rlRow['unit_type']) ? (string) $rlRow['unit_type'] : 'unit',
            ];
        }

        sendJson([
            'success' => true,
            'header' => [
                'request_id' => $requestId,
                'request_label' => 'REQ-' . str_pad((string) $requestId, 6, '0', STR_PAD_LEFT),
                'office_name' => (string) ($anchor['office_name'] ?? '—'),
                'facility_label' => buildFacilityLabel($anchor),
                'request_date' => $dateStr,
                'purpose' => (string) ($anchor['purpose'] ?? ''),
            ],
            'supplier_catalog' => $supplierCatalog,
            'item_catalog' => $itemCatalog,
            'items' => $items,
            'suppliers' => $matrixSuppliers,
            'approval' => $approvalWrap['approval'],
            'requisition_requested_items' => $requisitionRequestedItems,
        ]);
    }

        // Preferred suppliers API (requester-owned)
        if ($action === 'get_preferred') {
            $requestId = (int) ($_GET['request_id'] ?? 0);
            if ($requestId <= 0) {
                sendJson(['success' => false, 'message' => 'Invalid request id.']);
            }
            loadCanvassGetRequest($db, $requestId, $uid);
            // Create junction table if missing (no shop_url stored here)
            $db->exec(
                'CREATE TABLE IF NOT EXISTS requisition_preferred_suppliers (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    request_id INT NOT NULL,
                    supplier_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );

            // Detect if suppliers table has a shop_url column; prefer storing shop_url on suppliers
            $hasShopUrl = false;
            try {
                $colChk = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'shop_url'");
                $colChk->execute();
                $hasShopUrl = ((int) $colChk->fetchColumn()) > 0;
            } catch (Throwable $e) {
                $hasShopUrl = false;
            }

            $selectCols = 'rps.supplier_id, ' . ($hasShopUrl ? 's.shop_url, ' : '') . 's.supplier_name, s.contact_person, s.phone_number, s.email, s.supplier_image';
            $orderClause = 'ORDER BY rps.request_id ASC, rps.supplier_id ASC';
            try {
                $colChkId = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requisition_preferred_suppliers' AND COLUMN_NAME = 'id'");
                $colChkId->execute();
                if (((int) $colChkId->fetchColumn()) > 0) {
                    $orderClause = 'ORDER BY rps.id ASC';
                }
            } catch (Throwable $e) {
                // ignore and use fallback order
            }
            $stmt = $db->prepare(
                "SELECT {$selectCols} FROM requisition_preferred_suppliers rps LEFT JOIN suppliers s ON s.supplier_id = rps.supplier_id WHERE rps.request_id = ? {$orderClause}"
            );
            $stmt->execute([$requestId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'supplier_id' => isset($r['supplier_id']) ? (int) $r['supplier_id'] : 0,
                    'supplier_name' => (string) ($r['supplier_name'] ?? ''),
                    'contact_person' => (string) ($r['contact_person'] ?? ''),
                    'phone_number' => (string) ($r['phone_number'] ?? ''),
                    'email' => (string) ($r['email'] ?? ''),
                    'shop_url' => (string) ($r['shop_url'] ?? ''),
                    'supplier_image' => (string) ($r['supplier_image'] ?? ''),
                ];
            }
            sendJson(['success' => true, 'preferred_suppliers' => $out]);
        }

        if ($action === 'add_preferred') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            if ($requestId <= 0) sendJson(['success' => false, 'message' => 'Invalid request id.']);
            // only requester may add preferred suppliers
            loadOwnedRequest($db, $requestId, $uid);
            if (requisitionVerifierChainLockedForRequest($db, $requestId)) {
                sendJson(['success' => false, 'message' => 'This request can no longer be edited.']);
            }
            $supplierName = trim((string) ($_POST['supplier_name'] ?? ''));
            if ($supplierName === '') sendJson(['success' => false, 'message' => 'Supplier name is required.']);
            $contactPerson = trim((string) ($_POST['contact_person'] ?? ''));
            $phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $shopUrl = trim((string) ($_POST['shop_url'] ?? ''));

            // avoid duplicate supplier names in suppliers table
            $stmtChk = $db->prepare('SELECT supplier_id FROM suppliers WHERE LOWER(supplier_name) = LOWER(?) LIMIT 1');
            $stmtChk->execute([$supplierName]);
            if ($stmtChk->fetch(PDO::FETCH_ASSOC)) {
                sendJson(['success' => false, 'message' => 'A supplier with this name already exists. Choose it from the list instead.']);
            }

            $ins = $db->prepare('INSERT INTO suppliers (supplier_name, contact_person, phone_number, email, address, city, country, postal_code, status, supplier_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([
                $supplierName,
                $contactPerson !== '' ? $contactPerson : null,
                $phoneNumber !== '' ? $phoneNumber : null,
                $email !== '' ? $email : null,
                null,
                null,
                null,
                null,
                'Active',
                null,
            ]);
            $newSid = (int) $db->lastInsertId();

            // Ensure junction exists (no shop_url stored on junction)
            $db->exec(
                'CREATE TABLE IF NOT EXISTS requisition_preferred_suppliers (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    request_id INT NOT NULL,
                    supplier_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );

            $ins2 = $db->prepare('INSERT INTO requisition_preferred_suppliers (request_id, supplier_id) VALUES (?, ?)');
            $ins2->execute([$requestId, $newSid]);

            // If suppliers table includes shop_url, persist it there
            try {
                if ($shopUrl !== '') {
                    $colChk2 = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'shop_url'");
                    $colChk2->execute();
                    $hasShop = ((int) $colChk2->fetchColumn()) > 0;
                    if ($hasShop) {
                        $updShop = $db->prepare('UPDATE suppliers SET shop_url = ? WHERE supplier_id = ?');
                        $updShop->execute([$shopUrl, $newSid]);
                    }
                }
            } catch (Throwable $e) {
                // ignore if column missing or update fails
            }

            $sel = $db->prepare('SELECT supplier_id, supplier_name, supplier_image, contact_person, phone_number, email FROM suppliers WHERE supplier_id = ?');
            $sel->execute([$newSid]);
            $row = $sel->fetch(PDO::FETCH_ASSOC);
            sendJson(['success' => true, 'message' => 'Preferred supplier added.', 'supplier' => $row]);
        }

        if ($action === 'update_preferred') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $supplierId = (int) ($_POST['supplier_id'] ?? 0);
            if ($requestId <= 0 || $supplierId <= 0) sendJson(['success' => false, 'message' => 'Invalid payload.']);
            loadOwnedRequest($db, $requestId, $uid);
            if (requisitionVerifierChainLockedForRequest($db, $requestId)) {
                sendJson(['success' => false, 'message' => 'This request can no longer be edited.']);
            }
            $supplierName = trim((string) ($_POST['supplier_name'] ?? ''));
            if ($supplierName === '') sendJson(['success' => false, 'message' => 'Supplier name is required.']);
            $contactPerson = trim((string) ($_POST['contact_person'] ?? ''));
            $phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $shopUrl = trim((string) ($_POST['shop_url'] ?? ''));

            $upd = $db->prepare('UPDATE suppliers SET supplier_name = ?, contact_person = ?, phone_number = ?, email = ? WHERE supplier_id = ?');
            $upd->execute([
                $supplierName,
                $contactPerson !== '' ? $contactPerson : null,
                $phoneNumber !== '' ? $phoneNumber : null,
                $email !== '' ? $email : null,
                $supplierId,
            ]);

            // If suppliers table has shop_url, update it there
            try {
                $colChk3 = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'shop_url'");
                $colChk3->execute();
                $hasShop2 = ((int) $colChk3->fetchColumn()) > 0;
                if ($hasShop2) {
                    $updShop2 = $db->prepare('UPDATE suppliers SET shop_url = ? WHERE supplier_id = ?');
                    $updShop2->execute([$shopUrl !== '' ? $shopUrl : null, $supplierId]);
                }
            } catch (Throwable $e) {
                // ignore
            }

            sendJson(['success' => true, 'message' => 'Preferred supplier updated.']);
        }

        if ($action === 'remove_preferred') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $supplierId = (int) ($_POST['supplier_id'] ?? 0);
            if ($requestId <= 0 || $supplierId <= 0) sendJson(['success' => false, 'message' => 'Invalid payload.']);
            loadOwnedRequest($db, $requestId, $uid);
            if (requisitionVerifierChainLockedForRequest($db, $requestId)) {
                sendJson(['success' => false, 'message' => 'This request can no longer be edited.']);
            }
            $del = $db->prepare('DELETE FROM requisition_preferred_suppliers WHERE request_id = ? AND supplier_id = ?');
            $del->execute([$requestId, $supplierId]);
            sendJson(['success' => true, 'message' => 'Preferred supplier removed.']);
        }

        if ($action === 'link_preferred') {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $supplierId = (int) ($_POST['supplier_id'] ?? 0);
            if ($requestId <= 0 || $supplierId <= 0) sendJson(['success' => false, 'message' => 'Invalid payload.']);
            // only requester may link preferred suppliers
            loadOwnedRequest($db, $requestId, $uid);
            if (requisitionVerifierChainLockedForRequest($db, $requestId)) {
                sendJson(['success' => false, 'message' => 'This request can no longer be edited.']);
            }
            // ensure supplier exists
            $sel = $db->prepare('SELECT supplier_id FROM suppliers WHERE supplier_id = ? LIMIT 1');
            $sel->execute([$supplierId]);
            if (!$sel->fetch(PDO::FETCH_ASSOC)) {
                sendJson(['success' => false, 'message' => 'Supplier not found.']);
            }
            // create junction table if missing
            $db->exec(
                'CREATE TABLE IF NOT EXISTS requisition_preferred_suppliers (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    request_id INT NOT NULL,
                    supplier_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $ins = $db->prepare('INSERT IGNORE INTO requisition_preferred_suppliers (request_id, supplier_id) VALUES (?, ?)');
            $ins->execute([$requestId, $supplierId]);
            sendJson(['success' => true, 'message' => 'Preferred supplier added.']);
        }

    if ($action === 'suggest_suppliers') {
        $requestId = (int) ($_GET['request_id'] ?? 0);
        $itemName = trim((string) ($_GET['item_name'] ?? ''));
        $lineId = (int) ($_GET['requisition_line_id'] ?? 0);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }
        loadCanvassGetRequest($db, $requestId, $uid);
        if (!requisitionInventoryAccepted($db, $requestId)) {
            sendJson(['success' => false, 'message' => 'Open this form after the inventory manager accepts your requisition.']);
        }
        $lineIdOpt = $lineId > 0 ? $lineId : null;
        $ids = cwirmsCanvassRowSuggestedSupplierIds($db, $requestId, $lineIdOpt, $itemName);
        sendJson(['success' => true, 'supplier_ids' => $ids]);
    }

    if ($action === 'save') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $itemsRaw = $_POST['items'] ?? '[]';
        $suppliersRaw = $_POST['suppliers'] ?? '[]';
        $submissionMode = strtolower(trim((string) ($_POST['submission_mode'] ?? 'draft')));
        if ($submissionMode !== 'draft' && $submissionMode !== 'submitted') {
            $submissionMode = 'draft';
        }
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }
        $ownStmt = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ? AND user_id = ?');
        $ownStmt->execute([$requestId, $uid]);
        $isOwner = (bool) $ownStmt->fetch(PDO::FETCH_ASSOC);
        if (!$isOwner) {
            $existing = loadRequestApprovalCanvasRow($db, $requestId);
            if (!$existing) {
                sendJson([
                    'success' => false,
                    'message' => 'No approval record for this request yet. Ask GSD or inventory to assign the canvass before you can save quotes.',
                ]);
            }
            if (!userMayActAsCanvasAssigneeForCanvassApi($db, $existing, $uid)) {
                sendJson([
                    'success' => false,
                    'message' => 'You are not assigned to canvass this request, or your assignment no longer matches.',
                ]);
            }
            $cRaw = strtolower(trim((string) ($existing['canvas_status'] ?? 'pending')));
            if ($cRaw === '') {
                $cRaw = 'pending';
            }
            if ($cRaw === 'accept' || $cRaw === 'reject') {
                sendJson([
                    'success' => false,
                    'code' => 'canvas_finalized',
                    'message' => 'Canvassing is finalized. Use Undo completion on this page or on the requisition view if you need to edit again.',
                ]);
            }
        }
        if (!requisitionInventoryAccepted($db, $requestId)) {
            sendJson(['success' => false, 'message' => 'You can save the canvass form only after the requisition is accepted.']);
        }
        if (requisitionVerifierChainLockedForRequest($db, $requestId)) {
            sendJson([
                'success' => false,
                'message' => 'This canvass can no longer be edited because a verifier (G.S.D. officer, comptroller, or president) has already recorded a decision.',
            ]);
        }

        $suppliers = json_decode((string) $suppliersRaw, true);
        if (!is_array($suppliers)) {
            sendJson(['success' => false, 'message' => 'Invalid payload.']);
        }

        $lineIdsStmt = $db->prepare('SELECT requisition_line_id FROM requisition_line WHERE request_id = ?');
        $lineIdsStmt->execute([$requestId]);
        $allowedLineIds = array_map('intval', $lineIdsStmt->fetchAll(PDO::FETCH_COLUMN, 0));
        $allowedSet = array_fill_keys($allowedLineIds, true);

        $detailUserId = $uid;
        if (!$isOwner) {
            $ouStmt = $db->prepare('SELECT user_id FROM requisition_item WHERE request_id = ?');
            $ouStmt->execute([$requestId]);
            $ownerUid = (int) $ouStmt->fetchColumn();
            if ($ownerUid > 0) {
                $detailUserId = $ownerUid;
            }
        }

        $normalizedItems = [];
        if (!$isOwner) {
            $normalizedItems = cwirmsNormalizedCanvassItemsFromDb($db, $requestId, $allowedSet);
            if ($normalizedItems === []) {
                sendJson([
                    'success' => false,
                    'message' => 'The requester has not added canvass lines yet. You can only enter prices after those lines exist.',
                ]);
            }
        } else {
            $items = json_decode((string) $itemsRaw, true);
            if (!is_array($items)) {
                sendJson(['success' => false, 'message' => 'Invalid payload.']);
            }
            foreach ($items as $i => $r) {
                if (!is_array($r)) {
                    continue;
                }
                $name = trim((string) ($r['item_name'] ?? $r['component_label'] ?? ''));
                if ($name === '') {
                    sendJson(['success' => false, 'message' => 'Each canvass item needs an item name.']);
                }
                $spec = trim((string) ($r['specification'] ?? ''));
                $brand = trim((string) ($r['brand'] ?? ''));
                $model = trim((string) ($r['model'] ?? ''));
                $lineId = null;
                if (isset($r['requisition_line_id']) && $r['requisition_line_id'] !== '' && $r['requisition_line_id'] !== null) {
                    $lineId = (int) $r['requisition_line_id'];
                    if ($lineId > 0 && !isset($allowedSet[$lineId])) {
                        sendJson(['success' => false, 'message' => 'Invalid requisition line reference.']);
                    }
                    if ($lineId <= 0) {
                        $lineId = null;
                    }
                }
                $normalizedItems[] = [
                    'requisition_line_id' => $lineId,
                    'component_label' => substr($name, 0, 150),
                    'brand' => $brand !== '' ? substr($brand, 0, 100) : null,
                    'model' => $model !== '' ? substr($model, 0, 100) : null,
                    'specification' => $spec !== '' ? substr($spec, 0, 500) : null,
                    'sort_order' => $i,
                ];
            }

            if ($normalizedItems === []) {
                sendJson(['success' => false, 'message' => 'Add at least one canvass item.']);
            }
        }

        $n = count($normalizedItems);
        $validSupplierIds = [];
        $catStmt = $db->query('SELECT supplier_id FROM suppliers');
        while ($sid = $catStmt->fetchColumn()) {
            $validSupplierIds[(int) $sid] = true;
        }

        foreach ($suppliers as $sup) {
            if (!is_array($sup)) {
                continue;
            }
            $sid = (int) ($sup['supplier_id'] ?? 0);
            if ($sid <= 0 || !isset($validSupplierIds[$sid])) {
                sendJson(['success' => false, 'message' => 'Invalid supplier in matrix.']);
            }
            $prices = $sup['prices'] ?? [];
            if (!is_array($prices)) {
                continue;
            }
            foreach ($prices as $idxStr => $priceRaw) {
                $idx = (int) $idxStr;
                if ($idx < 0 || $idx >= $n) {
                    sendJson(['success' => false, 'message' => 'Price column does not match items.']);
                }
                if ($priceRaw === null || $priceRaw === '') {
                    continue;
                }
                if (!is_numeric($priceRaw)) {
                    sendJson(['success' => false, 'message' => 'Prices must be numbers.']);
                }
                $p = round((float) $priceRaw, 2);
                if ($p < 0) {
                    sendJson(['success' => false, 'message' => 'Price cannot be negative.']);
                }
            }
        }

        if (!$isOwner) {
            $existingSupplierIds = cwirmsDistinctSupplierIdsForRequest($db, $requestId);
            if ($existingSupplierIds !== []) {
                $payloadSidSet = [];
                foreach ($suppliers as $sup) {
                    if (!is_array($sup)) {
                        continue;
                    }
                    $sid = (int) ($sup['supplier_id'] ?? 0);
                    if ($sid > 0) {
                        $payloadSidSet[$sid] = true;
                    }
                }
                foreach ($existingSupplierIds as $esid) {
                    if (!isset($payloadSidSet[$esid])) {
                        sendJson([
                            'success' => false,
                            'message' => 'You cannot remove suppliers from the canvass. Only the requester can remove a supplier column.',
                        ]);
                    }
                }
            }
        }

        $db->beginTransaction();
        try {
            $del = $db->prepare('DELETE FROM requisition_canvass_detail WHERE request_id = ?');
            $del->execute([$requestId]);

            try {
                $insDetail = $db->prepare(
                    'INSERT INTO requisition_canvass_detail (request_id, requisition_line_id, user_id, component_label, brand, model, specification, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $newIds = [];
                foreach ($normalizedItems as $row) {
                    $insDetail->execute([
                        $requestId,
                        $row['requisition_line_id'],
                        $detailUserId,
                        $row['component_label'],
                        $row['brand'],
                        $row['model'],
                        $row['specification'],
                        $row['sort_order'],
                    ]);
                    $newIds[] = (int) $db->lastInsertId();
                }
            } catch (Throwable $e) {
                $insDetail = $db->prepare(
                    'INSERT INTO requisition_canvass_detail (request_id, requisition_line_id, user_id, component_label, specification, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $newIds = [];
                foreach ($normalizedItems as $row) {
                    $insDetail->execute([
                        $requestId,
                        $row['requisition_line_id'],
                        $detailUserId,
                        $row['component_label'],
                        $row['specification'],
                        $row['sort_order'],
                    ]);
                    $newIds[] = (int) $db->lastInsertId();
                }
            }

            $insCell = $db->prepare(
                'INSERT INTO requisition_canvass_detail_supplier (canvass_detail_id, supplier_id, price) VALUES (?, ?, ?)'
            );

            foreach ($suppliers as $sup) {
                if (!is_array($sup)) {
                    continue;
                }
                $sid = (int) ($sup['supplier_id'] ?? 0);
                if ($sid <= 0 || !isset($validSupplierIds[$sid])) {
                    continue;
                }
                $prices = $sup['prices'] ?? [];
                if (!is_array($prices)) {
                    $prices = [];
                }
                for ($idx = 0; $idx < $n; $idx++) {
                    $priceRaw = $prices[$idx] ?? $prices[(string) $idx] ?? null;
                    $p = null;
                    if ($priceRaw !== null && $priceRaw !== '') {
                        if (!is_numeric($priceRaw)) {
                            sendJson(['success' => false, 'message' => 'Prices must be numbers.']);
                        }
                        $p = round((float) $priceRaw, 2);
                        if ($p < 0) {
                            sendJson(['success' => false, 'message' => 'Price cannot be negative.']);
                        }
                    }
                    $cid = $newIds[$idx];
                    $insCell->execute([$cid, $sid, $p]);
                }
            }

            // Update canvas_submission_status based on submission_mode
            // Check if canvass_verification_approval row exists for this request
            $checkStmt = $db->prepare(
                'SELECT request_id FROM canvass_verification_approval WHERE request_id = ?'
            );
            $checkStmt->execute([$requestId]);
            $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($exists) {
                // Update existing row
                $statusUpdateStmt = $db->prepare(
                    'UPDATE canvass_verification_approval SET canvas_submission_status = ? WHERE request_id = ?'
                );
                $statusUpdateStmt->execute([$submissionMode, $requestId]);
            } else {
                // Create new row with submission status (for requester editing before GSD assignment)
                $insertStmt = $db->prepare(
                    'INSERT INTO canvass_verification_approval (request_id, canvas_submission_status) VALUES (?, ?)'
                );
                $insertStmt->execute([$requestId, $submissionMode]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        sendJson(['success' => true, 'message' => 'Canvass form saved.']);
    }

    sendJson(['success' => false, 'message' => 'Invalid action.']);
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
