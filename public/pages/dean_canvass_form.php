<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../app/classes/db.php';
require_once __DIR__ . '/../../app/api/requisition_detail_payload.php';

$db = Database::connect();
$stmt = $db->prepare('SELECT u.*, d.`office_name` AS office_name FROM user u LEFT JOIN offices d ON d.office_id = u.office_id WHERE u.user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$displayName = trim((string)($user['full_name'] ?? ''));
if ($displayName === '') {
    $displayName = explode('@', (string)($user['Email'] ?? ''))[0] ?? 'Unknown';
}
$requestId = (int) ($_GET['request_id'] ?? 0);
$progressFrom = trim((string) ($_GET['progress_from'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$roleLc = strtolower(trim((string) ($user['role'] ?? '')));
$isGsdOfficerRole = ($roleLc === 'gsd officer');
$presidentRolesLc = ['president', 'president verifier', 'verifier president', 'president_verifier'];
$isComptrollerRole = ($roleLc === 'comptroller');
$isPresidentRole = in_array($roleLc, $presidentRolesLc, true);
$isGsdCanvassReview = false;
$isCanvasserCanvassView = false;
$isInventoryManagerCanvassReview = false;
$isComptrollerCanvassReview = false;
$isPresidentCanvassReview = false;
$isComptrollerCanvassHistory = false;
$isPresidentCanvassHistory = false;
$isRequesterOwnedCanvass = false;
$requesterDisplayName = $displayName;
$requesterRoleDisplay = (string) ($user['role'] ?? '');
$accessError = null;
$backHref = 'dean_requisition_management.php';
$accessErrorReturnHref = 'dean_requisition_management.php';

if ($requestId <= 0) {
    $accessError = 'Invalid request reference.';
} elseif ($from === 'gsd') {
    $backHref = 'gsd_request.php';
    $accessErrorReturnHref = 'gsd_request.php';
    if (!$isGsdOfficerRole) {
        $accessError = 'Only GSD officers can open this view from the GSD workspace.';
    } else {
        $req = $db->prepare('SELECT request_id, user_id FROM requisition_item WHERE request_id = ?');
        $req->execute([$requestId]);
        $reqRow = $req->fetch(PDO::FETCH_ASSOC);
        if (!$reqRow) {
            $accessError = 'This requisition was not found.';
        } else {
            $rsStmt = $db->prepare(
                'SELECT LOWER(TRIM(COALESCE(requisition_status, \'\'))) FROM requisition_form_approval WHERE request_id = ? LIMIT 1'
            );
            $rsStmt->execute([$requestId]);
            $rs = strtolower(trim((string) ($rsStmt->fetchColumn() ?: '')));
            if ($rs !== 'accept') {
                $accessError = 'Open the canvass sheet only after the inventory manager accepts the requisition.';
            } else {
                $isGsdCanvassReview = true;
                $uStmt = $db->prepare('SELECT Email, role FROM user WHERE user_id = ?');
                $uStmt->execute([(int) $reqRow['user_id']]);
                $owner = $uStmt->fetch(PDO::FETCH_ASSOC);
                $em = (string) ($owner['Email'] ?? '');
                $requesterDisplayName = $em !== '' ? (explode('@', $em)[0] ?? $em) : '—';
                $requesterRoleDisplay = (string) ($owner['role'] ?? '');
            }
        }
    }
} elseif ($from === 'canvasser') {
    $backHref = 'canvasser_request.php';
    $accessErrorReturnHref = 'canvasser_request.php';
    $allowedCvRoles = ['employee', 'user', 'laboratory manager', 'canvasser'];
    if (!in_array($roleLc, $allowedCvRoles, true)) {
        $accessError = 'Only canvass workspace users can open this view.';
    } else {
        $req = $db->prepare('SELECT request_id, user_id FROM requisition_item WHERE request_id = ?');
        $req->execute([$requestId]);
        $reqRow = $req->fetch(PDO::FETCH_ASSOC);
        if (!$reqRow) {
            $accessError = 'This requisition was not found.';
        } else {
            $rsStmt = $db->prepare(
                'SELECT LOWER(TRIM(COALESCE(requisition_status, \'\'))) FROM requisition_form_approval WHERE request_id = ? LIMIT 1'
            );
            $rsStmt->execute([$requestId]);
            $rs = strtolower(trim((string) ($rsStmt->fetchColumn() ?: '')));
            if ($rs !== 'accept') {
                $accessError = 'Open the canvass sheet only after the inventory manager accepts the requisition.';
            } else {
                $emailFull = (string) ($user['Email'] ?? '');
                $localKey = $emailFull !== '' ? strtolower((string) (explode('@', $emailFull)[0] ?? '')) : '';
                $chk = $db->prepare(
                    'SELECT 1 FROM canvass_verification_approval cva
                     WHERE cva.request_id = ?
                     AND (
                         cva.canvas_assignee_user_id = ?
                         OR (
                             cva.canvas_assignee_user_id IS NULL
                             AND cva.canvassed_by IS NOT NULL
                             AND LOWER(TRIM(cva.canvassed_by)) = ?
                         )
                     )
                     AND LOWER(TRIM(COALESCE(cva.canvas_status, \'pending\'))) IN (\'pending\', \'\')
                     LIMIT 1'
                );
                $chk->execute([$requestId, (int) $_SESSION['user_id'], $localKey]);
                if (!$chk->fetchColumn()) {
                    $accessError = 'This requisition is not assigned to you for canvassing, or the canvass step is already complete.';
                } else {
                    $isCanvasserCanvassView = true;
                    $uStmt = $db->prepare('SELECT Email, role FROM user WHERE user_id = ?');
                    $uStmt->execute([(int) $reqRow['user_id']]);
                    $owner = $uStmt->fetch(PDO::FETCH_ASSOC);
                    $em = (string) ($owner['Email'] ?? '');
                    $requesterDisplayName = $em !== '' ? (explode('@', $em)[0] ?? $em) : '—';
                    $requesterRoleDisplay = (string) ($owner['role'] ?? '');
                }
            }
        }
    }
} elseif ($from === 'inventory') {
    $accessErrorReturnHref =
        $progressFrom === 'status'
            ? ('requisition_status_progress.php?rid=' . $requestId . '&from=status')
            : 'requisition_management.php';
    $backHref = $accessErrorReturnHref;
    $isInventoryRole = ($roleLc === 'inventory manager' || $roleLc === 'inventory_manager');
    if (!$isInventoryRole) {
        $accessError = 'Only inventory managers can open this view.';
    } else {
        $req = $db->prepare('SELECT request_id, user_id FROM requisition_item WHERE request_id = ?');
        $req->execute([$requestId]);
        $reqRow = $req->fetch(PDO::FETCH_ASSOC);
        if (!$reqRow) {
            $accessError = 'This requisition was not found.';
        } else {
            $rsStmt = $db->prepare(
                'SELECT LOWER(TRIM(COALESCE(requisition_status, \'\'))) FROM requisition_form_approval WHERE request_id = ? LIMIT 1'
            );
            $rsStmt->execute([$requestId]);
            $rs = strtolower(trim((string) ($rsStmt->fetchColumn() ?: '')));
            if ($rs !== 'accept') {
                $accessError = 'Open the canvass sheet only after you have accepted the requisition.';
            } else {
                $isInventoryManagerCanvassReview = true;
                $uStmt = $db->prepare('SELECT Email, role FROM user WHERE user_id = ?');
                $uStmt->execute([(int) $reqRow['user_id']]);
                $owner = $uStmt->fetch(PDO::FETCH_ASSOC);
                $em = (string) ($owner['Email'] ?? '');
                $requesterDisplayName = $em !== '' ? (explode('@', $em)[0] ?? $em) : '—';
                $requesterRoleDisplay = (string) ($owner['role'] ?? '');
            }
        }
    }
} elseif ($from === 'comptroller' || ($from === 'history' && $isComptrollerRole)) {
    $backHref = $from === 'history' ? 'audit_trail.php' : 'comptroller_requests.php';
    $accessErrorReturnHref = $backHref;
    if (!$isComptrollerRole) {
        $accessError = 'Only the comptroller can open this canvass view.';
    } else {
        $req = $db->prepare('SELECT request_id, user_id FROM requisition_item WHERE request_id = ?');
        $req->execute([$requestId]);
        $reqRow = $req->fetch(PDO::FETCH_ASSOC);
        if (!$reqRow) {
            $accessError = 'This requisition was not found.';
        } else {
            $rsStmt = $db->prepare(
                'SELECT LOWER(TRIM(COALESCE(requisition_status, \'\'))) FROM requisition_form_approval WHERE request_id = ? LIMIT 1'
            );
            $rsStmt->execute([$requestId]);
            $rs = strtolower(trim((string) ($rsStmt->fetchColumn() ?: '')));
            if ($rs !== 'accept') {
                $accessError = 'Open the canvass sheet only after the inventory manager accepts the requisition.';
            } else {
                if ($from === 'history') {
                    $isComptrollerCanvassHistory = true;
                } else {
                    $isComptrollerCanvassReview = true;
                }
                $uStmt = $db->prepare('SELECT Email, role FROM user WHERE user_id = ?');
                $uStmt->execute([(int) $reqRow['user_id']]);
                $owner = $uStmt->fetch(PDO::FETCH_ASSOC);
                $em = (string) ($owner['Email'] ?? '');
                $requesterDisplayName = $em !== '' ? (explode('@', $em)[0] ?? $em) : '—';
                $requesterRoleDisplay = (string) ($owner['role'] ?? '');
            }
        }
    }
} elseif ($from === 'president' || ($from === 'history' && $isPresidentRole)) {
    $backHref = $from === 'history' ? 'audit_trail.php' : 'president_request.php';
    $accessErrorReturnHref = $backHref;
    if (!$isPresidentRole) {
        $accessError = 'Only the president verifier can open this canvass view.';
    } else {
        $req = $db->prepare('SELECT request_id, user_id FROM requisition_item WHERE request_id = ?');
        $req->execute([$requestId]);
        $reqRow = $req->fetch(PDO::FETCH_ASSOC);
        if (!$reqRow) {
            $accessError = 'This requisition was not found.';
        } else {
            $rsStmt = $db->prepare(
                'SELECT LOWER(TRIM(COALESCE(requisition_status, \'\'))) FROM requisition_form_approval WHERE request_id = ? LIMIT 1'
            );
            $rsStmt->execute([$requestId]);
            $rs = strtolower(trim((string) ($rsStmt->fetchColumn() ?: '')));
            if ($rs !== 'accept') {
                $accessError = 'Open the canvass sheet only after the inventory manager accepts the requisition.';
            } else {
                if ($from === 'history') {
                    $isPresidentCanvassHistory = true;
                } else {
                    $isPresidentCanvassReview = true;
                }
                $uStmt = $db->prepare('SELECT Email, role FROM user WHERE user_id = ?');
                $uStmt->execute([(int) $reqRow['user_id']]);
                $owner = $uStmt->fetch(PDO::FETCH_ASSOC);
                $em = (string) ($owner['Email'] ?? '');
                $requesterDisplayName = $em !== '' ? (explode('@', $em)[0] ?? $em) : '—';
                $requesterRoleDisplay = (string) ($owner['role'] ?? '');
            }
        }
    }
} else {
    $backHref =
        'dean_requisition_status_progress.php?rid=' .
        $requestId .
        ($progressFrom === 'status' ? '&from=status' : '');
    $own = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ? AND user_id = ?');
    $own->execute([$requestId, $_SESSION['user_id']]);
    if (!$own->fetch(PDO::FETCH_ASSOC)) {
        $accessError = 'This requisition was not found or does not belong to your account.';
    } else {
        $rsStmt = $db->prepare(
            'SELECT LOWER(TRIM(COALESCE(requisition_status, \'\'))) FROM requisition_form_approval WHERE request_id = ? LIMIT 1'
        );
        $rsStmt->execute([$requestId]);
        $rs = strtolower(trim((string) ($rsStmt->fetchColumn() ?: '')));
        if ($rs !== 'accept') {
            $accessError = 'Open the canvass form only after the inventory manager accepts your requisition.';
        } else {
            $isRequesterOwnedCanvass = true;
        }
    }
}

$isReviewerCanvassReadonly = $isGsdCanvassReview
    || $isInventoryManagerCanvassReview
    || $isComptrollerCanvassReview
    || $isPresidentCanvassReview
    || $isComptrollerCanvassHistory
    || $isPresidentCanvassHistory;

$verifierChainLocked = ($accessError === null && $requestId > 0)
    ? requisitionVerifierChainLockedForRequest($db, $requestId)
    : false;
$isCanvassStructureUiHidden = $isReviewerCanvassReadonly
    || ($verifierChainLocked && ($isRequesterOwnedCanvass || $isCanvasserCanvassView));
$isCanvasMatrixReadonly = $isReviewerCanvassReadonly
    || ($verifierChainLocked && ($isRequesterOwnedCanvass || $isCanvasserCanvassView));
$prAfterCanvassAccepted = ($requestId > 0 && $accessError === null)
    ? requisitionCanvassFormAcceptedForRequest($db, $requestId)
    : false;
$canShowPurchaseRequisitionLink = $prAfterCanvassAccepted && (
    $isRequesterOwnedCanvass
    || $isInventoryManagerCanvassReview
    || $isPresidentCanvassReview
    || $isPresidentCanvassHistory
);

$rfRequestId = ($accessError === null && $requestId > 0) ? $requestId : 0;
$rfStepLine = '';
$rfHint = 'Verification order: Canvasser → G.S.D. → Comptroller → President.';
$rfLinkUrl = '';
$rfLinkText = '';
if ($rfRequestId > 0 && $accessError === null) {
    if ($isGsdCanvassReview) {
        $rfStepLine = 'Abstract of quotation · G.S.D.';
        $rfHint = 'Assign canvasser from the requisition form when needed. Verification: Canvasser → G.S.D. → Comptroller → President.';
        $rfLinkUrl = '';
        $rfLinkText = '';
    } elseif ($isCanvasserCanvassView) {
        $rfStepLine = 'Abstract of quotation · canvasser';
        $rfHint = 'Use this canvass sheet to add suppliers and quoted prices. Use the requisition form for request details only.';
        $rfLinkUrl = '';
        $rfLinkText = '';
    } elseif ($isInventoryManagerCanvassReview) {
        $rfStepLine = 'Abstract of quotation · inventory (read-only)';
        $rfHint = 'Review only. Editing is done by the requester and canvasser; downstream roles verify.';
        $rfProg = $progressFrom === 'status' ? '&progress_from=status' : '';
        $rfLinkUrl = 'dean_requisition_form.php?view=1&from=progress&request_id=' . $requestId . $rfProg;
        $rfLinkText = 'Open requisition form';
    } elseif ($isComptrollerCanvassReview || $isComptrollerCanvassHistory) {
        $rfStepLine = $isComptrollerCanvassHistory ? 'Abstract of quotation · history' : 'Abstract of quotation · comptroller';
        $rfLinkUrl = '';
        $rfLinkText = '';
    } elseif ($isPresidentCanvassReview || $isPresidentCanvassHistory) {
        $rfStepLine = $isPresidentCanvassHistory ? 'Abstract of quotation · history' : 'Abstract of quotation · president';
        $rfLinkUrl = '';
        $rfLinkText = '';
    } elseif ($isRequesterOwnedCanvass) {
        $rfStepLine = 'Step 2 of 2 · Abstract of quotation';
        $rfLinkUrl = 'dean_requisition_form.php?view=1&from=requisition&request_id=' . $requestId;
        $rfLinkText = 'Back to requisition';
    }
}
$pageTitle = $rfRequestId > 0
    ? 'Abstract of quotation · Request #' . $rfRequestId . ' · WLC-SMART'
    : 'Canvass sheet · WLC-SMART';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/requisition_form.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<main class="requisition-main">
    <div class="requisition-card<?php echo $isCanvasMatrixReadonly ? ' gsd-canvass-readonly' : ''; ?>" id="canvassCard" data-request-id="<?php echo (int) $requestId; ?>" data-api="../../app/api/canvass_detail.php" data-dean-api="../../app/api/dean_requisition.php" data-gsd-readonly="<?php echo $isCanvasMatrixReadonly ? '1' : '0'; ?>" data-canvasser-register="<?php echo $isCanvasserCanvassView ? '1' : '0'; ?>">
        <a href="<?php echo htmlspecialchars($accessError === null ? $backHref : $accessErrorReturnHref); ?>" class="requisition-close-btn" aria-label="Back" data-tooltip="Back">
            <i class="fas fa-times"></i>
        </a>

        <div class="requisition-top">
            <div class="logo-left">
                <div class="requisition-logo-wlc-wrap">
                    <img src="../assets/images/wlc-smart-logo.png" alt="WLC-SMART Inventory Office" class="requisition-logo-wlc" decoding="async" />
                </div>
            </div>
            <div class="requisition-title">
                <h1>Western Leyte College of Ormoc City Inc.</h1>
                <div class="requisition-subtitle">
                    <p>A. Bonifacio St., Ormoc City, Leyte, Philippines</p>
                    <p>Tel Nos.: (053) 561 - 5310 / 255 8549</p>
                    <p>E-mail Address: westernleytecollege@yahoo.com</p>
                </div>
                <p class="requisition-section" id="cvFormTitle">CANVASS SHEET / ABSTRACT OF QUOTATION</p>
            </div>
            <div class="logo-right">
                <img src="../assets/images/western-letye-logo.jpg" alt="College Logo" class="requisition-logo" />
            </div>
        </div>

        <?php if ($rfRequestId > 0 && $accessError === null) {
            require __DIR__ . '/partials/requisition_flow_context.php';
        } ?>
        <?php if ($rfRequestId > 0 && $accessError === null && $canShowPurchaseRequisitionLink): ?>
        <div class="req-flow-context">
            <div class="req-flow-context-top">
                <div class="req-flow-context-main">
                    <span class="req-flow-step">Purchase requisition is now available for review.</span>
                </div>
                <a class="req-flow-context-link" href="purchase_requisition_form.php?request_id=<?php echo (int) $rfRequestId; ?>&from=<?php echo htmlspecialchars($from !== '' ? $from : 'requisition'); ?>">Open purchase requisition</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($accessError !== null): ?>
        <div class="note-group" style="margin-top:0.5rem;">
            <p style="color:#b91c1c;font-weight:600;"><?php echo htmlspecialchars($accessError); ?></p>
            <p><a href="<?php echo htmlspecialchars($accessErrorReturnHref); ?>" style="color:#166534;font-weight:700;"><?php
                if ($from === 'gsd' && $isGsdOfficerRole) {
                    echo 'Return to GSD requests';
                } elseif ($from === 'canvasser') {
                    echo 'Return to canvasser requests';
                } elseif ($from === 'inventory') {
                    echo $progressFrom === 'status' ? 'Return to requisition progress' : 'Return to requisition management';
                } elseif ($from === 'comptroller' || ($from === 'history' && $isComptrollerRole)) {
                    echo $from === 'history' ? 'Return to audit trail' : 'Return to comptroller requests';
                } elseif ($from === 'president' || ($from === 'history' && $isPresidentRole)) {
                    echo $from === 'history' ? 'Return to audit trail' : 'Return to president requests';
                } else {
                    echo 'Return to requisition management';
                }
            ?></a></p>
        </div>
        <?php else: ?>

        <div class="requisition-info">
            <div class="info-left info-grid">
                <div class="field-group">
                    <label for="cvRequesterName">Requester Name</label>
                    <input type="text" id="cvRequesterName" value="<?php echo htmlspecialchars($requesterDisplayName); ?>" disabled>
                </div>
                <div class="field-group">
                    <label for="cvOfficeDisplay">Office</label>
                    <input type="text" id="cvOfficeDisplay" value="—" disabled>
                </div>
                <div class="field-group">
                    <label for="cvFacilityDisplay">Location / Facility</label>
                    <input type="text" id="cvFacilityDisplay" value="—" disabled>
                </div>
                <div class="field-group">
                    <label for="cvFacultyRole">Role</label>
                    <input type="text" id="cvFacultyRole" value="<?php echo htmlspecialchars($requesterRoleDisplay); ?>" disabled>
                </div>
            </div>
            <div class="info-right">
                <label for="cvRequestDate">Requested date</label>
                <input type="text" id="cvRequestDate" value="—" disabled>
                <label for="cvPurpose" style="margin-top:0.6rem;">Purpose of request</label>
                <input type="text" id="cvPurpose" value="" disabled placeholder="—">
            </div>
        </div>

        <div class="requested-items-summary cv-requested-ref" aria-label="Items from the requisition">
            <div class="section-label requested-items-summary-label">Items requested on requisition</div>
            <p class="cv-requested-ref-hint"><?php echo $isGsdCanvassReview
                ? 'Line items as recorded on the original requisition (read-only).'
                : ($isCanvasserCanvassView
                    ? 'Requester’s line items (read-only). Canvass quotation lines set by the requester appear below — you only add suppliers and prices.'
                    : 'What you originally submitted. Use <strong>Canvass items</strong> below for quotation lines (you can match or refine these).'); ?></p>
            <div class="requested-items-table-wrap">
                <table class="requested-items-table" id="cvRequestedItemsTable">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Item</th>
                            <th scope="col">Qty</th>
                            <th scope="col">Unit</th>
                        </tr>
                    </thead>
                    <tbody id="cvRequestedItemsTableBody">
                        <tr class="requested-items-empty">
                            <td colspan="4">Loading…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-section">
            <?php if (!$isCanvassStructureUiHidden && !$isCanvasserCanvassView): ?>
            <div class="cv-canvass-section-heading">
                <div class="section-label cv-canvass-section-label">Canvass items:</div>
                <button type="button" id="cvCanvassHintShow" class="cv-canvass-hint-show-btn" hidden aria-label="Show canvass items hint" title="Show hint">
                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                </button>
            </div>
            <div id="cvCanvassItemsHintWrap" class="cv-canvass-items-hint-wrap" role="note">
                <p class="cv-canvass-items-hint">One row per item you want suppliers to quote. Catalog suggestions appear when the name matches inventory; for anything else (paper, furniture, lab supplies, services, etc.) type freely. Use brand, variant / size / grade, and description the same way you would for maker, model, and specs on equipment.</p>
                <button type="button" id="cvCanvassHintDismiss" class="cv-canvass-hint-dismiss" aria-label="Hide this note and free space" title="Hide this note">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="form-grid form-grid-cv-canvass-items">
                <div class="cv-item-suggest-wrap">
                    <label for="cvItemName" class="sr-only">Item or service to quote (catalog suggestions when available)</label>
                    <input type="text" id="cvItemName" placeholder="Item or service (e.g. bond paper, office chair, laptop)" list="cvItemNameSuggestions" autocomplete="off">
                    <datalist id="cvItemNameSuggestions"></datalist>
                    <ul id="cvItemSuggestList" class="cv-item-suggest-list" role="listbox" aria-label="Matching catalog items" hidden></ul>
                </div>
                <input type="text" id="cvItemBrand" placeholder="Brand / manufacturer (optional)" autocomplete="off" maxlength="100">
                <input type="text" id="cvItemModel" placeholder="Model, SKU, size, grade, or variant (optional)" autocomplete="off" maxlength="100">
                <input type="text" id="cvItemSpecs" placeholder="Description &amp; details (dims, color, pack, finish…)" autocomplete="off">
                <button type="button" id="cvAddItemBtn" class="btn-add-small"><i class="fas fa-plus"></i> Add item</button>
            </div>
            <?php elseif ($isCanvasserCanvassView && !$verifierChainLocked): ?>
            <div class="section-label cv-canvass-section-label">Canvass quotation lines (view only)</div>
            <p class="cv-canvasser-items-readonly-note" style="margin:0 0 0.85rem;font-size:0.9rem;color:#475569;line-height:1.45;">These lines were defined by the requester. You cannot add or remove them. Use <strong>Suppliers</strong> below to add vendors and enter quoted prices (you can <strong>Register supplier</strong> if needed). You cannot remove supplier columns the requester already added—only the requester can.</p>
            <?php elseif ($isCanvasserCanvassView && $verifierChainLocked): ?>
            <div class="section-label cv-canvass-section-label">Canvass quotation lines (locked)</div>
            <p class="cv-canvasser-items-readonly-note" style="margin:0 0 0.85rem;font-size:0.9rem;color:#475569;line-height:1.45;">A verifier (G.S.D., comptroller, or president) has already recorded a decision. This canvass is <strong>read-only</strong>; you cannot change suppliers or prices here.</p>
            <?php elseif ($isRequesterOwnedCanvass && $verifierChainLocked): ?>
            <div class="section-label cv-canvass-section-label cv-verifier-canvass-label">Quotation lines &amp; prices (locked)</div>
            <p class="cv-verifier-canvass-readonly-hint" style="margin:0 0 0.85rem;font-size:0.9rem;color:#475569;line-height:1.45;">A verifier has already acted on this request. The canvass sheet is <strong>read-only</strong> so the recorded workflow is preserved.</p>
            <?php else: ?>
            <div class="section-label cv-canvass-section-label cv-verifier-canvass-label">Quotation lines &amp; prices</div>
            <p class="cv-verifier-canvass-readonly-hint" style="margin:0 0 0.85rem;font-size:0.9rem;color:#475569;line-height:1.45;">Read-only. Only the <strong>requester</strong> may add or remove canvass lines and supplier columns. Assigned <strong>canvassers</strong> may add suppliers, register new suppliers, and enter prices on their workspace.</p>
            <?php endif; ?>
            <div id="cvItemChips" class="item-chips"><p class="item-chips-empty">No canvass items yet.</p></div>

            <?php if ($isRequesterOwnedCanvass || $isReviewerCanvassReadonly): ?>
            <div class="cv-preferred-section" id="cvPreferredSection">
                <div class="cv-preferred-section-head">
                    <span class="section-label">Preferred Suppliers</span>
                    <?php if ($isRequesterOwnedCanvass && !$verifierChainLocked): ?>
                    <button type="button" class="btn-add-small" id="cvOpenAddPreferredBtn">
                        <i class="fas fa-plus"></i> Add preferred supplier
                    </button>
                    <?php endif; ?>
                </div>
                <p class="cv-preferred-hint" id="cvPreferredHint"></p>
                <div class="cv-preferred-picker" style="margin:0.5rem 0;">
                    <label for="cvPrefSupplierSearch" class="sr-only">Search suppliers</label>
                    <input type="text" id="cvPrefSupplierSearch" placeholder="Search suppliers (name, contact)…" autocomplete="off">
                    <div id="cvPrefSupplierSearchList" class="supplier-dropdown-list" style="max-height:240px;overflow:auto;margin-top:0.4rem;"></div>
                </div>
                <div class="supplier-table-wrapper">
                    <table id="cvPreferredTable" class="supplier-table">
                        <thead>
                            <tr>
                                <th>SUPPLIER</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="2" class="empty-state">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="supplier-section" id="cvCanvasSection" role="region" aria-label="Suppliers and pricing">
                <?php if (!$isCanvassStructureUiHidden): ?>
                <div class="supplier-picker">
                    <label for="cvSupplierDropdownBtn">Supplier</label>
                    <div class="supplier-picker-row">
                        <div class="supplier-dropdown" id="cvSupplierDropdown">
                            <button type="button" id="cvSupplierDropdownBtn" class="supplier-dropdown-btn">
                                <span class="supplier-dropdown-label">
                                    <img id="cvSupplierDropdownPreview" class="supplier-dropdown-preview" src="" alt="" width="28" height="28" decoding="async" hidden>
                                    <span id="cvSupplierSelectedText">Select Supplier</span>
                                </span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div id="cvSupplierDropdownList" class="supplier-dropdown-list"></div>
                        </div>
                        <button type="button" id="cvAddSupplierBtn" class="btn-add-small"><i class="fas fa-plus"></i> Add Supplier</button>
                        <?php if ($isCanvasserCanvassView): ?>
                        <button type="button" id="cvRegisterSupplierBtn" class="btn-add-small canvasser-register-supplier-btn" title="Add a new supplier to the directory"><i class="fas fa-store"></i> Register supplier</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($isCanvasserCanvassView && !$verifierChainLocked): ?>
                <div id="cvSupplierContactPanel" class="supplier-contact-panel" hidden>
                    <div class="supplier-contact-panel-title">
                        <span class="supplier-contact-panel-title-label"><i class="fas fa-address-card"></i> Supplier contact</span>
                        <button type="button" class="supplier-contact-panel-clear" id="cvClearSupplierContactBtn">Clear contact</button>
                    </div>
                    <p class="supplier-contact-panel-hint">Choose a supplier in the dropdown above, or <strong>click the supplier name</strong> in the quotation table. Phone and email are clickable.</p>
                    <div id="cvSupplierContactPanelBody" class="supplier-contact-panel-body"></div>
                </div>
                <?php endif; ?>
                <div id="cvSuggestedSupplierNotice" class="canvass-continue-banner cv-suggested-notice" role="status" aria-live="polite" hidden>
                    <div class="canvass-continue-banner-inner">
                        <span class="canvass-continue-banner-icon" aria-hidden="true"><i class="fas fa-circle-info"></i></span>
                        <p class="canvass-continue-banner-msg cv-suggested-notice-text" id="cvSuggestedSupplierNoticeText"></p>
                        <button type="button" id="cvSuggestedSupplierNoticeDismiss" class="cv-suggested-notice-dismiss" aria-label="Dismiss suggested suppliers info">
                            <i class="fas fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="supplier-table-wrapper">
                    <table id="cvSupplierTable" class="supplier-table">
                        <thead>
                            <tr>
                                <th>SUPPLIER</th>
                                <th>ITEM 1</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="2" class="empty-state">Add items and suppliers to build the matrix.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="approval-section cv-approval-section" aria-label="Canvass verification status">
            <div class="approval-card approval-card-canvass-verifiers" id="cvApprovalStrip">
                <div class="approval-role<?php echo $isGsdCanvassReview ? ' approval-role-cv-assignee' : ''; ?>">
                    <div class="circle-icon inactive"><i class="fas fa-check"></i></div>
                    <div class="approval-role-body">
                        <div class="approval-name">Canvasser</div>
                        <div class="approval-sub cv-appr-kind"><?php echo $isGsdCanvassReview ? 'Assign &amp; status' : 'Canvassed by'; ?></div>
                        <div class="cv-appr-detail" id="cvApprCanvasserDetail"></div>
                        <?php if ($isGsdCanvassReview): ?>
                        <div class="gsd-canvas-assignee-field gsd-assignee-in-approval" id="gsdAssigneeInApprovalWrap">
                            <input type="hidden" id="gsdCanvasAssigneeUserId" value="">
                            <input type="hidden" id="gsdSuggestedSupplierId" value="">
                            <label class="sr-only" for="gsdCanvasAssigneeInput">Assign staff to canvass</label>
                            <input type="text" id="gsdCanvasAssigneeInput" class="gsd-canvas-assignee-input" autocomplete="off" placeholder="Search name or email to assign…">
                            <ul id="gsdCanvasAssigneeSuggestions" class="gsd-canvas-assignee-suggestions" role="listbox" hidden></ul>
                            <p class="gsd-canvas-assignee-hint gsd-assignee-in-approval-hint">Office staff only. Required before <strong>Verify</strong> while canvassing is open.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="approval-role">
                    <div class="circle-icon inactive"><i class="fas fa-check"></i></div>
                    <div class="approval-role-body">
                        <div class="approval-name">G.S.D officer</div>
                        <div class="approval-sub cv-appr-kind">Verified by</div>
                        <div class="cv-appr-detail" id="cvApprGsdDetail"></div>
                    </div>
                </div>
                <div class="approval-role">
                    <div class="circle-icon inactive"><i class="fas fa-check"></i></div>
                    <div class="approval-role-body">
                        <div class="approval-name">Comptroller</div>
                        <div class="approval-sub cv-appr-kind">Checked by</div>
                        <div class="cv-appr-detail" id="cvApprCompDetail"></div>
                    </div>
                </div>
                <div class="approval-role">
                    <div class="circle-icon inactive"><i class="fas fa-check"></i></div>
                    <div class="approval-role-body">
                        <div class="approval-name">President</div>
                        <div class="approval-sub cv-appr-kind">Approved by</div>
                        <div class="cv-appr-detail" id="cvApprPresDetail"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($isGsdCanvassReview): ?>
        <div class="comptroller-approve-wrapper gsd-on-cv-actions">
            <button type="button" id="comptrollerApproveBtn" class="btn-submit">Verify</button>
            <button type="button" id="comptrollerRejectBtn" class="btn-secondary comptroller-reject-btn">Reject</button>
            <button type="button" id="comptrollerUndoBtn" class="btn-secondary comptroller-undo-btn" style="display: none;">Undo decision</button>
        </div>
        <p id="gsdVerifyHint" class="gsd-verify-hint" aria-live="polite"></p>
        <?php elseif ($isComptrollerCanvassReview || $isPresidentCanvassReview): ?>
        <div class="comptroller-approve-wrapper gsd-on-cv-actions">
            <button type="button" id="comptrollerApproveBtn" class="btn-submit">Approve</button>
            <button type="button" id="comptrollerRejectBtn" class="btn-secondary comptroller-reject-btn">Reject</button>
            <button type="button" id="comptrollerUndoBtn" class="btn-secondary comptroller-undo-btn" style="display: none;">Undo decision</button>
        </div>
        <?php endif; ?>

        <?php if (!$isCanvasMatrixReadonly): ?>
        <div class="btn-submit-wrapper<?php echo $isCanvasserCanvassView ? ' cv-canvasser-submit-row' : ''; ?>">
            <?php if ($isCanvasserCanvassView): ?>
            <button type="button" id="cvSaveDraftBtn" class="btn-secondary">Save draft</button>
            <button type="button" id="cvCompleteCanvassBtn" class="btn-submit">Complete canvassing</button>
            <button type="button" id="cvCanvasserUndoBtn" class="btn-secondary comptroller-undo-btn" hidden>Undo completion</button>
            <p class="cv-canvasser-save-hint" style="flex-basis:100%;margin:0.35rem 0 0;font-size:0.85rem;color:#64748b;">Save draft keeps your supplier quotes without finishing the step. Complete canvassing saves and records your approval. Use <strong>Undo completion</strong> to reopen the canvass step.</p>
            <?php else: ?>
            <button type="button" id="cvSaveBtn" class="btn-submit">Save canvass form</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<div id="cvFormToast" class="toast success" style="display:none;"></div>

<?php if ($isCanvasserCanvassView && !$verifierChainLocked): ?>
<div id="canvasserNewSupplierModal" class="canvasser-supplier-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="canvasserNewSupplierTitle">
    <div class="canvasser-supplier-modal-backdrop" data-close-canvasser-supplier-modal></div>
    <div class="canvasser-supplier-modal-card">
        <div class="canvasser-supplier-modal-header">
            <h3 id="canvasserNewSupplierTitle">Register a new supplier</h3>
            <button type="button" class="canvasser-supplier-modal-close" data-close-canvasser-supplier-modal aria-label="Close">&times;</button>
        </div>
        <p class="canvasser-supplier-modal-intro">Use this when no suitable supplier exists in the list (for example the requester left suppliers blank). Inventory can add photos later in Supplier Management.</p>
        <form id="canvasserNewSupplierForm" class="canvasser-supplier-form">
            <label class="canvasser-supplier-field"><span>Supplier name <em>*</em></span><input type="text" id="canvasserNewSupplierName" name="supplier_name" required maxlength="100" autocomplete="organization"></label>
            <label class="canvasser-supplier-field"><span>Contact person</span><input type="text" id="canvasserNewSupplierContact" name="contact_person" maxlength="100" autocomplete="name"></label>
            <label class="canvasser-supplier-field"><span>Phone</span><input type="text" id="canvasserNewSupplierPhone" name="phone_number" maxlength="30" autocomplete="tel"></label>
            <label class="canvasser-supplier-field"><span>Email</span><input type="email" id="canvasserNewSupplierEmail" name="email" maxlength="100" autocomplete="email"></label>
            <label class="canvasser-supplier-field"><span>Address</span><input type="text" id="canvasserNewSupplierAddress" name="address" maxlength="255"></label>
            <div class="canvasser-supplier-form-grid">
                <label class="canvasser-supplier-field"><span>City</span><input type="text" id="canvasserNewSupplierCity" name="city" maxlength="50"></label>
                <label class="canvasser-supplier-field"><span>Country</span><input type="text" id="canvasserNewSupplierCountry" name="country" maxlength="50"></label>
                <label class="canvasser-supplier-field"><span>Postal code</span><input type="text" id="canvasserNewSupplierPostal" name="postal_code" maxlength="20"></label>
            </div>
        </form>
        <div class="canvasser-supplier-modal-actions">
            <button type="button" class="btn-secondary" data-close-canvasser-supplier-modal>Cancel</button>
            <button type="button" id="canvasserNewSupplierSubmit" class="btn-submit">Save supplier</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isRequesterOwnedCanvass && !$verifierChainLocked): ?>
<div id="cvPrefSupModal" class="confirm-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="cvPrefSupModalTitle">
    <div class="confirm-modal-backdrop" id="cvPrefSupModalBackdrop"></div>
    <div class="confirm-modal-card" style="max-width:420px;width:92%;">
        <div class="confirm-modal-header">
            <h3 id="cvPrefSupModalTitle">Add preferred supplier</h3>
        </div>
        <div class="confirm-modal-body" style="display:flex;flex-direction:column;gap:0.7rem;">
            <input type="hidden" id="cvPrefSupModalSupplierId" value="">
            <div class="field-group">
                <label for="cvPrefSupName">Supplier name <em style="color:#b91c1c">*</em></label>
                <input type="text" id="cvPrefSupName" placeholder="e.g. Lazada, SM Stationery" maxlength="100" autocomplete="organization">
            </div>
            <div class="field-group">
                <label for="cvPrefSupContact">Contact person</label>
                <input type="text" id="cvPrefSupContact" maxlength="100" autocomplete="name">
            </div>
            <div class="field-group">
                <label for="cvPrefSupPhone">Phone</label>
                <input type="text" id="cvPrefSupPhone" maxlength="30" autocomplete="tel">
            </div>
            <div class="field-group">
                <label for="cvPrefSupEmail">Email</label>
                <input type="email" id="cvPrefSupEmail" maxlength="100" autocomplete="email">
            </div>
            <div class="field-group">
                <label for="cvPrefSupUrl">Shop / Website URL</label>
                <input type="text" id="cvPrefSupUrl" maxlength="255" placeholder="https://...">
            </div>
        </div>
        <div class="confirm-modal-actions">
            <button type="button" id="cvPrefSupModalCancel" class="confirm-btn confirm-btn-cancel">Cancel</button>
            <button type="button" id="cvPrefSupModalSave" class="confirm-btn confirm-btn-ok">Save</button>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="cvConfirmModal" class="confirm-modal" style="display:none;">
    <div class="confirm-modal-backdrop"></div>
    <div class="confirm-modal-card" role="dialog" aria-modal="true" aria-labelledby="cvConfirmTitle">
        <div class="confirm-modal-header">
            <h3 id="cvConfirmTitle">Please Confirm</h3>
        </div>
        <div class="confirm-modal-body" id="cvConfirmMessage">Are you sure?</div>
        <div class="confirm-modal-actions">
            <button type="button" id="cvConfirmCancelBtn" class="confirm-btn confirm-btn-cancel">Cancel</button>
            <button type="button" id="cvConfirmOkBtn" class="confirm-btn confirm-btn-ok">Confirm</button>
        </div>
    </div>
</div>

<?php if ($accessError === null): ?>
<script>
window.CWIRMS_PREF_SUP = <?php echo json_encode([
    'editable'  => ($isRequesterOwnedCanvass && !$verifierChainLocked),
    'requestId' => $requestId,
    'api'       => '../../app/api/canvass_detail.php',
    'isRequester' => $isRequesterOwnedCanvass,
]); ?>;
</script>
<script src="../assets/js/dean_canvass_form.js"></script>
<?php if ($isGsdCanvassReview): ?>
<script>
window.IMRMS_GSD_CANVASS = <?php echo json_encode([
    'requestId' => $requestId,
    'gsdApi' => '../../app/api/gsd/requests.php',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
</script>
<script src="../assets/js/gsd_canvass_actions.js"></script>
<?php elseif ($isComptrollerCanvassReview || $isPresidentCanvassReview): ?>
<script>
window.IMRMS_CANVASS_REVIEWER = <?php echo json_encode([
    'requestId' => $requestId,
    'role' => $isPresidentCanvassReview ? 'president' : 'comptroller',
    'comptrollerApi' => '../../app/api/comptroller.php',
    'presidentApi' => '../../app/api/president/requests.php',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
</script>
<script src="../assets/js/canvass_reviewer_approval.js"></script>
<?php endif; ?>
<?php endif; ?>
</body>
</html>
