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

/** @return array<string, float> */
function poEwtTransactionRateMap(): array
{
    return [
        'purchase of goods' => 0.01,
        'purchase of services' => 0.02,
        'professional fees' => 0.05,
        'professional fees (high income)' => 0.1,
    ];
}

function poNormalizeEwtTransactionType(string $value): string
{
    $map = [
        'purchase_of_goods' => 'Purchase of goods',
        'purchase_of_services' => 'Purchase of services',
        'professional_fees' => 'Professional fees',
        'professional_fees_high' => 'Professional fees (high income)',
    ];
    $trim = trim($value);
    if (isset($map[$trim])) {
        return $map[$trim];
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
        'SELECT net_payable, tax_computed, tax_status, tax_finalized_at, status
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

    $grossAmount = round((float) ($header['total_amount'] ?? 0), 2);
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
        poSendJson(['success' => false, 'message' => 'Add at least one deduction before saving.']);
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
    $sql = 'SELECT * FROM purchase_orders WHERE id = ?';
    if (!$includeDeleted) {
        $sql .= ' AND deleted_at IS NULL';
    }
    $sql .= ' LIMIT 1';

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

    return [
        'id' => (int) ($header['id'] ?? 0),
        'po_number' => (string) ($header['po_number'] ?? ''),
        'requisition_id' => $header['requisition_id'] !== null ? (int) $header['requisition_id'] : null,
        'requested_by_user_id' => $header['requested_by_user_id'] !== null ? (int) $header['requested_by_user_id'] : null,
        'requested_by' => (string) ($header['requested_by_name'] ?? ''),
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
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        poSendJson(['success' => false, 'message' => 'Failed to finalize tax computation.']);
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
        'gross_amount' => round((float) ($record['header']['total_amount'] ?? 0), 2),
        'computed_by' => $saved['computed_by'],
        'computed_at' => $saved['computed_at'],
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

poSendJson(['success' => false, 'message' => 'Unknown action.']);
