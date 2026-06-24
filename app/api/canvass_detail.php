<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/requisition_detail_payload.php';
require_once __DIR__ . '/item_supplier_helpers.php';
require_once __DIR__ . '/approval_tables.php';
require_once __DIR__ . '/../helpers/canvass_pricing_overview.php';

function sendJson(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function saveCanvassQuotePhoto(array $file): string
{
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Quote photo upload failed.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 8 * 1024 * 1024) {
        throw new RuntimeException('Quote photo must be less than 8MB.');
    }
    $mime = (string) (mime_content_type($tmp) ?: '');
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($extMap[$mime])) {
        throw new RuntimeException('Quote photo must be JPG, PNG, WEBP, or GIF.');
    }
    $ext = $extMap[$mime];
    $relDir = 'uploads/canvass_quotes';
    $absDir = __DIR__ . '/../../public/' . $relDir;
    if (!is_dir($absDir) && !mkdir($absDir, 0777, true) && !is_dir($absDir)) {
        throw new RuntimeException('Could not create quote uploads directory.');
    }
    $fileName = 'quote_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $absPath = $absDir . '/' . $fileName;
    if (!move_uploaded_file($tmp, $absPath)) {
        throw new RuntimeException('Failed to save quote photo.');
    }

    return $relDir . '/' . $fileName;
}

/**
 * Preferred-supplier quoted prices stored in requisition_preferred_supplier_item (junction).
 *
 * @return array<int, array<int, string>> supplier_id => [sort_order => price]
 */
