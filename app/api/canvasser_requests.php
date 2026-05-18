<?php
/**
 * Canvasser workspace API:
 * - list requests
 * - set/retrieve canvasser approval (canvass_verification_approval.canvas_status)
 * - canvasser action history
 */
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

function assertCanvasser(PDO $db): void
{
    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Unauthorized']);
    }

    $stmt = $db->prepare('SELECT u.role FROM user u WHERE u.user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        sendJson(['success' => false, 'message' => 'Unauthorized']);
    }

    $roleLc = strtolower(trim((string) ($row['role'] ?? '')));
    $allowedRoles = ['employee', 'user', 'laboratory manager', 'canvasser'];
    if (!in_array($roleLc, $allowedRoles, true)) {
        sendJson(['success' => false, 'message' => 'Forbidden']);
    }
}

function canvasserEmailLocalPart(PDO $db, int $userId): string
{
    $stmt = $db->prepare('SELECT Email FROM user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $email = (string) ($row['Email'] ?? '');

    return $email !== '' ? (strtolower(explode('@', $email)[0] ?? $email)) : '';
}

/** GSD-assigned canvasser: match user_id or legacy canvassed_by label. */
function userMayActAsCanvasAssignee(PDO $db, ?array $existing, int $sessionUid): bool
{
    if (!$existing) {
        return false;
    }
    $aid = (int) ($existing['canvas_assignee_user_id'] ?? 0);
    if ($aid > 0) {
        return $aid === $sessionUid;
    }
    $local = canvasserEmailLocalPart($db, $sessionUid);
    $by = strtolower(trim((string) ($existing['canvassed_by'] ?? '')));

    return $local !== '' && $by === $local;
}

function canvasserLabel(PDO $db): string
{
    $stmt = $db->prepare('SELECT Email FROM user WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $email = (string) ($row['Email'] ?? '');
    if ($email === '') {
        return 'Canvasser';
    }

    return explode('@', $email)[0] ?? $email;
}

/**
 * Prevent saving supplier rows that contain no quoted price at all.
 *
 * @param array<int, mixed> $suppliers
 * @return string|null error message
 */
function validateSupplierRowsHaveQuotedPrice(array $suppliers): ?string
{
    foreach ($suppliers as $s) {
        if (!is_array($s)) {
            continue;
        }
        $sid = (int) ($s['supplier_id'] ?? 0);
        if ($sid <= 0) {
            continue;
        }
        $prices = $s['prices'] ?? [];
        if (!is_array($prices)) {
            $prices = [];
        }
        $hasQuote = false;
        foreach ($prices as $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            if (!is_numeric($raw)) {
                continue;
            }
            if ((float) $raw >= 0) {
                $hasQuote = true;
                break;
            }
        }
        if (!$hasQuote) {
            return 'Each supplier must have at least one quoted price. Remove supplier rows with no quote before completing.';
        }
    }

    return null;
}

try {
    $db = Database::connect();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'list_requests') {
        assertCanvasser($db);

        $mapRequestRow = static function (array $row): array {
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
        };

        $uid = (int) $_SESSION['user_id'];
        $localKey = canvasserEmailLocalPart($db, $uid);

        $requests = [];
        try {
            $agg = requisitionSqlSelectListAggregates();
            $stmtAm = $db->prepare("
                SELECT r.request_id, r.created_at, r.status, r.message,
                       u.Email, d.`office_name` AS office_name,
                       {$agg}
                FROM requisition_item r
                INNER JOIN canvass_verification_approval cva ON cva.request_id = r.request_id
                LEFT JOIN user u ON u.user_id = r.user_id
                LEFT JOIN offices d ON d.office_id = r.office_id
                WHERE (
                    cva.canvas_assignee_user_id = ?
                    OR (
                        cva.canvas_assignee_user_id IS NULL
                        AND cva.canvassed_by IS NOT NULL
                        AND LOWER(TRIM(cva.canvassed_by)) = ?
                    )
                )
                AND LOWER(TRIM(COALESCE(cva.canvas_status, 'pending'))) IN ('pending', '')
                ORDER BY r.created_at DESC, r.request_id DESC
            ");
            $stmtAm->execute([$uid, $localKey]);
            $requests = array_map($mapRequestRow, $stmtAm->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            $requests = [];
        }

        sendJson([
            'success' => true,
            'requests' => $requests,
            'assigned_count' => count($requests),
        ]);
    }

    if ($action === 'get_approval_status') {
        assertCanvasser($db);
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
            SELECT canvas_status, canvassed_by, canvassed_at, canvas_assignee_user_id, checked_by, checked_at, comp_status, gsd_status, pres_status
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
                    'canvas_status' => 'pending',
                    'canvassed_by' => null,
                    'canvassed_at' => null,
                    'canvas_assignee_user_id' => null,
                    'comp_status' => null,
                    'gsd_status' => null,
                    'pres_status' => null,
                ],
            ]);
        }

        sendJson([
            'success' => true,
            'approval' => [
                'canvas_status' => (string) ($row['canvas_status'] ?? 'pending'),
                'canvassed_by' => $row['canvassed_by'] ?? null,
                'canvassed_at' => $row['canvassed_at'] ?? null,
                'canvas_assignee_user_id' => isset($row['canvas_assignee_user_id']) ? (int) $row['canvas_assignee_user_id'] : null,
                'comp_status' => $row['comp_status'] ?? null,
                'gsd_status' => $row['gsd_status'] ?? null,
                'pres_status' => $row['pres_status'] ?? null,
            ],
        ]);
    }

    if ($action === 'save_canvas_quotations') {
        assertCanvasser($db);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $suppliersRaw = $_POST['suppliers'] ?? '[]';
        $suppliers = json_decode($suppliersRaw, true);
        if ($requestId <= 0 || !is_array($suppliers)) {
            sendJson(['success' => false, 'message' => 'Invalid request.']);
        }

        $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $chk->execute([$requestId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        $find = $db->prepare(
            'SELECT canvas_status, canvassed_by, canvas_assignee_user_id FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $find->execute([$requestId]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);

        $sessionUid = (int) $_SESSION['user_id'];
        if (!userMayActAsCanvasAssignee($db, $existing, $sessionUid)) {
            sendJson(['success' => false, 'message' => 'You are not assigned to canvass this request.']);
        }

        $cRaw = strtolower(trim((string) ($existing['canvas_status'] ?? 'pending')));
        if ($cRaw === '') {
            $cRaw = 'pending';
        }
        if ($cRaw === 'accept' || $cRaw === 'reject') {
            sendJson(['success' => false, 'message' => 'Canvassing is finalized. Use Undo to edit suppliers and prices.']);
        }

        if (requisitionVerifierChainLockedForRequest($db, $requestId)) {
            sendJson([
                'success' => false,
                'message' => 'This canvass can no longer be edited because a verifier (G.S.D. officer, comptroller, or president) has already recorded a decision.',
            ]);
        }

        $lineStmt = $db->prepare(
            'SELECT requisition_line_id FROM requisition_line WHERE request_id = ? ORDER BY sort_order ASC, requisition_line_id ASC'
        );
        $lineStmt->execute([$requestId]);
        $lineIds = $lineStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!is_array($lineIds) || count($lineIds) === 0) {
            sendJson(['success' => false, 'message' => 'This requisition has no line items.']);
        }

        $payloadSupplierIds = [];
        foreach ($suppliers as $s) {
            if (!is_array($s)) {
                continue;
            }
            $sid = (int) ($s['supplier_id'] ?? 0);
            if ($sid > 0) {
                $payloadSupplierIds[] = $sid;
            }
        }
        $payloadSupplierIds = array_values(array_unique($payloadSupplierIds));
        if (count($payloadSupplierIds) === 0) {
            sendJson(['success' => false, 'message' => 'Add at least one supplier.']);
        }

        $quoteErr = validateSupplierRowsHaveQuotedPrice($suppliers);
        if ($quoteErr !== null) {
            sendJson(['success' => false, 'message' => $quoteErr]);
        }

        $verifySup = $db->prepare('SELECT supplier_id FROM suppliers WHERE supplier_id = ?');
        foreach ($payloadSupplierIds as $sid) {
            $verifySup->execute([$sid]);
            if (!$verifySup->fetchColumn()) {
                sendJson(['success' => false, 'message' => 'Invalid supplier in list.']);
            }
        }

        $ownStmt = $db->prepare('SELECT user_id FROM requisition_item WHERE request_id = ?');
        $ownStmt->execute([$requestId]);
        $ownerUid = (int) $ownStmt->fetchColumn();
        if ($ownerUid <= 0) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        $findCdStmt = $db->prepare(
            'SELECT canvass_detail_id FROM requisition_canvass_detail WHERE request_id = ? AND requisition_line_id = ? ORDER BY canvass_detail_id ASC LIMIT 1'
        );
        $lineMetaStmt = $db->prepare(
            'SELECT item_name, item_brand, item_category, sort_order FROM requisition_line WHERE requisition_line_id = ? AND request_id = ?'
        );
        $insCdStmt = $db->prepare(
            'INSERT INTO requisition_canvass_detail (request_id, requisition_line_id, user_id, component_label, specification, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $db->beginTransaction();
        try {
            $cdByLineIndex = [];
            foreach ($lineIds as $li => $lineId) {
                $lineId = (int) $lineId;
                $findCdStmt->execute([$requestId, $lineId]);
                $cdId = $findCdStmt->fetchColumn();
                if (!$cdId) {
                    $lineMetaStmt->execute([$lineId, $requestId]);
                    $meta = $lineMetaStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$meta) {
                        throw new RuntimeException('Missing requisition line.');
                    }
                    $itemName = trim((string) ($meta['item_name'] ?? ''));
                    if ($itemName === '') {
                        $itemName = 'Item';
                    }
                    $specParts = array_filter([
                        trim((string) ($meta['item_brand'] ?? '')),
                        trim((string) ($meta['item_category'] ?? '')),
                    ]);
                    $specStr = $specParts !== [] ? implode(' · ', $specParts) : null;
                    $insCdStmt->execute([
                        $requestId,
                        $lineId,
                        $ownerUid,
                        substr($itemName, 0, 150),
                        $specStr !== null ? substr($specStr, 0, 500) : null,
                        (int) ($meta['sort_order'] ?? $li),
                    ]);
                    $cdId = (int) $db->lastInsertId();
                } else {
                    $cdId = (int) $cdId;
                }
                $cdByLineIndex[$li] = $cdId;
            }

            $upsertCell = $db->prepare(
                'INSERT INTO requisition_canvass_detail_supplier (canvass_detail_id, supplier_id, price) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE price = VALUES(price)'
            );
            $delCell = $db->prepare(
                'DELETE FROM requisition_canvass_detail_supplier WHERE canvass_detail_id = ? AND supplier_id = ?'
            );

            foreach ($lineIds as $li => $lineId) {
                $cdId = $cdByLineIndex[$li];
                foreach ($suppliers as $s) {
                    if (!is_array($s)) {
                        continue;
                    }
                    $sid = (int) ($s['supplier_id'] ?? 0);
                    if ($sid <= 0) {
                        continue;
                    }
                    $prices = $s['prices'] ?? [];
                    if (!is_array($prices)) {
                        $prices = [];
                    }
                    $raw = null;
                    if (array_key_exists($li, $prices)) {
                        $raw = $prices[$li];
                    } elseif (array_key_exists((string) $li, $prices)) {
                        $raw = $prices[(string) $li];
                    }
                    if ($raw === null || $raw === '' || !is_numeric($raw)) {
                        $delCell->execute([$cdId, $sid]);
                        continue;
                    }
                    $priceVal = round((float) $raw, 2);
                    if ($priceVal < 0) {
                        throw new RuntimeException('Prices cannot be negative.');
                    }
                    $upsertCell->execute([$cdId, $sid, $priceVal]);
                }
            }

            $placeholders = implode(',', array_fill(0, count($payloadSupplierIds), '?'));
            $delOrphan = $db->prepare(
                "DELETE FROM requisition_canvass_detail_supplier WHERE canvass_detail_id = ? AND supplier_id NOT IN ($placeholders)"
            );
            foreach ($cdByLineIndex as $cdId) {
                $delOrphan->execute(array_merge([$cdId], $payloadSupplierIds));
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            sendJson(['success' => false, 'message' => 'Could not save: ' . $e->getMessage()]);
        }

        sendJson(['success' => true, 'message' => 'Suppliers and prices saved. Requester and reviewers will see the updated canvas.']);
    }

    if ($action === 'create_supplier') {
        assertCanvasser($db);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            sendJson(['success' => false, 'message' => 'Invalid request.']);
        }

        $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $chk->execute([$requestId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        $find = $db->prepare(
            'SELECT canvas_status, canvassed_by, canvas_assignee_user_id FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $find->execute([$requestId]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);

        $sessionUid = (int) $_SESSION['user_id'];
        if (!userMayActAsCanvasAssignee($db, $existing, $sessionUid)) {
            sendJson(['success' => false, 'message' => 'You are not assigned to canvass this request.']);
        }

        $cRaw = strtolower(trim((string) ($existing['canvas_status'] ?? 'pending')));
        if ($cRaw === '') {
            $cRaw = 'pending';
        }
        if ($cRaw === 'accept' || $cRaw === 'reject') {
            sendJson(['success' => false, 'message' => 'Canvassing is finalized. Undo to register new suppliers.']);
        }

        if (requisitionVerifierChainLockedForRequest($db, $requestId)) {
            sendJson([
                'success' => false,
                'message' => 'Suppliers cannot be registered here because a verifier has already recorded a decision on this request.',
            ]);
        }

        $supplierName = trim((string) ($_POST['supplier_name'] ?? ''));
        $contactPerson = trim((string) ($_POST['contact_person'] ?? ''));
        $phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $country = trim((string) ($_POST['country'] ?? ''));
        $postalCode = trim((string) ($_POST['postal_code'] ?? ''));

        if ($supplierName === '') {
            sendJson(['success' => false, 'message' => 'Supplier name is required.']);
        }

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
            $address !== '' ? $address : null,
            $city !== '' ? $city : null,
            $country !== '' ? $country : null,
            $postalCode !== '' ? $postalCode : null,
            'Active',
            null,
        ]);
        $newId = (int) $db->lastInsertId();

        $sel = $db->prepare('SELECT supplier_id, supplier_name, supplier_image, contact_person, phone_number, email, address, city, country, postal_code FROM suppliers WHERE supplier_id = ?');
        $sel->execute([$newId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);

        sendJson([
            'success' => true,
            'message' => 'Supplier registered. Select them from the list and add to the canvas.',
            'supplier' => $row,
        ]);
    }

    if ($action === 'set_canvas_approval') {
        assertCanvasser($db);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $canvasStatus = strtolower(trim((string) ($_POST['canvas_status'] ?? '')));
        if ($requestId <= 0 || !in_array($canvasStatus, ['accept', 'reject', 'pending'], true)) {
            sendJson(['success' => false, 'message' => 'Invalid approval payload.']);
        }

        $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $chk->execute([$requestId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }

        $canvassedBy = canvasserLabel($db);
        $requisitionStatus = ($canvasStatus === 'pending') ? 'Pending' : 'Ongoing';

        $find = $db->prepare(
            'SELECT canvas_status, canvassed_by, canvas_assignee_user_id FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $find->execute([$requestId]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);

        $sessionUid = (int) $_SESSION['user_id'];

        if (in_array($canvasStatus, ['accept', 'reject'], true)) {
            if (!$existing) {
                sendJson([
                    'success' => false,
                    'message' => 'This request has no approval record yet. Ask GSD to assign the canvass before you can complete it.',
                ]);
            }
            if (!userMayActAsCanvasAssignee($db, $existing, $sessionUid)) {
                sendJson([
                    'success' => false,
                    'message' => 'You are not assigned to canvass this request.',
                ]);
            }
        }

        if ($canvasStatus === 'pending' && $existing) {
            $prevRaw = strtolower(trim((string) ($existing['canvas_status'] ?? 'pending')));
            if ($prevRaw === 'accept' || $prevRaw === 'reject') {
                if (!userMayActAsCanvasAssignee($db, $existing, $sessionUid)) {
                    sendJson([
                        'success' => false,
                        'message' => 'Only the assigned canvasser can undo this decision.',
                    ]);
                }
            }
        }

        $previousStatus = 'pending';
        if ($existing) {
            $prevRaw = strtolower(trim((string) ($existing['canvas_status'] ?? 'pending')));
            if ($prevRaw === '') {
                $prevRaw = 'pending';
            }
            $previousStatus = in_array($prevRaw, ['accept', 'reject', 'pending'], true) ? $prevRaw : 'pending';
        }

        if ($canvasStatus === $previousStatus) {
            $unchangedBy = $canvasStatus === 'pending' ? null : ($existing['canvassed_by'] ?? null);
            sendJson([
                'success' => true,
                'message' => 'This decision is already recorded. No changes made.',
                'canvas_status' => $canvasStatus,
                'canvassed_by' => $unchangedBy,
                'requisition_status' => $requisitionStatus,
                'unchanged' => true,
            ]);
        }

        /** After undo (pending), show GSD-assigned staff again — derive label from canvas_assignee_user_id. */
        $restoredCanvassedBy = null;
        if ($canvasStatus === 'pending' && $existing) {
            $aid = (int) ($existing['canvas_assignee_user_id'] ?? 0);
            if ($aid > 0) {
                $su = $db->prepare('SELECT Email FROM user WHERE user_id = ?');
                $su->execute([$aid]);
                $ur = $su->fetch(PDO::FETCH_ASSOC);
                $em = (string) ($ur['Email'] ?? '');
                if ($em !== '') {
                    $restoredCanvassedBy = explode('@', $em)[0] ?? $em;
                }
            }
        }

        $db->beginTransaction();
        try {
            if ($existing) {
                if ($canvasStatus === 'pending') {
                    $up = $db->prepare('
                        UPDATE canvass_verification_approval
                        SET canvassed_by = ?,
                            canvassed_at = NULL,
                            canvas_status = ?,
                            checked_by = NULL,
                            checked_at = NULL,
                            comp_status = NULL,
                            verified_by = NULL,
                            verified_at = NULL,
                            gsd_status = NULL,
                            approved_by = NULL,
                            approved_at = NULL,
                            pres_status = NULL
                        WHERE request_id = ?
                    ');
                    $up->execute([$restoredCanvassedBy, $canvasStatus, $requestId]);
                } else {
                    $up = $db->prepare('
                        UPDATE canvass_verification_approval
                        SET canvassed_by = ?,
                            canvassed_at = NOW(),
                            canvas_status = ?,
                            checked_by = NULL,
                            checked_at = NULL,
                            comp_status = NULL,
                            verified_by = NULL,
                            verified_at = NULL,
                            gsd_status = NULL,
                            approved_by = NULL,
                            approved_at = NULL,
                            pres_status = NULL
                        WHERE request_id = ?
                    ');
                    $up->execute([$canvassedBy, $canvasStatus, $requestId]);
                }
            } elseif ($canvasStatus !== 'pending') {
                ensureCanvassVerificationApprovalRow($db, $requestId);
                $ins = $db->prepare('
                    UPDATE canvass_verification_approval
                    SET canvas_status = ?, canvassed_by = ?, canvassed_at = NOW()
                    WHERE request_id = ?
                ');
                $ins->execute([$canvasStatus, $canvassedBy, $requestId]);
            }

            $updReq = $db->prepare('UPDATE requisition_item SET status = ? WHERE request_id = ?');
            $updReq->execute([$requisitionStatus, $requestId]);

            $logIns = $db->prepare(
                'INSERT INTO canvasser_action_history (request_id, user_id, action) VALUES (?, ?, ?)'
            );
            $logIns->execute([$requestId, (int) $_SESSION['user_id'], $canvasStatus]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $msg = 'Canvasser decision saved.';
        if ($canvasStatus === 'accept') {
            $msg = 'Request approved by canvasser. Status set to Ongoing.';
        } elseif ($canvasStatus === 'reject') {
            $msg = 'Request rejected by canvasser. Status set to Ongoing.';
        } elseif ($canvasStatus === 'pending') {
            $msg = 'Canvasser decision cleared. Status set to Pending.';
        }

        $outCanvassedBy = $canvasStatus === 'pending' ? $restoredCanvassedBy : $canvassedBy;

        sendJson([
            'success' => true,
            'message' => $msg,
            'canvas_status' => $canvasStatus,
            'canvassed_by' => $outCanvassedBy,
            'requisition_status' => $requisitionStatus,
        ]);
    }

    if ($action === 'get_canvasser_action_history') {
        assertCanvasser($db);
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
            FROM canvasser_action_history h
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

    sendJson(['success' => false, 'message' => 'Invalid action.']);
} catch (Exception $e) {
    sendJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
