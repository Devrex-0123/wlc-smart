<?php
/**
 * GSD officer — requisition list and GSD verification (canvass_verification_approval.gsd_status).
 */
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../classes/db.php';
require_once __DIR__ . '/../requisition_detail_payload.php';
require_once __DIR__ . '/../approval_tables.php';

function sendJson(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function assertGsdOfficer(PDO $db): void
{
    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Unauthorized']);
    }
    $stmt = $db->prepare('SELECT role FROM user WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        sendJson(['success' => false, 'message' => 'Unauthorized']);
    }
    $r = strtolower(trim((string) ($row['role'] ?? '')));
    if ($r !== 'gsd officer') {
        sendJson(['success' => false, 'message' => 'Forbidden']);
    }
}

function gsdVerifiedByLabel(PDO $db): string
{
    $stmt = $db->prepare('SELECT Email FROM user WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $email = (string) ($row['Email'] ?? '');
    if ($email === '') {
        return 'GSD officer';
    }

    return explode('@', $email)[0] ?? $email;
}

function gsdOfficerOfficeId(PDO $db): int
{
    $stmt = $db->prepare('SELECT office_id FROM user WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $id = (int) ($row['office_id'] ?? 0);

    return $id;
}

function userCanvasAssigneeLabel(array $userRow): string
{
    $email = (string) ($userRow['Email'] ?? '');
    if ($email === '') {
        return '';
    }

    return explode('@', $email)[0] ?? $email;
}

/** Roles allowed to be assigned as canvassing staff (same office as GSD). */
function roleMayBeCanvasAssignee(string $role): bool
{
    $r = strtolower(trim($role));
    $blocked = ['dean', 'gsd officer', 'comptroller', 'president', 'president verifier', 'verifier president', 'president_verifier'];

    return $r !== '' && !in_array($r, $blocked, true);
}

/**
 * @return array{0: array|null, 1: string|null} [user row or null, error message]
 */
function loadValidatedCanvasAssignee(PDO $db, int $gsdDeptId, int $assigneeUserId): array
{
    if ($assigneeUserId <= 0) {
        return [null, 'Invalid assignee.'];
    }
    $stmt = $db->prepare('SELECT user_id, Email, role, office_id FROM user WHERE user_id = ?');
    $stmt->execute([$assigneeUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [null, 'Assignee not found.'];
    }
    if ((int) ($row['office_id'] ?? 0) !== $gsdDeptId) {
        return [null, 'Assignee must be in your office.'];
    }
    if (!roleMayBeCanvasAssignee((string) ($row['role'] ?? ''))) {
        return [null, 'This user cannot be assigned as canvassing staff.'];
    }

    return [$row, null];
}

/**
 * Validate canvassed or requester-preferred supplier quote for GSD suggested selection.
 *
 * @return array{0: array|null, 1: string|null} [supplier row or null, error message]
 */
function cwirmsPreferredQuotedPriceForItemIndex(?string $quotedPricesRaw, int $itemIndex): ?string
{
    if ($quotedPricesRaw === null || trim($quotedPricesRaw) === '') {
        return null;
    }
    $decoded = json_decode($quotedPricesRaw, true);
    if (!is_array($decoded)) {
        return null;
    }
    $priceRaw = $decoded[$itemIndex] ?? $decoded[(string) $itemIndex] ?? null;
    if ($priceRaw === null || $priceRaw === '') {
        return null;
    }
    if (!is_numeric($priceRaw)) {
        return null;
    }
    $price = round((float) $priceRaw, 2);
    if ($price < 0) {
        return null;
    }

    return (string) $price;
}

function loadValidatedCanvassedSuggestedSupplierForDetail(PDO $db, int $requestId, int $canvassDetailId, int $supplierId): array
{
    if ($supplierId <= 0) {
        return [null, 'Select a supplier before saving.'];
    }
    if ($canvassDetailId <= 0) {
        return [null, 'Invalid canvass item reference.'];
    }
    $stmt = $db->prepare('
        SELECT s.supplier_id, s.supplier_name
        FROM suppliers s
        WHERE s.supplier_id = ?
          AND EXISTS (
              SELECT 1
              FROM requisition_canvass_detail cd
              INNER JOIN requisition_canvass_detail_supplier cds ON cds.canvass_detail_id = cd.canvass_detail_id
              WHERE cd.request_id = ?
                AND cd.canvass_detail_id = ?
                AND cds.supplier_id = s.supplier_id
                AND cds.price IS NOT NULL
          )
        LIMIT 1
    ');
    $stmt->execute([$supplierId, $requestId, $canvassDetailId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [null, 'Selected supplier must come from quoted suppliers in the canvassed matrix.'];
    }

    return [$row, null];
}

function loadValidatedPreferredSuggestedSupplierForDetail(PDO $db, int $requestId, int $canvassDetailId, int $supplierId): array
{
    if ($supplierId <= 0) {
        return [null, 'Select a supplier before saving.'];
    }
    if ($canvassDetailId <= 0) {
        return [null, 'Invalid canvass item reference.'];
    }

    $sortStmt = $db->prepare(
        'SELECT sort_order FROM requisition_canvass_detail WHERE request_id = ? AND canvass_detail_id = ? LIMIT 1'
    );
    $sortStmt->execute([$requestId, $canvassDetailId]);
    $sortOrder = (int) ($sortStmt->fetchColumn() ?: 0);

    require_once __DIR__ . '/../approval_tables.php';
    ensurePreferredSupplierItemQuotesTable($db);
    
    // Check that supplier exists in the preferred supplier list (requisition_preferred_supplier_item)
    $dupChk = $db->prepare(
        'SELECT 1 FROM requisition_preferred_supplier_item
         WHERE request_id = ? AND supplier_id = ?
         LIMIT 1'
    );
    $dupChk->execute([$requestId, $supplierId]);
    if (!$dupChk->fetchColumn()) {
        return [null, 'Selected supplier must come from the requester preferred supplier matrix.'];
    }
    
    // Get supplier details from suppliers table
    $prefStmt = $db->prepare(
        'SELECT supplier_id, supplier_name FROM suppliers WHERE supplier_id = ? LIMIT 1'
    );
    $prefStmt->execute([$supplierId]);
    $prefRow = $prefStmt->fetch(PDO::FETCH_ASSOC);
    if (!$prefRow) {
        return [null, 'Supplier not found.'];
    }
    $quotedPrice = cwirmsPreferredQuotedPriceForSortOrder($db, $requestId, $supplierId, $sortOrder);
    if ($quotedPrice === null) {
        return [null, 'Selected preferred supplier must have a quoted price for this item.'];
    }

    return [
        [
            'supplier_id' => (int) ($prefRow['supplier_id'] ?? 0),
            'supplier_name' => (string) ($prefRow['supplier_name'] ?? ''),
        ],
        null,
    ];
}

function loadValidatedSuggestedSupplierForDetail(
    PDO $db,
    int $requestId,
    int $canvassDetailId,
    int $supplierId,
    string $selectionSource = 'canvassed'
): array {
    if ($selectionSource === 'preferred') {
        return loadValidatedPreferredSuggestedSupplierForDetail($db, $requestId, $canvassDetailId, $supplierId);
    }

    return loadValidatedCanvassedSuggestedSupplierForDetail($db, $requestId, $canvassDetailId, $supplierId);
}

function requestAllCanvassItemsHaveSuggestedSupplier(PDO $db, int $requestId): bool
{
    $totalStmt = $db->prepare('SELECT COUNT(*) FROM requisition_canvass_detail WHERE request_id = ?');
    $totalStmt->execute([$requestId]);
    $total = (int) $totalStmt->fetchColumn();
    if ($total <= 0) {
        return false;
    }
    $selectedStmt = $db->prepare(
        'SELECT COUNT(*) FROM request_approval_suggested_supplier_item WHERE request_id = ?'
    );
    $selectedStmt->execute([$requestId]);
    $selected = (int) $selectedStmt->fetchColumn();

    return $selected >= $total;
}

try {
    $db = Database::connect();
    ensureRequisitionCanvassSubmissionColumn($db);
    ensureSuggestedSupplierSelectionSourceColumn($db);
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'list_requests') {
        assertGsdOfficer($db);

        $agg = requisitionSqlSelectListAggregates();
        $stmt = $db->query("
            SELECT r.request_id, r.created_at, r.status, r.message,
                   u.Email, d.`office_name` AS office_name,
                   {$agg}
            FROM requisition_item r
            LEFT JOIN requisition_form_approval rfa ON rfa.request_id = r.request_id
            LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
            LEFT JOIN user u ON u.user_id = r.user_id
            LEFT JOIN offices d ON d.office_id = r.office_id
            WHERE LOWER(TRIM(COALESCE(rfa.requisition_status, 'pending'))) = 'accept'
            AND r.submission_status = 'submitted'
            AND EXISTS (
                SELECT 1
                FROM requisition_canvass_detail rcd
                WHERE rcd.request_id = r.request_id
                  AND LOWER(TRIM(COALESCE(rcd.canvass_submission_status, 'draft'))) = 'submitted'
                LIMIT 1
            )
            ORDER BY r.created_at DESC, r.request_id DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $requests = array_map(static function ($row) {
            $email = (string) ($row['Email'] ?? '');
            $requester = $email !== '' ? (explode('@', $email)[0] ?? 'Unknown') : 'Unknown';
            $price = $row['list_min_price'] ?? null;
            $amountLabel = '—';
            if ($price !== null && $price !== '' && is_numeric($price)) {
                $amountLabel = 'PHP ' . number_format((float) $price, 2);
            }

            return [
                'id' => 'REQ-' . str_pad((string) $row['request_id'], 6, '0', STR_PAD_LEFT),
                'request_id' => (int) $row['request_id'],
                'date' => $row['created_at'],
                'items' => requisitionExplodePipeOrDefault($row['items_concat'] ?? null, '—'),
                'suppliers' => requisitionExplodePipeOrDefault($row['suppliers_concat'] ?? null, 'N/A'),
                'status' => $row['status'] ?? 'Pending',
                'message' => $row['message'] ?? '',
                'requester' => $requester,
                'office' => $row['office_name'] ?? '—',
                'amount_label' => $amountLabel,
            ];
        }, $rows);

        sendJson(['success' => true, 'requests' => $requests]);
    }

    if ($action === 'get_approval_status') {
        assertGsdOfficer($db);
        $requestId = (int) ($_GET['request_id'] ?? 0);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }

        $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $chk->execute([$requestId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        $stmt = $db->prepare('
            SELECT canvas_status, canvassed_by, canvassed_at, canvas_assignee_user_id, suggested_supplier_id, suggested_supplier_name, comp_status, checked_by, checked_at, gsd_status, pres_status
            FROM canvass_verification_approval
            WHERE request_id = ?
            LIMIT 1
        ');
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            sendJson([
                'success' => true,
                'approval' => [
                    'canvas_status' => null,
                    'canvassed_by' => null,
                    'canvassed_at' => null,
                    'canvas_assignee_user_id' => null,
                    'suggested_supplier_id' => null,
                    'suggested_supplier_name' => null,
                    'comp_status' => 'pending',
                    'checked_by' => null,
                    'checked_at' => null,
                    'gsd_status' => null,
                    'pres_status' => null,
                ],
            ]);
        }

        sendJson([
            'success' => true,
            'approval' => [
                'canvas_status' => $row['canvas_status'] ?? null,
                'canvassed_by' => $row['canvassed_by'] ?? null,
                'canvassed_at' => $row['canvassed_at'] ?? null,
                'canvas_assignee_user_id' => isset($row['canvas_assignee_user_id']) ? (int) $row['canvas_assignee_user_id'] : null,
                'suggested_supplier_id' => isset($row['suggested_supplier_id']) ? (int) $row['suggested_supplier_id'] : null,
                'suggested_supplier_name' => $row['suggested_supplier_name'] ?? null,
                'comp_status' => (string) ($row['comp_status'] ?? 'pending'),
                'checked_by' => $row['checked_by'],
                'checked_at' => $row['checked_at'],
                'gsd_status' => $row['gsd_status'],
                'pres_status' => $row['pres_status'],
            ],
        ]);
    }

    if ($action === 'list_canvas_assignees') {
        assertGsdOfficer($db);
        $deptId = gsdOfficerOfficeId($db);
        if ($deptId <= 0) {
            sendJson(['success' => false, 'message' => 'You are not assigned to a office.', 'assignees' => []]);
        }
        $uid = (int) $_SESSION['user_id'];
        $stmt = $db->prepare('
            SELECT user_id, Email, role
            FROM user
            WHERE office_id = ?
              AND user_id != ?
            ORDER BY Email ASC
        ');
        $stmt->execute([$deptId, $uid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $assignees = [];
        foreach ($rows as $r) {
            if (!roleMayBeCanvasAssignee((string) ($r['role'] ?? ''))) {
                continue;
            }
            $label = userCanvasAssigneeLabel($r);
            if ($label === '') {
                continue;
            }
            $assignees[] = [
                'user_id' => (int) $r['user_id'],
                'label' => $label,
                'email' => (string) ($r['Email'] ?? ''),
                'role' => (string) ($r['role'] ?? ''),
            ];
        }

        sendJson(['success' => true, 'assignees' => $assignees]);
    }

    if ($action === 'save_canvas_assignee') {
        assertGsdOfficer($db);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $assigneeUserId = (int) ($_POST['canvas_assignee_user_id'] ?? 0);
        if ($requestId <= 0 || $assigneeUserId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request or assignee.']);
        }

        $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $chk->execute([$requestId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        $gsdDeptId = gsdOfficerOfficeId($db);
        if ($gsdDeptId <= 0) {
            sendJson(['success' => false, 'message' => 'You are not assigned to a office.']);
        }

        [$assigneeRow, $err] = loadValidatedCanvasAssignee($db, $gsdDeptId, $assigneeUserId);
        if ($err !== null) {
            sendJson(['success' => false, 'message' => $err]);
        }

        $label = userCanvasAssigneeLabel($assigneeRow);

        $find = $db->prepare(
            'SELECT canvas_status FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $find->execute([$requestId]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);
        ensureCanvassVerificationApprovalRow($db, $requestId);
        if ($existing) {
            $cSt = strtolower(trim((string) ($existing['canvas_status'] ?? 'pending')));
            if ($cSt === 'accept' || $cSt === 'reject') {
                sendJson(['success' => false, 'message' => 'Canvassing is already recorded; assignment cannot be changed.']);
            }
            $up = $db->prepare('
                UPDATE canvass_verification_approval
                SET canvassed_by = ?, canvassed_at = NOW(), canvas_assignee_user_id = ?
                WHERE request_id = ?
            ');
            $up->execute([$label, $assigneeUserId, $requestId]);
        } else {
            $ins = $db->prepare('
                UPDATE canvass_verification_approval
                SET canvas_status = \'pending\', canvassed_by = ?, canvassed_at = NOW(), canvas_assignee_user_id = ?,
                    comp_status = \'pending\', gsd_status = \'pending\', pres_status = NULL
                WHERE request_id = ?
            ');
            $ins->execute([$label, $assigneeUserId, $requestId]);
        }

        sendJson([
            'success' => true,
            'message' => 'Canvassing assignee saved.',
            'canvassed_by' => $label,
            'canvas_assignee_user_id' => $assigneeUserId,
        ]);
    }

    if ($action === 'save_suggested_supplier_item') {
        assertGsdOfficer($db);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $canvassDetailId = (int) ($_POST['canvass_detail_id'] ?? 0);
        $supplierId = (int) ($_POST['suggested_supplier_id'] ?? 0);
        $selectionSource = strtolower(trim((string) ($_POST['selection_source'] ?? 'canvassed')));
        if ($selectionSource !== 'preferred') {
            $selectionSource = 'canvassed';
        }
        if ($requestId <= 0 || $canvassDetailId <= 0 || $supplierId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request, item, or supplier.']);
        }

        $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $chk->execute([$requestId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        [$supplierRow, $supplierErr] = loadValidatedSuggestedSupplierForDetail(
            $db,
            $requestId,
            $canvassDetailId,
            $supplierId,
            $selectionSource
        );
        if ($supplierErr !== null) {
            sendJson(['success' => false, 'message' => $supplierErr]);
        }

        $find = $db->prepare(
            'SELECT gsd_status FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $find->execute([$requestId]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);
        ensureCanvassVerificationApprovalRow($db, $requestId);
        if ($existing) {
            $gSt = strtolower(trim((string) ($existing['gsd_status'] ?? 'pending')));
            if ($gSt === 'accept' || $gSt === 'reject') {
                sendJson(['success' => false, 'message' => 'GSD decision is already recorded; suggested supplier cannot be changed.']);
            }
        } else {
            $ins = $db->prepare('
                UPDATE canvass_verification_approval
                SET canvas_status = \'pending\', comp_status = \'pending\', gsd_status = \'pending\', pres_status = NULL
                WHERE request_id = ?
            ');
            $ins->execute([$requestId]);
        }

        $upsert = $db->prepare('
            INSERT INTO request_approval_suggested_supplier_item (request_id, canvass_detail_id, supplier_id, selection_source, selected_by_user_id, selected_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                supplier_id = VALUES(supplier_id),
                selection_source = VALUES(selection_source),
                selected_by_user_id = VALUES(selected_by_user_id),
                selected_at = NOW()
        ');
        $upsert->execute([$requestId, $canvassDetailId, $supplierId, $selectionSource, (int) $_SESSION['user_id']]);

        sendJson([
            'success' => true,
            'message' => 'Suggested supplier saved for item.',
            'canvass_detail_id' => $canvassDetailId,
            'suggested_supplier_id' => $supplierId,
            'selection_source' => $selectionSource,
            'suggested_supplier_name' => (string) ($supplierRow['supplier_name'] ?? ''),
        ]);
    }

    if ($action === 'clear_suggested_supplier_item') {
        assertGsdOfficer($db);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $canvassDetailId = (int) ($_POST['canvass_detail_id'] ?? 0);
        if ($requestId <= 0 || $canvassDetailId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request or item reference.']);
        }

        $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $chk->execute([$requestId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        $find = $db->prepare(
            'SELECT gsd_status FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $find->execute([$requestId]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $gSt = strtolower(trim((string) ($existing['gsd_status'] ?? 'pending')));
            if ($gSt === 'accept' || $gSt === 'reject') {
                sendJson(['success' => false, 'message' => 'GSD decision is already recorded; suggested supplier cannot be changed.']);
            }
        }

        $del = $db->prepare(
            'DELETE FROM request_approval_suggested_supplier_item
             WHERE request_id = ? AND canvass_detail_id = ?'
        );
        $del->execute([$requestId, $canvassDetailId]);

        sendJson([
            'success' => true,
            'message' => 'Suggested supplier cleared for item.',
            'canvass_detail_id' => $canvassDetailId,
        ]);
    }

    if ($action === 'get_gsd_action_history') {
        assertGsdOfficer($db);
        $requestId = isset($_GET['request_id']) ? (int) $_GET['request_id'] : 0;
        $uid = (int) $_SESSION['user_id'];

        $filterDateRaw = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
        $filterDate = null;
        if ($filterDateRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateRaw)) {
            $dp = explode('-', $filterDateRaw);
            if (count($dp) === 3 && checkdate((int) $dp[1], (int) $dp[2], (int) $dp[0])) {
                $filterDate = $filterDateRaw;
            }
        }
        $dateClause = $filterDate !== null ? ' AND DATE(h.acted_at) = ?' : '';

        $histItems = requisitionSqlHistoryItemsLabel();
        $baseSql = "
            SELECT h.id, h.request_id, h.action, h.acted_at,
                   {$histItems},
                   d.`office_name` AS office_name,
                   u.Email AS requester_email
            FROM gsd_action_history h
            INNER JOIN requisition_item r ON r.request_id = h.request_id
            LEFT JOIN offices d ON d.office_id = r.office_id
            LEFT JOIN user u ON u.user_id = r.user_id
        ";

        if ($requestId > 0) {
            $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
            $chk->execute([$requestId]);
            if (!$chk->fetch(PDO::FETCH_ASSOC)) {
                sendJson(['success' => false, 'message' => 'Request not found.']);
            }
            $sql = $baseSql . '
                WHERE h.request_id = ? AND h.user_id = ?' . $dateClause . '
                ORDER BY h.acted_at DESC, h.id DESC
                LIMIT 100
            ';
            $stmt = $db->prepare($sql);
            $params = [$requestId, $uid];
            if ($filterDate !== null) {
                $params[] = $filterDate;
            }
            $stmt->execute($params);
        } else {
            $sql = $baseSql . '
                WHERE h.user_id = ?' . $dateClause . '
                ORDER BY h.acted_at DESC, h.id DESC
                LIMIT 500
            ';
            $stmt = $db->prepare($sql);
            $params = [$uid];
            if ($filterDate !== null) {
                $params[] = $filterDate;
            }
            $stmt->execute($params);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $history = array_map(static function ($row) {
            $email = (string) ($row['requester_email'] ?? '');
            $requester = $email !== '' ? (explode('@', $email)[0] ?? 'Unknown') : '—';

            return [
                'id' => (int) $row['id'],
                'request_id' => (int) $row['request_id'],
                'action' => (string) $row['action'],
                'acted_at' => $row['acted_at'],
                'item_name' => (string) ($row['item_name'] ?? ''),
                'office_name' => (string) ($row['office_name'] ?? '—'),
                'requester' => $requester,
            ];
        }, $rows);

        sendJson(['success' => true, 'history' => $history]);
    }

    if ($action === 'set_gsd_approval') {
        assertGsdOfficer($db);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $gsdStatus = strtolower(trim((string) ($_POST['gsd_status'] ?? '')));
        if ($requestId <= 0 || !in_array($gsdStatus, ['accept', 'reject', 'pending'], true)) {
            sendJson(['success' => false, 'message' => 'Invalid approval payload.']);
        }

        $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $chk->execute([$requestId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        $gsdDeptId = gsdOfficerOfficeId($db);
        if ($gsdDeptId <= 0) {
            sendJson(['success' => false, 'message' => 'You are not assigned to a office.']);
        }

        $assigneePostId = (int) ($_POST['canvas_assignee_user_id'] ?? 0);

        $verifiedBy = gsdVerifiedByLabel($db);
        $requisitionStatus = ($gsdStatus === 'pending') ? 'Pending' : 'Ongoing';

        $find = $db->prepare(
            'SELECT gsd_status, canvassed_by, canvas_status, canvas_assignee_user_id, suggested_supplier_id, suggested_supplier_name FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $find->execute([$requestId]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);

        $previousGsdStatus = 'pending';
        if ($existing) {
            $prevRaw = strtolower(trim((string) ($existing['gsd_status'] ?? 'pending')));
            if ($prevRaw === '') {
                $prevRaw = 'pending';
            }
            $previousGsdStatus = in_array($prevRaw, ['accept', 'reject', 'pending'], true) ? $prevRaw : 'pending';
        }

        $canvasDone = false;
        if ($existing) {
            $cRaw = strtolower(trim((string) ($existing['canvas_status'] ?? 'pending')));
            $canvasDone = ($cRaw === 'accept' || $cRaw === 'reject');
        }

        $canvassedByValue = null;
        $suggestedSupplierIdValue = null;
        $suggestedSupplierNameValue = null;
        if ($gsdStatus === 'accept') {
            if (!$canvasDone) {
                sendJson(['success' => false, 'message' => 'Canvasser must complete canvassing before GSD can verify.']);
            }
            if ($existing && $canvasDone) {
                $canvassedByValue = trim((string) ($existing['canvassed_by'] ?? ''));
            } elseif ($assigneePostId > 0) {
                [$rowA, $errA] = loadValidatedCanvasAssignee($db, $gsdDeptId, $assigneePostId);
                if ($errA !== null) {
                    sendJson(['success' => false, 'message' => $errA]);
                }
                $canvassedByValue = userCanvasAssigneeLabel($rowA);
            } elseif ($existing) {
                $canvassedByValue = trim((string) ($existing['canvassed_by'] ?? ''));
            } else {
                $canvassedByValue = '';
            }
            if ($canvassedByValue === '') {
                sendJson(['success' => false, 'message' => 'Assign a office staff member for canvassing before verifying.']);
            }
            if (!requestAllCanvassItemsHaveSuggestedSupplier($db, $requestId)) {
                sendJson(['success' => false, 'message' => 'Select a suggested supplier for each canvass item before verifying.']);
            }
        } elseif ($gsdStatus === 'reject' && !$canvasDone && $assigneePostId > 0) {
            [$rowA, $errA] = loadValidatedCanvasAssignee($db, $gsdDeptId, $assigneePostId);
            if ($errA === null) {
                $canvassedByValue = userCanvasAssigneeLabel($rowA);
            }
        }

        if ($gsdStatus === $previousGsdStatus) {
            sendJson([
                'success' => true,
                'message' => 'This decision is already recorded. No changes made.',
                'gsd_status' => $gsdStatus,
                'verified_by' => $gsdStatus === 'pending' ? null : $verifiedBy,
                'requisition_status' => $requisitionStatus,
                'unchanged' => true,
            ]);
        }

        $canvasAssigneeUserIdForDb = null;
        if (!$canvasDone) {
            if ($assigneePostId > 0) {
                $canvasAssigneeUserIdForDb = $assigneePostId;
            } elseif ($existing) {
                $canvasAssigneeUserIdForDb = (int) ($existing['canvas_assignee_user_id'] ?? 0) ?: null;
            }
        }

        $db->beginTransaction();
        try {
            if ($existing) {
                if ($gsdStatus === 'pending') {
                    $up = $db->prepare('
                        UPDATE canvass_verification_approval
                        SET verified_by = NULL,
                            verified_at = NULL,
                            gsd_status = ?
                        WHERE request_id = ?
                    ');
                    $up->execute([$gsdStatus, $requestId]);
                } else {
                    if ($canvassedByValue !== null && !$canvasDone) {
                        $up = $db->prepare('
                            UPDATE canvass_verification_approval
                            SET verified_by = ?,
                                verified_at = NOW(),
                                gsd_status = ?,
                                canvassed_by = ?,
                                canvassed_at = NOW(),
                                canvas_assignee_user_id = ?,
                                suggested_supplier_id = ?,
                                suggested_supplier_name = ?
                            WHERE request_id = ?
                        ');
                        $up->execute([
                            $verifiedBy,
                            $gsdStatus,
                            $canvassedByValue,
                            $canvasAssigneeUserIdForDb,
                            $suggestedSupplierIdValue,
                            $suggestedSupplierNameValue,
                            $requestId,
                        ]);
                    } else {
                        $up = $db->prepare('
                            UPDATE canvass_verification_approval
                            SET verified_by = ?,
                                verified_at = NOW(),
                                gsd_status = ?,
                                suggested_supplier_id = ?,
                            suggested_supplier_name = ?
                            WHERE request_id = ?
                        ');
                        $up->execute([$verifiedBy, $gsdStatus, $suggestedSupplierIdValue, $suggestedSupplierNameValue, $requestId]);
                    }
                }
            } elseif ($gsdStatus !== 'pending') {
                $cbIns = ($canvassedByValue !== null && $canvassedByValue !== '') ? $canvassedByValue : null;
                if ($gsdStatus === 'accept' && ($cbIns === null || $cbIns === '')) {
                    $db->rollBack();
                    sendJson(['success' => false, 'message' => 'Assign a office staff member for canvassing before verifying.']);
                }
                ensureCanvassVerificationApprovalRow($db, $requestId);
                if ($cbIns !== null) {
                    $ins = $db->prepare('
                        UPDATE canvass_verification_approval
                        SET verified_by = ?, verified_at = NOW(), gsd_status = ?, comp_status = \'pending\', pres_status = NULL,
                            canvassed_by = ?, canvassed_at = NOW(), canvas_status = \'pending\', canvas_assignee_user_id = ?,
                            suggested_supplier_id = ?, suggested_supplier_name = ?
                        WHERE request_id = ?
                    ');
                    $ins->execute([$verifiedBy, $gsdStatus, $cbIns, $canvasAssigneeUserIdForDb, $suggestedSupplierIdValue, $suggestedSupplierNameValue, $requestId]);
                } else {
                    $ins = $db->prepare('
                        UPDATE canvass_verification_approval
                        SET verified_by = ?, verified_at = NOW(), gsd_status = ?, comp_status = \'pending\', pres_status = NULL,
                            suggested_supplier_id = ?, suggested_supplier_name = ?
                        WHERE request_id = ?
                    ');
                    $ins->execute([$verifiedBy, $gsdStatus, $suggestedSupplierIdValue, $suggestedSupplierNameValue, $requestId]);
                }
            }

            $updReq = $db->prepare('UPDATE requisition_item SET status = ? WHERE request_id = ?');
            $updReq->execute([$requisitionStatus, $requestId]);

            $logIns = $db->prepare(
                'INSERT INTO gsd_action_history (request_id, user_id, action) VALUES (?, ?, ?)'
            );
            $logIns->execute([$requestId, (int) $_SESSION['user_id'], $gsdStatus]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $msg = 'GSD decision saved.';
        if ($gsdStatus === 'accept') {
            $msg = 'Request verified. Status set to Ongoing.';
        } elseif ($gsdStatus === 'reject') {
            $msg = 'Request rejected at GSD. Status set to Ongoing.';
        } elseif ($gsdStatus === 'pending') {
            $msg = 'GSD decision cleared. Status set to Pending.';
        }

        sendJson([
            'success' => true,
            'message' => $msg,
            'gsd_status' => $gsdStatus,
            'verified_by' => $gsdStatus === 'pending' ? null : $verifiedBy,
            'requisition_status' => $requisitionStatus,
            'canvassed_by' => ($gsdStatus !== 'pending' && $canvassedByValue !== null) ? $canvassedByValue : null,
            'suggested_supplier_id' => $gsdStatus === 'pending' ? null : $suggestedSupplierIdValue,
            'suggested_supplier_name' => $gsdStatus === 'pending' ? null : $suggestedSupplierNameValue,
        ]);
    }

    sendJson(['success' => false, 'message' => 'Invalid action.']);
} catch (Exception $e) {
    sendJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
