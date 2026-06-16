<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/requisition_detail_payload.php';
require_once __DIR__ . '/approval_tables.php';

function sendJson(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function assertInventoryManager(PDO $db): void
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
    $r = strtolower(trim((string)($row['role'] ?? '')));
    if ($r !== 'inventory manager' && $r !== 'inventory_manager') {
        sendJson(['success' => false, 'message' => 'Forbidden']);
    }
}

function assertInventoryManagerOrComptroller(PDO $db): void
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
    $r = strtolower(trim((string)($row['role'] ?? '')));
    $allowed = ['inventory manager', 'inventory_manager', 'comptroller', 'gsd officer', 'president', 'president verifier', 'verifier president', 'president_verifier'];
    if (!in_array($r, $allowed, true)) {
        sendJson(['success' => false, 'message' => 'Forbidden']);
    }
}

/**
 * Read-only requisition form payload (get_request_detail_view): privileged roles plus
 * canvas workspace users only when GSD assigned them (canvass_verification_approval assignee fields).
 */
function assertRequestDetailViewAuth(PDO $db): void
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
    $allowed = [
        'inventory manager', 'inventory_manager', 'comptroller', 'gsd officer',
        'president', 'president verifier', 'verifier president', 'president_verifier',
        'employee', 'user', 'laboratory manager', 'canvasser',
    ];
    if (!in_array($r, $allowed, true)) {
        sendJson(['success' => false, 'message' => 'Forbidden']);
    }
}

/** Canvas workspace roles use admin detail API only from the canvasser form; must match GSD assignee. */
function isCanvasWorkspaceViewerRole(string $roleLc): bool
{
    return in_array($roleLc, ['employee', 'user', 'laboratory manager', 'canvasser'], true);
}

function adminViewerEmailLocalPart(PDO $db, int $userId): string
{
    $stmt = $db->prepare('SELECT Email FROM user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $email = (string) ($row['Email'] ?? '');

    return $email !== '' ? strtolower(explode('@', $email)[0] ?? $email) : '';
}

function adminCanvasAssigneeMatchesSession(PDO $db, ?array $raRow, int $sessionUid): bool
{
    if (!$raRow) {
        return false;
    }
    $aid = (int) ($raRow['canvas_assignee_user_id'] ?? 0);
    if ($aid > 0) {
        return $aid === $sessionUid;
    }
    $local = adminViewerEmailLocalPart($db, $sessionUid);
    $by = strtolower(trim((string) ($raRow['canvassed_by'] ?? '')));

    return $local !== '' && $by === $local;
}

function assertCanvasWorkspaceAdminDetailAllowed(PDO $db, int $requestId, int $sessionUid): void
{
    $stmt = $db->prepare(
        'SELECT canvas_assignee_user_id, canvassed_by FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
    );
    $stmt->execute([$requestId]);
    $raRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!adminCanvasAssigneeMatchesSession($db, $raRow, $sessionUid)) {
        sendJson(['success' => false, 'message' => 'You can only open requisitions assigned to you for canvassing.']);
    }
}