function cwirmsPreferredSupplierPricesByRequest(PDO $db, int $requestId): array
{
    $out = [];
    try {
        $maps = cwirmsLoadPreferredSupplierQuoteMapsForRequest($db, $requestId);
        foreach ($maps as $sid => $entry) {
            if (!empty($entry['prices'])) {
                $out[(int) $sid] = $entry['prices'];
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    return $out;
}

/**
 * @return array<int, true>
 */
function cwirmsPreferredSupplierIdSet(PDO $db, int $requestId): array
{
    $set = [];
    try {
        ensurePreferredSupplierItemQuotesTable($db);
        $stmt = $db->prepare(
            'SELECT DISTINCT supplier_id FROM requisition_preferred_supplier_item WHERE request_id = ?'
        );
        $stmt->execute([$requestId]);
        while ($sid = $stmt->fetchColumn()) {
            $id = (int) $sid;
            if ($id > 0) {
                $set[$id] = true;
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    return $set;
}

function cwirmsIsCanvassedMatrixSupplierRow(int $supplierId, ?string $quoteSource, array $preferredIdSet): bool
{
    $source = strtolower(trim((string) $quoteSource));
    if ($source === 'preferred') {
        return false;
    }
    if ($source === 'canvasser') {
        return true;
    }

    return !isset($preferredIdSet[$supplierId]);
}

/**
 * @return array<int, array<int, string>> supplier_id => [item_index => photo path]
 */
function cwirmsPreferredSupplierPhotosByRequest(PDO $db, int $requestId): array
{
    $out = [];
    try {
        $maps = cwirmsLoadPreferredSupplierQuoteMapsForRequest($db, $requestId);
        foreach ($maps as $sid => $entry) {
            if (!empty($entry['photos'])) {
                $out[(int) $sid] = $entry['photos'];
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    return $out;
}

function cwirmsPreferredSupplierLinkExists(PDO $db, int $requestId, int $supplierId): bool
{
    try {
        ensurePreferredSupplierItemQuotesTable($db);
        $stmt = $db->prepare(
            'SELECT 1 FROM requisition_preferred_supplier_item WHERE request_id = ? AND supplier_id = ? LIMIT 1'
        );
        $stmt->execute([$requestId, $supplierId]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function cwirmsUpdatePreferredSupplierQuotePhoto(
    PDO $db,
    int $requestId,
    int $supplierId,
    int $itemIndex,
    ?string $photoPath
): void {
    ensurePreferredSupplierItemQuotesTable($db);
    if (!cwirmsPreferredSupplierLinkExists($db, $requestId, $supplierId)) {
        throw new RuntimeException('Preferred supplier not found for this request.');
    }

    if ($photoPath !== null && trim($photoPath) !== '') {
        $photoVal = substr(trim($photoPath), 0, 255);
        $upsert = $db->prepare(
            'INSERT INTO requisition_preferred_supplier_item (request_id, supplier_id, sort_order, quote_photo)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE quote_photo = VALUES(quote_photo)'
        );
        $upsert->execute([$requestId, $supplierId, $itemIndex, $photoVal]);
    } else {
        $upd = $db->prepare(
            'UPDATE requisition_preferred_supplier_item
             SET quote_photo = NULL
             WHERE request_id = ? AND supplier_id = ? AND sort_order = ?'
        );
        $upd->execute([$requestId, $supplierId, $itemIndex]);
    }
}

/**
 * @param list<array{supplier_id: int, prices?: array<int|string, mixed>, photos?: array<int|string, mixed>, item_indices?: list<int>}> $preferredQuotes
 */
function cwirmsPersistPreferredSupplierQuotes(PDO $db, int $requestId, array $preferredQuotes): void
{
    ensurePreferredSupplierItemQuotesTable($db);

    $db->beginTransaction();
    try {
        $touchedSuppliers = [];

        foreach ($preferredQuotes as $pq) {
            if (!is_array($pq)) {
                continue;
            }
            $sid = (int) ($pq['supplier_id'] ?? 0);
            if ($sid <= 0 || !cwirmsPreferredSupplierLinkExists($db, $requestId, $sid)) {
                continue;
            }
            $touchedSuppliers[$sid] = true;

            $prices = $pq['prices'] ?? [];
            if (!is_array($prices)) {
                $prices = [];
            }
            $photosIn = $pq['photos'] ?? [];
            if (!is_array($photosIn)) {
                $photosIn = [];
            }

            $desiredSortOrders = [];
            if (isset($pq['item_indices']) && is_array($pq['item_indices'])) {
                foreach ($pq['item_indices'] as $idxRaw) {
                    $idx = (int) $idxRaw;
                    if ($idx >= 0) {
                        $desiredSortOrders[$idx] = true;
                    }
                }
            }
            foreach (array_keys($prices) as $idxStr) {
                $idx = (int) $idxStr;
                if ($idx >= 0) {
                    $desiredSortOrders[$idx] = true;
                }
            }
            foreach (array_keys($photosIn) as $idxStr) {
                $idx = (int) $idxStr;
                if ($idx >= 0) {
                    $desiredSortOrders[$idx] = true;
                }
            }

            $existingStmt = $db->prepare(
                'SELECT sort_order FROM requisition_preferred_supplier_item
                 WHERE request_id = ? AND supplier_id = ?'
            );
            $existingStmt->execute([$requestId, $sid]);
            $existingSortOrders = array_map('intval', $existingStmt->fetchAll(PDO::FETCH_COLUMN, 0));

            foreach ($existingSortOrders as $existingSort) {
                if (!isset($desiredSortOrders[$existingSort])) {
                    $del = $db->prepare(
                        'DELETE FROM requisition_preferred_supplier_item
                         WHERE request_id = ? AND supplier_id = ? AND sort_order = ?'
                    );
                    $del->execute([$requestId, $sid, $existingSort]);
                }
            }

            $upsert = $db->prepare(
                'INSERT INTO requisition_preferred_supplier_item (request_id, supplier_id, sort_order, price, quote_photo)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE price = VALUES(price), quote_photo = VALUES(quote_photo)'
            );

            foreach (array_keys($desiredSortOrders) as $sortOrder) {
                $sortOrder = (int) $sortOrder;
                $priceRaw = $prices[$sortOrder] ?? $prices[(string) $sortOrder] ?? null;
                $priceVal = null;
                if ($priceRaw !== null && $priceRaw !== '') {
                    if (!is_numeric($priceRaw)) {
                        throw new RuntimeException('Preferred supplier prices must be numbers.');
                    }
                    $p = round((float) $priceRaw, 2);
                    if ($p < 0) {
                        throw new RuntimeException('Preferred supplier price cannot be negative.');
                    }
                    $priceVal = $p;
                }

                $photoRaw = $photosIn[$sortOrder] ?? $photosIn[(string) $sortOrder] ?? null;
                $photoVal = null;
                if ($photoRaw !== null) {
                    $path = trim((string) $photoRaw);
                    if ($path !== '' && !str_starts_with($path, 'blob:')) {
                        $photoVal = substr($path, 0, 255);
                    }
                }

                $upsert->execute([$requestId, $sid, $sortOrder, $priceVal, $photoVal]);
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function cwirmsExistingCanvassSubmissionStatus(PDO $db, int $requestId): string
{
    try {
        $stmt = $db->prepare(
            'SELECT LOWER(TRIM(COALESCE(canvass_submission_status, \'draft\')))
             FROM requisition_canvass_detail
             WHERE request_id = ?
             ORDER BY sort_order ASC, canvass_detail_id ASC
             LIMIT 1'
        );
        $stmt->execute([$requestId]);
        $status = strtolower(trim((string) ($stmt->fetchColumn() ?: 'draft')));

        return $status === 'submitted' ? 'submitted' : 'draft';
    } catch (Throwable $e) {
        return 'draft';
    }
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
        // For verifiers, canvass is visible only after requester submits it.
        $statusCheckStmt = $db->prepare(
            'SELECT EXISTS(
                SELECT 1
                FROM requisition_canvass_detail
                WHERE request_id = ?
                  AND LOWER(TRIM(COALESCE(canvass_submission_status, \'draft\'))) = \'submitted\'
                LIMIT 1
            )'
        );
        $statusCheckStmt->execute([$requestId]);
        $canvasVisible = ((int) $statusCheckStmt->fetchColumn()) === 1;
        
        // Only allow verifiers to see submitted canvass forms
        if (!$canvasVisible) {
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
                'SELECT EXISTS(
                    SELECT 1
                    FROM requisition_canvass_detail
                    WHERE request_id = ?
                      AND LOWER(TRIM(COALESCE(canvass_submission_status, \'draft\'))) = \'submitted\'
                    LIMIT 1
                )'
            );
            $statusCheckStmt->execute([$requestId]);
            $canvasVisible = ((int) $statusCheckStmt->fetchColumn()) === 1;
            
            // Canvass assignees can only see submitted forms
            if (!$canvasVisible) {
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
    require_once __DIR__ . '/../helpers/supplier.php';
    ensureSupplierTinColumn($db);

    $stmt = $db->query(
        'SELECT supplier_id, supplier_name, supplier_image, contact_person, phone_number, email, address, city, country, postal_code, tin
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
    ensureRequisitionCanvassSubmissionColumn($db);
    ensureRequisitionPreferredQuoteColumns($db);
    ensurePreferredSupplierItemQuotesTable($db);
    ensureRequisitionLineQuotesTable($db);
    dropRequisitionCanvassDetailPhotoColumns($db);
    $uid = (int) $_SESSION['user_id'];
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'upload_quote_photo') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $itemIndex = (int) ($_POST['item_index'] ?? -1);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }
        if ($supplierId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid supplier.']);
        }
        if ($itemIndex < 0) {
            sendJson(['success' => false, 'message' => 'Invalid item reference.']);
        }
        loadOwnedRequest($db, $requestId, $uid);
        if (!isset($_FILES['quote_photo']) || !is_array($_FILES['quote_photo'])) {
            sendJson(['success' => false, 'message' => 'Missing quote photo file.']);
        }
        try {
            $path = saveCanvassQuotePhoto($_FILES['quote_photo']);
            cwirmsUpdatePreferredSupplierQuotePhoto($db, $requestId, $supplierId, $itemIndex, $path);
            sendJson(['success' => true, 'photo_url' => $path]);
        } catch (Throwable $e) {
            sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    if ($action === 'remove_quote_photo') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $itemIndex = (int) ($_POST['item_index'] ?? -1);
        if ($requestId <= 0 || $supplierId <= 0 || $itemIndex < 0) {
            sendJson(['success' => false, 'message' => 'Invalid request.']);
        }
        loadOwnedRequest($db, $requestId, $uid);
        try {
            cwirmsUpdatePreferredSupplierQuotePhoto($db, $requestId, $supplierId, $itemIndex, null);
            sendJson(['success' => true]);
        } catch (Throwable $e) {
            sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    if ($action === 'pricing_overview') {
        $requestId = (int) ($_GET['request_id'] ?? 0);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }
        if (!userIsGsdOfficer($db, $uid) && !userIsComptroller($db, $uid)) {
            sendJson(['success' => false, 'message' => 'Pricing overview is available to G.S.D. officers and comptrollers only.']);
        }
        loadCanvassGetRequest($db, $requestId, $uid);
        require_once __DIR__ . '/../helpers/canvass_pricing_overview.php';
        $overview = cwirmsCanvassPricingOverviewForRequest($db, $requestId);
        sendJson(['success' => true, 'pricing_overview' => $overview]);
    }

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
                'selected_supplier_source' => null,
            ];
        }

        if ($idxByDetailId !== []) {
            ensureSuggestedSupplierSelectionSourceColumn($db);
            $selStmt = $db->prepare(
                'SELECT canvass_detail_id, supplier_id, selection_source
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
                $srcRaw = strtolower(trim((string) ($sel['selection_source'] ?? '')));
                $items[$idx]['selected_supplier_source'] = in_array($srcRaw, ['preferred', 'canvassed'], true)
                    ? $srcRaw
                    : null;
            }
        }

        $preferredIdSet = cwirmsPreferredSupplierIdSet($db, $requestId);
        $supplierPricesBySid = [];
        $supplierNotesBySid = [];
        if ($idxByDetailId !== []) {
            $ids = array_keys($idxByDetailId);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            ensureCanvassSupplierNotesColumns($db);
            $cj = $db->prepare(
                "SELECT canvass_detail_id, supplier_id, price, quote_source, benefits
                 FROM requisition_canvass_detail_supplier
                 WHERE canvass_detail_id IN ($placeholders)"
            );
            $cj->execute($ids);
            while ($row = $cj->fetch(PDO::FETCH_ASSOC)) {
                $cid = (int) $row['canvass_detail_id'];
                $sid = (int) $row['supplier_id'];
                $quoteSource = isset($row['quote_source']) ? (string) $row['quote_source'] : null;
                if (!cwirmsIsCanvassedMatrixSupplierRow($sid, $quoteSource, $preferredIdSet)) {
                    continue;
                }
                $idx = $idxByDetailId[$cid] ?? null;
                if ($idx === null) {
                    continue;
                }
                if (!isset($supplierPricesBySid[$sid])) {
                    $supplierPricesBySid[$sid] = [];
                }
                $supplierPricesBySid[$sid][$idx] = $row['price'] !== null ? (string) $row['price'] : '';
                if (!isset($supplierNotesBySid[$sid])) {
                    $benefits = trim((string) ($row['benefits'] ?? ''));
                    $supplierNotesBySid[$sid] = [
                        'benefits' => $benefits !== '' ? $benefits : null,
                    ];
                }
            }
        }

        $discountsBySupplier = cwirmsCanvassSupplierDiscountsBySupplierForRequest($db, $requestId);

        $matrixSuppliers = [];
        foreach ($supplierPricesBySid as $sid => $prices) {
            $s = $catalogById[$sid] ?? null;
            if (!$s) {
                continue;
            }
            $notes = $supplierNotesBySid[$sid] ?? ['benefits' => null];
            $matrixSuppliers[] = [
                'supplier_id' => $sid,
                'supplier_name' => (string) ($s['supplier_name'] ?? ''),
                'supplier_image' => (string) ($s['supplier_image'] ?? ''),
                'prices' => $prices,
                'photos' => [],
                'benefits' => $notes['benefits'],
                'discounts' => $discountsBySupplier[$sid] ?? [],
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
            require_once __DIR__ . '/../helpers/supplier.php';
            ensureSupplierTinColumn($db);
            ensurePreferredSupplierItemQuotesTable($db);

            $requestId = (int) ($_GET['request_id'] ?? 0);
            if ($requestId <= 0) {
                sendJson(['success' => false, 'message' => 'Invalid request id.']);
            }
            loadCanvassGetRequest($db, $requestId, $uid);

            // Get all distinct preferred suppliers for this request from requisition_preferred_supplier_item
            $stmt = $db->prepare(
                'SELECT DISTINCT rpsi.supplier_id, s.supplier_name, s.contact_person, s.phone_number, s.email, s.address, s.city, s.country, s.postal_code, s.tin, s.supplier_image, s.is_preferred, s.preferred_request_id, s.shop_url
                 FROM requisition_preferred_supplier_item rpsi
                 LEFT JOIN suppliers s ON s.supplier_id = rpsi.supplier_id
                 WHERE rpsi.request_id = ?
                 ORDER BY rpsi.supplier_id ASC'
            );
            $stmt->execute([$requestId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $quoteMaps = cwirmsLoadPreferredSupplierQuoteMapsForRequest($db, $requestId);
            $out = [];
            foreach ($rows as $r) {
                $sid = isset($r['supplier_id']) ? (int) $r['supplier_id'] : 0;
                if ($sid <= 0) {
                    continue;
                }
                $quoteEntry = $quoteMaps[$sid] ?? ['sort_orders' => [], 'prices' => [], 'photos' => []];
                $quotedPrices = $quoteEntry['prices'];
                $quotePhotos = $quoteEntry['photos'];
                $quotedItemIndices = $quoteEntry['sort_orders'];
                $out[] = [
                    'supplier_id' => $sid,
                    'supplier_name' => (string) ($r['supplier_name'] ?? ''),
                    'contact_person' => (string) ($r['contact_person'] ?? ''),
                    'phone_number' => (string) ($r['phone_number'] ?? ''),
                    'email' => (string) ($r['email'] ?? ''),
                    'shop_url' => (string) ($r['shop_url'] ?? ''),
                    'address' => (string) ($r['address'] ?? ''),
                    'city' => (string) ($r['city'] ?? ''),
                    'country' => (string) ($r['country'] ?? ''),
                    'postal_code' => (string) ($r['postal_code'] ?? ''),
                    'tin' => (string) ($r['tin'] ?? ''),
                    'supplier_image' => (string) ($r['supplier_image'] ?? ''),
                    'quoted_prices' => $quotedPrices,
                    'quote_photos' => $quotePhotos,
                    'quoted_item_indices' => $quotedItemIndices,
                    'is_preferred' => isset($r['is_preferred']) ? (int) $r['is_preferred'] : 0,
                    'preferred_request_id' => isset($r['preferred_request_id']) ? (int) $r['preferred_request_id'] : null,
                ];
            }
            sendJson(['success' => true, 'preferred_suppliers' => $out]);
        }

        if ($action === 'add_preferred') {
            require_once __DIR__ . '/../helpers/supplier.php';
            ensureSupplierTinColumn($db);

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
            $address = trim((string) ($_POST['address'] ?? ''));
            $city = trim((string) ($_POST['city'] ?? ''));
            $country = trim((string) ($_POST['country'] ?? ''));
            $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
            $tin = cwirmsNormalizeSupplierTin($_POST['tin'] ?? null);

            // avoid duplicate supplier names in suppliers table
            $stmtChk = $db->prepare('SELECT supplier_id FROM suppliers WHERE LOWER(supplier_name) = LOWER(?) LIMIT 1');
            $stmtChk->execute([$supplierName]);
            if ($stmtChk->fetch(PDO::FETCH_ASSOC)) {
                sendJson(['success' => false, 'message' => 'A supplier with this name already exists. Choose it from the list instead.']);
            }

            try {
                $ins = $db->prepare(
                    'INSERT INTO suppliers (supplier_name, contact_person, phone_number, email, address, city, country, postal_code, tin, status, supplier_image, is_preferred, preferred_request_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $supplierName,
                    $contactPerson !== '' ? $contactPerson : null,
                    $phoneNumber !== '' ? $phoneNumber : null,
                    $email !== '' ? $email : null,
                    $address !== '' ? $address : null,
                    $city !== '' ? $city : null,
                    $country !== '' ? $country : null,
                    $postalCode !== '' ? $postalCode : null,
                    $tin,
                    'Active',
                    null,
                    1,
                    $requestId,
                ]);
            } catch (Throwable $e) {
                $ins = $db->prepare(
                    'INSERT INTO suppliers (supplier_name, contact_person, phone_number, email, address, city, country, postal_code, tin, status, supplier_image)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $supplierName,
                    $contactPerson !== '' ? $contactPerson : null,
                    $phoneNumber !== '' ? $phoneNumber : null,
                    $email !== '' ? $email : null,
                    $address !== '' ? $address : null,
                    $city !== '' ? $city : null,
                    $country !== '' ? $country : null,
                    $postalCode !== '' ? $postalCode : null,
                    $tin,
                    'Active',
                    null,
                ]);
            }
            $newSid = (int) $db->lastInsertId();

            // Insert a placeholder row into requisition_preferred_supplier_item to mark supplier as preferred
            // This allows the supplier to appear in the preferred list even before any quotes are added
            ensurePreferredSupplierItemQuotesTable($db);
            $placeholder = $db->prepare(
                'INSERT INTO requisition_preferred_supplier_item (request_id, supplier_id, sort_order, price, quote_photo)
                 VALUES (?, ?, ?, NULL, NULL)
                 ON DUPLICATE KEY UPDATE created_at = created_at'
            );
            $placeholder->execute([$requestId, $newSid, -1]);

            $verifyLink = $db->prepare(
                'SELECT preferred_supplier_item_id FROM requisition_preferred_supplier_item
                 WHERE request_id = ? AND supplier_id = ? LIMIT 1'
            );
            $verifyLink->execute([$requestId, $newSid]);
            if (!(int) ($verifyLink->fetchColumn() ?: 0)) {
                sendJson(['success' => false, 'message' => 'Preferred supplier could not be linked. Please try again.']);
            }

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

            $sel = $db->prepare(
                'SELECT supplier_id, supplier_name, supplier_image, contact_person, phone_number, email, address, city, country, postal_code, tin
                 FROM suppliers WHERE supplier_id = ?'
            );
            $sel->execute([$newSid]);
            $row = $sel->fetch(PDO::FETCH_ASSOC);
            sendJson(['success' => true, 'message' => 'Preferred supplier added.', 'supplier' => $row]);
        }

        if ($action === 'update_preferred') {
            require_once __DIR__ . '/../helpers/supplier.php';
            ensureSupplierTinColumn($db);

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
            $address = trim((string) ($_POST['address'] ?? ''));
            $city = trim((string) ($_POST['city'] ?? ''));
            $country = trim((string) ($_POST['country'] ?? ''));
            $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
            $tin = cwirmsNormalizeSupplierTin($_POST['tin'] ?? null);

            $upd = $db->prepare(
                'UPDATE suppliers
                 SET supplier_name = ?, contact_person = ?, phone_number = ?, email = ?, address = ?, city = ?, country = ?, postal_code = ?, tin = ?
                 WHERE supplier_id = ?'
            );
            $upd->execute([
                $supplierName,
                $contactPerson !== '' ? $contactPerson : null,
                $phoneNumber !== '' ? $phoneNumber : null,
                $email !== '' ? $email : null,
                $address !== '' ? $address : null,
                $city !== '' ? $city : null,
                $country !== '' ? $country : null,
                $postalCode !== '' ? $postalCode : null,
                $tin,
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
            ensurePreferredSupplierItemQuotesTable($db);
            $delItems = $db->prepare(
                'DELETE FROM requisition_preferred_supplier_item WHERE request_id = ? AND supplier_id = ?'
            );
            $delItems->execute([$requestId, $supplierId]);
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
            
            ensurePreferredSupplierItemQuotesTable($db);
            
            // Check if supplier is already linked (has ANY row in requisition_preferred_supplier_item)
            $dupChk = $db->prepare(
                'SELECT 1 FROM requisition_preferred_supplier_item WHERE request_id = ? AND supplier_id = ? LIMIT 1'
            );
            $dupChk->execute([$requestId, $supplierId]);
            if ($dupChk->fetchColumn()) {
                sendJson(['success' => false, 'message' => 'This supplier is already added.', 'already_added' => true]);
            }
            
            // Insert a placeholder row to mark supplier as preferred
            $ins = $db->prepare(
                'INSERT INTO requisition_preferred_supplier_item (request_id, supplier_id, sort_order, price, quote_photo)
                 VALUES (?, ?, ?, NULL, NULL)'
            );
            $ins->execute([$requestId, $supplierId, -1]);

            $verifyLink = $db->prepare(
                'SELECT preferred_supplier_item_id FROM requisition_preferred_supplier_item
                 WHERE request_id = ? AND supplier_id = ? LIMIT 1'
            );
            $verifyLink->execute([$requestId, $supplierId]);
            if (!(int) ($verifyLink->fetchColumn() ?: 0)) {
                sendJson(['success' => false, 'message' => 'Preferred supplier could not be linked. Please try again.']);
            }

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
        $preferredQuotesRaw = $_POST['preferred_quotes'] ?? null;
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
            $discountErr = cwirmsValidateCanvassSupplierDiscountPayload($sup['discounts'] ?? null);
            if ($discountErr !== null) {
                sendJson(['success' => false, 'message' => $discountErr]);
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

        ensureRequisitionPreferredQuoteColumns($db);
        ensurePreferredSupplierItemQuotesTable($db);
        ensureCanvassSupplierQuoteSourceColumn($db);
        ensureCanvassSupplierNotesColumns($db);
        ensureCanvassSupplierDiscountsTable($db);

        $preferredQuotes = [];
        if ($isOwner && $preferredQuotesRaw !== null) {
            $decodedPreferred = json_decode((string) $preferredQuotesRaw, true);
            if (is_array($decodedPreferred)) {
                $preferredQuotes = $decodedPreferred;
            }
        }

        // Only the requester may change canvass submission status via POST.
        if (!$isOwner) {
            $submissionMode = cwirmsExistingCanvassSubmissionStatus($db, $requestId);
        } elseif ($submissionMode !== 'submitted') {
            $submissionMode = 'draft';
        }

        $db->beginTransaction();
        try {
            $del = $db->prepare('DELETE FROM requisition_canvass_detail WHERE request_id = ?');
            $del->execute([$requestId]);

            try {
                $insDetail = $db->prepare(
                    'INSERT INTO requisition_canvass_detail (request_id, requisition_line_id, user_id, component_label, brand, model, specification, sort_order, canvass_submission_status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
                        $submissionMode,
                    ]);
                    $newIds[] = (int) $db->lastInsertId();
                }
            } catch (Throwable $e) {
                $insDetail = $db->prepare(
                    'INSERT INTO requisition_canvass_detail (request_id, requisition_line_id, user_id, component_label, specification, sort_order, canvass_submission_status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
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
                        $submissionMode,
                    ]);
                    $newIds[] = (int) $db->lastInsertId();
                }
            }

            $insCell = $db->prepare(
                'INSERT INTO requisition_canvass_detail_supplier (canvass_detail_id, supplier_id, price, quote_source, benefits) VALUES (?, ?, ?, ?, ?)'
            );
            $insertedCells = [];

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
                $benefitsRaw = trim((string) ($sup['benefits'] ?? ''));
                $benefitsVal = $benefitsRaw !== '' ? $benefitsRaw : null;
                foreach ($prices as $idxStr => $priceRaw) {
                    $idx = (int) $idxStr;
                    if ($idx < 0 || $idx >= $n) {
                        throw new RuntimeException('Price column does not match items.');
                    }
                    $p = null;
                    if ($priceRaw !== null && $priceRaw !== '') {
                        if (!is_numeric($priceRaw)) {
                            throw new RuntimeException('Prices must be numbers.');
                        }
                        $p = round((float) $priceRaw, 2);
                        if ($p < 0) {
                            throw new RuntimeException('Price cannot be negative.');
                        }
                    }
                    $cid = $newIds[$idx];
                    $insCell->execute([$cid, $sid, $p, 'canvasser', $benefitsVal]);
                    $insertedCells[$cid . ':' . $sid] = true;
                }
            }

            cwirmsPersistCanvassSupplierDiscountsForRequest($db, $requestId, $suppliers);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($isOwner && $preferredQuotesRaw !== null) {
            cwirmsPersistPreferredSupplierQuotes($db, $requestId, $preferredQuotes);
        }

        sendJson(['success' => true, 'message' => 'Canvass form saved.']);
    }

    // ── Requester per-line preferred quote actions ──────────────────────────────

    if ($action === 'get_requester_line_view') {
        ensureRequisitionLineQuotesTable($db);

        $requestId = (int) ($_GET['request_id'] ?? 0);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }
        // Ownership check
        $ownStmt = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ? AND user_id = ?');
        $ownStmt->execute([$requestId, $uid]);
        if (!$ownStmt->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Access denied.']);
        }
        if (!requisitionInventoryAccepted($db, $requestId)) {
            sendJson(['success' => false, 'message' => 'Open after the inventory manager accepts the requisition.']);
        }

        $hStmt = $db->prepare(
            'SELECT ri.created_at, ri.purpose, d.office_name, f.building, f.room, f.laboratory
             FROM requisition_item ri
             LEFT JOIN offices d ON d.office_id = ri.office_id
             LEFT JOIN facilities f ON f.facility_id = ri.facility_id
             WHERE ri.request_id = ? LIMIT 1'
        );
        $hStmt->execute([$requestId]);
        $hRow = $hStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        // Use the existing buildFacilityLabel() function to construct the string
        $hRow['facility_label'] = buildFacilityLabel($hRow);

        $lineStmt = $db->prepare(
            "SELECT requisition_line_id, item_name, item_brand, model, specification,
                    quantity, unit_type, group_label, estimated_unit_cost
             FROM requisition_line
             WHERE request_id = ? AND (deleted_at IS NULL OR deleted_at = '')
             ORDER BY sort_order ASC, requisition_line_id ASC"
        );
        $lineStmt->execute([$requestId]);
        $lines   = $lineStmt->fetchAll(PDO::FETCH_ASSOC);
        $lineIds = array_map('intval', array_column($lines, 'requisition_line_id'));

        $prefQuotesByLine = [];
        if (!empty($lineIds)) {
            $ph    = implode(',', array_fill(0, count($lineIds), '?'));
            $qStmt = $db->prepare(
                "SELECT rlq.requisition_line_id, rlq.supplier_id, rlq.quoted_unit_price,
                        rlq.benefits, s.supplier_name, s.supplier_image
                 FROM requisition_line_quotes rlq
                 INNER JOIN suppliers s ON s.supplier_id = rlq.supplier_id
                 WHERE rlq.requisition_line_id IN ($ph) AND rlq.quote_type = 'preferred'
                 ORDER BY s.supplier_name ASC"
            );
            $qStmt->execute($lineIds);
            while ($q = $qStmt->fetch(PDO::FETCH_ASSOC)) {
                $lid = (int) $q['requisition_line_id'];
                $prefQuotesByLine[$lid][] = [
                    'supplier_id'       => (int) $q['supplier_id'],
                    'supplier_name'     => (string) $q['supplier_name'],
                    'supplier_image'    => (string) ($q['supplier_image'] ?? ''),
                    'quoted_unit_price' => $q['quoted_unit_price'],
                    'benefits'          => $q['benefits'],
                ];
            }
        }

        $result = [];
        foreach ($lines as $line) {
            $lid      = (int) $line['requisition_line_id'];
            $result[] = [
                'requisition_line_id' => $lid,
                'item_name'           => (string) ($line['item_name'] ?? ''),
                'brand'               => (string) ($line['item_brand'] ?? ''),
                'model'               => (string) ($line['model'] ?? ''),
                'specification'       => (string) ($line['specification'] ?? ''),
                'quantity'            => (int) ($line['quantity'] ?? 1),
                'unit_type'           => (string) ($line['unit_type'] ?? 'unit'),
                'group_label'         => (string) ($line['group_label'] ?? ''),
                'estimated_unit_cost' => $line['estimated_unit_cost'],
                'preferred_quotes'    => $prefQuotesByLine[$lid] ?? [],
            ];
        }
        require_once __DIR__ . '/../helpers/supplier.php';
        ensureSupplierTinColumn($db);
        $supStmt   = $db->query(
            'SELECT supplier_id, supplier_name, supplier_image, contact_person, phone_number, email
             FROM suppliers ORDER BY supplier_name ASC'
        );
        $suppliers = $supStmt->fetchAll(PDO::FETCH_ASSOC);

        sendJson([
            'success'   => true,
            'lines'     => $result,
            'suppliers' => $suppliers,
            'header'    => [
                'request_date'   => $hRow['created_at'] ?? '',
                'purpose'        => $hRow['purpose'] ?? '',
                'office_name'    => $hRow['office_name'] ?? '—',
                'facility_label' => $hRow['facility_label'] ?? '—',
            ],
        ]);
    }

    if ($action === 'save_preferred_quote') {
        ensureRequisitionLineQuotesTable($db);

        $requestId  = (int) ($_POST['request_id'] ?? 0);
        $lineId     = (int) ($_POST['requisition_line_id'] ?? 0);
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $priceRaw   = trim((string) ($_POST['quoted_unit_price'] ?? ''));
        $benefits   = trim((string) ($_POST['benefits'] ?? ''));

        if ($requestId <= 0 || $lineId <= 0 || $supplierId <= 0) {
            sendJson(['success' => false, 'message' => 'Missing required fields.']);
        }
        if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw < 0) {
            sendJson(['success' => false, 'message' => 'Unit price must be a valid non-negative number.']);
        }
        $price = round((float) $priceRaw, 2);
        $benefitsVal = $benefits !== '' ? $benefits : null;

        loadOwnedRequest($db, $requestId, $uid);
        if (!requisitionInventoryAccepted($db, $requestId)) {
            sendJson(['success' => false, 'message' => 'Open after the inventory manager accepts the requisition.']);
        }
        if (requisitionVerifierChainLockedForRequest($db, $requestId)) {
            sendJson(['success' => false, 'message' => 'A verifier has already acted on this request. Preferred quotes are locked.']);
        }

        $lChk = $db->prepare(
            'SELECT requisition_line_id FROM requisition_line
             WHERE requisition_line_id = ? AND request_id = ?
               AND (deleted_at IS NULL OR deleted_at = \'\')
             LIMIT 1'
        );
        $lChk->execute([$lineId, $requestId]);
        if (!$lChk->fetchColumn()) {
            sendJson(['success' => false, 'message' => 'Invalid line item.']);
        }

        $sChk = $db->prepare('SELECT supplier_id FROM suppliers WHERE supplier_id = ? LIMIT 1');
        $sChk->execute([$supplierId]);
        if (!$sChk->fetchColumn()) {
            sendJson(['success' => false, 'message' => 'Invalid supplier.']);
        }

        $upsert = $db->prepare(
            "INSERT INTO requisition_line_quotes
                 (requisition_line_id, supplier_id, quoted_unit_price, quote_type, submitted_by_user_id, benefits)
             VALUES (?, ?, ?, 'preferred', ?, ?)
             ON DUPLICATE KEY UPDATE
                 quoted_unit_price    = VALUES(quoted_unit_price),
                 benefits             = VALUES(benefits),
                 submitted_by_user_id = VALUES(submitted_by_user_id),
                 quote_type           = 'preferred'"
        );
        $upsert->execute([$lineId, $supplierId, $price, $uid, $benefitsVal]);

        $verify = $db->prepare(
            "SELECT quote_id FROM requisition_line_quotes
             WHERE requisition_line_id = ? AND supplier_id = ? AND quote_type = 'preferred'
             LIMIT 1"
        );
        $verify->execute([$lineId, $supplierId]);
        $quoteId = (int) ($verify->fetchColumn() ?: 0);
        if ($quoteId <= 0) {
            sendJson(['success' => false, 'message' => 'Preferred quote could not be saved. Please try again.']);
        }

        sendJson([
            'success'  => true,
            'message'  => 'Preferred quote saved.',
            'quote_id' => $quoteId,
        ]);
    }

    if ($action === 'delete_preferred_quote') {
        ensureRequisitionLineQuotesTable($db);

        $requestId  = (int) ($_POST['request_id'] ?? 0);
        $lineId     = (int) ($_POST['requisition_line_id'] ?? 0);
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);

        if ($requestId <= 0 || $lineId <= 0 || $supplierId <= 0) {
            sendJson(['success' => false, 'message' => 'Missing required fields.']);
        }

        loadOwnedRequest($db, $requestId, $uid);
        if (requisitionVerifierChainLockedForRequest($db, $requestId)) {
            sendJson(['success' => false, 'message' => 'A verifier has already acted on this request.']);
        }

        $del = $db->prepare(
            "DELETE FROM requisition_line_quotes WHERE requisition_line_id = ? AND supplier_id = ? AND quote_type = 'preferred'"
        );
        $del->execute([$lineId, $supplierId]);

        sendJson(['success' => true]);
    }

    sendJson(['success' => false, 'message' => 'Invalid action.']);
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
