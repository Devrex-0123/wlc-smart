<?php

declare(strict_types=1);

require_once __DIR__ . '/canvass_pricing_overview.php';
require_once __DIR__ . '/../api/approval_tables.php';

function cwirmsComptrollerDeferredContextMessage(int $acceptedQty, int $requestedQty): ?string
{
    if ($acceptedQty >= $requestedQty || $requestedQty <= 0) {
        return null;
    }
    if ($acceptedQty === 0) {
        return 'No units approved — full quantity deferred to next procurement cycle.';
    }

    $deferredQty = $requestedQty - $acceptedQty;

    return $deferredQty . ' unit(s) deferred — will be requested next procurement cycle.';
}

function cwirmsComptrollerDeferredBadgeLabel(int $acceptedQty, int $requestedQty): ?string
{
    if ($acceptedQty >= $requestedQty || $requestedQty <= 0) {
        return null;
    }
    if ($acceptedQty === 0) {
        return 'None approved';
    }

    $deferredQty = $requestedQty - $acceptedQty;
    $unit = $deferredQty === 1 ? 'unit' : 'units';

    return $deferredQty . ' ' . $unit . ' deferred';
}

/** @deprecated Use cwirmsComptrollerDeferredContextMessage() */
function cwirmsComptrollerDeferredNoticeMessage(int $deferredQty): string
{
    return max(0, $deferredQty) . ' unit(s) deferred — will be requested next procurement cycle.';
}

function cwirmsComptrollerDeferredBannerMessage(int $acceptedQty, int $requestedQty, string $itemName): string
{
    $name = trim($itemName) !== '' ? trim($itemName) : 'item';
    if ($acceptedQty === 0 && $requestedQty > 0) {
        return 'No units of ' . $name . ' approved — full quantity deferred to the next procurement cycle.';
    }

    $deferredQty = max(0, $requestedQty - $acceptedQty);

    return $deferredQty . ' unit(s) of ' . $name . ' will be deferred to the next procurement cycle due to partial approval.';
}

function cwirmsComptrollerQtyStatus(int $acceptedQty, int $requestedQty): string
{
    return $acceptedQty < $requestedQty ? 'deferred' : 'fully_approved';
}

/**
 * @return array<string, mixed>
 */
