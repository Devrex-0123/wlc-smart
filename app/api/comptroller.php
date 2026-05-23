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

function assertComptroller(PDO $db): void
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
    if ($r !== 'comptroller') {
        sendJson(['success' => false, 'message' => 'Forbidden']);
    }
}

function comptrollerCheckedByLabel(PDO $db): string
{
    $stmt = $db->prepare('SELECT Email FROM user WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $email = (string) ($row['Email'] ?? '');
    if ($email === '') {
        return 'Comptroller';
    }

    return explode('@', $email)[0] ?? $email;
}

function requestHasSuggestedSuppliersPerItem(PDO $db, int $requestId): bool
{
    $totalStmt = $db->prepare('SELECT COUNT(*) FROM requisition_canvass_detail WHERE request_id = ?');
    $totalStmt->execute([$requestId]);
    $total = (int) $totalStmt->fetchColumn();
    if ($total <= 0) {
        return false;
    }
    $selStmt = $db->prepare('SELECT COUNT(*) FROM request_approval_suggested_supplier_item WHERE request_id = ?');
    $selStmt->execute([$requestId]);
    $selected = (int) $selStmt->fetchColumn();

    return $selected >= $total;
}

try {
    $db = Database::connect();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'dashboard_stats') {
        assertComptroller($db);

        $pending = (int) $db->query("SELECT COUNT(*) FROM requisition_item WHERE status = 'Pending'")->fetchColumn();
        $ongoing = (int) $db->query("SELECT COUNT(*) FROM requisition_item WHERE status = 'Ongoing'")->fetchColumn();
        $completed = (int) $db->query("SELECT COUNT(*) FROM requisition_item WHERE status = 'Completed'")->fetchColumn();

        $clearedMonthStmt = $db->query("
            SELECT COUNT(*) FROM requisition_item
            WHERE status = 'Completed'
              AND YEAR(created_at) = YEAR(CURRENT_DATE())
              AND MONTH(created_at) = MONTH(CURRENT_DATE())
        ");
        $clearedThisMonth = (int) $clearedMonthStmt->fetchColumn();

        $flaggedStmt = $db->query("
            SELECT COUNT(*) FROM requisition_item
            WHERE status = 'Pending'
              AND message IS NOT NULL
              AND TRIM(message) <> ''
        ");
        $flagged = (int) $flaggedStmt->fetchColumn();

        sendJson([
            'success' => true,
            'pending' => $pending,
            'ongoing' => $ongoing,
            'completed' => $completed,
            'cleared_this_month' => $clearedThisMonth,
            'flagged' => $flagged,
            'total_lines' => (int) $db->query('SELECT COUNT(*) FROM requisition_item')->fetchColumn(),
        ]);
    }

    if ($action === 'list_requests') {
        assertComptroller($db);

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
            AND (cva.request_id IS NULL OR LOWER(TRIM(COALESCE(cva.canvas_submission_status, 'draft'))) != 'draft')
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
        assertComptroller($db);
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
            SELECT canvas_status, comp_status, checked_by, checked_at, gsd_status, pres_status, suggested_supplier_id, suggested_supplier_name
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
                    'comp_status' => 'pending',
                    'checked_by' => null,
                    'checked_at' => null,
                    'gsd_status' => null,
                    'pres_status' => null,
                    'suggested_supplier_id' => null,
                    'suggested_supplier_name' => null,
                ],
            ]);
        }

        sendJson([
            'success' => true,
            'approval' => [
                'canvas_status' => $row['canvas_status'] ?? null,
                'comp_status' => (string) ($row['comp_status'] ?? 'pending'),
                'checked_by' => $row['checked_by'],
                'checked_at' => $row['checked_at'],
                'gsd_status' => $row['gsd_status'],
                'pres_status' => $row['pres_status'],
                'suggested_supplier_id' => isset($row['suggested_supplier_id']) ? (int) $row['suggested_supplier_id'] : null,
                'suggested_supplier_name' => $row['suggested_supplier_name'] ?? null,
            ],
        ]);
    }

    if ($action === 'get_comptroller_action_history') {
        assertComptroller($db);
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
            FROM comptroller_action_history h
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

    if ($action === 'set_comptroller_approval') {
        assertComptroller($db);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $compStatus = strtolower(trim((string) ($_POST['comp_status'] ?? '')));
        if ($requestId <= 0 || !in_array($compStatus, ['accept', 'reject', 'pending'], true)) {
            sendJson(['success' => false, 'message' => 'Invalid approval payload.']);
        }

        $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
        $chk->execute([$requestId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            sendJson(['success' => false, 'message' => 'Request not found.']);
        }
        if ($compStatus !== 'pending' && !requestHasSuggestedSuppliersPerItem($db, $requestId)) {
            sendJson(['success' => false, 'message' => 'GSD must select suggested suppliers for all items before comptroller action.']);
        }

        $checkedBy = comptrollerCheckedByLabel($db);
        $requisitionStatus = ($compStatus === 'pending') ? 'Pending' : 'Ongoing';

        $find = $db->prepare(
            'SELECT comp_status, checked_by FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $find->execute([$requestId]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);

        $previousCompStatus = 'pending';
        if ($existing) {
            $prevRaw = strtolower(trim((string) ($existing['comp_status'] ?? 'pending')));
            if ($prevRaw === '') {
                $prevRaw = 'pending';
            }
            $previousCompStatus = in_array($prevRaw, ['accept', 'reject', 'pending'], true) ? $prevRaw : 'pending';
        }

        if ($compStatus === $previousCompStatus) {
            $unchangedCheckedBy = $compStatus === 'pending' ? null : ($existing['checked_by'] ?? null);
            sendJson([
                'success' => true,
                'message' => 'This decision is already recorded. No changes made.',
                'comp_status' => $compStatus,
                'checked_by' => $unchangedCheckedBy,
                'requisition_status' => $requisitionStatus,
                'unchanged' => true,
            ]);
        }

        $db->beginTransaction();
        try {
            if ($existing) {
                if ($compStatus === 'pending') {
                    $up = $db->prepare('
                        UPDATE canvass_verification_approval
                        SET checked_by = NULL,
                            checked_at = NULL,
                            comp_status = ?
                        WHERE request_id = ?
                    ');
                    $up->execute([$compStatus, $requestId]);
                } else {
                    $up = $db->prepare('
                        UPDATE canvass_verification_approval
                        SET checked_by = ?,
                            checked_at = NOW(),
                            comp_status = ?
                        WHERE request_id = ?
                    ');
                    $up->execute([$checkedBy, $compStatus, $requestId]);
                }
            } elseif ($compStatus !== 'pending') {
                ensureCanvassVerificationApprovalRow($db, $requestId);
                $ins = $db->prepare('
                    UPDATE canvass_verification_approval
                    SET checked_by = ?, checked_at = NOW(), comp_status = ?
                    WHERE request_id = ?
                ');
                $ins->execute([$checkedBy, $compStatus, $requestId]);
            }

            $updReq = $db->prepare('UPDATE requisition_item SET status = ? WHERE request_id = ?');
            $updReq->execute([$requisitionStatus, $requestId]);

            $logIns = $db->prepare(
                'INSERT INTO comptroller_action_history (request_id, user_id, action) VALUES (?, ?, ?)'
            );
            $logIns->execute([$requestId, (int) $_SESSION['user_id'], $compStatus]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $msg = 'Comptroller decision saved.';
        if ($compStatus === 'accept') {
            $msg = 'Request approved. Status set to Ongoing.';
        } elseif ($compStatus === 'reject') {
            $msg = 'Request rejected. Status set to Ongoing.';
        } elseif ($compStatus === 'pending') {
            $msg = 'Comptroller decision cleared. Status set to Pending.';
        }

        sendJson([
            'success' => true,
            'message' => $msg,
            'comp_status' => $compStatus,
            'checked_by' => $compStatus === 'pending' ? null : $checkedBy,
            'requisition_status' => $requisitionStatus,
        ]);
    }

    if ($action === 'login_history') {
        assertComptroller($db);

        $stmt = $db->query("
            SELECT l.log_id, l.time_in, l.time_out, u.Email, u.role
            FROM log_history l
            LEFT JOIN user u ON u.user_id = l.user_id
            ORDER BY l.time_in DESC
            LIMIT 150
        ");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJson(['success' => true, 'logs' => $logs]);
    }

    sendJson(['success' => false, 'message' => 'Invalid action.']);
} catch (Exception $e) {
    sendJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
