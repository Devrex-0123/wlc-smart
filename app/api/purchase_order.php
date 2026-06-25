<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/approval_tables.php';
require_once __DIR__ . '/../helpers/supplier.php';
require_once __DIR__ . '/../helpers/purchase_order.php';
require_once __DIR__ . '/../helpers/user_notifications.php';

function poSendJson(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function poAssertSessionUserId(): int
{
    if (!isset($_SESSION['user_id'])) {
        poSendJson(['success' => false, 'message' => 'Unauthorized']);
    }

    return (int) $_SESSION['user_id'];
}

function poViewerRoleLc(PDO $db, int $userId): string
{
    $stmt = $db->prepare('SELECT role FROM user WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$userId]);

    return strtolower(trim((string) ($stmt->fetchColumn() ?: '')));
}

function poIsPresidentRole(string $roleLc): bool
{
    return in_array($roleLc, ['president', 'president verifier', 'verifier president', 'president_verifier'], true);
}

function poIsComptrollerRole(string $roleLc): bool
{
    return $roleLc === 'comptroller';
}

function poIsPresidentApproved(array $header): bool
{
    $status = strtolower(trim((string) ($header['status'] ?? '')));

    return $status === 'approved' || (int) ($header['approved_by_president'] ?? 0) === 1;
}

function poAssertPresidentApprovedPo(array $header): void
{
    if (!poIsPresidentApproved($header)) {
        poSendJson([
            'success' => false,
            'message' => 'Tax computation is available only after the President approves this purchase order.',
        ]);
    }
}

function poAssertComptroller(PDO $db, int $userId): void
{
    if (!poIsComptrollerRole(poViewerRoleLc($db, $userId))) {
        poSendJson(['success' => false, 'message' => 'Forbidden']);
    }
}

function poAssertInventoryManager(PDO $db, int $userId): void
{
    $role = poViewerRoleLc($db, $userId);
    if ($role !== 'inventory manager' && $role !== 'inventory_manager') {
        poSendJson(['success' => false, 'message' => 'Forbidden']);
    }
}

/**
 * BIR EWT rates keyed by normalized transaction type slug.
 * @return array<string, array{label: string, rate: float}>
 */
function poEwtTransactionTypePresets(): array
{
    return [
        'goods'                  => ['label' => 'Purchase of Goods / Supplies',       'rate' => 0.01],
        'services'               => ['label' => 'Purchase of Services',               'rate' => 0.02],
        'professional_small'     => ['label' => 'Professional Fees (≤ ₱3M income)',   'rate' => 0.10],
        'professional_large'     => ['label' => 'Professional Fees (Corp / > ₱3M)',   'rate' => 0.15],
        'rental'                 => ['label' => 'Rental',                             'rate' => 0.05],
        'construction'           => ['label' => 'Construction / Contractor',          'rate' => 0.02],
        'media'                  => ['label' => 'Media / Talent / Entertainment',     'rate' => 0.15],
    ];
}

/** @return array<string, float> */
function poEwtTransactionRateMap(): array
{
    $out = [];
    foreach (poEwtTransactionTypePresets() as $slug => $info) {
        $out[$slug] = $info['rate'];
    }

    return $out;
}

function poNormalizeEwtTransactionType(string $value): string
{
    $presets = poEwtTransactionTypePresets();
    $trim = trim($value);
    if (isset($presets[$trim])) {
        return $presets[$trim]['label'];
    }

    return poSanitizeString($trim, 100);
}

/**
 * @return array<int, array<string, mixed>>
 */
function poNormalizeTaxRows(mixed $taxesRaw, float $grossAmount): array
{
    if (is_string($taxesRaw)) {
        $decoded = json_decode($taxesRaw, true);
        $taxesRaw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($taxesRaw)) {
        poSendJson(['success' => false, 'message' => 'Invalid tax payload.']);
    }

    $allowedEwtRates = [0.01, 0.02, 0.05, 0.1];
    $normalized = [];
    foreach ($taxesRaw as $idx => $row) {
        if (!is_array($row)) {
            continue;
        }
        $taxType = strtolower(trim((string) ($row['tax_type'] ?? '')));
        if ($taxType === '') {
            continue;
        }
        if ($taxType === 'vat') {
            $taxType = 'vat withholding';
        }
        if (!in_array($taxType, ['ewt', 'vat withholding', 'other'], true)) {
            poSendJson(['success' => false, 'message' => 'Invalid tax type at row ' . ($idx + 1) . '.']);
        }

        $label = poSanitizeString($row['label'] ?? '', 100);
        $rate = round((float) ($row['rate'] ?? 0), 4);
        $amountDeducted = round((float) ($row['amount_deducted'] ?? 0), 2);
        $rateOverride = !empty($row['rate_override']) || (int) ($row['rate_override'] ?? 0) === 1;
        $transactionType = poNormalizeEwtTransactionType((string) ($row['transaction_type'] ?? ''));
        $supplierVatRegistered = (int) ($row['supplier_vat_registered'] ?? 0) === 1 ? 1 : 0;
        $transactionVatExempt = (int) ($row['transaction_vat_exempt'] ?? 0) === 1 ? 1 : 0;

        if ($taxType === 'ewt') {
            if ($transactionType === '') {
                poSendJson(['success' => false, 'message' => 'EWT requires a transaction type.']);
            }
            if (!$rateOverride && !in_array($rate, $allowedEwtRates, true)) {
                poSendJson(['success' => false, 'message' => 'EWT rate must be 1%, 2%, 5%, or 10% unless manually overridden.']);
            }
            if ($rateOverride && ($rate <= 0 || $rate > 1)) {
                poSendJson(['success' => false, 'message' => 'EWT override rate must be between 0 and 100%.']);
            }
            $amountDeducted = round($grossAmount * $rate, 2);
            $normalized[] = [
                'tax_type' => 'EWT',
                'transaction_type' => $transactionType,
                'rate' => $rate,
                'rate_override' => $rateOverride ? 1 : 0,
                'amount_deducted' => $amountDeducted,
                'label' => null,
                'supplier_vat_registered' => null,
                'transaction_vat_exempt' => null,
            ];
            continue;
        }

        if ($taxType === 'vat withholding') {
            if ($supplierVatRegistered === 0) {
                poSendJson(['success' => false, 'message' => 'VAT withholding cannot be applied to non-VAT registered supplier']);
            }
            if ($transactionVatExempt === 1) {
                poSendJson(['success' => false, 'message' => 'VAT withholding cannot be applied to VAT-exempt transaction']);
            }
            $rate = 0.05;
            $amountDeducted = round($grossAmount * $rate, 2);
            $normalized[] = [
                'tax_type' => 'VAT Withholding',
                'transaction_type' => null,
                'rate' => $rate,
                'rate_override' => 0,
                'amount_deducted' => $amountDeducted,
                'label' => null,
                'supplier_vat_registered' => 1,
                'transaction_vat_exempt' => 0,
            ];
            continue;
        }

        if ($label === '') {
            poSendJson(['success' => false, 'message' => 'Other deductions require a label.']);
        }
        if ($amountDeducted <= 0) {
            poSendJson(['success' => false, 'message' => 'Other deductions require a positive amount.']);
        }
        $normalized[] = [
            'tax_type' => 'Other',
            'transaction_type' => null,
            'rate' => $grossAmount > 0 ? round($amountDeducted / $grossAmount, 4) : 0.0,
            'rate_override' => 0,
            'amount_deducted' => $amountDeducted,
            'label' => $label,
            'supplier_vat_registered' => null,
            'transaction_vat_exempt' => null,
        ];
    }

    return $normalized;
}

/**
 * @return array{taxes: array<int, array<string, mixed>>, notes: string, net_payable: ?float, tax_computed: bool, tax_status: string, tax_finalized_at: ?string, po_status: string, computed_by: ?int, computed_at: ?string}
 */
function poFetchTaxRecord(PDO $db, int $poId): array
{
    $headerStmt = $db->prepare(
        'SELECT net_payable, tax_computed, tax_status, tax_finalized_at, status,
                total_amount, taxable_amount
         FROM purchase_orders WHERE id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $headerStmt->execute([$poId]);
    $header = $headerStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $taxStmt = $db->prepare(
        'SELECT tax_type, transaction_type, rate, rate_override, amount_deducted, label,
                supplier_vat_registered, transaction_vat_exempt, notes, computed_by, computed_at
         FROM purchase_order_taxes
         WHERE purchase_order_id = ?
         ORDER BY id ASC'
    );
    $taxStmt->execute([$poId]);
    $rows = $taxStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $notes = '';
    $computedBy = null;
    $computedAt = null;
    if ($rows !== []) {
        $first = $rows[0];
        $notes = (string) ($first['notes'] ?? '');
        $computedBy = !empty($first['computed_by']) ? (int) $first['computed_by'] : null;
        $computedAt = $first['computed_at'] ?? null;
    }

    $rawTaxable = $header['taxable_amount'] ?? null;
    $grossForTax = ($rawTaxable !== null && (float) $rawTaxable > 0)
        ? round((float) $rawTaxable, 2)
        : round((float) ($header['total_amount'] ?? 0), 2);

    return [
        'taxes' => array_map(static function (array $row): array {
            return [
                'tax_type' => (string) ($row['tax_type'] ?? ''),
                'transaction_type' => $row['transaction_type'] ?? null,
                'rate' => round((float) ($row['rate'] ?? 0), 4),
                'rate_override' => (int) ($row['rate_override'] ?? 0) === 1,
                'amount_deducted' => round((float) ($row['amount_deducted'] ?? 0), 2),
                'label' => $row['label'] ?? null,
                'supplier_vat_registered' => $row['supplier_vat_registered'] !== null
                    ? (int) $row['supplier_vat_registered']
                    : null,
                'transaction_vat_exempt' => $row['transaction_vat_exempt'] !== null
                    ? (int) $row['transaction_vat_exempt']
                    : null,
            ];
        }, $rows),
        'notes' => $notes,
        'net_payable' => isset($header['net_payable']) && $header['net_payable'] !== null
            ? round((float) $header['net_payable'], 2)
            : null,
        'tax_computed' => (int) ($header['tax_computed'] ?? 0) === 1,
        'tax_status' => (string) ($header['tax_status'] ?? 'draft'),
        'tax_finalized_at' => $header['tax_finalized_at'] ?? null,
        'po_status' => (string) ($header['status'] ?? 'pending'),
        'computed_by' => $computedBy,
        'computed_at' => $computedAt,
        'gross_amount' => $grossForTax,
    ];
}

/**
 * @param array<int, array<string, mixed>> $taxRows
 */
function poPersistTaxComputation(
    PDO $db,
    int $poId,
    int $userId,
    array $taxRows,
    string $notes,
    float $netPayable,
    string $taxStatus,
    bool $finalize = false
): void {
    $del = $db->prepare('DELETE FROM purchase_order_taxes WHERE purchase_order_id = ?');
    $del->execute([$poId]);

    $insert = $db->prepare(
        'INSERT INTO purchase_order_taxes
         (purchase_order_id, tax_type, transaction_type, rate, rate_override, amount_deducted, label,
          supplier_vat_registered, transaction_vat_exempt, notes, computed_by, computed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    foreach ($taxRows as $taxRow) {
        $insert->execute([
            $poId,
            $taxRow['tax_type'],
            $taxRow['transaction_type'],
            $taxRow['rate'],
            (int) ($taxRow['rate_override'] ?? 0),
            $taxRow['amount_deducted'],
            $taxRow['label'],
            $taxRow['supplier_vat_registered'],
            $taxRow['transaction_vat_exempt'],
            $notes !== '' ? $notes : null,
            $userId,
        ]);
    }

    if ($finalize) {
        $upd = $db->prepare(
            'UPDATE purchase_orders
             SET net_payable = ?, tax_computed = 1, tax_status = ?, tax_finalized_at = NOW(),
                 status = ?, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL'
        );
        $upd->execute([$netPayable, 'finalized', 'ready_for_release', $poId]);

        return;
    }

    $upd = $db->prepare(
        'UPDATE purchase_orders
         SET net_payable = ?, tax_computed = 1, tax_status = ?, tax_finalized_at = NULL, updated_at = NOW()
         WHERE id = ? AND deleted_at IS NULL'
    );
    $upd->execute([$netPayable, $taxStatus, $poId]);
}

/**
 * @return array{taxRows: array<int, array<string, mixed>>, notes: string, netPayable: float, grossAmount: float, header: array<string, mixed>}
 */
function poPrepareTaxSavePayload(PDO $db, int $poId, int $userId, mixed $taxesRaw, mixed $notesRaw, mixed $netPayableRaw): array
{
    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    poAssertPresidentApprovedPo($record['header']);

    $header = $record['header'];
    $currentTaxStatus = strtolower(trim((string) ($header['tax_status'] ?? 'draft')));
    if ($currentTaxStatus === 'finalized') {
        poSendJson(['success' => false, 'message' => 'Tax computation is finalized. Reopen for edit before making changes.']);
    }

    // Use taxable_amount (after fees + discounts) when available; fall back to raw total_amount.
    $rawTaxable = $header['taxable_amount'] ?? null;
    $grossAmount = ($rawTaxable !== null && (float) $rawTaxable > 0)
        ? round((float) $rawTaxable, 2)
        : round((float) ($header['total_amount'] ?? 0), 2);
    $taxRows = poNormalizeTaxRows($taxesRaw, $grossAmount);
    $notes = poSanitizeString($notesRaw ?? '', 65535);
    $netPayable = round((float) $netPayableRaw, 2);

    $deductionSum = 0.0;
    foreach ($taxRows as $taxRow) {
        $deductionSum += (float) $taxRow['amount_deducted'];
    }
    $deductionSum = round($deductionSum, 2);
    $expectedNet = round($grossAmount - $deductionSum, 2);
    if (abs($expectedNet - $netPayable) > 0.02) {
        $netPayable = $expectedNet;
    }
    if ($taxRows === []) {
        $netPayable = $grossAmount;
    }

    return [
        'taxRows' => $taxRows,
        'notes' => $notes,
        'netPayable' => $netPayable,
        'grossAmount' => $grossAmount,
        'header' => $header,
    ];
}

function poInsertPaymentReadyNotification(PDO $db, array $header, float $netPayable): void
{
    $recipientId = (int) ($header['requested_by_user_id'] ?? 0);
    if ($recipientId <= 0) {
        return;
    }

    $requesterName = trim((string) ($header['requested_by_name'] ?? ''));
    if ($requesterName === '') {
        $userStmt = $db->prepare('SELECT full_name, Email FROM user WHERE user_id = ? LIMIT 1');
        $userStmt->execute([$recipientId]);
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $requesterName = trim((string) ($userRow['full_name'] ?? ''));
        if ($requesterName === '') {
            $requesterName = explode('@', (string) ($userRow['Email'] ?? 'Requester'))[0] ?? 'Requester';
        }
    }

    cwirmsInsertUserNotification(
        $db,
        $recipientId,
        'payment_ready',
        (int) ($header['id'] ?? 0),
        isset($header['requisition_id']) ? (int) $header['requisition_id'] : null,
        [
            'po_number' => (string) ($header['po_number'] ?? ''),
            'requester_full_name' => $requesterName,
            'net_payable' => round($netPayable, 2),
        ]
    );
}

function poSanitizeString(mixed $value, int $maxLen): string
{
    $text = trim((string) ($value ?? ''));

    return mb_substr($text, 0, $maxLen);
}

function poResolveModeOfPaymentFromTotal(float $totalAmount): string
{
    return cwirmsResolvePoModeOfPayment($totalAmount);
}

function poSyncModeOfPaymentFromTotal(PDO $db, int $poId, float $totalAmount, array $header): void
{
    if ($poId <= 0) {
        return;
    }
    $computed = poResolveModeOfPaymentFromTotal($totalAmount);
    $stored = strtolower(trim((string) ($header['mode_of_payment'] ?? '')));
    if ($stored === $computed) {
        return;
    }
    $upd = $db->prepare(
        'UPDATE purchase_orders SET mode_of_payment = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL'
    );
    $upd->execute([$computed, $poId]);
    $header['mode_of_payment'] = $computed;
}

function poEnrichSupplierTin(PDO $db, array $header): string
{
    $tin = trim((string) ($header['supplier_tin'] ?? ''));
    if ($tin !== '') {
        $normalized = cwirmsNormalizeSupplierTin($tin);

        return $normalized ?? $tin;
    }

    ensureSupplierTinColumn($db);
    $supplierId = (int) ($header['supplier_id'] ?? 0);
    if ($supplierId > 0) {
        $stmt = $db->prepare('SELECT tin FROM suppliers WHERE supplier_id = ? LIMIT 1');
        $stmt->execute([$supplierId]);
        $raw = $stmt->fetchColumn();
        if ($raw !== false && $raw !== null && trim((string) $raw) !== '') {
            return cwirmsNormalizeSupplierTin($raw) ?? trim((string) $raw);
        }
    }

    $supplierName = trim((string) ($header['supplier_name'] ?? ''));
    if ($supplierName !== '') {
        $stmt = $db->prepare('SELECT tin FROM suppliers WHERE supplier_name = ? ORDER BY supplier_id ASC LIMIT 1');
        $stmt->execute([$supplierName]);
        $raw = $stmt->fetchColumn();
        if ($raw !== false && $raw !== null && trim((string) $raw) !== '') {
            return cwirmsNormalizeSupplierTin($raw) ?? trim((string) $raw);
        }
    }

    return '';
}

/**
 * @return array{quantity: int, unit_price: float, amount: float, description: string, sub_description: ?string}
 */
function poNormalizeLineRow(mixed $row, int $index): array
{
    if (!is_array($row)) {
        poSendJson(['success' => false, 'message' => 'Invalid line item at row ' . ($index + 1)]);
    }

    $description = poSanitizeString($row['description'] ?? '', 255);
    if ($description === '') {
        poSendJson(['success' => false, 'message' => 'Line ' . ($index + 1) . ': description is required.']);
    }

    $subDescription = poSanitizeString($row['sub_description'] ?? '', 255);
    $quantity = (int) ($row['quantity'] ?? 0);
    if ($quantity < 1) {
        poSendJson(['success' => false, 'message' => 'Line ' . ($index + 1) . ': quantity must be at least 1.']);
    }

    $unitPriceRaw = $row['unit_price'] ?? null;
    if ($unitPriceRaw === null || $unitPriceRaw === '' || !is_numeric($unitPriceRaw)) {
        poSendJson(['success' => false, 'message' => 'Line ' . ($index + 1) . ': unit price is required.']);
    }
    $unitPrice = round((float) $unitPriceRaw, 2);
    if ($unitPrice < 0) {
        poSendJson(['success' => false, 'message' => 'Line ' . ($index + 1) . ': unit price cannot be negative.']);
    }

    $amount = round($quantity * $unitPrice, 2);

    return [
        'description' => $description,
        'sub_description' => $subDescription !== '' ? $subDescription : null,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'amount' => $amount,
    ];
}

function poGenerateNextNumber(PDO $db): string
{
    $stmt = $db->query(
        "SELECT po_number FROM purchase_orders
         WHERE po_number REGEXP '^PO-[0-9]+$'
         ORDER BY CAST(SUBSTRING(po_number, 4) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $last = (string) ($stmt->fetchColumn() ?: '');
    $next = 1;
    if ($last !== '' && preg_match('/^PO-(\d+)$/i', $last, $matches)) {
        $next = (int) $matches[1] + 1;
    }

    return 'PO-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

function poFetchById(PDO $db, int $poId, bool $includeDeleted = false): ?array
{
    $deletedFilter = $includeDeleted ? '' : ' AND po.deleted_at IS NULL';
    $sql = "SELECT po.*, COALESCE(s.vat_registered, 0) AS supplier_vat_registered
            FROM purchase_orders po
            LEFT JOIN suppliers s ON s.supplier_id = po.supplier_id
            WHERE po.id = ?{$deletedFilter}
            LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute([$poId]);
    $header = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$header) {
        return null;
    }

    $lineStmt = $db->prepare(
        'SELECT id, description, sub_description, quantity, unit_price, amount, sort_order
         FROM purchase_order_lines
         WHERE purchase_order_id = ?
         ORDER BY sort_order ASC, id ASC'
    );
    $lineStmt->execute([$poId]);
    $lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'header' => $header,
        'lines' => $lines,
    ];
}

function poCanView(PDO $db, int $userId, array $header): bool
{
    $role = poViewerRoleLc($db, $userId);
    if (
        poIsPresidentRole($role)
        || $role === 'inventory manager'
        || $role === 'inventory_manager'
        || $role === 'comptroller'
    ) {
        return true;
    }

    $createdBy = (int) ($header['created_by_user_id'] ?? 0);
    $requestedBy = (int) ($header['requested_by_user_id'] ?? 0);
    if ($userId === $createdBy || $userId === $requestedBy) {
        return true;
    }

    $requisitionId = (int) ($header['requisition_id'] ?? 0);
    if ($requisitionId > 0) {
        $own = $db->prepare('SELECT 1 FROM requisition_item WHERE request_id = ? AND user_id = ? LIMIT 1');
        $own->execute([$requisitionId, $userId]);

        return (bool) $own->fetchColumn();
    }

    return false;
}

function poAssertPoRequester(PDO $db, int $userId, array $header): void
{
    $requestedBy = (int) ($header['requested_by_user_id'] ?? 0);
    if ($requestedBy <= 0 || $requestedBy !== $userId) {
        poSendJson(['success' => false, 'message' => 'Only the requester may perform this action.']);
    }
}

function poFormatRecord(PDO $db, array $header, array $lines): array
{
    $dateIssued = '';
    if (!empty($header['created_at'])) {
        $ts = strtotime((string) $header['created_at']);
        $dateIssued = $ts ? date('F j, Y', $ts) : (string) $header['created_at'];
    }

    $totalAmount = round((float) ($header['total_amount'] ?? 0), 2);
    $modeOfPayment = poResolveModeOfPaymentFromTotal($totalAmount);
    $supplierTin = poEnrichSupplierTin($db, $header);

    $requestedByName = (string) ($header['requested_by_name'] ?? '');
    $requisitionId = (int) ($header['requisition_id'] ?? 0);
    if ($requisitionId > 0) {
        $nameStmt = $db->prepare('SELECT requester_name FROM requisition_item WHERE request_id = ? LIMIT 1');
        $nameStmt->execute([$requisitionId]);
        $storedName = trim((string) ($nameStmt->fetchColumn() ?: ''));
        if ($storedName !== '') {
            $requestedByName = $storedName;
        }
    }

    return [
        'id' => (int) ($header['id'] ?? 0),
        'po_number' => (string) ($header['po_number'] ?? ''),
        'requisition_id' => $header['requisition_id'] !== null ? (int) $header['requisition_id'] : null,
        'requested_by_user_id' => $header['requested_by_user_id'] !== null ? (int) $header['requested_by_user_id'] : null,
        'requested_by' => $requestedByName, 
        'facility_id' => $header['facility_id'] !== null ? (int) $header['facility_id'] : null,
        'location_facility' => (string) ($header['location_facility'] ?? ''),
        'supplier_id' => $header['supplier_id'] !== null ? (int) $header['supplier_id'] : null,
        'supplier_name' => (string) ($header['supplier_name'] ?? ''),
        'supplier_tin' => $supplierTin,
        'mode_of_payment' => $modeOfPayment,
        'mode_of_payment_label' => cwirmsFormatPoModeOfPaymentLabel($modeOfPayment),
        'purpose_of_request' => (string) ($header['purpose_of_request'] ?? ''),
        'total_amount' => $totalAmount,
        'net_payable' => isset($header['net_payable']) && $header['net_payable'] !== null
            ? round((float) $header['net_payable'], 2)
            : null,
        'tax_computed' => (int) ($header['tax_computed'] ?? 0) === 1,
        'tax_status' => (string) ($header['tax_status'] ?? 'draft'),
        'tax_finalized_at' => $header['tax_finalized_at'] ?? null,
        'payment_released_at' => $header['payment_released_at'] ?? null,
        'items_received_at' => $header['items_received_at'] ?? null,
        'status' => (string) ($header['status'] ?? 'pending'),
        'approved_by_president' => (int) ($header['approved_by_president'] ?? 0) === 1,
        'approved_at' => $header['approved_at'] ?? null,
        'date_issued' => $dateIssued,
        'created_at' => $header['created_at'] ?? null,
        'shipping_fee'              => round((float) ($header['shipping_fee']              ?? 0), 2),
        'shipping_method'           => (string) ($header['shipping_method']           ?? ''),
        'shipping_address'          => (string) ($header['shipping_address']          ?? ''),
        'handling_fee'              => round((float) ($header['handling_fee']              ?? 0), 2),
        'insurance_fee'             => round((float) ($header['insurance_fee']             ?? 0), 2),
        'installation_fee'          => round((float) ($header['installation_fee']          ?? 0), 2),
        'other_charges'             => round((float) ($header['other_charges']             ?? 0), 2),
        'other_charges_description' => (string) ($header['other_charges_description'] ?? ''),
        'discount_amount'           => round((float) ($header['discount_amount']           ?? 0), 2),
        'discount_percentage'       => round((float) ($header['discount_percentage']       ?? 0), 2),
        'discount_reason'           => (string) ($header['discount_reason']           ?? ''),
        'taxable_amount'            => isset($header['taxable_amount']) && $header['taxable_amount'] !== null
            ? round((float) $header['taxable_amount'], 2)
            : null,
        'payment_terms'             => (string) ($header['payment_terms']             ?? ''),
        'payment_due_date'          => $header['payment_due_date'] ?? null,
        'transaction_type'          => (string) ($header['transaction_type']          ?? ''),
        'supplier_vat_registered'   => (int) ($header['supplier_vat_registered'] ?? 0) === 1,
        'lines' => array_map(static function (array $line): array {
            return [
                'id' => (int) ($line['id'] ?? 0),
                'description' => (string) ($line['description'] ?? ''),
                'sub_description' => (string) ($line['sub_description'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'unit_price' => round((float) ($line['unit_price'] ?? 0), 2),
                'amount' => round((float) ($line['amount'] ?? 0), 2),
                'sort_order' => (int) ($line['sort_order'] ?? 0),
            ];
        }, $lines),
    ];
}

$db = Database::connect();
ensurePurchaseOrderTables($db);
ensureSupplierTinColumn($db);

$userId = poAssertSessionUserId();
$roleLc = poViewerRoleLc($db, $userId);
$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));

if ($action === 'create') {
    $linesRaw = $_POST['lines'] ?? null;
    if (is_string($linesRaw)) {
        $decoded = json_decode($linesRaw, true);
        $linesRaw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($linesRaw) || $linesRaw === []) {
        poSendJson(['success' => false, 'message' => 'At least one line item is required.']);
    }

    $normalizedLines = [];
    $totalAmount = 0.0;
    foreach ($linesRaw as $idx => $row) {
        $line = poNormalizeLineRow($row, (int) $idx);
        $normalizedLines[] = $line;
        $totalAmount += $line['amount'];
    }
    $totalAmount = round($totalAmount, 2);

    $requisitionId = (int) ($_POST['requisition_id'] ?? 0);
    if ($requisitionId > 0) {
        $reqCheck = $db->prepare('SELECT 1 FROM requisition_item WHERE request_id = ? LIMIT 1');
        $reqCheck->execute([$requisitionId]);
        if (!$reqCheck->fetchColumn()) {
            $requisitionId = 0;
        }
    } else {
        $requisitionId = 0;
    }

    $supplierId = (int) ($_POST['supplier_id'] ?? 0);
    $supplierName = poSanitizeString($_POST['supplier_name'] ?? '', 100);
    $supplierTin = cwirmsNormalizeSupplierTin($_POST['supplier_tin'] ?? null);
    if ($supplierId > 0) {
        $supStmt = $db->prepare('SELECT supplier_name, tin FROM suppliers WHERE supplier_id = ? LIMIT 1');
        $supStmt->execute([$supplierId]);
        $supRow = $supStmt->fetch(PDO::FETCH_ASSOC);
        if ($supRow) {
            if ($supplierName === '') {
                $supplierName = poSanitizeString($supRow['supplier_name'] ?? '', 100);
            }
            if ($supplierTin === null && !empty($supRow['tin'])) {
                $supplierTin = cwirmsNormalizeSupplierTin($supRow['tin']);
            }
        } else {
            $supplierId = 0;
        }
    }

    if ($supplierName === '') {
        poSendJson(['success' => false, 'message' => 'Supplier name is required.']);
    }

    $facilityId = (int) ($_POST['facility_id'] ?? 0);
    if ($facilityId > 0) {
        $facCheck = $db->prepare('SELECT 1 FROM facilities WHERE facility_id = ? LIMIT 1');
        $facCheck->execute([$facilityId]);
        if (!$facCheck->fetchColumn()) {
            $facilityId = 0;
        }
    } else {
        $facilityId = 0;
    }

    $mode = poResolveModeOfPaymentFromTotal($totalAmount);

    $requestedByName = poSanitizeString($_POST['requested_by'] ?? '', 150);
    if ($requestedByName === '') {
        poSendJson(['success' => false, 'message' => 'Requested by is required.']);
    }

    $locationFacility = poSanitizeString($_POST['location_facility'] ?? '', 255);
    if ($locationFacility === '') {
        poSendJson(['success' => false, 'message' => 'Location / facility is required.']);
    }

    $purpose = poSanitizeString($_POST['purpose_of_request'] ?? '', 65535);

    $db->beginTransaction();
    try {
        $poNumber = poGenerateNextNumber($db);
        $insert = $db->prepare(
            'INSERT INTO purchase_orders
             (po_number, requisition_id, requested_by_user_id, requested_by_name, facility_id,
              location_facility, supplier_id, supplier_name, supplier_tin,
              mode_of_payment, purpose_of_request, total_amount, status, created_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?)'
        );
        $insert->execute([
            $poNumber,
            $requisitionId > 0 ? $requisitionId : null,
            $userId,
            $requestedByName,
            $facilityId > 0 ? $facilityId : null,
            $locationFacility,
            $supplierId > 0 ? $supplierId : null,
            $supplierName,
            $supplierTin,
            $mode,
            $purpose !== '' ? $purpose : null,
            $totalAmount,
            $userId,
        ]);
        $poId = (int) $db->lastInsertId();

        $lineInsert = $db->prepare(
            'INSERT INTO purchase_order_lines
             (purchase_order_id, description, sub_description, quantity, unit_price, amount, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($normalizedLines as $sort => $line) {
            $lineInsert->execute([
                $poId,
                $line['description'],
                $line['sub_description'],
                $line['quantity'],
                $line['unit_price'],
                $line['amount'],
                $sort,
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        poSendJson(['success' => false, 'message' => 'Failed to create purchase order.']);
    }

    $record = poFetchById($db, $poId);
    poSendJson([
        'success' => true,
        'message' => 'Purchase order created successfully.',
        'data' => poFormatRecord($db, $record['header'], $record['lines']),
    ]);
}

if ($action === 'fetch') {
    $poId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Purchase order id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    if (!poCanView($db, $userId, $record['header'])) {
        poSendJson(['success' => false, 'message' => 'Forbidden']);
    }

    $header = $record['header'];
    $totalAmount = round((float) ($header['total_amount'] ?? 0), 2);
    poSyncModeOfPaymentFromTotal($db, $poId, $totalAmount, $header);

    poSendJson([
        'success' => true,
        'data' => poFormatRecord($db, $header, $record['lines']),
    ]);
}

if ($action === 'list') {
    $page = max(1, (int) ($_POST['page'] ?? $_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int) ($_POST['per_page'] ?? $_GET['per_page'] ?? 10)));
    $offset = ($page - 1) * $perPage;

    $where = 'WHERE deleted_at IS NULL';
    $params = [];
    if (!poIsPresidentRole($roleLc) && $roleLc !== 'inventory manager' && $roleLc !== 'inventory_manager') {
        $where .= ' AND (created_by_user_id = ? OR requested_by_user_id = ?)';
        $params[] = $userId;
        $params[] = $userId;
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM purchase_orders {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $lim = (int) $perPage;
    $off = (int) $offset;
    $listStmt = $db->prepare(
        "SELECT id, po_number, requested_by_name, supplier_name, total_amount, status,
                approved_by_president, created_at
         FROM purchase_orders
         {$where}
         ORDER BY created_at DESC, id DESC
         LIMIT {$lim} OFFSET {$off}"
    );
    $listStmt->execute($params);
    $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    poSendJson([
        'success' => true,
        'data' => [
            'items' => array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'po_number' => (string) ($row['po_number'] ?? ''),
                    'requested_by' => (string) ($row['requested_by_name'] ?? ''),
                    'supplier_name' => (string) ($row['supplier_name'] ?? ''),
                    'total_amount' => round((float) ($row['total_amount'] ?? 0), 2),
                    'status' => (string) ($row['status'] ?? 'pending'),
                    'approved_by_president' => (int) ($row['approved_by_president'] ?? 0) === 1,
                    'created_at' => $row['created_at'] ?? null,
                ];
            }, $rows),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
        ],
    ]);
}

if ($action === 'approve' || $action === 'reject' || $action === 'undo') {
    if (!poIsPresidentRole($roleLc)) {
        poSendJson(['success' => false, 'message' => 'Only the President may perform this action.']);
    }

    $poId = (int) ($_POST['id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Purchase order id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    $status = strtolower(trim((string) ($record['header']['status'] ?? 'pending')));

    if ($action === 'approve') {
        if ($status !== 'pending') {
            poSendJson(['success' => false, 'message' => 'Only pending purchase orders can be approved.']);
        }
        $upd = $db->prepare(
            'UPDATE purchase_orders
             SET status = \'approved\', approved_by_president = 1, approved_at = NOW(), updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL'
        );
        $upd->execute([$poId]);
        poSendJson(['success' => true, 'message' => 'Purchase order approved.']);
    }

    if ($action === 'reject') {
        if ($status !== 'pending') {
            poSendJson(['success' => false, 'message' => 'Only pending purchase orders can be rejected.']);
        }
        $upd = $db->prepare(
            'UPDATE purchase_orders
             SET status = \'rejected\', approved_by_president = 0, approved_at = NULL, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL'
        );
        $upd->execute([$poId]);
        poSendJson(['success' => true, 'message' => 'Purchase order rejected.']);
    }

    if ($action === 'undo') {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            poSendJson(['success' => false, 'message' => 'Only approved or rejected purchase orders can be undone.']);
        }
        $upd = $db->prepare(
            'UPDATE purchase_orders
             SET status = \'pending\', approved_by_president = 0, approved_at = NULL, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL'
        );
        $upd->execute([$poId]);
        poSendJson(['success' => true, 'message' => 'Purchase order decision reset to pending.']);
    }
}

if ($action === 'ensure_for_request') {
    require_once __DIR__ . '/../helpers/purchase_order.php';

    $requestId = (int) ($_POST['request_id'] ?? $_GET['request_id'] ?? 0);
    if ($requestId <= 0) {
        poSendJson(['success' => false, 'message' => 'Request id is required.']);
    }

    $reqCheck = $db->prepare('SELECT user_id FROM requisition_item WHERE request_id = ? LIMIT 1');
    $reqCheck->execute([$requestId]);
    $reqRow = $reqCheck->fetch(PDO::FETCH_ASSOC);
    if (!$reqRow) {
        poSendJson(['success' => false, 'message' => 'Request not found.']);
    }

    $role = poViewerRoleLc($db, $userId);
    $reqOwnerId = (int) ($reqRow['user_id'] ?? 0);
    $allowed = poIsPresidentRole($role)
        || $role === 'inventory manager'
        || $role === 'inventory_manager'
        || $role === 'comptroller'
        || $userId === $reqOwnerId;
    if (!$allowed) {
        poSendJson(['success' => false, 'message' => 'Forbidden']);
    }

    if (!cwirmsPurchaseRequisitionFullyAccepted($db, $requestId)) {
        poSendJson(['success' => false, 'message' => 'Purchase requisition must be fully verified before generating a purchase order.']);
    }

    $poId = cwirmsEnsurePurchaseOrderFromRequisition($db, $requestId, $userId);
    if ($poId === null || $poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Unable to generate purchase order for this request.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order was created but could not be loaded.']);
    }

    $header = $record['header'];
    $totalAmount = round((float) ($header['total_amount'] ?? 0), 2);
    poSyncModeOfPaymentFromTotal($db, $poId, $totalAmount, $header);

    poSendJson([
        'success' => true,
        'message' => 'Purchase order is ready.',
        'data' => poFormatRecord($db, $header, $record['lines']),
    ]);
}

if ($action === 'save_tax' || $action === 'save_tax_draft') {
    poAssertComptroller($db, $userId);

    $poId = (int) ($_POST['purchase_order_id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Purchase order id is required.']);
    }

    $payload = poPrepareTaxSavePayload(
        $db,
        $poId,
        $userId,
        $_POST['taxes'] ?? null,
        $_POST['notes'] ?? '',
        $_POST['net_payable'] ?? 0
    );

    $db->beginTransaction();
    try {
        poPersistTaxComputation(
            $db,
            $poId,
            $userId,
            $payload['taxRows'],
            $payload['notes'],
            $payload['netPayable'],
            'draft',
            false
        );
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        poSendJson(['success' => false, 'message' => 'Failed to save tax draft.']);
    }

    $saved = poFetchTaxRecord($db, $poId);
    poSendJson([
        'success' => true,
        'message' => 'Tax draft saved.',
        'net_payable' => $payload['netPayable'],
        'taxes' => $saved['taxes'],
        'notes' => $saved['notes'],
        'tax_computed' => true,
        'tax_status' => $saved['tax_status'],
        'tax_finalized_at' => $saved['tax_finalized_at'],
        'saved_at' => date('c'),
    ]);
}

if ($action === 'finalize_tax') {
    poAssertComptroller($db, $userId);

    $poId = (int) ($_POST['purchase_order_id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Purchase order id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    poAssertPresidentApprovedPo($record['header']);

    $header = $record['header'];
    $currentTaxStatus = strtolower(trim((string) ($header['tax_status'] ?? 'draft')));
    if ($currentTaxStatus === 'finalized') {
        poSendJson(['success' => false, 'message' => 'Tax computation is already finalized.']);
    }

    $payload = poPrepareTaxSavePayload(
        $db,
        $poId,
        $userId,
        $_POST['taxes'] ?? null,
        $_POST['notes'] ?? '',
        $_POST['net_payable'] ?? 0
    );

    $db->beginTransaction();
    try {
        poPersistTaxComputation(
            $db,
            $poId,
            $userId,
            $payload['taxRows'],
            $payload['notes'],
            $payload['netPayable'],
            'finalized',
            true
        );
        poInsertPaymentReadyNotification($db, $header, $payload['netPayable']);
        if ($db->inTransaction()) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
         poSendJson(['success' => false, 'message' => 'Failed to finalize tax computation: ' . $e->getMessage()]);
    }

    $saved = poFetchTaxRecord($db, $poId);
    poSendJson([
        'success' => true,
        'message' => 'Tax computation finalized. Requester has been notified.',
        'net_payable' => $payload['netPayable'],
        'taxes' => $saved['taxes'],
        'notes' => $saved['notes'],
        'tax_computed' => true,
        'tax_status' => $saved['tax_status'],
        'tax_finalized_at' => $saved['tax_finalized_at'],
        'po_status' => $saved['po_status'],
    ]);
}

if ($action === 'reopen_tax') {
    poAssertComptroller($db, $userId);

    $poId = (int) ($_POST['purchase_order_id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Purchase order id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    poAssertPresidentApprovedPo($record['header']);

    $header = $record['header'];
    $currentTaxStatus = strtolower(trim((string) ($header['tax_status'] ?? 'draft')));
    if ($currentTaxStatus !== 'finalized') {
        poSendJson(['success' => false, 'message' => 'Only finalized tax computations can be reopened.']);
    }

    $poStatus = strtolower(trim((string) ($header['status'] ?? '')));
    $restoreStatus = $poStatus === 'ready_for_release' ? 'approved' : $poStatus;

    $upd = $db->prepare(
        'UPDATE purchase_orders
         SET tax_status = ?, tax_finalized_at = NULL, status = ?, updated_at = NOW()
         WHERE id = ? AND deleted_at IS NULL'
    );
    $upd->execute(['draft', $restoreStatus, $poId]);

    $saved = poFetchTaxRecord($db, $poId);
    poSendJson([
        'success' => true,
        'message' => 'Tax computation reopened for editing.',
        'tax_status' => $saved['tax_status'],
        'tax_finalized_at' => $saved['tax_finalized_at'],
        'po_status' => $saved['po_status'],
        'taxes' => $saved['taxes'],
        'notes' => $saved['notes'],
        'net_payable' => $saved['net_payable'],
    ]);
}

if ($action === 'fetch_tax') {
    poAssertComptroller($db, $userId);

    $poId = (int) ($_GET['purchase_order_id'] ?? $_POST['purchase_order_id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Purchase order id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    poAssertPresidentApprovedPo($record['header']);

    $saved = poFetchTaxRecord($db, $poId);
    poSendJson([
        'success' => true,
        'taxes' => $saved['taxes'],
        'notes' => $saved['notes'],
        'net_payable' => $saved['net_payable'],
        'tax_computed' => $saved['tax_computed'],
        'tax_status' => $saved['tax_status'],
        'tax_finalized_at' => $saved['tax_finalized_at'],
        'po_status' => $saved['po_status'],
        'gross_amount' => $saved['gross_amount'],
        'computed_by' => $saved['computed_by'],
        'computed_at' => $saved['computed_at'],
        'transaction_type' => (string) ($record['header']['transaction_type'] ?? ''),
        'supplier_vat_registered' => (int) ($record['header']['supplier_vat_registered'] ?? 0) === 1,
    ]);
}

if ($action === 'mark_payment_released') {
    $poId = (int) ($_POST['purchase_order_id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Purchase order id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    $header = $record['header'];
    poAssertPoRequester($db, $userId, $header);

    $status = strtolower(trim((string) ($header['status'] ?? '')));
    if ($status !== 'ready_for_release') {
        poSendJson(['success' => false, 'message' => 'Payment can only be marked released once the purchase order is ready for release.']);
    }

    if (!empty($header['payment_released_at'])) {
        poSendJson(['success' => false, 'message' => 'Payment has already been marked as released.']);
    }

    $upd = $db->prepare(
        'UPDATE purchase_orders
         SET payment_released_at = NOW(), updated_at = NOW()
         WHERE id = ? AND deleted_at IS NULL'
    );
    $upd->execute([$poId]);

    $record = poFetchById($db, $poId);
    poSendJson([
        'success' => true,
        'message' => 'Payment marked as released to the supplier.',
        'data' => poFormatRecord($db, $record['header'], $record['lines']),
    ]);
}

if ($action === 'mark_items_received') {
    $poId = (int) ($_POST['purchase_order_id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Purchase order id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    $header = $record['header'];
    poAssertInventoryManager($db, $userId);

    if (empty($header['payment_released_at'])) {
        poSendJson(['success' => false, 'message' => 'Payment must be released by the requester before confirming receipt.']);
    }

    if (!empty($header['items_received_at'])) {
        poSendJson(['success' => false, 'message' => 'Items have already been marked as received.']);
    }

    $upd = $db->prepare(
        'UPDATE purchase_orders
         SET items_received_at = NOW(), status = ?, updated_at = NOW()
         WHERE id = ? AND deleted_at IS NULL'
    );
    $upd->execute(['completed', $poId]);

    $record = poFetchById($db, $poId);
    poSendJson([
        'success' => true,
        'message' => 'Items marked as received. This requisition is now complete.',
        'data' => poFormatRecord($db, $record['header'], $record['lines']),
    ]);
}

if ($action === 'delete') {
    $poId = (int) ($_POST['id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'Purchase order id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    if (!poCanView($db, $userId, $record['header']) && !poIsPresidentRole($roleLc)) {
        poSendJson(['success' => false, 'message' => 'Forbidden']);
    }

    $upd = $db->prepare(
        'UPDATE purchase_orders SET deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND deleted_at IS NULL'
    );
    $upd->execute([$poId]);
    poSendJson(['success' => true, 'message' => 'Purchase order deleted.']);
}

// ─────────────────────────────────────────────────────────────────────────────
// get_tax_presets  – returns transaction type list + VAT withholding rate
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'get_tax_presets') {
    poSendJson([
        'success'             => true,
        'transaction_types'   => poEwtTransactionTypePresets(),
        'vat_withholding_rate' => 0.05,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// save_transaction_type
//
// Saves the selected transaction type onto the PO and rebuilds the tax rows:
//   - Deletes all existing (non-finalized) tax rows for the PO
//   - Inserts an EWT row using the rate for the chosen transaction type
//   - If supplier is VAT-registered, inserts a 5% VAT withholding row
//   - Updates net_payable on purchase_orders
//
// POST params: purchase_order_id, transaction_type
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'save_transaction_type') {
    poAssertComptroller($db, $userId);

    $poId = (int) ($_POST['purchase_order_id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'purchase_order_id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    $header = $record['header'];
    poAssertPresidentApprovedPo($header);

    $taxStatus = strtolower(trim((string) ($header['tax_status'] ?? 'draft')));
    if ($taxStatus === 'finalized') {
        poSendJson(['success' => false, 'message' => 'Tax is finalized and cannot be changed.']);
    }

    $txTypeRaw = trim((string) ($_POST['transaction_type'] ?? ''));
    $presets    = poEwtTransactionTypePresets();
    $isExempt   = ($txTypeRaw === 'exempt');
    if ($txTypeRaw === '' || (!$isExempt && !isset($presets[$txTypeRaw]))) {
        poSendJson(['success' => false, 'message' => 'Invalid transaction type.']);
    }

    $taxableAmount = isset($header['taxable_amount']) && $header['taxable_amount'] !== null
        ? round((float) $header['taxable_amount'], 2)
        : round((float) ($header['total_amount'] ?? 0), 2);

    $supplierVatRegistered = (int) ($header['supplier_vat_registered'] ?? 0) === 1;

    $db->beginTransaction();
    try {
        // Save transaction_type on PO header.
        $db->prepare(
            'UPDATE purchase_orders SET transaction_type = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$txTypeRaw, $poId]);

        // Delete existing draft tax rows.
        $db->prepare(
            'DELETE FROM purchase_order_taxes WHERE purchase_order_id = ?'
        )->execute([$poId]);

        $totalDeductions = 0;

        if (!$isExempt) {
            $ewtRate  = $presets[$txTypeRaw]['rate'];
            $ewtLabel = $presets[$txTypeRaw]['label'];

            // Insert EWT row.
            $ewtAmount = round($taxableAmount * $ewtRate, 2);
            $db->prepare(
                'INSERT INTO purchase_order_taxes
                 (purchase_order_id, tax_type, transaction_type, rate, amount_deducted, label, supplier_vat_registered, computed_by, computed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $poId, 'ewt', $txTypeRaw, $ewtRate, $ewtAmount,
                "EWT – {$ewtLabel}",
                $supplierVatRegistered ? 1 : 0,
                $userId,
            ]);
            $totalDeductions += $ewtAmount;

            // Insert VAT withholding row if supplier is VAT-registered.
            if ($supplierVatRegistered) {
                $vatRate   = 0.05;
                $vatAmount = round($taxableAmount * $vatRate, 2);
                $db->prepare(
                    'INSERT INTO purchase_order_taxes
                     (purchase_order_id, tax_type, transaction_type, rate, amount_deducted, label, supplier_vat_registered, computed_by, computed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                )->execute([
                    $poId, 'vat_withholding', $txTypeRaw, $vatRate, $vatAmount,
                    'VAT Withholding (5% Final)',
                    1,
                    $userId,
                ]);
                $totalDeductions += $vatAmount;
            }
        }

        $netPayable = round($taxableAmount - $totalDeductions, 2);

        // Update net_payable on the PO header.
        $db->prepare(
            'UPDATE purchase_orders SET net_payable = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL'
        )->execute([$netPayable, $poId]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        poSendJson(['success' => false, 'message' => 'Failed to save transaction type: ' . $e->getMessage()]);
    }

    // Return fresh tax rows so JS can repopulate the table.
    $saved = poFetchTaxRecord($db, $poId);
    poSendJson([
        'success'          => true,
        'message'          => 'Transaction type saved and tax rows rebuilt.',
        'taxes'            => $saved['taxes'],
        'net_payable'      => $netPayable,
        'taxable_amount'   => $taxableAmount,
        'transaction_type' => $txTypeRaw,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// save_fees
//
// Persists shipping, additional charges, discount, and payment term fields.
// Recomputes total_amount (= gross_total) and taxable_amount.
// If existing tax rows exist, net_payable is refreshed against taxable_amount.
//
// POST params: purchase_order_id, shipping_fee, shipping_method, shipping_address,
//   handling_fee, insurance_fee, installation_fee, other_charges,
//   other_charges_description, discount_amount, discount_percentage,
//   discount_reason, payment_terms, payment_due_date
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'save_fees') {
    poAssertComptroller($db, $userId);

    $poId = (int) ($_POST['purchase_order_id'] ?? 0);
    if ($poId <= 0) {
        poSendJson(['success' => false, 'message' => 'purchase_order_id is required.']);
    }

    $record = poFetchById($db, $poId);
    if (!$record) {
        poSendJson(['success' => false, 'message' => 'Purchase order not found.']);
    }

    $taxStatus = strtolower(trim((string) ($record['header']['tax_status'] ?? 'draft')));
    if ($taxStatus === 'finalized') {
        poSendJson(['success' => false, 'message' => 'Cannot update fees after tax computation is finalized. Reopen first.']);
    }

    // ── Sanitize inputs ──────────────────────────────────────────────────────
    $shippingFee     = max(0.0, round((float) ($_POST['shipping_fee']     ?? 0), 2));
    $handlingFee     = max(0.0, round((float) ($_POST['handling_fee']     ?? 0), 2));
    $insuranceFee    = max(0.0, round((float) ($_POST['insurance_fee']    ?? 0), 2));
    $installationFee = max(0.0, round((float) ($_POST['installation_fee'] ?? 0), 2));
    $otherCharges    = max(0.0, round((float) ($_POST['other_charges']    ?? 0), 2));

    $discountPct = min(100.0, max(0.0, round((float) ($_POST['discount_percentage'] ?? 0), 2)));
    $discountAmt = max(0.0, round((float) ($_POST['discount_amount'] ?? 0), 2));

    $shippingMethod           = poSanitizeString($_POST['shipping_method']           ?? '', 100);
    $shippingAddress          = poSanitizeString($_POST['shipping_address']          ?? '', 65535);
    $otherChargesDescription  = poSanitizeString($_POST['other_charges_description'] ?? '', 255);
    $discountReason           = poSanitizeString($_POST['discount_reason']           ?? '', 255);
    $paymentTerms             = poSanitizeString($_POST['payment_terms']             ?? '', 100);

    $dueDateRaw  = trim((string) ($_POST['payment_due_date'] ?? ''));
    $paymentDueDate = ($dueDateRaw !== '' && strtotime($dueDateRaw) !== false)
        ? date('Y-m-d', strtotime($dueDateRaw))
        : null;

    // ── Compute totals ────────────────────────────────────────────────────────
    $linesSumStmt = $db->prepare(
        'SELECT COALESCE(SUM(amount), 0) FROM purchase_order_lines WHERE purchase_order_id = ?'
    );
    $linesSumStmt->execute([$poId]);
    $itemsSubtotal = round((float) $linesSumStmt->fetchColumn(), 2);

    $totalFees  = round($shippingFee + $handlingFee + $insuranceFee + $installationFee + $otherCharges, 2);
    $grossTotal = round($itemsSubtotal + $totalFees, 2);

    // Percentage discount takes priority over fixed amount when both are set.
    $calculatedDiscount = $discountPct > 0
        ? round($grossTotal * $discountPct / 100, 2)
        : min($discountAmt, $grossTotal);

    $taxableAmount = round($grossTotal - $calculatedDiscount, 2);

    // ── Persist ──────────────────────────────────────────────────────────────
    $upd = $db->prepare(
        'UPDATE purchase_orders
         SET shipping_fee              = ?,
             shipping_method           = ?,
             shipping_address          = ?,
             handling_fee              = ?,
             insurance_fee             = ?,
             installation_fee          = ?,
             other_charges             = ?,
             other_charges_description = ?,
             discount_amount           = ?,
             discount_percentage       = ?,
             discount_reason           = ?,
             taxable_amount            = ?,
             payment_terms             = ?,
             payment_due_date          = ?,
             total_amount              = ?,
             mode_of_payment           = ?,
             updated_at                = NOW()
         WHERE id = ? AND deleted_at IS NULL'
    );
    $upd->execute([
        $shippingFee,
        $shippingMethod !== ''          ? $shippingMethod          : null,
        $shippingAddress !== ''         ? $shippingAddress         : null,
        $handlingFee,
        $insuranceFee,
        $installationFee,
        $otherCharges,
        $otherChargesDescription !== '' ? $otherChargesDescription : null,
        $discountAmt,
        $discountPct,
        $discountReason !== ''          ? $discountReason          : null,
        $taxableAmount,
        $paymentTerms !== ''            ? $paymentTerms            : null,
        $paymentDueDate,
        $grossTotal,
        poResolveModeOfPaymentFromTotal($grossTotal),
        $poId,
    ]);

    // Refresh net_payable if tax rows already exist.
    $taxSumStmt = $db->prepare(
        'SELECT COALESCE(SUM(amount_deducted), 0) FROM purchase_order_taxes WHERE purchase_order_id = ?'
    );
    $taxSumStmt->execute([$poId]);
    $taxTotal  = round((float) $taxSumStmt->fetchColumn(), 2);
    $netPayable = round($taxableAmount - $taxTotal, 2);

    if ($taxTotal > 0) {
        $db->prepare(
            'UPDATE purchase_orders SET net_payable = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL'
        )->execute([$netPayable, $poId]);
    }

    poSendJson([
        'success'             => true,
        'message'             => 'Fees and discounts saved.',
        'items_subtotal'      => $itemsSubtotal,
        'total_fees'          => $totalFees,
        'gross_total'         => $grossTotal,
        'calculated_discount' => $calculatedDiscount,
        'taxable_amount'      => $taxableAmount,
        'tax_total'           => $taxTotal,
        'net_payable'         => $netPayable,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// generate_from_comptroller_approval
//
// Triggered after the Comptroller fully approves a requisition.
// Reads requisition_line_awards (comptroller_status IN ('fully_approved','deferred'))
// joined with requisition_line_quotes (the awarded supplier's price) and
// requisition_line (item details). Groups results by supplier_id and creates
// one Purchase Order per supplier inside a single wrapping transaction.
//
// POST params:
//   request_id       – requisition_item.request_id (required)
//   ewt_rate         – optional float, e.g. 0.01 for 1% EWT (default: 0.01)
//   ewt_type         – optional string key (default: 'Purchase of goods')
//   overwrite        – '1' to delete existing draft POs for this request first
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'generate_from_comptroller_approval') {

    // ── 1. Authorisation ────────────────────────────────────────────────────
    if (!poIsComptrollerRole($roleLc) && !poIsPresidentRole($roleLc)) {
        poSendJson(['success' => false, 'message' => 'Only the Comptroller may generate purchase orders from an approval.']);
    }

    $requestId = (int) ($_POST['request_id'] ?? $_GET['request_id'] ?? 0);
    if ($requestId <= 0) {
        poSendJson(['success' => false, 'message' => 'request_id is required.']);
    }

    // ── 2. Validate requisition exists ───────────────────────────────────────
    $reqStmt = $db->prepare(
        'SELECT r.request_id, r.user_id, r.facility_id, r.purpose,
                u.full_name, u.Email,
                f.room, f.laboratory, f.building
         FROM requisition_item r
         LEFT JOIN user u ON u.user_id = r.user_id
         LEFT JOIN facilities f ON f.facility_id = r.facility_id
         WHERE r.request_id = ?
         LIMIT 1'
    );
    $reqStmt->execute([$requestId]);
    $reqHeader = $reqStmt->fetch(PDO::FETCH_ASSOC);
    if (!$reqHeader) {
        poSendJson(['success' => false, 'message' => 'Requisition not found.']);
    }

    // ── 3. Fetch awarded lines (fully_approved + deferred with awarded_qty > 0) ──
    //
    // Join path:
    //   requisition_line_awards  (rla)
    //     → requisition_line     (rl)   – item name / brand / unit_type
    //     → requisition_line_quotes (rlq) – winning price (match on line + awarded supplier)
    //     → suppliers            (s)    – supplier name / tin
    //
    // We include 'deferred' rows where awarded_qty > 0 (partial approvals):
    // those items should still appear on the PO for the approved portion.
    $awardedStmt = $db->prepare(
        "SELECT
            rla.requisition_line_id,
            rla.supplier_id,
            rla.awarded_qty,
            rla.deferred_qty,
            rla.comptroller_status,
            rl.item_name,
            rl.item_brand,
            rl.item_category,
            rl.unit_type,
            rl.quantity        AS requested_qty,
            rlq.quoted_unit_price,
            rlq.discount_percent,
            s.supplier_name,
            s.tin              AS supplier_tin
         FROM requisition_line_awards rla
         INNER JOIN requisition_line rl
            ON rl.requisition_line_id = rla.requisition_line_id
         LEFT JOIN requisition_line_quotes rlq
            ON rlq.requisition_line_id = rla.requisition_line_id
           AND rlq.supplier_id         = rla.supplier_id
         LEFT JOIN suppliers s
            ON s.supplier_id = rla.supplier_id
         WHERE rl.request_id = ?
           AND rla.awarded_qty > 0
           AND rla.comptroller_status IN ('fully_approved', 'deferred')
         ORDER BY rla.supplier_id ASC, rl.sort_order ASC, rl.requisition_line_id ASC"
    );
    $awardedStmt->execute([$requestId]);
    $awardedRows = $awardedStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($awardedRows === []) {
        poSendJson([
            'success' => false,
            'message' => 'No fully-approved or partially-approved awarded lines found for this requisition.',
        ]);
    }

    // ── 4. Group awarded lines by supplier ───────────────────────────────────
    /** @var array<int, array{supplier_name: string, supplier_tin: ?string, lines: list<array<string,mixed>>}> */
    $bySupplier = [];
    foreach ($awardedRows as $row) {
        $supplierId = (int) ($row['supplier_id'] ?? 0);
        if ($supplierId <= 0) {
            continue;
        }

        if (!isset($bySupplier[$supplierId])) {
            $bySupplier[$supplierId] = [
                'supplier_name' => poSanitizeString($row['supplier_name'] ?? '', 100),
                'supplier_tin'  => cwirmsNormalizeSupplierTin($row['supplier_tin'] ?? null),
                'lines'         => [],
            ];
        }

        $awardedQty    = max(0, (int) ($row['awarded_qty'] ?? 0));
        $rawPrice      = $row['quoted_unit_price'];
        $unitPrice     = ($rawPrice !== null && is_numeric($rawPrice)) ? round((float) $rawPrice, 2) : 0.0;
        $discountPct   = isset($row['discount_percent']) && is_numeric($row['discount_percent'])
            ? (float) $row['discount_percent']
            : null;
        $discountFactor = ($discountPct !== null) ? (1 - $discountPct / 100) : 1.0;
        $effectivePrice = round($unitPrice * $discountFactor, 2);
        $lineAmount     = round($awardedQty * $effectivePrice, 2);

        $itemName  = trim((string) ($row['item_name'] ?? ''));
        $brand     = trim((string) ($row['item_brand'] ?? ''));
        $category  = trim((string) ($row['item_category'] ?? ''));
        $subParts  = array_values(array_filter([$brand], static fn ($v) => $v !== ''));
        $subDesc   = $subParts !== [] ? implode(' · ', $subParts) : ($category !== '' ? $category : null);

        $bySupplier[$supplierId]['lines'][] = [
            'description'     => $itemName !== '' ? $itemName : '—',
            'sub_description' => $subDesc,
            'quantity'        => $awardedQty,
            'unit_price'      => $effectivePrice,
            'amount'          => $lineAmount,
        ];
    }

    if ($bySupplier === []) {
        poSendJson(['success' => false, 'message' => 'No valid awarded lines could be mapped to a supplier.']);
    }

    // ── 5. Build shared requester / facility details ──────────────────────────
    $requesterUserId = (int) ($reqHeader['user_id'] ?? 0);
    $fullName        = trim((string) ($reqHeader['full_name'] ?? ''));
    $email           = (string) ($reqHeader['Email'] ?? '');
    $requestedByName = $fullName !== ''
        ? $fullName
        : ($email !== '' ? (explode('@', $email)[0] ?? $email) : 'Requester');

    $room      = trim((string) ($reqHeader['room'] ?? ''));
    $lab       = trim((string) ($reqHeader['laboratory'] ?? ''));
    $building  = trim((string) ($reqHeader['building'] ?? ''));
    $roomOrLab = $room !== '' ? $room : $lab;
    $location  = $roomOrLab !== '' && $building !== ''
        ? ($roomOrLab . ' · ' . $building)
        : ($roomOrLab !== '' ? $roomOrLab : ($building !== '' ? $building : '—'));

    $facilityId = !empty($reqHeader['facility_id']) ? (int) $reqHeader['facility_id'] : null;
    $purpose    = poSanitizeString($reqHeader['purpose'] ?? '', 65535);

    // ── 6. EWT seed parameters ───────────────────────────────────────────────
    $allowedEwtRates  = [0.01, 0.02, 0.05, 0.1];
    $requestedEwtRate = round((float) ($_POST['ewt_rate'] ?? 0.01), 4);
    $ewtRate          = in_array($requestedEwtRate, $allowedEwtRates, true) ? $requestedEwtRate : 0.01;

    $rawEwtType = poSanitizeString($_POST['ewt_type'] ?? 'Purchase of goods', 100);
    $ewtType    = $rawEwtType !== '' ? $rawEwtType : 'Purchase of goods';

    // ── 7. Optional overwrite: remove pending draft POs for this request ─────
    $overwrite = trim((string) ($_POST['overwrite'] ?? '0')) === '1';
    if ($overwrite) {
        $existingIds = $db->prepare(
            "SELECT id FROM purchase_orders
             WHERE requisition_id = ? AND status = 'pending' AND deleted_at IS NULL"
        );
        $existingIds->execute([$requestId]);
        $toDelete = $existingIds->fetchAll(PDO::FETCH_COLUMN, 0);
        if ($toDelete !== []) {
            $delPh  = implode(',', array_fill(0, count($toDelete), '?'));
            $delStmt = $db->prepare(
                "UPDATE purchase_orders SET deleted_at = NOW(), updated_at = NOW()
                 WHERE id IN ($delPh) AND deleted_at IS NULL"
            );
            $delStmt->execute($toDelete);
        }
    }

    // ── 8. Insert one PO per supplier inside a single transaction ─────────────
    $db->beginTransaction();
    $createdPos = [];
    try {
        $lineInsertStmt = $db->prepare(
            'INSERT INTO purchase_order_lines
             (purchase_order_id, description, sub_description, quantity, unit_price, amount, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $taxInsertStmt = $db->prepare(
            'INSERT INTO purchase_order_taxes
             (purchase_order_id, tax_type, transaction_type, rate, rate_override,
              amount_deducted, label, supplier_vat_registered, transaction_vat_exempt,
              notes, computed_by, computed_at)
             VALUES (?, \'EWT\', ?, ?, 0, ?, NULL, NULL, NULL, NULL, ?, NOW())'
        );

        foreach ($bySupplier as $supplierId => $supplierGroup) {
            $lines       = $supplierGroup['lines'];
            $totalAmount = 0.0;
            foreach ($lines as $line) {
                $totalAmount += (float) $line['amount'];
            }
            $totalAmount = round($totalAmount, 2);

            $ewtAmount  = round($totalAmount * $ewtRate, 2);
            $netPayable = round($totalAmount - $ewtAmount, 2);
            $mode       = poResolveModeOfPaymentFromTotal($totalAmount);
            $poNumber   = poGenerateNextNumber($db);

            // Insert PO header
            $poInsert = $db->prepare(
                'INSERT INTO purchase_orders
                 (po_number, requisition_id, requested_by_user_id, requested_by_name, facility_id,
                  location_facility, supplier_id, supplier_name, supplier_tin,
                  mode_of_payment, purpose_of_request, total_amount, net_payable,
                  tax_computed, tax_status, status, created_by_user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, \'draft\', \'pending\', ?)'
            );
            $poInsert->execute([
                $poNumber,
                $requestId,
                $requesterUserId > 0 ? $requesterUserId : null,
                $requestedByName,
                $facilityId,
                $location,
                $supplierId,
                $supplierGroup['supplier_name'] !== '' ? $supplierGroup['supplier_name'] : '—',
                $supplierGroup['supplier_tin'],
                $mode,
                $purpose !== '' ? $purpose : null,
                $totalAmount,
                $netPayable,
                $userId,
            ]);
            $poId = (int) $db->lastInsertId();

            // Insert line items
            foreach ($lines as $sort => $line) {
                $lineInsertStmt->execute([
                    $poId,
                    $line['description'],
                    $line['sub_description'],
                    $line['quantity'],
                    $line['unit_price'],
                    $line['amount'],
                    $sort,
                ]);
            }

            // Seed an EWT tax row so the Comptroller can review and adjust before finalising
            $taxInsertStmt->execute([
                $poId,
                $ewtType,
                $ewtRate,
                $ewtAmount,
                $userId,
            ]);

            $createdPos[] = [
                'po_id'          => $poId,
                'po_number'      => $poNumber,
                'supplier_id'    => $supplierId,
                'supplier_name'  => $supplierGroup['supplier_name'],
                'total_amount'   => $totalAmount,
                'ewt_rate'       => $ewtRate,
                'ewt_amount'     => $ewtAmount,
                'net_payable'    => $netPayable,
            ];
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        poSendJson([
            'success' => false,
            'message' => 'Failed to generate purchase order(s): ' . $e->getMessage(),
        ]);
    }

    $poNumbers = array_column($createdPos, 'po_number');
    $count     = count($createdPos);
    $message   = $count === 1
        ? 'Purchase order ' . $poNumbers[0] . ' generated successfully.'
        : $count . ' purchase orders generated: ' . implode(', ', $poNumbers) . '.';

    poSendJson([
        'success'          => true,
        'message'          => $message,
        'purchase_orders'  => $createdPos,
        'po_count'         => $count,
    ]);
}

poSendJson(['success' => false, 'message' => 'Unknown action.']);
