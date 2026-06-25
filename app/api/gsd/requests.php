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
 * Validate that a canvassed quote exists in requisition_line_quotes for the given line + supplier.
 *
 * @return array{0: array|null, 1: string|null} [supplier row or null, error message]
 */
function loadValidatedCanvassedSuggestedSupplierForDetail(PDO $db, int $requestId, int $lineId, int $supplierId): array
{
    if ($supplierId <= 0) {
        return [null, 'Select a supplier before saving.'];
    }
    if ($lineId <= 0) {
        return [null, 'Invalid line reference.'];
    }
    $stmt = $db->prepare("
        SELECT s.supplier_id, s.supplier_name
        FROM suppliers s
        WHERE s.supplier_id = ?
          AND EXISTS (
              SELECT 1
              FROM requisition_line_quotes rlq
              INNER JOIN requisition_line rl ON rl.requisition_line_id = rlq.requisition_line_id
              WHERE rl.request_id = ?
                AND rlq.requisition_line_id = ?
                AND rlq.supplier_id = s.supplier_id
                AND rlq.quote_type = 'canvassed'
                AND rlq.quoted_unit_price IS NOT NULL
          )
        LIMIT 1
    ");
    $stmt->execute([$supplierId, $requestId, $lineId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [null, 'Selected supplier must come from quoted suppliers in the canvassed matrix.'];
    }

    return [$row, null];
}

/**
 * Validate that a preferred quote exists in requisition_line_quotes for the given line + supplier.
 *
 * @return array{0: array|null, 1: string|null} [supplier row or null, error message]
 */
function loadValidatedPreferredSuggestedSupplierForDetail(PDO $db, int $requestId, int $lineId, int $supplierId): array
{
    if ($supplierId <= 0) {
        return [null, 'Select a supplier before saving.'];
    }
    if ($lineId <= 0) {
        return [null, 'Invalid line reference.'];
    }
    $stmt = $db->prepare("
        SELECT s.supplier_id, s.supplier_name
        FROM suppliers s
        WHERE s.supplier_id = ?
          AND EXISTS (
              SELECT 1
              FROM requisition_line_quotes rlq
              WHERE rlq.requisition_line_id = ?
                AND rlq.supplier_id = s.supplier_id
                AND rlq.quote_type = 'preferred'
                AND rlq.quoted_unit_price IS NOT NULL
          )
        LIMIT 1
    ");
    $stmt->execute([$supplierId, $lineId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [null, 'Selected preferred supplier must have a quoted price for this line item.'];
    }

    return [$row, null];
}

function loadValidatedSuggestedSupplierForDetail(
    PDO $db,
    int $requestId,
    int $lineId,
    int $supplierId,
    string $selectionSource = 'canvassed'
): array {
    if ($selectionSource === 'preferred') {
        return loadValidatedPreferredSuggestedSupplierForDetail($db, $requestId, $lineId, $supplierId);
    }

    return loadValidatedCanvassedSuggestedSupplierForDetail($db, $requestId, $lineId, $supplierId);
}

/**
 * True when every line that has at least one supplier quote also has a GSD award.
 */
function requestAllCanvassItemsHaveSuggestedSupplier(PDO $db, int $requestId): bool
{
    $totalStmt = $db->prepare(
        "SELECT COUNT(DISTINCT rl.requisition_line_id)
         FROM requisition_line rl
         WHERE rl.request_id = ?
           AND (rl.deleted_at IS NULL OR rl.deleted_at = '')
           AND EXISTS (
               SELECT 1
               FROM requisition_line_quotes rlq
               WHERE rlq.requisition_line_id = rl.requisition_line_id
           )"
    );
    $totalStmt->execute([$requestId]);
    $total = (int) $totalStmt->fetchColumn();
    if ($total <= 0) {
        return false;
    }

    $selectedStmt = $db->prepare(
        'SELECT COUNT(*)
         FROM requisition_line_awards rla
         INNER JOIN requisition_line rl ON rl.requisition_line_id = rla.requisition_line_id
         WHERE rl.request_id = ?'
    );
    $selectedStmt->execute([$requestId]);
    $selected = (int) $selectedStmt->fetchColumn();

    return $selected >= $total;
}

try {
    $db = Database::connect();
    ensureRequisitionCanvassSubmissionColumn($db);
    ensureSuggestedSupplierSelectionSourceColumn($db);
    ensureRequisitionLineQuotesTable($db);
    ensureRequisitionLineAwardsTable($db);
    ensureRequisitionLineQuotesGsdColumns($db);
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
            AND LOWER(TRIM(COALESCE(cva.pres_status, ''))) NOT IN ('accept')
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
                'updated_at' => $row['created_at'],
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

    if ($action === 'get_gsd_review_view') {
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

        $rsStmt = $db->prepare(
            "SELECT LOWER(TRIM(COALESCE(requisition_status,''))) FROM requisition_form_approval WHERE request_id = ? LIMIT 1"
        );
        $rsStmt->execute([$requestId]);
        $rs = strtolower(trim((string) ($rsStmt->fetchColumn() ?: '')));
        if ($rs !== 'accept') {
            sendJson(['success' => false, 'message' => 'Open after the inventory manager accepts the requisition.']);
        }

        require_once __DIR__ . '/../../helpers/gsd_canvass_outcome.php';
        $payload = cwirmsBuildGsdCanvassOutcomeView($db, $requestId);
        sendJson(array_merge(['success' => true], $payload));
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
        $supplierId = (int) ($_POST['suggested_supplier_id'] ?? 0);
        $selectionSource = strtolower(trim((string) ($_POST['selection_source'] ?? 'canvassed')));
        if ($selectionSource !== 'preferred') {
            $selectionSource = 'canvassed';
        }

        $lineId = (int) ($_POST['requisition_line_id'] ?? 0);

        if ($requestId <= 0 || $lineId <= 0 || $supplierId <= 0) {
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
            $lineId,
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

        // Resolve the matching quote_id for the FK on requisition_line_awards.
        $quoteStmt = $db->prepare(
            "SELECT quote_id FROM requisition_line_quotes
             WHERE requisition_line_id = ? AND supplier_id = ? AND quote_type = ?
             LIMIT 1"
        );
        $quoteStmt->execute([$lineId, $supplierId, $selectionSource]);
        $quoteId = $quoteStmt->fetchColumn() ?: null;

        // Use the line's requested quantity as the initial awarded_qty (comptroller will adjust later).
        $lineQtyStmt = $db->prepare(
            'SELECT COALESCE(quantity, 1) FROM requisition_line WHERE requisition_line_id = ? LIMIT 1'
        );
        $lineQtyStmt->execute([$lineId]);
        $requestedQty = max(1, (int) ($lineQtyStmt->fetchColumn() ?: 1));

        // Upsert into canonical award table (uq_line_award unique key on requisition_line_id).
        $upsert = $db->prepare("
            INSERT INTO requisition_line_awards
                (requisition_line_id, quote_id, supplier_id, selection_source, awarded_qty, awarded_by_user_id, awarded_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                quote_id           = VALUES(quote_id),
                supplier_id        = VALUES(supplier_id),
                selection_source   = VALUES(selection_source),
                awarded_qty        = VALUES(awarded_qty),
                awarded_by_user_id = VALUES(awarded_by_user_id),
                awarded_at         = NOW()
        ");
        $upsert->execute([$lineId, $quoteId ?: null, $supplierId, $selectionSource, $requestedQty, (int) $_SESSION['user_id']]);

        sendJson([
            'success' => true,
            'message' => 'Suggested supplier saved for item.',
            'requisition_line_id' => $lineId,
            'canvass_detail_id' => $lineId,  // backward-compat alias
            'suggested_supplier_id' => $supplierId,
            'selection_source' => $selectionSource,
            'suggested_supplier_name' => (string) ($supplierRow['supplier_name'] ?? ''),
        ]);
    }

    if ($action === 'clear_suggested_supplier_item') {
        assertGsdOfficer($db);
        $requestId = (int) ($_POST['request_id'] ?? 0);

        $lineId = (int) ($_POST['requisition_line_id'] ?? 0);

        if ($requestId <= 0 || $lineId <= 0) {
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
            'DELETE FROM requisition_line_awards WHERE requisition_line_id = ?'
        );
        $del->execute([$lineId]);

        sendJson([
            'success' => true,
            'message' => 'Suggested supplier cleared for item.',
            'requisition_line_id' => $lineId,
            'canvass_detail_id' => $lineId,  // backward-compat alias
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
        $dateClause = $filterDate !== null ? ' AND DATE(h.verified_at) = ?' : '';

        $histItems = requisitionSqlHistoryItemsLabel();
        $baseSql = "
            SELECT h.request_id AS id, h.request_id, h.gsd_status AS action, h.verified_at AS acted_at,
                   {$histItems},
                   d.`office_name` AS office_name,
                   u.Email AS requester_email
            FROM canvass_verification_approval h
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
                WHERE h.request_id = ?
                AND h.verified_by = (SELECT full_name FROM user WHERE user_id = ?)' . $dateClause . '
                ORDER BY h.verified_at DESC
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
                WHERE h.verified_by = (SELECT full_name FROM user WHERE user_id = ?)' . $dateClause . '
                ORDER BY h.verified_at DESC
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

    if ($action === 'add_canvass_quote') {
        assertGsdOfficer($db);
        $requestId    = (int) ($_POST['request_id'] ?? 0);
        $lineId       = (int) ($_POST['requisition_line_id'] ?? 0);
        $supplierId   = (int) ($_POST['supplier_id'] ?? 0);
        $priceRaw     = trim((string) ($_POST['unit_price'] ?? ''));
        $benefits     = trim((string) ($_POST['benefits'] ?? ''));
        $discountRaw  = trim((string) ($_POST['discount_percent'] ?? ''));
        $canvasserName = trim((string) ($_POST['canvasser_name'] ?? ''));

        if ($requestId <= 0 || $lineId <= 0 || $supplierId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request, line, or supplier.']);
        }
        if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw < 0) {
            sendJson(['success' => false, 'message' => 'Enter a valid unit price (≥ 0).']);
        }
        $price = round((float) $priceRaw, 2);
        $discountPercent = ($discountRaw !== '' && is_numeric($discountRaw)) ? min(100.0, max(0.0, (float) $discountRaw)) : null;

        $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $chk->execute([$requestId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }
        $lineChk = $db->prepare(
            'SELECT requisition_line_id FROM requisition_line WHERE requisition_line_id = ? AND request_id = ? LIMIT 1'
        );
        $lineChk->execute([$lineId, $requestId]);
        if (!$lineChk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Line item not found on this request.']);
        }
        $supChk = $db->prepare('SELECT supplier_id FROM suppliers WHERE supplier_id = ? LIMIT 1');
        $supChk->execute([$supplierId]);
        if (!$supChk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Supplier not found.']);
        }

        $upsert = $db->prepare(
            "INSERT INTO requisition_line_quotes
                (requisition_line_id, supplier_id, quoted_unit_price, quote_type,
                 submitted_by_user_id, benefits, canvasser_name, discount_percent)
             VALUES (?, ?, ?, 'canvassed', ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                quoted_unit_price = VALUES(quoted_unit_price),
                benefits          = VALUES(benefits),
                canvasser_name    = VALUES(canvasser_name),
                discount_percent  = VALUES(discount_percent),
                submitted_by_user_id = VALUES(submitted_by_user_id),
                updated_at        = NOW()"
        );
        $upsert->execute([
            $lineId,
            $supplierId,
            $price,
            (int) $_SESSION['user_id'],
            $benefits !== '' ? $benefits : null,
            $canvasserName !== '' ? $canvasserName : null,
            $discountPercent,
        ]);

        sendJson(['success' => true, 'message' => 'Canvass quote saved.']);
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
        $gsdOfficerName = trim((string) ($_POST['gsd_officer_name'] ?? ''));
        if ($gsdStatus === 'accept') {
            if (!requestAllCanvassItemsHaveSuggestedSupplier($db, $requestId)) {
                sendJson([
                    'success' => false,
                    'message' => 'Select a supplier quote for every line item before verifying. Each selection is saved to requisition_line_awards when you pick a supplier in Section C.',
                ]);
            }
            // Prefer the typed GSD officer name; fall back to existing canvassed_by, then session label.
            if ($gsdOfficerName !== '') {
                $canvassedByValue = $gsdOfficerName;
            } elseif ($existing) {
                $canvassedByValue = trim((string) ($existing['canvassed_by'] ?? ''));
            }
            if (!$canvassedByValue) {
                $canvassedByValue = $verifiedBy;
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

        $db->beginTransaction();
        try {
            ensureCanvassVerificationApprovalRow($db, $requestId);
            if ($gsdStatus === 'pending') {
                $up = $db->prepare(
                    'UPDATE canvass_verification_approval
                     SET verified_by = NULL, verified_at = NULL, gsd_status = ?
                     WHERE request_id = ?'
                );
                $up->execute([$gsdStatus, $requestId]);
            } else {
                $up = $db->prepare(
                    'UPDATE canvass_verification_approval
                     SET verified_by = ?, verified_at = NOW(), gsd_status = ?,
                         canvassed_by = COALESCE(canvassed_by, ?),
                         comp_status = COALESCE(comp_status, \'pending\'),
                         suggested_supplier_id = ?, suggested_supplier_name = ?
                     WHERE request_id = ?'
                );
                $up->execute([
                    $verifiedBy,
                    $gsdStatus,
                    $canvassedByValue,
                    $suggestedSupplierIdValue,
                    $suggestedSupplierNameValue,
                    $requestId,
                ]);
            }

            $updReq = $db->prepare('UPDATE requisition_item SET status = ? WHERE request_id = ?');
            $updReq->execute([$requisitionStatus, $requestId]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $msg = 'GSD decision saved.';
        if ($gsdStatus === 'accept') {
            $msg = 'Canvass approved. Forwarding to Comptroller.';
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

    if ($action === 'remove_canvass_supplier') {
        assertGsdOfficer($db);
        $requestId  = (int) ($_POST['request_id'] ?? 0);
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        if ($requestId <= 0 || $supplierId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request or supplier.']);
        }
        $lineStmt = $db->prepare(
            "SELECT requisition_line_id FROM requisition_line WHERE request_id = ? AND (deleted_at IS NULL OR deleted_at = '')"
        );
        $lineStmt->execute([$requestId]);
        $lineIds = array_column($lineStmt->fetchAll(PDO::FETCH_ASSOC), 'requisition_line_id');
        if (!empty($lineIds)) {
            $ph = implode(',', array_fill(0, count($lineIds), '?'));
            $db->prepare("DELETE FROM requisition_line_quotes WHERE requisition_line_id IN ($ph) AND supplier_id = ? AND quote_type = 'canvassed'")->execute([...$lineIds, $supplierId]);
            $db->prepare("DELETE FROM requisition_line_awards WHERE requisition_line_id IN ($ph) AND supplier_id = ?")->execute([...$lineIds, $supplierId]);
        }
        sendJson(['success' => true, 'message' => 'Supplier removed.']);
    }

    if ($action === 'register_supplier') {
        assertGsdOfficer($db);
        require_once __DIR__ . '/../../helpers/supplier.php';
        ensureSupplierTinColumn($db);
        $supplierName  = trim((string) ($_POST['supplier_name'] ?? ''));
        $contactPerson = trim((string) ($_POST['contact_person'] ?? ''));
        $phoneNumber   = trim((string) ($_POST['phone_number'] ?? ''));
        $tin           = trim((string) ($_POST['tin'] ?? ''));
        $address       = trim((string) ($_POST['address'] ?? ''));
        if ($supplierName === '') {
            sendJson(['success' => false, 'message' => 'Supplier name is required.']);
        }
        $chk = $db->prepare('SELECT supplier_id FROM suppliers WHERE LOWER(supplier_name) = LOWER(?) LIMIT 1');
        $chk->execute([$supplierName]);
        if ($chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'A supplier with this name already exists. Select it from the list.']);
        }
        $ins = $db->prepare(
            'INSERT INTO suppliers (supplier_name, contact_person, phone_number, tin, address, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $supplierName,
            $contactPerson !== '' ? $contactPerson : null,
            $phoneNumber   !== '' ? $phoneNumber   : null,
            $tin           !== '' ? $tin           : null,
            $address       !== '' ? $address       : null,
            'Active',
        ]);
        $newId = (int) $db->lastInsertId();
        sendJson([
            'success'      => true,
            'supplier_id'  => $newId,
            'supplier_name'=> $supplierName,
            'contact_person' => $contactPerson,
            'phone_number' => $phoneNumber,
            'tin'          => $tin,
            'address'      => $address,
            'supplier_image' => null,
        ]);
    }

    if ($action === 'save_draft') {
        assertGsdOfficer($db);
        $requestId     = (int) ($_POST['request_id']     ?? 0);
        $canvasserName = trim((string) ($_POST['canvasser_name'] ?? ''));
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request.']);
        }
        if ($canvasserName === '') {
            sendJson(['success' => false, 'message' => 'Canvasser name is required.']);
        }
        $db->prepare(
            "UPDATE requisition_line_quotes rlq
             INNER JOIN requisition_line rl ON rl.requisition_line_id = rlq.requisition_line_id
             SET rlq.canvasser_name = ?
             WHERE rl.request_id = ? AND rlq.quote_type = 'canvassed'"
        )->execute([$canvasserName, $requestId]);
        sendJson(['success' => true, 'message' => 'Draft saved.']);
    }

    if ($action === 'remove_canvass_line') {
        assertGsdOfficer($db);
        $requestId  = (int) ($_POST['request_id'] ?? 0);
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $lineId     = (int) ($_POST['requisition_line_id'] ?? 0);
        if ($requestId <= 0 || $supplierId <= 0 || $lineId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request, supplier, or line.']);
        }
        $db->prepare(
            "DELETE FROM requisition_line_quotes WHERE requisition_line_id = ? AND supplier_id = ? AND quote_type = 'canvassed'"
        )->execute([$lineId, $supplierId]);
        $db->prepare(
            'DELETE FROM requisition_line_awards WHERE requisition_line_id = ? AND supplier_id = ?'
        )->execute([$lineId, $supplierId]);
        sendJson(['success' => true, 'message' => 'Line removed.']);
    }

    sendJson(['success' => false, 'message' => 'Invalid action.']);
} catch (Exception $e) {
    sendJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
