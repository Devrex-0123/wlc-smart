<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/../api/approval_tables.php';
require_once __DIR__ . '/../helpers/comptroller_qty_approval.php';

function redirectComptrollerCanvass(int $requestId, string $type, string $message): void
{
    $params = http_build_query([
        'request_id' => $requestId,
        'from' => 'comptroller',
        'approval_type' => $type,
        'approval_msg' => $message,
    ]);
    header('Location: ../../public/pages/dean_canvass_form.php?' . $params);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Method not allowed.');
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$requestId = (int) ($_POST['request_id'] ?? 0);
$compStatus = strtolower(trim((string) ($_POST['comp_status'] ?? '')));

if ($requestId <= 0 || $compStatus !== 'accept') {
    redirectComptrollerCanvass($requestId > 0 ? $requestId : 0, 'error', 'Invalid approval submission.');
}

try {
    $db = Database::connect();

    $roleStmt = $db->prepare('SELECT role FROM user WHERE user_id = ?');
    $roleStmt->execute([$userId]);
    $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
    $role = strtolower(trim((string) ($roleRow['role'] ?? '')));
    if ($role !== 'comptroller') {
        redirectComptrollerCanvass($requestId, 'error', 'Only the comptroller can submit this approval.');
    }

    $chk = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ?');
    $chk->execute([$requestId]);
    if (!$chk->fetch(PDO::FETCH_ASSOC)) {
        redirectComptrollerCanvass($requestId, 'error', 'Request not found.');
    }

    if (!cwirmsComptrollerRequestHasSuggestedSuppliersPerItem($db, $requestId)) {
        redirectComptrollerCanvass(
            $requestId,
            'error',
            'GSD must select suggested suppliers for all items before comptroller approval.'
        );
    }

    $existingStmt = $db->prepare(
        'SELECT comp_status FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
    );
    $existingStmt->execute([$requestId]);
    $existingComp = strtolower(trim((string) ($existingStmt->fetchColumn() ?: 'pending')));
    if ($existingComp === 'accept') {
        redirectComptrollerCanvass($requestId, 'success', 'This request is already approved.');
    }

    $rawAccepted = $_POST['accepted_qty'] ?? [];
    if (!is_array($rawAccepted) || $rawAccepted === []) {
        redirectComptrollerCanvass($requestId, 'error', 'Accepted quantities are required for every line item.');
    }

    $acceptedByDetail = [];
    foreach ($rawAccepted as $detailId => $qty) {
        $cid = (int) $detailId;
        if ($cid <= 0) {
            continue;
        }
        $acceptedByDetail[$cid] = max(0, (int) $qty);
    }

    $rawMessages = $_POST['deferred_message'] ?? [];
    $messagesByDetail = [];
    if (is_array($rawMessages)) {
        foreach ($rawMessages as $detailId => $msg) {
            $cid = (int) $detailId;
            if ($cid <= 0) {
                continue;
            }
            $messagesByDetail[$cid] = trim((string) $msg);
        }
    }

    // Schema helpers may run DDL (implicit commit in MySQL) — must run before beginTransaction().
    ensureComptrollerPartialQtyColumns($db);
    ensureRequisitionPreferredQuoteColumns($db);
    ensureSuggestedSupplierSelectionSourceColumn($db);

    $db->beginTransaction();
    try {
        cwirmsSaveComptrollerQtyApprovals($db, $requestId, $userId, $acceptedByDetail, $messagesByDetail);
        cwirmsApplyComptrollerCanvasApproval($db, $requestId, $userId, 'accept');
        if ($db->inTransaction()) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    redirectComptrollerCanvass($requestId, 'success', 'Request approved with quantity review saved.');
} catch (InvalidArgumentException $e) {
    redirectComptrollerCanvass($requestId, 'error', $e->getMessage());
} catch (Throwable $e) {
    redirectComptrollerCanvass($requestId, 'error', 'Could not save approval: ' . $e->getMessage());
}
