<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';

$db = Database::connect();
$stmt = $db->prepare('SELECT * FROM user WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$role = strtolower(trim($user['role'] ?? ''));
$isComptroller = ($role === 'comptroller');
$isInventoryManager = ($role === 'inventory manager' || $role === 'inventory_manager');

if (!$isComptroller && !$isInventoryManager) {
    header('Location: ../../index.php');
    exit;
}

$rspReadonly = '1';
$rspViewer = $isComptroller ? 'comptroller' : 'inventory';
$rspBackHref = $isComptroller ? 'comptroller_requests.php' : 'requisition_management.php';
$progressPageFrom = $_GET['from'] ?? '';
$rspProgressBackHref = ($progressPageFrom === 'status') ? 'requisition_status.php' : $rspBackHref;
$rspBackAriaLabel = ($progressPageFrom === 'status')
    ? 'Back to Status list'
    : ($isComptroller ? 'Back to Requisition Management' : 'Back to Requisition Management');

if ($isComptroller) {
    $comptrollerActive = 'requests';
}

$rspShowItemsReceivedAction = false;
$rspItemsReceivedPoId = 0;
$rspItemsReceivedPoNumber = '';

if ($isInventoryManager) {
    $progressRequestId = isset($_GET['rid']) ? (int) $_GET['rid'] : (isset($_GET['request_id']) ? (int) $_GET['request_id'] : 0);
    if ($progressRequestId > 0) {
        $poStmt = $db->prepare(
            'SELECT id, po_number, payment_released_at, items_received_at
             FROM purchase_orders
             WHERE requisition_id = ? AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );
        $poStmt->execute([$progressRequestId]);
        $poRow = $poStmt->fetch(PDO::FETCH_ASSOC);
        if (
            $poRow
            && !empty($poRow['payment_released_at'])
            && empty($poRow['items_received_at'])
        ) {
            $rspShowItemsReceivedAction = true;
            $rspItemsReceivedPoId = (int) ($poRow['id'] ?? 0);
            $rspItemsReceivedPoNumber = trim((string) ($poRow['po_number'] ?? ''));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requisition Progress - IMRMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc43">
    <link rel="stylesheet" href="../assets/css/requisition_status_progress.css?v=wlc13">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<?php if ($isComptroller): ?>
    <?php require __DIR__ . '/partials/comptroller_sidebar.php'; ?>
<?php else: ?>
    <?php $imActivePage = 'requisition_management.php'; require __DIR__ . '/partials/inventory_manager_sidebar.php'; ?>
<?php endif; ?>

<main class="main-content">
    <div id="rspRoot" data-readonly="<?php echo htmlspecialchars($rspReadonly, ENT_QUOTES, 'UTF-8'); ?>" data-viewer="<?php echo htmlspecialchars($rspViewer, ENT_QUOTES, 'UTF-8'); ?>" data-back-href="<?php echo htmlspecialchars($rspProgressBackHref, ENT_QUOTES, 'UTF-8'); ?>" data-back-aria-label="<?php echo htmlspecialchars($rspBackAriaLabel, ENT_QUOTES, 'UTF-8'); ?>" data-progress-from="<?php echo htmlspecialchars($progressPageFrom, ENT_QUOTES, 'UTF-8'); ?>"<?php if ($rspShowItemsReceivedAction): ?> data-items-received-action="1" data-po-id="<?php echo (int) $rspItemsReceivedPoId; ?>" data-po-number="<?php echo htmlspecialchars($rspItemsReceivedPoNumber, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>></div>
</main>

<button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu"><i class="fas fa-bars"></i></button>

<script src="../assets/js/logout.js?v=wlc1"></script>
<?php if ($isComptroller): ?>
<script src="../assets/js/comptroller_shell.js"></script>
<?php else: ?>
<?php require __DIR__ . '/partials/inventory_manager_sidebar_scripts.php'; ?>
<?php endif; ?>
<script src="../assets/js/requisition_status_progress.js?v=wlc13"></script>
</body>
</html>