function cwirmsComptrollerPricingOverviewForRequest(PDO $db, int $requestId): array
{
    $overview = cwirmsCanvassPricingOverviewForRequest($db, $requestId);
    if ($requestId <= 0 || ($overview['lines'] ?? []) === []) {
        return $overview;
    }

    ensureComptrollerPartialQtyColumns($db);

    $savedStmt = $db->prepare(
        'SELECT canvass_detail_id, accepted_qty, deferred_qty, deferred_message,
                comptroller_qty_status, comptroller_approved_by_user_id, comptroller_approved_at
         FROM request_approval_suggested_supplier_item
         WHERE request_id = ?'
    );
    $savedStmt->execute([$requestId]);
    $savedByDetail = [];
    $approverUserIds = [];
    while ($row = $savedStmt->fetch(PDO::FETCH_ASSOC)) {
        $cid = (int) ($row['canvass_detail_id'] ?? 0);
        if ($cid > 0) {
            $savedByDetail[$cid] = $row;
            $approverId = (int) ($row['comptroller_approved_by_user_id'] ?? 0);
            if ($approverId > 0) {
                $approverUserIds[$approverId] = true;
            }
        }
    }

    $approverLabelsByUserId = [];
    if ($approverUserIds !== []) {
        $placeholders = implode(',', array_fill(0, count($approverUserIds), '?'));
        $approverStmt = $db->prepare(
            "SELECT user_id, Email FROM user WHERE user_id IN ($placeholders)"
        );
        $approverStmt->execute(array_keys($approverUserIds));
        while ($approverRow = $approverStmt->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int) ($approverRow['user_id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $email = (string) ($approverRow['Email'] ?? '');
            $approverLabelsByUserId[$uid] = $email !== ''
                ? (explode('@', $email)[0] ?? $email)
                : 'Comptroller';
        }
    }

    $lines = [];
    $approvedGrandTotal = 0.0;
    $fullyApprovedCount = 0;

    foreach ($overview['lines'] as $line) {
        $requestedQty = max(0, (int) ($line['quantity'] ?? 0));
        $detailId = (int) ($line['canvass_detail_id'] ?? 0);
        $saved = $savedByDetail[$detailId] ?? null;

        $acceptedQty = $requestedQty;
        $hasSavedApproval = $saved !== null
            && !empty($saved['comptroller_approved_at']);
        if ($hasSavedApproval && $saved['accepted_qty'] !== null && $saved['accepted_qty'] !== '') {
            $acceptedQty = max(0, (int) $saved['accepted_qty']);
        }
        if ($acceptedQty > $requestedQty) {
            $acceptedQty = $requestedQty;
        }

        $deferredQty = max(0, $requestedQty - $acceptedQty);
        $unitPrice = isset($line['unit_price']) && is_numeric($line['unit_price'])
            ? (float) $line['unit_price']
            : null;
        $approvedLineTotal = $unitPrice !== null ? round($unitPrice * $acceptedQty, 2) : null;
        $deferredAmount = $unitPrice !== null ? round($unitPrice * $deferredQty, 2) : null;

        if ($approvedLineTotal !== null) {
            $approvedGrandTotal += $approvedLineTotal;
        }

        $status = cwirmsComptrollerQtyStatus($acceptedQty, $requestedQty);
        if ($status === 'fully_approved' && ($line['supplier_id'] ?? null)) {
            $fullyApprovedCount++;
        }

        $deferredMessage = null;
        if ($saved !== null && trim((string) ($saved['deferred_message'] ?? '')) !== '') {
            $deferredMessage = (string) $saved['deferred_message'];
        }

        $approverUserId = (int) ($saved['comptroller_approved_by_user_id'] ?? 0);
        $approverLabel = $approverUserId > 0
            ? ($approverLabelsByUserId[$approverUserId] ?? 'Comptroller')
            : null;
        $savedQtyStatus = trim((string) ($saved['comptroller_qty_status'] ?? ''));
        $rowQtyStatus = $hasSavedApproval && $savedQtyStatus !== ''
            ? $savedQtyStatus
            : ($hasSavedApproval ? $status : null);

        $lines[] = array_merge($line, [
            'requested_qty' => $requestedQty,
            'accepted_qty' => $acceptedQty,
            'deferred_qty' => $deferredQty,
            'deferred_message' => $deferredMessage,
            'deferred_amount' => $deferredAmount,
            'approved_line_total' => $approvedLineTotal,
            'comptroller_qty_status' => $rowQtyStatus,
            'comptroller_approved_at' => $saved['comptroller_approved_at'] ?? null,
            'comptroller_approved_by_label' => $approverLabel,
            'qty_per_set' => max(1, (int) ($line['qty_per_set'] ?? 1)),
            'requisition_qty' => $requestedQty,
        ]);
    }

    $itemCount = (int) ($overview['item_count'] ?? count($lines));
    $selectedCount = (int) ($overview['selected_count'] ?? 0);

    return array_merge($overview, [
        'lines' => $lines,
        'item_count' => $itemCount,
        'selected_count' => $selectedCount,
        'fully_approved_count' => $fullyApprovedCount,
        'approved_grand_total' => round($approvedGrandTotal, 2),
    ]);
}

/**
 * @param array<int, int>    $acceptedByDetail canvass_detail_id => accepted_qty
 * @param array<int, string> $messagesByDetail canvass_detail_id => comptroller deferred reason
 */
function cwirmsSaveComptrollerQtyApprovals(
    PDO $db,
    int $requestId,
    int $userId,
    array $acceptedByDetail,
    array $messagesByDetail = []
): void {
    $overview = cwirmsComptrollerPricingOverviewForRequest($db, $requestId);
    $lines = $overview['lines'] ?? [];
    if ($lines === []) {
        throw new InvalidArgumentException('No suggested supplier lines found for this request.');
    }

    $update = $db->prepare(
        'UPDATE request_approval_suggested_supplier_item
         SET accepted_qty = ?,
             deferred_qty = ?,
             deferred_message = ?,
             comptroller_qty_status = ?,
             comptroller_approved_by_user_id = ?,
             comptroller_approved_at = NOW()
         WHERE request_id = ? AND canvass_detail_id = ?'
    );

    foreach ($lines as $line) {
        $detailId = (int) ($line['canvass_detail_id'] ?? 0);
        if ($detailId <= 0) {
            continue;
        }
        $requestedQty = max(0, (int) ($line['requested_qty'] ?? $line['quantity'] ?? 0));
        if (!array_key_exists($detailId, $acceptedByDetail)) {
            throw new InvalidArgumentException('Accepted quantity is required for every line item.');
        }
        $acceptedQty = max(0, (int) $acceptedByDetail[$detailId]);
        if ($acceptedQty > $requestedQty) {
            throw new InvalidArgumentException(
                'Accepted quantity cannot exceed requested quantity for item: '
                . (string) ($line['item_name'] ?? 'line')
            );
        }

        $deferredQty = max(0, $requestedQty - $acceptedQty);
        $status = cwirmsComptrollerQtyStatus($acceptedQty, $requestedQty);
        $deferredMessage = null;
        if ($deferredQty > 0) {
            $msg = trim((string) ($messagesByDetail[$detailId] ?? ''));
            if ($msg === '') {
                throw new InvalidArgumentException(
                    'Please enter a reason for the deferred quantity on item: '
                    . (string) ($line['item_name'] ?? 'line')
                );
            }
            $deferredMessage = $msg;
        }

        $update->execute([
            $acceptedQty,
            $deferredQty,
            $deferredMessage,
            $status,
            $userId,
            $requestId,
            $detailId,
        ]);
    }
}

function cwirmsComptrollerCheckedByLabel(PDO $db, int $userId): string
{
    $stmt = $db->prepare('SELECT Email FROM user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $email = (string) ($row['Email'] ?? '');
    if ($email === '') {
        return 'Comptroller';
    }

    return explode('@', $email)[0] ?? $email;
}

function cwirmsComptrollerRequestHasSuggestedSuppliersPerItem(PDO $db, int $requestId): bool
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

function cwirmsApplyComptrollerCanvasApproval(PDO $db, int $requestId, int $userId, string $compStatus): void
{
    if (!in_array($compStatus, ['accept', 'reject', 'pending'], true)) {
        throw new InvalidArgumentException('Invalid comptroller approval status.');
    }

    $checkedBy = cwirmsComptrollerCheckedByLabel($db, $userId);
    $requisitionStatus = ($compStatus === 'pending') ? 'Pending' : 'Ongoing';

    $find = $db->prepare(
        'SELECT comp_status, checked_by FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
    );
    $find->execute([$requestId]);
    $existing = $find->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($compStatus === 'pending') {
            $up = $db->prepare(
                'UPDATE canvass_verification_approval
                 SET checked_by = NULL, checked_at = NULL, comp_status = ?
                 WHERE request_id = ?'
            );
            $up->execute([$compStatus, $requestId]);
        } else {
            $up = $db->prepare(
                'UPDATE canvass_verification_approval
                 SET checked_by = ?, checked_at = NOW(), comp_status = ?
                 WHERE request_id = ?'
            );
            $up->execute([$checkedBy, $compStatus, $requestId]);
        }
    } elseif ($compStatus !== 'pending') {
        ensureCanvassVerificationApprovalRow($db, $requestId);
        $ins = $db->prepare(
            'UPDATE canvass_verification_approval
             SET checked_by = ?, checked_at = NOW(), comp_status = ?
             WHERE request_id = ?'
        );
        $ins->execute([$checkedBy, $compStatus, $requestId]);
    }

    $updReq = $db->prepare('UPDATE requisition_item SET status = ? WHERE request_id = ?');
    $updReq->execute([$requisitionStatus, $requestId]);

    $logIns = $db->prepare(
        'INSERT INTO comptroller_action_history (request_id, user_id, action) VALUES (?, ?, ?)'
    );
    $logIns->execute([$requestId, $userId, $compStatus]);
}
