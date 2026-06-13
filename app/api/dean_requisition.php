<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/requisition_detail_payload.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function sendJson($payload) {
    echo json_encode($payload);
    exit;
}

/**
 * @return array{is_department: bool, user_id: int, office_id: ?int}
 */
function resolveDeanRequestScope(PDO $db, int $userId): array
{
    $stmt = $db->prepare('SELECT role, office_id FROM user WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['is_department' => false, 'user_id' => $userId, 'office_id' => null];
    }

    $isDepartment = strtolower(trim((string) ($row['role'] ?? ''))) === 'department';
    $officeId = (int) ($row['office_id'] ?? 0);

    return [
        'is_department' => $isDepartment,
        'user_id' => $userId,
        'office_id' => $officeId > 0 ? $officeId : null,
    ];
}

function fetchOwnedRequisition(PDO $db, int $requestId, array $scope): ?array
{
    if ($requestId <= 0) {
        return null;
    }

    if (!empty($scope['is_department'])) {
        $officeId = (int) ($scope['office_id'] ?? 0);
        if ($officeId <= 0) {
            return null;
        }
        $stmt = $db->prepare('SELECT * FROM requisition_item WHERE request_id = ? AND office_id = ? LIMIT 1');
        $stmt->execute([$requestId, $officeId]);
    } else {
        $stmt = $db->prepare('SELECT * FROM requisition_item WHERE request_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$requestId, (int) $scope['user_id']]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function deleteOwnedRequisition(PDO $db, int $requestId, array $scope): int
{
    if (!empty($scope['is_department'])) {
        $officeId = (int) ($scope['office_id'] ?? 0);
        if ($officeId <= 0) {
            return 0;
        }
        $stmt = $db->prepare('DELETE FROM requisition_item WHERE request_id = ? AND office_id = ?');
        $stmt->execute([$requestId, $officeId]);
    } else {
        $stmt = $db->prepare('DELETE FROM requisition_item WHERE request_id = ? AND user_id = ?');
        $stmt->execute([$requestId, (int) $scope['user_id']]);
    }

    return $stmt->rowCount();
}

/**
 * @param array<int, mixed> $items
 * @param array<int, mixed> $suppliers
 */
function insertRequisitionBatch(PDO $db, int $userId, int $officeId, int $facilityId, string $requestDate, string $message, string $purpose, string $urgentNote, array $items, array $suppliers, string $submissionStatus = 'draft'): void
{
    $msgVal = $message !== '' ? $message : null;
    $purposeVal = $purpose !== '' ? $purpose : null;
    $urgentNoteVal = $urgentNote !== '' ? $urgentNote : null;
    // Keep the requested date, but stamp with actual current time (not 12:00 AM).
    $createdAt = $requestDate . ' ' . date('H:i:s');
    $stmt = $db->prepare("INSERT INTO requisition_item (user_id, office_id, facility_id, status, created_at, message, purpose, urgent_note, submission_status) VALUES (?, ?, ?, 'Pending', ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $officeId, $facilityId, $createdAt, $msgVal, $purposeVal, $urgentNoteVal, $submissionStatus]);
    $requestId = (int) $db->lastInsertId();
    requisitionInsertLinesForRequest($db, $requestId, $items, is_array($suppliers) ? $suppliers : []);
}

try {
    $db = Database::connect();
    $requestScope = resolveDeanRequestScope($db, (int) $_SESSION['user_id']);
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'bootstrap') {
        $userStmt = $db->prepare("SELECT u.user_id, u.Email, u.role, u.office_id, d.`office_name` AS office_name
            FROM user u
            LEFT JOIN offices d ON d.office_id = u.office_id
            WHERE u.user_id = ?");
        $userStmt->execute([$_SESSION['user_id']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        $deptStmt = $db->query("SELECT office_id, `office_name` AS office_name FROM offices ORDER BY `office_name` ASC");
        $offices = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

        $facStmt = $db->query("SELECT facility_id, office_id, building, room, laboratory, code
            FROM facilities ORDER BY building ASC, room ASC");
        $facilities = $facStmt->fetchAll(PDO::FETCH_ASSOC);

        // Facilities marked as new can be configured here.
        // Add facility_id values to this array to show a "New" badge in the dean requisition form.
      $newFacilityIds = [];
        $newFacStmt = $db->query("SELECT facility_id FROM facilities WHERE date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        foreach ($newFacStmt->fetchAll(PDO::FETCH_COLUMN) as $nfId) {
            $newFacilityIds[] = (int) $nfId;
        }
        foreach ($facilities as &$facility) {
            $facility['is_new'] = in_array((int) $facility['facility_id'], $newFacilityIds, true);
        }
        unset($facility);

        $supplierStmt = $db->query("SELECT supplier_id, supplier_name, supplier_image, contact_person, phone_number, email, address, city, country, postal_code FROM suppliers ORDER BY supplier_name ASC");
        $suppliers = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);

        try {
            $itemStmt = $db->query("SELECT item_id, item_name, brand, model, category FROM items ORDER BY item_name ASC");
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $itemStmt = $db->query("SELECT item_id, item_name, brand, category FROM items ORDER BY item_name ASC");
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as &$itRow) {
                $itRow['model'] = null;
            }
            unset($itRow);
        }

        sendJson([
            'success' => true,
            'user' => $user,
            'offices' => $offices,
            'facilities' => $facilities,
            'suppliers' => $suppliers,
            'items' => $items
        ]);
    }

    if ($action === 'supplier_catalog') {
        $supplierStmt = $db->query("SELECT supplier_id, supplier_name, supplier_image, contact_person, phone_number, email, address, city, country, postal_code FROM suppliers ORDER BY supplier_name ASC");
        sendJson([
            'success' => true,
            'suppliers' => $supplierStmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    if ($action === 'item_suggestions') {
        $term = trim($_GET['term'] ?? '');
        if ($term === '') {
            sendJson(['success' => true, 'items' => []]);
        }

        try {
            $stmt = $db->prepare("SELECT item_id, item_name, brand, model, category
                FROM items
                WHERE item_name LIKE :term OR brand LIKE :term OR category LIKE :term
                   OR (model IS NOT NULL AND model != '' AND model LIKE :term)
                ORDER BY item_name ASC
                LIMIT 10");
            $stmt->execute(['term' => '%' . $term . '%']);
            $sugRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $stmt = $db->prepare("SELECT item_id, item_name, brand, category
                FROM items
                WHERE item_name LIKE :term OR brand LIKE :term OR category LIKE :term
                ORDER BY item_name ASC
                LIMIT 10");
            $stmt->execute(['term' => '%' . $term . '%']);
            $sugRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sugRows as &$sr) {
                $sr['model'] = null;
            }
            unset($sr);
        }
        sendJson(['success' => true, 'items' => $sugRows]);
    }

    if ($action === 'list_requests') {
        $agg = requisitionSqlSelectListAggregates();
        $listSql = "
            SELECT r.request_id, r.created_at, r.status, r.message,
                   u.Email, d.`office_name` AS office_name, rfa.requisition_status, rfa.requisition_note, cva.canvas_status, cva.gsd_status, cva.comp_status, cva.pres_status,
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
            WHERE ";
        if (!empty($requestScope['is_department'])) {
            $officeId = (int) ($requestScope['office_id'] ?? 0);
            if ($officeId <= 0) {
                sendJson(['success' => true, 'requests' => []]);
            }
            $listSql .= 'r.office_id = ? ORDER BY r.created_at DESC, r.request_id DESC';
            $stmt = $db->prepare($listSql);
            $stmt->execute([$officeId]);
        } else {
            $listSql .= 'r.user_id = ? ORDER BY r.created_at DESC, r.request_id DESC';
            $stmt = $db->prepare($listSql);
            $stmt->execute([(int) $requestScope['user_id']]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $requests = array_map(function ($row) {
            $email = (string)($row['Email'] ?? '');
            $requester = $email !== '' ? (explode('@', $email)[0] ?? 'Unknown') : '—';

            return [
                'id' => 'REQ-' . str_pad((string)$row['request_id'], 6, '0', STR_PAD_LEFT),
                'request_id' => (int)$row['request_id'],
                'date' => $row['created_at'],
                'updated_at' => $row['created_at'],
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

    if ($action === 'update_request') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $urgentNote = trim($_POST['urgent_note'] ?? '');

        if ($requestId <= 0 || $status === '') {
            sendJson(['success' => false, 'message' => 'Invalid update payload.']);
        }

        $allowedStatus = ['Pending', 'Ongoing', 'Completed'];
        if (!in_array($status, $allowedStatus, true)) {
            sendJson(['success' => false, 'message' => 'Invalid status value.']);
        }

        $anchor = fetchOwnedRequisition($db, $requestId, $requestScope);
        if (!$anchor) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        if (!empty($requestScope['is_department'])) {
            $officeId = (int) ($requestScope['office_id'] ?? 0);
            $updateStmt = $db->prepare('UPDATE requisition_item SET status = ?, message = ? WHERE request_id = ? AND office_id = ?');
            $updateStmt->execute([$status, ($message !== '' ? $message : null), $requestId, $officeId]);
        } else {
            $updateStmt = $db->prepare('UPDATE requisition_item SET status = ?, message = ? WHERE request_id = ? AND user_id = ?');
            $updateStmt->execute([$status, ($message !== '' ? $message : null), $requestId, (int) $requestScope['user_id']]);
        }

        sendJson(['success' => true, 'message' => 'Request updated successfully.']);
    }

    if ($action === 'delete_request') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }

        if (deleteOwnedRequisition($db, $requestId, $requestScope) === 0) {
            sendJson(['success' => false, 'message' => 'Request not found or already deleted.']);
        }

        sendJson(['success' => true, 'message' => 'Request deleted successfully.']);
    }

    if ($action === 'get_request_detail') {
        $requestId = (int)($_GET['request_id'] ?? 0);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }

        $anchor = fetchOwnedRequisition($db, $requestId, $requestScope);
        if (!$anchor) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }
        $canEdit = (($anchor['status'] ?? '') === 'Pending');
        if (!$canEdit && ($anchor['status'] ?? '') === 'Ongoing') {
            $reviewStmt = $db->prepare('SELECT requisition_status FROM requisition_form_approval WHERE request_id = ? LIMIT 1');
            $reviewStmt->execute([$requestId]);
            $reviewStatus = strtolower(trim((string)($reviewStmt->fetchColumn() ?: '')));
            $canEdit = ($reviewStatus === 'accept');
        }
        if (!$canEdit) {
            sendJson(['success' => false, 'message' => 'You can edit pending requests or inventory-approved requisitions.']);
        }

        $rows = requisitionFetchDetailMatrixRows($db, $requestId);

        if (count($rows) === 0) {
            sendJson(['success' => false, 'message' => 'Could not load requisition rows.']);
        }

        $payload = buildRequisitionDetailPayload($anchor, $rows, $requestId);
        requisitionAttachApprovalToPayload($db, $requestId, $payload);
        $payload['dean_edit_locked'] = requisitionVerifierChainLocked($payload['approval'] ?? null);
        sendJson($payload);
    }

    if ($action === 'get_request_detail_view') {
        $requestId = (int)($_GET['request_id'] ?? 0);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }

        $anchor = fetchOwnedRequisition($db, $requestId, $requestScope);
        if (!$anchor) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        $rows = requisitionFetchDetailMatrixRows($db, $requestId);

        if (count($rows) === 0) {
            sendJson(['success' => false, 'message' => 'Could not load requisition rows.']);
        }

        $payload = buildRequisitionDetailPayload($anchor, $rows, $requestId);
        requisitionAttachApprovalToPayload($db, $requestId, $payload);
        $ownerStmt = $db->prepare('SELECT Email, role FROM user WHERE user_id = ?');
        $ownerStmt->execute([(int)$anchor['user_id']]);
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
        $emailOwn = (string)($owner['Email'] ?? '');
        $payload['requester_display'] = $emailOwn !== '' ? (explode('@', $emailOwn)[0] ?? '—') : '—';
        $payload['requester_role'] = (string)($owner['role'] ?? '');
        $payload['dean_edit_locked'] = requisitionVerifierChainLocked($payload['approval'] ?? null);
        sendJson($payload);
    }

    if ($action === 'update_requisition') {
        $anchorId = (int)($_POST['request_id'] ?? 0);
        $officeId = (int)($_POST['office_id'] ?? 0);
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $requestDate = trim($_POST['request_date'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $itemsRaw = $_POST['items'] ?? '[]';
        $suppliersRaw = $_POST['suppliers'] ?? '[]';
        $items = json_decode($itemsRaw, true);
        $suppliers = json_decode($suppliersRaw, true);

        if ($anchorId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request id.']);
        }
        if (!$officeId || !$facilityId || !$requestDate) {
            sendJson(['success' => false, 'message' => 'Office, location, and date are required.']);
        }
        if (!is_array($items) || count($items) === 0) {
            sendJson(['success' => false, 'message' => 'Add at least one requested item.']);
        }

        $anchorStmt = $db->prepare('SELECT * FROM requisition_item WHERE request_id = ? AND user_id = ?');
        $anchorStmt->execute([$anchorId, $_SESSION['user_id']]);
        $anchor = $anchorStmt->fetch(PDO::FETCH_ASSOC);
        if (!$anchor) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }
        $canEdit = (($anchor['status'] ?? '') === 'Pending');
        if (!$canEdit && ($anchor['status'] ?? '') === 'Ongoing') {
            $reviewStmt = $db->prepare('SELECT requisition_status FROM requisition_form_approval WHERE request_id = ? LIMIT 1');
            $reviewStmt->execute([$anchorId]);
            $reviewStatus = strtolower(trim((string)($reviewStmt->fetchColumn() ?: '')));
            $canEdit = ($reviewStatus === 'accept');
        }
        if (!$canEdit) {
            sendJson(['success' => false, 'message' => 'You can update pending requests or inventory-approved requisitions only.']);
        }

        $verStmt = $db->prepare(
            'SELECT canvas_status, gsd_status, comp_status, pres_status FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $verStmt->execute([$anchorId]);
        $verRow = $verStmt->fetch(PDO::FETCH_ASSOC);
        if ($verRow && requisitionVerifierChainLocked($verRow)) {
            sendJson([
                'success' => false,
                'message' => 'This requisition can no longer be edited because the canvasser or a verifier (G.S.D. officer, comptroller, or president) has already recorded a decision.',
            ]);
        }

        // Keep original time component when updating so created_at is not forced to 12:00 AM.
        $timePart = date('H:i:s');
        if (!empty($anchor['created_at']) && preg_match('/\b(\d{2}:\d{2}:\d{2})\b/', (string)$anchor['created_at'], $m)) {
            $timePart = $m[1];
        }
        $createdAt = $requestDate . ' ' . $timePart;
        $msgVal = $message !== '' ? $message : null;
        $db->beginTransaction();
        try {
            $updStmt = $db->prepare('UPDATE requisition_item SET office_id = ?, facility_id = ?, created_at = ?, message = ?, purpose = ?, urgent_note = ? WHERE request_id = ? AND user_id = ?');
            $updStmt->execute([$officeId, $facilityId, $createdAt, $msgVal, ($purpose !== '' ? $purpose : null), ($urgentNote !== '' ? $urgentNote : null), $anchorId, (int) $_SESSION['user_id']]);

            $delLines = $db->prepare('DELETE FROM requisition_line WHERE request_id = ?');
            $delLines->execute([$anchorId]);

            $resetReviewStmt = $db->prepare("UPDATE requisition_form_approval SET requisition_status = 'pending', requisition_note = NULL, requisition_reviewed_by = NULL, requisition_reviewed_at = NULL WHERE request_id = ? AND requisition_status = 'reject'");
            $resetReviewStmt->execute([$anchorId]);

            requisitionInsertLinesForRequest($db, $anchorId, $items, is_array($suppliers) ? $suppliers : []);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        sendJson(['success' => true, 'message' => 'Requisition updated successfully.']);
    }

    if ($action === 'submit') {
        $officeId = (int)($_POST['office_id'] ?? 0);
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $requestDate = trim($_POST['request_date'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $urgentNote = trim($_POST['urgent_note'] ?? '');
        $itemsRaw = $_POST['items'] ?? '[]';
        $suppliersRaw = $_POST['suppliers'] ?? '[]';
        $items = json_decode($itemsRaw, true);
        $suppliers = json_decode($suppliersRaw, true);

        if (!$officeId || !$facilityId || !$requestDate) {
            sendJson(['success' => false, 'message' => 'Office, location, and date are required.']);
        }
        if (!is_array($items) || count($items) === 0) {
            sendJson(['success' => false, 'message' => 'Add at least one requested item.']);
        }

        $db->beginTransaction();
        try {
            insertRequisitionBatch(
                $db,
                (int)$_SESSION['user_id'],
                $officeId,
                $facilityId,
                $requestDate,
                $message,
                $purpose,
                $urgentNote,
                $items,
                is_array($suppliers) ? $suppliers : [],
                'submitted'
            );
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        sendJson(['success' => true, 'message' => 'Requisition submitted successfully.']);
    }

    if ($action === 'save_draft') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $officeId = (int)($_POST['office_id'] ?? 0);
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $requestDate = trim($_POST['request_date'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $urgentNote = trim($_POST['urgent_note'] ?? '');
        $itemsRaw = $_POST['items'] ?? '[]';
        $suppliersRaw = $_POST['suppliers'] ?? '[]';
        $targetSubmissionStatus = strtolower(trim((string)($_POST['submission_status'] ?? 'draft')));
        $items = json_decode($itemsRaw, true);
        $suppliers = json_decode($suppliersRaw, true);

        if (!in_array($targetSubmissionStatus, ['draft', 'submitted'], true)) {
            $targetSubmissionStatus = 'draft';
        }

        if (!$officeId || !$facilityId || !$requestDate) {
            sendJson(['success' => false, 'message' => 'Office, location, and date are required.']);
        }
        if (!is_array($items) || count($items) === 0) {
            sendJson(['success' => false, 'message' => 'Add at least one requested item.']);
        }

        $db->beginTransaction();
        try {
            if ($requestId <= 0) {
                // Create new draft requisition
                insertRequisitionBatch(
                    $db,
                    (int)$_SESSION['user_id'],
                    $officeId,
                    $facilityId,
                    $requestDate,
                    $message,
                    $purpose,
                    $urgentNote,
                    $items,
                    is_array($suppliers) ? $suppliers : [],
                    'draft'
                );
                $newRequestId = (int) $db->lastInsertId();
                $db->commit();
                sendJson(['success' => true, 'message' => 'Draft saved successfully.', 'request_id' => $newRequestId]);
            } else {
                // Update existing draft requisition
                $checkStmt = $db->prepare('SELECT request_id, submission_status FROM requisition_item WHERE request_id = ? AND user_id = ?');
                $checkStmt->execute([$requestId, $_SESSION['user_id']]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existing) {
                    $db->rollBack();
                    sendJson(['success' => false, 'message' => 'Request not found.']);
                }
                
                if ($existing['submission_status'] === 'submitted') {
                    $db->rollBack();
                    sendJson(['success' => false, 'message' => 'Cannot modify submitted requisition.']);
                }

                $msgVal = $message !== '' ? $message : null;
                $purposeVal = $purpose !== '' ? $purpose : null;
                $urgentNoteVal = $urgentNote !== '' ? $urgentNote : null;

                $updateStmt = $db->prepare('UPDATE requisition_item SET office_id = ?, facility_id = ?, message = ?, purpose = ?, urgent_note = ?, submission_status = ? WHERE request_id = ? AND user_id = ?');
                $updateStmt->execute([$officeId, $facilityId, $msgVal, $purposeVal, $urgentNoteVal, $targetSubmissionStatus, $requestId, $_SESSION['user_id']]);

                // Delete old items and re-insert
                $delItemsStmt = $db->prepare('DELETE FROM requisition_line WHERE request_id = ?');
                $delItemsStmt->execute([$requestId]);

                requisitionInsertLinesForRequest($db, $requestId, $items, is_array($suppliers) ? $suppliers : []);

                $db->commit();
                $responseMessage = $targetSubmissionStatus === 'submitted'
                    ? 'Requisition submitted successfully.'
                    : 'Draft saved successfully.';
                sendJson([
                    'success' => true,
                    'message' => $responseMessage,
                    'request_id' => $requestId,
                    'new_status' => $targetSubmissionStatus,
                ]);
            }
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    if ($action === 'change_submission_status') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');

        if ($requestId <= 0 || !in_array($newStatus, ['draft', 'submitted'], true)) {
            sendJson(['success' => false, 'message' => 'Invalid request or status.']);
        }

        $checkStmt = $db->prepare('SELECT request_id, submission_status FROM requisition_item WHERE request_id = ? AND user_id = ?');
        $checkStmt->execute([$requestId, $_SESSION['user_id']]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        if ($newStatus === 'submitted' && $existing['submission_status'] === 'submitted') {
            sendJson(['success' => false, 'message' => 'Requisition is already submitted.']);
        }

        $updateStmt = $db->prepare('UPDATE requisition_item SET submission_status = ? WHERE request_id = ? AND user_id = ?');
        $updateStmt->execute([$newStatus, $requestId, $_SESSION['user_id']]);

        sendJson(['success' => true, 'message' => 'Status updated successfully.', 'new_status' => $newStatus]);
    }

    sendJson(['success' => false, 'message' => 'Invalid action.']);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    sendJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
