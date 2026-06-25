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
 * Builds the comptroller pricing overview by layering quantity-approval data from
 * requisition_line_awards on top of the base canvass pricing overview.
 *
 * Column mapping from old request_approval_suggested_supplier_item → requisition_line_awards:
 *   accepted_qty                  → awarded_qty
 *   deferred_message              → deferred_reason
 *   comptroller_qty_status        → comptroller_status
 *   comptroller_approved_by_user_id → awarded_by_user_id
 *   comptroller_approved_at       → awarded_at
 *
 * @return array<string, mixed>
 */
function cwirmsComptrollerPricingOverviewForRequest(PDO $db, int $requestId): array
{
    $overview = cwirmsCanvassPricingOverviewForRequest($db, $requestId);
    if ($requestId <= 0 || ($overview['lines'] ?? []) === []) {
        return $overview;
    }

    // Read stored comptroller qty approvals from the canonical requisition_line_awards table.
    // canvass_detail_id is aliased to requisition_line_id in the base pricing overview,
    // so we key this map on requisition_line_id for a direct lookup.
    $savedStmt = $db->prepare(
        'SELECT rla.requisition_line_id,
                rla.awarded_qty,
                rla.deferred_qty,
                rla.deferred_reason,
                rla.comptroller_status,
                rla.awarded_by_user_id,
                rla.awarded_at
         FROM requisition_line_awards rla
         INNER JOIN requisition_line rl ON rl.requisition_line_id = rla.requisition_line_id
         WHERE rl.request_id = ?'
    );
    $savedStmt->execute([$requestId]);
    $savedByLine   = [];
    $approverUserIds = [];
    while ($row = $savedStmt->fetch(PDO::FETCH_ASSOC)) {
        $lid = (int) ($row['requisition_line_id'] ?? 0);
        if ($lid > 0) {
            $savedByLine[$lid] = $row;
            $approverId = (int) ($row['awarded_by_user_id'] ?? 0);
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

    $lines              = [];
    $approvedGrandTotal = 0.0;
    $fullyApprovedCount = 0;

    foreach ($overview['lines'] as $line) {
        $requestedQty = max(0, (int) ($line['quantity'] ?? 0));
        // canvass_detail_id is aliased to requisition_line_id in the pricing overview.
        $lineId = (int) ($line['canvass_detail_id'] ?? 0);
        $saved  = $savedByLine[$lineId] ?? null;

        $acceptedQty     = $requestedQty;
        $hasSavedApproval = $saved !== null && !empty($saved['awarded_at']);
        if ($hasSavedApproval && $saved['awarded_qty'] !== null && $saved['awarded_qty'] !== '') {
            $acceptedQty = max(0, (int) $saved['awarded_qty']);
        }
        if ($acceptedQty > $requestedQty) {
            $acceptedQty = $requestedQty;
        }

        $deferredQty       = max(0, $requestedQty - $acceptedQty);
        $unitPrice         = isset($line['unit_price']) && is_numeric($line['unit_price'])
            ? (float) $line['unit_price']
            : null;
        $discountPercent   = cwirmsNormalizeCanvassSupplierDiscountPercent($line['discount_percent'] ?? null);
        $discountFactor    = $discountPercent !== null ? (1 - $discountPercent / 100) : 1.0;
        $approvedLineTotal = $unitPrice !== null
            ? round($unitPrice * $acceptedQty * $discountFactor, 2)
            : null;
        $deferredAmount    = $unitPrice !== null
            ? round($unitPrice * $deferredQty * $discountFactor, 2)
            : null;

        if ($approvedLineTotal !== null) {
            $approvedGrandTotal += $approvedLineTotal;
        }

        $status = cwirmsComptrollerQtyStatus($acceptedQty, $requestedQty);
        if ($status === 'fully_approved' && ($line['supplier_id'] ?? null)) {
            $fullyApprovedCount++;
        }

        $deferredMessage = null;
        if ($saved !== null && trim((string) ($saved['deferred_reason'] ?? '')) !== '') {
            $deferredMessage = (string) $saved['deferred_reason'];
        }

        $approverUserId = (int) ($saved['awarded_by_user_id'] ?? 0);
        $approverLabel  = $approverUserId > 0
            ? ($approverLabelsByUserId[$approverUserId] ?? 'Comptroller')
            : null;
        $savedQtyStatus = trim((string) ($saved['comptroller_status'] ?? ''));
        $rowQtyStatus   = $hasSavedApproval && $savedQtyStatus !== ''
            ? $savedQtyStatus
            : ($hasSavedApproval ? $status : null);

        $lines[] = array_merge($line, [
            'requested_qty'               => $requestedQty,
            'accepted_qty'                => $acceptedQty,
            'deferred_qty'                => $deferredQty,
            'deferred_message'            => $deferredMessage,
            'deferred_amount'             => $deferredAmount,
            'approved_line_total'         => $approvedLineTotal,
            'comptroller_qty_status'      => $rowQtyStatus,
            'comptroller_approved_at'     => $saved['awarded_at'] ?? null,
            'comptroller_approved_by_label' => $approverLabel,
            'qty_per_set'                 => max(1, (int) ($line['qty_per_set'] ?? 1)),
            'requisition_qty'             => $requestedQty,
        ]);
    }

    $itemCount    = (int) ($overview['item_count'] ?? count($lines));
    $selectedCount = (int) ($overview['selected_count'] ?? 0);

    return array_merge($overview, [
        'lines'               => $lines,
        'item_count'          => $itemCount,
        'selected_count'      => $selectedCount,
        'fully_approved_count' => $fullyApprovedCount,
        'approved_grand_total' => round($approvedGrandTotal, 2),
    ]);
}

/**
 * Persist the comptroller's quantity decisions into requisition_line_awards.
 *
 * The $acceptedByLine map is keyed by requisition_line_id (the same value exposed
 * as canvass_detail_id in the pricing overview, so existing form POST arrays work
 * without any frontend changes during the transition period).
 *
 * Invariant enforced: deferred_qty + awarded_qty === requisition_line.quantity
 * Status enum: 'fully_approved' | 'deferred' | 'rejected'
 *
 * @param array<int, int>    $acceptedByLine  requisition_line_id => accepted_qty
 * @param array<int, string> $messagesByLine  requisition_line_id => deferred reason
 */
function cwirmsSaveComptrollerQtyApprovals(
    PDO $db,
    int $requestId,
    int $userId,
    array $acceptedByLine,
    array $messagesByLine = []
): void {
    $overview = cwirmsComptrollerPricingOverviewForRequest($db, $requestId);
    $lines    = $overview['lines'] ?? [];
    if ($lines === []) {
        throw new InvalidArgumentException('No suggested supplier lines found for this request.');
    }

    $update = $db->prepare(
        'UPDATE requisition_line_awards
         SET awarded_qty        = ?,
             deferred_qty       = ?,
             deferred_reason    = ?,
             comptroller_status = ?,
             awarded_by_user_id = ?,
             awarded_at         = NOW()
         WHERE requisition_line_id = ?'
    );

    foreach ($lines as $line) {
        // canvass_detail_id is aliased to requisition_line_id in the pricing overview.
        $lineId = (int) ($line['canvass_detail_id'] ?? 0);
        if ($lineId <= 0) {
            continue;
        }
        $requestedQty = max(0, (int) ($line['requested_qty'] ?? $line['quantity'] ?? 0));
        if (!array_key_exists($lineId, $acceptedByLine)) {
            throw new InvalidArgumentException('Accepted quantity is required for every line item.');
        }
        $acceptedQty = max(0, (int) $acceptedByLine[$lineId]);
        if ($acceptedQty > $requestedQty) {
            throw new InvalidArgumentException(
                'Accepted quantity cannot exceed requested quantity for item: '
                . (string) ($line['item_name'] ?? 'line')
            );
        }

        $deferredQty    = max(0, $requestedQty - $acceptedQty);
        $status         = cwirmsComptrollerQtyStatus($acceptedQty, $requestedQty);
        $deferredMessage = null;
        if ($deferredQty > 0) {
            $msg = trim((string) ($messagesByLine[$lineId] ?? ''));
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
            $lineId,
        ]);
    }
}

function cwirmsComptrollerCheckedByLabel(PDO $db, int $userId): string
{
    $stmt = $db->prepare('SELECT Email FROM user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row   = $stmt->fetch(PDO::FETCH_ASSOC);
    $email = (string) ($row['Email'] ?? '');
    if ($email === '') {
        return 'Comptroller';
    }

    return explode('@', $email)[0] ?? $email;
}

/**
 * True when every canvassed line has a GSD award in requisition_line_awards.
 */
function cwirmsComptrollerRequestHasSuggestedSuppliersPerItem(PDO $db, int $requestId): bool
{
    // Count lines that have canvassed quotes (these require a GSD award).
    $totalStmt = $db->prepare(
        "SELECT COUNT(DISTINCT rlq.requisition_line_id)
         FROM requisition_line_quotes rlq
         INNER JOIN requisition_line rl ON rl.requisition_line_id = rlq.requisition_line_id
         WHERE rl.request_id = ? AND rlq.quote_type = 'canvassed'"
    );
    $totalStmt->execute([$requestId]);
    $total = (int) $totalStmt->fetchColumn();
    if ($total <= 0) {
        return false;
    }

    $selStmt = $db->prepare(
        'SELECT COUNT(*)
         FROM requisition_line_awards rla
         INNER JOIN requisition_line rl ON rl.requisition_line_id = rla.requisition_line_id
         WHERE rl.request_id = ?'
    );
    $selStmt->execute([$requestId]);
    $selected = (int) $selStmt->fetchColumn();

    return $selected >= $total;
}

function cwirmsApplyComptrollerCanvasApproval(PDO $db, int $requestId, int $userId, string $compStatus): void
{
    if (!in_array($compStatus, ['accept', 'reject', 'pending'], true)) {
        throw new InvalidArgumentException('Invalid comptroller approval status.');
    }

    $checkedBy         = cwirmsComptrollerCheckedByLabel($db, $userId);
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

}