try {
    $db = Database::connect();
    ensureRequisitionCanvassSubmissionColumn($db);
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'bootstrap') {
        assertRequestDetailViewAuth($db);
        $sessionUid = (int) $_SESSION['user_id'];
        $userStmt = $db->prepare(
            'SELECT u.user_id, u.Email, u.role, u.office_id, d.office_name AS office_name
             FROM user u
             LEFT JOIN offices d ON d.office_id = u.office_id
             WHERE u.user_id = ?'
        );
        $userStmt->execute([$sessionUid]);
        $bootstrapUser = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'user_id' => $sessionUid,
            'Email' => '',
            'role' => '',
            'office_id' => null,
            'office_name' => '',
        ];

        $deptStmt = $db->query('SELECT office_id, office_name AS office_name FROM offices ORDER BY office_name ASC');
        $offices = $deptStmt ? $deptStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $facStmt = $db->query(
            'SELECT facility_id, office_id, building, room, laboratory, code
             FROM facilities ORDER BY building ASC, room ASC'
        );
        $facilities = $facStmt ? $facStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $newFacilityIds = [];
        $newFacStmt = $db->query('SELECT facility_id FROM facilities WHERE date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        if ($newFacStmt) {
            foreach ($newFacStmt->fetchAll(PDO::FETCH_COLUMN) as $nfId) {
                $newFacilityIds[] = (int) $nfId;
            }
        }
        foreach ($facilities as &$facility) {
            $facility['is_new'] = in_array((int) $facility['facility_id'], $newFacilityIds, true);
        }
        unset($facility);

        $supplierStmt = $db->query(
            'SELECT supplier_id, supplier_name, supplier_image, contact_person, phone_number, email, address, city, country, postal_code
             FROM suppliers ORDER BY supplier_name ASC'
        );
        $suppliers = $supplierStmt ? $supplierStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        try {
            $itemStmt = $db->query('SELECT item_id, item_name, brand, model, category FROM items ORDER BY item_name ASC');
            $items = $itemStmt ? $itemStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $itemStmt = $db->query('SELECT item_id, item_name, brand, category FROM items ORDER BY item_name ASC');
            $items = $itemStmt ? $itemStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($items as &$itRow) {
                $itRow['model'] = null;
            }
            unset($itRow);
        }

        sendJson([
            'success' => true,
            'user' => $bootstrapUser,
            'offices' => $offices,
            'facilities' => $facilities,
            'suppliers' => $suppliers,
            'items' => $items,
        ]);
    }

    if ($action === 'get_request_detail_view') {
        assertRequestDetailViewAuth($db);
        $requestId = (int)($_GET['request_id'] ?? 0);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }

        $sessionUid = (int) $_SESSION['user_id'];
        $viewerRoleStmt = $db->prepare('SELECT role FROM user WHERE user_id = ?');
        $viewerRoleStmt->execute([$sessionUid]);
        $viewerRoleLc = strtolower(trim((string) ($viewerRoleStmt->fetchColumn() ?: '')));
        if (isCanvasWorkspaceViewerRole($viewerRoleLc)) {
            assertCanvasWorkspaceAdminDetailAllowed($db, $requestId, $sessionUid);
        }

        $anchorStmt = $db->prepare('SELECT * FROM requisition_item WHERE request_id = ?');
        $anchorStmt->execute([$requestId]);
        $anchor = $anchorStmt->fetch(PDO::FETCH_ASSOC);
        if (!$anchor) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        $rows = requisitionFetchDetailMatrixRows($db, $requestId);

        if (count($rows) === 0) {
            sendJson(['success' => false, 'message' => 'Could not load requisition rows.']);
        }

        $payload = buildRequisitionDetailPayload($anchor, $rows, $requestId);
        if ($payload === null) {
            sendJson(['success' => false, 'message' => 'Could not load requisition rows.']);
        }

        requisitionAttachApprovalToPayload($db, $requestId, $payload);

        $ownerStmt = $db->prepare('SELECT Email, role, contact_number FROM user WHERE user_id = ?');
        $ownerStmt->execute([(int) $anchor['user_id']]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
        $emailOwn = (string) ($owner['Email'] ?? '');
        $payload['requester_display'] = $emailOwn !== '' ? (explode('@', $emailOwn)[0] ?? '—') : '—';
        $payload['requester_role'] = (string) ($owner['role'] ?? '');
        $payload['requester_email'] = $emailOwn;
        $payload['requester_contact'] = (string) ($owner['contact_number'] ?? '');
        sendJson($payload);
    }

    if ($action === 'get_requisition_review') {
        assertInventoryManagerOrComptroller($db);
        $requestId = (int) ($_GET['request_id'] ?? 0);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }
        $stmt = $db->prepare('SELECT requisition_status, requisition_note, requisition_reviewed_by, requisition_reviewed_at FROM requisition_form_approval WHERE request_id = ? LIMIT 1');
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            sendJson([
                'success' => true,
                'review' => [
                    'requisition_status' => 'pending',
                    'requisition_note' => null,
                    'requisition_reviewed_by' => null,
                    'requisition_reviewed_at' => null,
                ],
            ]);
        }
        sendJson(['success' => true, 'review' => $row]);
    }

    assertInventoryManager($db);

    if ($action === 'list_requests') {
        $agg = requisitionSqlSelectListAggregates();
        $stmt = $db->query("
            SELECT r.request_id, r.created_at, r.status, r.message,
                   u.Email, d.`office_name` AS office_name, rfa.requisition_status, rfa.requisition_note, cva.canvas_status, cva.gsd_status,
                   COALESCE(cva.comp_status, 'pending') AS comp_status,
                   COALESCE(cva.pres_status, 'pending') AS pres_status,
                   COALESCE(pra.pr_inv_status, 'pending') AS pr_inv_status,
                   COALESCE(pra.pr_pres_status, 'pending') AS pr_pres_status,
                   po.id AS purchase_order_id,
                   po.po_number AS purchase_order_number,
                   po.status AS purchase_order_status,
                   {$agg}
            FROM requisition_item r
            LEFT JOIN user u ON u.user_id = r.user_id
            LEFT JOIN offices d ON d.office_id = r.office_id
            LEFT JOIN requisition_form_approval rfa ON rfa.request_id = r.request_id
            LEFT JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
            LEFT JOIN purchase_requisition_approval pra ON pra.request_id = r.request_id
            LEFT JOIN purchase_orders po ON po.requisition_id = r.request_id AND po.deleted_at IS NULL
            WHERE r.submission_status = 'submitted'
            ORDER BY r.created_at DESC, r.request_id DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $requests = array_map(static function ($row) {
            $email = (string)($row['Email'] ?? '');
            $requester = explode('@', $email)[0] ?? 'Unknown';

            return [
                'id' => 'REQ-' . str_pad((string)$row['request_id'], 6, '0', STR_PAD_LEFT),
                'request_id' => (int)$row['request_id'],
                'date' => $row['created_at'],
                'items' => requisitionExplodePipeOrDefault($row['items_concat'] ?? null, '—'),
                'suppliers' => requisitionExplodePipeOrDefault($row['suppliers_concat'] ?? null, 'N/A'),
                'status' => $row['status'] ?? 'Pending',
                'message' => $row['message'] ?? '',
                'requisition_status' => (string)($row['requisition_status'] ?? 'pending'),
                'requisition_note' => (string)($row['requisition_note'] ?? ''),
                'canvas_status' => (string)($row['canvas_status'] ?? 'pending'),
                'gsd_status' => (string)($row['gsd_status'] ?? 'pending'),
                'comp_status' => (string)($row['comp_status'] ?? 'pending'),
                'pres_status' => (string)($row['pres_status'] ?? 'pending'),
                'pr_inv_status' => (string)($row['pr_inv_status'] ?? 'pending'),
                'pr_pres_status' => (string)($row['pr_pres_status'] ?? 'pending'),
                'purchase_order_id' => !empty($row['purchase_order_id']) ? (int) $row['purchase_order_id'] : null,
                'purchase_order_number' => (string)($row['purchase_order_number'] ?? ''),
                'purchase_order_status' => (string)($row['purchase_order_status'] ?? ''),
                'requester' => $requester,
                'office' => $row['office_name'] ?? '—',
            ];
        }, $rows);
        sendJson(['success' => true, 'requests' => $requests]);
    }

    if ($action === 'set_requisition_review') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $requisitionStatus = strtolower(trim((string)($_POST['requisition_status'] ?? '')));
        $requisitionNote = trim((string)($_POST['requisition_note'] ?? ''));
        if ($requestId <= 0 || !in_array($requisitionStatus, ['accept', 'reject', 'pending'], true)) {
            sendJson(['success' => false, 'message' => 'Invalid review payload.']);
        }
        if ($requisitionStatus === 'reject' && $requisitionNote === '') {
            sendJson(['success' => false, 'message' => 'Please add a rejection reason.']);
        }
        $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $chk->execute([$requestId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }
        $reviewer = adminViewerEmailLocalPart($db, (int)$_SESSION['user_id']);
        $statusForReq = $requisitionStatus === 'accept' ? 'Ongoing' : 'Pending';

        ensureRequisitionFormApprovalRow($db, $requestId);

        $db->beginTransaction();
        try {
            $up = $db->prepare('UPDATE requisition_form_approval SET requisition_status = ?, requisition_note = ?, requisition_reviewed_by = ?, requisition_reviewed_at = ? WHERE request_id = ?');
            $up->execute([
                $requisitionStatus,
                $requisitionNote !== '' ? $requisitionNote : null,
                $requisitionStatus === 'pending' ? null : $reviewer,
                $requisitionStatus === 'pending' ? null : date('Y-m-d H:i:s'),
                $requestId,
            ]);
            $updReq = $db->prepare('UPDATE requisition_item SET status = ? WHERE request_id = ?');
            $updReq->execute([$statusForReq, $requestId]);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        sendJson([
            'success' => true,
            'message' => $requisitionStatus === 'accept'
                ? 'Requisition accepted. Canvass form is now available.'
                : ($requisitionStatus === 'reject' ? 'Requisition rejected.' : 'Requisition review reset.'),
            'requisition_status' => $requisitionStatus,
            'requisition_status_label' => $statusForReq,
        ]);
    }

    if ($action === 'delete_request') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }

        $deleteStmt = $db->prepare('DELETE FROM requisition_item WHERE request_id = ?');
        $deleteStmt->execute([$requestId]);

        if ($deleteStmt->rowCount() === 0) {
            sendJson(['success' => false, 'message' => 'Request not found or already deleted.']);
        }

        sendJson(['success' => true, 'message' => 'Request deleted successfully.']);
    }

    if ($action === 'update_status') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? ''));
        if ($requestId <= 0 || $status === '') {
            sendJson(['success' => false, 'message' => 'Invalid update payload.']);
        }

        $allowedStatus = ['Pending', 'Ongoing', 'Completed'];
        if (!in_array($status, $allowedStatus, true)) {
            sendJson(['success' => false, 'message' => 'Invalid status value.']);
        }

        $checkStmt = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $checkStmt->execute([$requestId]);
        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        $updateStmt = $db->prepare('UPDATE requisition_item SET status = ? WHERE request_id = ?');
        $updateStmt->execute([$status, $requestId]);

        sendJson(['success' => true, 'message' => 'Status updated.']);
    }

    sendJson(['success' => false, 'message' => 'Invalid action.']);
} catch (Exception $e) {
    sendJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
