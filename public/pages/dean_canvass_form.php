<?php
require_once __DIR__ . '/partials/requisition_workspace_page_context.php';
require_once __DIR__ . '/../../app/api/requisition_detail_payload.php';

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
$isDepartmentCanvassView = false;
$isDeanCanvassView = false;
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
                
                // Get stored requester name
                $nameStmt = $db->prepare('SELECT requester_name FROM requisition_item WHERE request_id = ?');
                $nameStmt->execute([$requestId]);
                $storedName = trim((string) ($nameStmt->fetchColumn() ?: ''));
                
                if ($storedName !== '') {
                    $requesterDisplayName = $storedName;
                } else {
                    $requesterDisplayName = $em !== '' ? (explode('@', $em)[0] ?? $em) : '—';
                }
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
                
                // Get stored requester name
                $nameStmt = $db->prepare('SELECT requester_name FROM requisition_item WHERE request_id = ?');
                $nameStmt->execute([$requestId]);
                $storedName = trim((string) ($nameStmt->fetchColumn() ?: ''));
                
                if ($storedName !== '') {
                    $requesterDisplayName = $storedName;
                } else {
                    $requesterDisplayName = $em !== '' ? (explode('@', $em)[0] ?? $em) : '—';
                }
                $requesterRoleDisplay = (string) ($owner['role'] ?? '');
                }
            }
        }
    }
} elseif ($from === 'inventory') {
    $inventoryProgressQs = 'rid=' . $requestId . ($progressFrom === 'status' ? '&from=status' : '');
    $accessErrorReturnHref = 'requisition_status_progress.php?' . $inventoryProgressQs;
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
                
                // Get stored requester name
                $nameStmt = $db->prepare('SELECT requester_name FROM requisition_item WHERE request_id = ?');
                $nameStmt->execute([$requestId]);
                $storedName = trim((string) ($nameStmt->fetchColumn() ?: ''));
                
                if ($storedName !== '') {
                    $requesterDisplayName = $storedName;
                } else {
                    $requesterDisplayName = $em !== '' ? (explode('@', $em)[0] ?? $em) : '—';
                }
                $requesterRoleDisplay = (string) ($owner['role'] ?? '');
            }
        }
    }
} elseif ($from === 'comptroller' || ($from === 'history' && $isComptrollerRole)) {
    $comptrollerProgressQs = $requestId > 0
        ? ('rid=' . $requestId . ($progressFrom === 'status' ? '&from=status' : ''))
        : '';
    $comptrollerProgressHref = 'requisition_status_progress.php' . ($comptrollerProgressQs !== '' ? ('?' . $comptrollerProgressQs) : '');
    $backHref = $from === 'history' ? 'audit_trail.php' : $comptrollerProgressHref;
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
                
                // Get stored requester name
                $nameStmt = $db->prepare('SELECT requester_name FROM requisition_item WHERE request_id = ?');
                $nameStmt->execute([$requestId]);
                $storedName = trim((string) ($nameStmt->fetchColumn() ?: ''));
                
                if ($storedName !== '') {
                    $requesterDisplayName = $storedName;
                } else {
                    $requesterDisplayName = $em !== '' ? (explode('@', $em)[0] ?? $em) : '—';
                }
                $requesterRoleDisplay = (string) ($owner['role'] ?? '');
            }
        }
    }
} elseif ($from === 'president' || ($from === 'history' && $isPresidentRole)) {
    $presidentProgressHref = 'president_requisition_status_progress.php' . ($requestId > 0 ? ('?rid=' . $requestId) : '');
    $backHref = $from === 'history' ? 'audit_trail.php' : $presidentProgressHref;
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
                
                // Get stored requester name
                $nameStmt = $db->prepare('SELECT requester_name FROM requisition_item WHERE request_id = ?');
                $nameStmt->execute([$requestId]);
                $storedName = trim((string) ($nameStmt->fetchColumn() ?: ''));
                
                if ($storedName !== '') {
                    $requesterDisplayName = $storedName;
                } else {
                    $requesterDisplayName = $em !== '' ? (explode('@', $em)[0] ?? $em) : '—';
                }
                $requesterRoleDisplay = (string) ($owner['role'] ?? '');
            }
        }
    }
} elseif ($from === 'department') {
    $backHref = 'department_approval_workflow.php';
    $accessErrorReturnHref = 'department_approval_workflow.php';
    if (!$isDepartmentLogin) {
        $accessError = 'Only department users can open this view.';
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
                $isDepartmentCanvassView = true;
                $uStmt = $db->prepare('SELECT Email, role FROM user WHERE user_id = ?');
                $uStmt->execute([(int) $reqRow['user_id']]);
                $owner = $uStmt->fetch(PDO::FETCH_ASSOC);
                $em = (string) ($owner['Email'] ?? '');
                
                // Get stored requester name
                $nameStmt = $db->prepare('SELECT requester_name FROM requisition_item WHERE request_id = ?');
                $nameStmt->execute([$requestId]);
                $storedName = trim((string) ($nameStmt->fetchColumn() ?: ''));
                
                if ($storedName !== '') {
                    $requesterDisplayName = $storedName;
                } else {
                    $requesterDisplayName = $em !== '' ? (explode('@', $em)[0] ?? $em) : '—';
                }
                $requesterRoleDisplay = (string) ($owner['role'] ?? '');
            }
        }
    }
} elseif ($from === 'dean') {
    $deanProgressQs = $requestId > 0
        ? ('rid=' . $requestId . ($progressFrom === 'status' ? '&from=status' : ''))
        : '';
    $backHref = 'dean_requisition_status_progress.php' . ($deanProgressQs !== '' ? ('?' . $deanProgressQs) : '');
    $accessErrorReturnHref = $backHref;
    $isDeanRole = (strtolower(trim((string) ($user['role'] ?? ''))) === 'user');
    if (!$isDeanRole) {
        $accessError = 'Only dean users can open this view.';
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
                $isDeanCanvassView = true;
                $uStmt = $db->prepare('SELECT Email, role FROM user WHERE user_id = ?');
                $uStmt->execute([(int) $reqRow['user_id']]);
                $owner = $uStmt->fetch(PDO::FETCH_ASSOC);
                $em = (string) ($owner['Email'] ?? '');
                
                // Get stored requester name from requisition_item
                $nameStmt = $db->prepare('SELECT requester_name FROM requisition_item WHERE request_id = ?');
                $nameStmt->execute([$requestId]);
                $storedName = trim((string) ($nameStmt->fetchColumn() ?: ''));
                
                if ($storedName !== '') {
                    $requesterDisplayName = $storedName;
                } else {
                    $requesterDisplayName = $em !== '' ? (explode('@', $em)[0] ?? $em) : '—';
                }
                $requesterRoleDisplay = (string) ($owner['role'] ?? '');
            }
        }
    }
    } else {
    $backHref =
        'dean_requisition_status_progress.php?rid=' .
        $requestId .
        ($progressFrom === 'status' ? '&from=status' : '');
    $own = $db->prepare('SELECT request_id, user_id FROM requisition_item WHERE request_id = ? AND user_id = ?');
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
            // Get stored requester name from requisition_item
            $nameStmt = $db->prepare('SELECT requester_name FROM requisition_item WHERE request_id = ?');
            $nameStmt->execute([$requestId]);
            $storedName = trim((string) ($nameStmt->fetchColumn() ?: ''));
            
            if ($storedName !== '') {
                $requesterDisplayName = $storedName;
            } else {
                // Fallback to current user's display name
                $requesterDisplayName = $displayName;
            }
        }
    }
}

$isReviewerCanvassReadonly = $isGsdCanvassReview
    || $isInventoryManagerCanvassReview
    || $isComptrollerCanvassReview
    || $isPresidentCanvassReview
    || $isComptrollerCanvassHistory
    || $isPresidentCanvassHistory
    || $isDepartmentCanvassView;

$isOwner = false;
    if ($requestId > 0 && isset($_SESSION['user_id'])) {
        $checkOwner = $db->prepare('SELECT request_id FROM requisition_item WHERE request_id = ? AND user_id = ?');
        $checkOwner->execute([$requestId, $_SESSION['user_id']]);
        $isOwner = (bool) $checkOwner->fetchColumn();
    }
    if ($isOwner) {
        $isRequesterOwnedCanvass = true;
    }

$gsdVerificationStatus = 'pending';
$showCanvassPricingOverview = $isGsdCanvassReview;

$canViewQtyPricingOverview = $isComptrollerCanvassReview
    || $isComptrollerCanvassHistory
    || $isGsdCanvassReview
    || $isInventoryManagerCanvassReview
    || $isRequesterOwnedCanvass
    || $isPresidentCanvassReview
    || $isPresidentCanvassHistory
    || $isDepartmentCanvassView;
$showComptrollerPricingOverview = false;
$pricingOverviewViewerRole = '';
$pricingOverviewInteractive = false;

$comptrollerCompStatus = 'pending';
$comptrollerPricingOverview = null;
$comptrollerPricingReadonly = $isComptrollerCanvassHistory;
$comptrollerApprovalFlash = null;
if ($accessError === null && $requestId > 0 && ($canViewQtyPricingOverview || $isGsdCanvassReview)) {
    require_once __DIR__ . '/../../app/api/approval_tables.php';
    require_once __DIR__ . '/../../app/helpers/comptroller_qty_approval.php';
    ensureComptrollerPartialQtyColumns($db);
    ensureRequisitionPreferredQuoteColumns($db);
    ensureSuggestedSupplierSelectionSourceColumn($db);

    if ($isGsdCanvassReview) {
        $gsdStatusStmt = $db->prepare(
            'SELECT LOWER(TRIM(COALESCE(gsd_status, \'pending\'))) FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $gsdStatusStmt->execute([$requestId]);
        $gsdVerificationStatus = strtolower(trim((string) ($gsdStatusStmt->fetchColumn() ?: 'pending')));
        if ($gsdVerificationStatus === '') {
            $gsdVerificationStatus = 'pending';
        }
        $showCanvassPricingOverview = !in_array($gsdVerificationStatus, ['accept', 'reject'], true);
    }

    if ($canViewQtyPricingOverview && cwirmsComptrollerRequestHasSuggestedSuppliersPerItem($db, $requestId)) {
        $showComptrollerPricingOverview = true;

        if ($isComptrollerCanvassReview || $isComptrollerCanvassHistory) {
            $pricingOverviewViewerRole = 'comptroller';
        } elseif ($isGsdCanvassReview) {
            $pricingOverviewViewerRole = 'gsd_officer';
        } elseif ($isInventoryManagerCanvassReview) {
            $pricingOverviewViewerRole = 'inventory_manager';
        } elseif ($isPresidentCanvassReview || $isPresidentCanvassHistory) {
            $pricingOverviewViewerRole = 'president';
        } elseif ($isRequesterOwnedCanvass) {
            $pricingOverviewViewerRole = 'requester';
        }

        $compStmt = $db->prepare(
            'SELECT LOWER(TRIM(COALESCE(comp_status, \'pending\'))) FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
        );
        $compStmt->execute([$requestId]);
        $comptrollerCompStatus = strtolower(trim((string) ($compStmt->fetchColumn() ?: 'pending')));
        if ($comptrollerCompStatus === '') {
            $comptrollerCompStatus = 'pending';
        }
        $comptrollerPricingReadonly = $isComptrollerCanvassHistory || in_array($comptrollerCompStatus, ['accept', 'reject'], true);
        $pricingOverviewInteractive = $isComptrollerCanvassReview && !$comptrollerPricingReadonly;
        $comptrollerPricingOverview = cwirmsComptrollerPricingOverviewForRequest($db, $requestId);

        if ($isGsdCanvassReview) {
            $showCanvassPricingOverview = false;
        }
    }
}
$approvalMsg = trim((string) ($_GET['approval_msg'] ?? ''));
$approvalType = trim((string) ($_GET['approval_type'] ?? ''));
if ($approvalMsg !== '' && in_array($approvalType, ['success', 'error'], true) && $showComptrollerPricingOverview) {
    $comptrollerApprovalFlash = ['type' => $approvalType, 'message' => $approvalMsg];
}

$verifierChainLocked = ($accessError === null && $requestId > 0)
    ? requisitionVerifierChainLockedForRequest($db, $requestId)
    : false;
$isCanvassStructureUiHidden = $isReviewerCanvassReadonly
    || ($verifierChainLocked && ($isRequesterOwnedCanvass || $isCanvasserCanvassView));
$isCanvasMatrixReadonly = $isReviewerCanvassReadonly
    || ($verifierChainLocked && ($isRequesterOwnedCanvass || $isCanvasserCanvassView));
// New per-line preferred-quote view for the request owner (replaces old breakdown + matrix UI).
// Covers both the direct owner path (no ?from=) and the department-user path (?from=dean).
$isRequesterEditView = ($isRequesterOwnedCanvass || $isDeanCanvassView)
    && !$isReviewerCanvassReadonly && !$verifierChainLocked;
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
    } elseif ($isDepartmentCanvassView) {
        $rfStepLine = 'Abstract of quotation · department (read-only)';
        $rfLinkUrl = '';
        $rfLinkText = '';
    }
}
$cvWfCanvasStatus = 'pending';
$cvWfGsdStatus = 'pending';
$cvWfCompStatus = 'pending';
$cvWfPresStatus = 'pending';
if ($rfRequestId > 0 && $accessError === null) {
    $cvApprStmt = $db->prepare(
        'SELECT LOWER(TRIM(COALESCE(canvas_status, \'pending\'))) AS canvas_status,
                LOWER(TRIM(COALESCE(gsd_status, \'pending\'))) AS gsd_status,
                LOWER(TRIM(COALESCE(comp_status, \'pending\'))) AS comp_status,
                LOWER(TRIM(COALESCE(pres_status, \'pending\'))) AS pres_status
         FROM canvass_verification_approval WHERE request_id = ? LIMIT 1'
    );
    $cvApprStmt->execute([$rfRequestId]);
    $cvApprRow = $cvApprStmt->fetch(PDO::FETCH_ASSOC);
    if ($cvApprRow) {
        $cvWfCanvasStatus = strtolower(trim((string) ($cvApprRow['canvas_status'] ?? 'pending'))) ?: 'pending';
        $cvWfGsdStatus = strtolower(trim((string) ($cvApprRow['gsd_status'] ?? 'pending'))) ?: 'pending';
        $cvWfCompStatus = strtolower(trim((string) ($cvApprRow['comp_status'] ?? 'pending'))) ?: 'pending';
        $cvWfPresStatus = strtolower(trim((string) ($cvApprRow['pres_status'] ?? 'pending'))) ?: 'pending';
    }
}
$cvWfActiveStage = 'canvass';
if ($isGsdCanvassReview) {
    $cvWfActiveStage = 'gsd';
} elseif ($isComptrollerCanvassReview || $isComptrollerCanvassHistory) {
    $cvWfActiveStage = 'comptroller';
} elseif ($isPresidentCanvassReview || $isPresidentCanvassHistory) {
    $cvWfActiveStage = 'president';
} elseif ($isCanvasserCanvassView) {
    $cvWfActiveStage = 'canvass';
}
$cvWfPillClass = static function (string $status, string $stageKey, string $activeStage): string {
    $s = strtolower(trim($status));
    if ($s === 'accept') {
        return 'cv-wf-pill--done';
    }
    if ($s === 'reject') {
        return 'cv-wf-pill--rejected';
    }
    if ($stageKey === $activeStage) {
        return 'cv-wf-pill--active';
    }
    return '';
};
$cvCanvassStatusLabel = 'Pending';
$cvCanvassStatusClass = 'cv-canvass-status-badge--pending';
if ($cvWfCanvasStatus === 'accept') {
    $cvCanvassStatusLabel = 'Canvass complete';
    $cvCanvassStatusClass = 'cv-canvass-status-badge--complete';
} elseif ($cvWfCanvasStatus === 'reject') {
    $cvCanvassStatusLabel = 'Canvass rejected';
    $cvCanvassStatusClass = 'cv-canvass-status-badge--rejected';
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
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=wlc33">
    <link rel="stylesheet" href="../assets/css/requisition_form.css">
    <?php if ($showCanvassPricingOverview || $showComptrollerPricingOverview): ?>
    <link rel="stylesheet" href="../assets/css/gsd_canvass_pricing_overview.css">
    <?php endif; ?>
    <?php if ($showComptrollerPricingOverview): ?>
    <link rel="stylesheet" href="../assets/css/comptroller_pricing_overview.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="page-canvass-form">

<main class="requisition-main">
        <div class="requisition-card<?php echo $isCanvasMatrixReadonly ? ' gsd-canvass-readonly' : ''; ?>" id="canvassCard" data-request-id="<?php echo (int) $requestId; ?>" data-api="../../app/api/canvass_detail.php" data-dean-api="../../app/api/dean_requisition.php" data-gsd-readonly="<?php echo $isCanvasMatrixReadonly ? '1' : '0'; ?>" data-canvasser-register="<?php echo $isCanvasserCanvassView ? '1' : '0'; ?>" data-canvasser-api="../../app/api/canvasser_requests.php" data-requester-edit="<?php echo $isRequesterEditView ? '1' : '0'; ?>">
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

        <?php if ($comptrollerApprovalFlash): ?>
        <div class="cv-comptroller-flash cv-comptroller-flash-<?php echo htmlspecialchars($comptrollerApprovalFlash['type']); ?>" role="status">
            <?php echo htmlspecialchars($comptrollerApprovalFlash['message']); ?>
        </div>
        <?php endif; ?>

        <?php if ($rfRequestId > 0 && $accessError === null): ?>
        <nav class="cv-workflow-breadcrumb" aria-label="Workflow progress">
            <div class="cv-workflow-breadcrumb-track">
                <span class="cv-wf-pill cv-wf-pill--id">Request #<?php echo (int) $rfRequestId; ?></span>
                <span class="cv-wf-sep" aria-hidden="true">›</span>
                <span class="cv-wf-pill <?php echo htmlspecialchars($cvWfPillClass($cvWfCanvasStatus, 'canvass', $cvWfActiveStage)); ?>">Canvass</span>
                <span class="cv-wf-sep" aria-hidden="true">›</span>
                <span class="cv-wf-pill <?php echo htmlspecialchars($cvWfPillClass($cvWfGsdStatus, 'gsd', $cvWfActiveStage)); ?>">G.S.D.</span>
                <span class="cv-wf-sep" aria-hidden="true">›</span>
                <span class="cv-wf-pill <?php echo htmlspecialchars($cvWfPillClass($cvWfCompStatus, 'comptroller', $cvWfActiveStage)); ?>">Comptroller</span>
                <span class="cv-wf-sep" aria-hidden="true">›</span>
                <span class="cv-wf-pill <?php echo htmlspecialchars($cvWfPillClass($cvWfPresStatus, 'president', $cvWfActiveStage)); ?>">President</span>
            </div>
            <?php if ($rfLinkUrl !== '' && $rfLinkText !== ''): ?>
            <a class="cv-workflow-breadcrumb-link" href="<?php echo htmlspecialchars($rfLinkUrl); ?>"><?php echo htmlspecialchars($rfLinkText); ?></a>
            <?php endif; ?>
            <?php if ($rfHint !== ''): ?>
            <p class="cv-workflow-breadcrumb-hint"><?php echo htmlspecialchars($rfHint); ?></p>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
        <?php if ($rfRequestId > 0 && $accessError === null && $canShowPurchaseRequisitionLink): ?>
        <div class="req-flow-context">
            <div class="req-flow-context-top">
                <div class="req-flow-context-main">
                    <span class="req-flow-step">Purchase requisition is available after G.S.D., Comptroller, and President verify this canvass sheet.</span>
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
                    echo $from === 'history' ? 'Return to audit trail' : 'Return to requisition progress';
                } elseif ($from === 'president' || ($from === 'history' && $isPresidentRole)) {
                    echo $from === 'history' ? 'Return to audit trail' : 'Return to president requests';
                } else {
                    echo 'Return to requisition management';
                }
            ?></a></p>
        </div>
        <?php else: ?>

        <div class="requisition-info cv-meta-grid">
            <div class="field-group">
                <label for="cvRequesterName">Requester Name</label>
                <input type="text" id="cvRequesterName" value="<?php echo htmlspecialchars($requesterDisplayName); ?>" disabled>
            </div>
            <div class="field-group">
                <label for="cvOfficeDisplay">Office</label>
                <input type="text" id="cvOfficeDisplay" value="—" disabled>
            </div>
            <div class="field-group">
                <label for="cvRequestDate">Requested Date</label>
                <input type="text" id="cvRequestDate" value="—" disabled>
            </div>
            <div class="field-group">
                <label for="cvPurpose">Purpose of Request</label>
                <input type="text" id="cvPurpose" value="" disabled placeholder="—">
            </div>
            <div class="field-group">
                <label for="cvFacilityDisplay">Location / Facility</label>
                <input type="text" id="cvFacilityDisplay" value="—" disabled>
            </div>
            <div class="field-group">
                <label for="cvFacultyRole">Role</label>
                <input type="text" id="cvFacultyRole" value="<?php echo htmlspecialchars($requesterRoleDisplay); ?>" disabled>
            </div>
            <div class="field-group cv-meta-status-field">
                <span class="cv-meta-status-label">Canvass Status</span>
                <span id="cvCanvassStatusBadge" class="cv-canvass-status-badge <?php echo htmlspecialchars($cvCanvassStatusClass); ?>"><?php echo htmlspecialchars($cvCanvassStatusLabel); ?></span>
            </div>
        </div>

        <section class="rf-section rf-section-requisition-items cv-requested-ref" aria-label="Items from the requisition">
            <h2 class="rf-section-heading">Items on Requisition</h2>
            <p class="cv-requested-ref-hint"><?php echo $isGsdCanvassReview
                ? 'Line items as recorded on the original requisition (read-only).'
                : ($isCanvasserCanvassView
                    ? 'Requester’s line items (read-only). Canvass quotation lines set by the requester appear below — you only add suppliers and prices.'
                    : ($isRequesterEditView
                        ? 'These are the items you requested. Add your <strong>preferred supplier quotes</strong> for each item using the button in the last column.'
                        : 'What you originally submitted. Use <strong>Canvass items</strong> below for quotation lines (you can match or refine these).')); ?></p>
            <div class="requested-items-table-wrap">
                <table class="requested-items-table" id="cvRequestedItemsTable">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Item / Sub-description</th>
                            <th scope="col">Qty</th>
                            <th scope="col">Unit</th>
                            <?php if ($isCanvasserCanvassView): ?>
                            <th scope="col">Quotes</th>
                            <?php elseif ($isRequesterEditView): ?>
                            <th scope="col">Preferred Quote</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="cvRequestedItemsTableBody">
                        <tr class="requested-items-empty">
                            <td colspan="<?php echo ($isCanvasserCanvassView || $isRequesterEditView) ? 5 : 4; ?>">Loading…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if (!$isRequesterEditView): ?>
        <section class="rf-section rf-section-canvass-lines">
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
        </div>
        </section>
        <?php endif; // !$isRequesterEditView — hide legacy canvass breakdown for requester ?>

            <?php if (!$isRequesterEditView): ?>
            <section class="rf-section rf-section-preferred cv-preferred-section" id="cvPreferredSection">
                <h2 class="rf-section-heading">Preferred Supplier Matrix</h2>
                <div class="cv-preferred-section-head" id="cvPreferredSectionHead">
                    <span class="section-label sr-only">Preferred Suppliers</span>
                </div>
                <p class="cv-preferred-hint" id="cvPreferredHint"></p>
                <div class="cv-preferred-picker">
                    <label for="cvPrefSupplierSearch" class="sr-only">Search suppliers</label>
                    <div class="cv-pref-search-row">
                        <span class="cv-pref-search-icon" aria-hidden="true"><i class="fas fa-magnifying-glass"></i></span>
                        <input type="text" id="cvPrefSupplierSearch" class="cv-pref-search-input" placeholder="Search supplier name or contact…" autocomplete="off">
                    </div>
                    <div id="cvPrefSupplierSearchList" class="cv-pref-search-list"></div>
                    <div id="cvPrefSupplierInfoPanel" class="cv-pref-supplier-info-panel" hidden>
                        <div id="cvPrefSupplierInfoPanelBody" class="cv-pref-supplier-info-panel-body"></div>
                    </div>
                </div>
                <div class="supplier-table-group-label preferred-supplier-label sr-only">Preferred supplier matrix</div>
                <div id="cvPreferredCards" class="cv-preferred-cards" aria-live="polite">
                    <div class="empty-state">Loading preferred suppliers…</div>
                </div>
            </section>
            <?php endif; ?>
            <?php if (!$isRequesterEditView): ?>
            <section class="rf-section rf-section-canvassed supplier-section" id="cvCanvasSection" role="region" aria-label="Suppliers and pricing">
                <h2 class="rf-section-heading">Canvassed Supplier Matrix</h2>
                    <?php if ($isCanvasserCanvassView && !$verifierChainLocked): ?>
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

                <div class="supplier-table-group-label canvassed-supplier-label sr-only">Canvassed supplier matrix</div>
                <div id="cvCanvassedSupplierWrap" class="cv-canvassed-wrap" hidden>
                    <div id="cvCanvassedCards" class="cv-preferred-cards cv-canvassed-cards" aria-live="polite">
                        <div class="empty-state">No canvassed supplier quotes yet. This section is filled in by the assigned canvasser.</div>
                    </div>
                </div>
            </section>

            <?php endif; ?>

        <?php if ($showCanvassPricingOverview): ?>
        <section class="rf-section rf-section-abstract-total">
            <h2 class="rf-section-heading">Abstract Total</h2>
        <?php require __DIR__ . '/partials/canvass_pricing_overview.php'; ?>
        </section>
        <?php endif; ?>
        <?php if ($showComptrollerPricingOverview): ?>
        <section class="rf-section rf-section-abstract-total">
            <h2 class="rf-section-heading">Abstract Total</h2>
        <?php require __DIR__ . '/partials/comptroller_pricing_overview.php'; ?>
        </section>
        <?php endif; ?>

        <div class="approval-section cv-approval-section" aria-label="Canvass verification status">
            <div class="approval-card approval-card-canvass-verifiers" id="cvApprovalStrip">
                <div class="approval-role<?php echo $isGsdCanvassReview ? ' approval-role-cv-assignee' : ''; ?>">
                    <div class="circle-icon inactive"><i class="fas fa-check"></i></div>
                    <div class="approval-role-body">
                        <div class="approval-name">Canvasser</div>
                        <div class="approval-sub cv-appr-kind"><?php echo $isGsdCanvassReview ? 'Assign &amp; status' : 'Canvassed by'; ?></div>
                        <div class="cv-appr-detail" id="cvApprCanvasserDetail"></div>
                        <?php if ($isGsdCanvassReview): ?>
                        <div class="gsd-canvas-assignee-field gsd-assignee-in-approval gsd-assignee-mode-pick" id="gsdAssigneeInApprovalWrap">
                            <input type="hidden" id="gsdCanvasAssigneeUserId" value="">
                            <input type="hidden" id="gsdSuggestedSupplierId" value="">
                            <div id="gsdAssigneePickWrap" class="gsd-assignee-pick-wrap">
                                <label class="sr-only" for="gsdCanvasAssigneeInput">Assign staff to canvass</label>
                                <input type="text" id="gsdCanvasAssigneeInput" class="gsd-canvas-assignee-input" autocomplete="off" placeholder="Search name or email to assign…">
                                <ul id="gsdCanvasAssigneeSuggestions" class="gsd-canvas-assignee-suggestions" role="listbox" hidden></ul>
                                <button type="button" id="gsdCanvasAssignBtn" class="gsd-canvas-assign-btn" disabled>Assign</button>
                                <p id="gsdAssigneePickHint" class="gsd-canvas-assignee-hint gsd-assignee-pick-hint">Selecting a name does not save automatically. Click Assign to confirm.</p>
                            </div>
                            <div id="gsdAssigneeAssignedWrap" class="gsd-assignee-assigned-wrap" hidden>
                                <div id="gsdAssigneeAssignedName" class="gsd-assignee-assigned-name" aria-live="polite"></div>
                                <button type="button" id="gsdCanvasAssigneeChangeBtn" class="gsd-canvas-assignee-change-btn">Change</button>
                            </div>
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
        <?php if ($showComptrollerPricingOverview): ?>
        <div id="cvComptrollerDeferredBanners" class="cv-comptroller-deferred-banners" hidden aria-live="polite"></div>
        <button type="button" id="cvComptrollerDeferredBannersRestore" class="cv-comptroller-deferred-banners-restore" hidden>
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <span class="cv-comptroller-deferred-banners-restore-label">Show deferred warnings</span>
        </button>
        <?php endif; ?>
        <?php if ($isGsdCanvassReview): ?>
        <div class="comptroller-approve-wrapper gsd-on-cv-actions verifier-decision-bar rf-form-actions">
            <button type="button" id="comptrollerApproveBtn" class="btn-submit"><i class="fas fa-check" aria-hidden="true"></i> Verify</button>
            <button type="button" id="comptrollerRejectBtn" class="btn-secondary comptroller-reject-btn"><i class="fas fa-xmark" aria-hidden="true"></i> Reject</button>
            <button type="button" id="comptrollerUndoBtn" class="btn-secondary comptroller-undo-btn" style="display: none;"><i class="fas fa-rotate-left" aria-hidden="true"></i> Undo decision</button>
        </div>
        <p id="gsdVerifyHint" class="gsd-verify-hint" aria-live="polite"></p>
        <?php elseif ($isComptrollerCanvassReview || $isPresidentCanvassReview): ?>
        <div class="comptroller-approve-wrapper gsd-on-cv-actions verifier-decision-bar rf-form-actions">
            <button type="button" id="comptrollerApproveBtn" class="btn-submit"><i class="fas fa-check" aria-hidden="true"></i> Approve</button>
            <button type="button" id="comptrollerRejectBtn" class="btn-secondary comptroller-reject-btn"><i class="fas fa-xmark" aria-hidden="true"></i> Reject</button>
            <button type="button" id="comptrollerUndoBtn" class="btn-secondary comptroller-undo-btn" style="display: none;"><i class="fas fa-rotate-left" aria-hidden="true"></i> Undo decision</button>
        </div>
        <?php endif; ?>

        <?php if (!$isCanvasMatrixReadonly): ?>
        <div class="btn-submit-wrapper rf-form-actions<?php echo $isCanvasserCanvassView ? ' cv-canvasser-submit-row' : ''; ?>">
            <?php if ($isCanvasserCanvassView): ?>
            <button type="button" id="cvSaveDraftBtn" class="btn-secondary">Save draft</button>
            <button type="button" id="cvCompleteCanvassBtn" class="btn-submit">Complete canvassing</button>
            <button type="button" id="cvCanvasserUndoBtn" class="btn-secondary comptroller-undo-btn" hidden>Undo completion</button>
            <p class="cv-canvasser-save-hint" style="flex-basis:100%;margin:0.35rem 0 0;font-size:0.85rem;color:#64748b;">Save draft keeps your supplier quotes without finishing the step. Complete canvassing saves and records your approval. Use <strong>Undo completion</strong> to reopen the canvass step.</p>
            <?php elseif ($isRequesterOwnedCanvass || $isOwner): ?>
            <button type="button" id="cvSaveDraftBtn" class="btn-secondary">Save draft</button>
            <button type="button" id="cvSaveBtn" class="btn-submit">Save and continue</button>
            <?php else: ?>
            <button type="button" id="cvSaveBtn" class="btn-submit">Save canvass form</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<div id="cvFormToast" class="toast success" style="display:none;"></div>

<?php if ($isCanvasserCanvassView): ?>
<div id="cvCanvasserQuoteModal" class="cv-canvasser-quote-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="cvQuoteModalTitle">
    <div class="cv-canvasser-quote-modal-backdrop" id="cvQuoteModalBackdrop"></div>
    <div class="cv-canvasser-quote-modal-card">
        <div class="cv-canvasser-quote-modal-header">
            <h3 id="cvQuoteModalTitle">Add / Edit Quote</h3>
            <button type="button" class="cv-canvasser-quote-modal-close" id="cvQuoteModalClose" aria-label="Close">&times;</button>
        </div>
        <p class="cv-quote-modal-line-name" id="cvQuoteModalLineName"></p>
        <input type="hidden" id="cvQuoteModalLineId" value="">

        <div class="cv-quote-modal-existing" id="cvQuoteModalExistingWrap">
            <div class="cv-quote-modal-existing-label">Canvassed quotes for this item:</div>
            <div id="cvQuoteModalExistingQuotes"></div>
        </div>

        <?php if (!$verifierChainLocked): ?>
        <hr class="cv-quote-modal-divider">
        <div class="cv-quote-modal-form">
            <div class="cv-quote-modal-form-title">Add / update a quote</div>
            <div class="field-group">
                <label for="cvQuoteModalSupplierBtn">Supplier <em style="color:#b91c1c">*</em></label>
                <div class="cv-quote-modal-supplier-dropdown" id="cvQuoteModalSupplierDropdown">
                    <button type="button" id="cvQuoteModalSupplierBtn" class="supplier-dropdown-btn cv-quote-modal-supplier-btn">
                        <span id="cvQuoteModalSupplierText">Select supplier…</span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <input type="hidden" id="cvQuoteModalSupplierId" value="">
                    <div id="cvQuoteModalSupplierList" class="supplier-dropdown-list cv-quote-modal-supplier-list"></div>
                </div>
            </div>
            <div class="field-group">
                <label for="cvQuoteModalPrice">Unit Price (PHP) <em style="color:#b91c1c">*</em></label>
                <input type="number" id="cvQuoteModalPrice" min="0" step="0.01" placeholder="0.00" autocomplete="off">
            </div>
            <div class="field-group">
                <label for="cvQuoteModalBenefits">Benefits / Notes <span style="color:#64748b;font-weight:400;">(optional)</span></label>
                <textarea id="cvQuoteModalBenefits" rows="2" placeholder="e.g. Includes VAT, free delivery, warranty…"></textarea>
            </div>
        </div>
        <div class="cv-canvasser-quote-modal-actions">
            <button type="button" class="btn-secondary" id="cvQuoteModalCancel">Cancel</button>
            <button type="button" class="btn-submit" id="cvQuoteModalSave"><i class="fas fa-check" aria-hidden="true"></i> Save quote</button>
        </div>
        <?php else: ?>
        <p style="margin:1rem 0 0;font-size:0.9rem;color:#64748b;">This canvass is locked — no changes can be made.</p>
        <div class="cv-canvasser-quote-modal-actions">
            <button type="button" class="btn-secondary" id="cvQuoteModalCancel">Close</button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($isRequesterEditView): ?>
<div id="cvRequesterPrefQuoteModal" class="cv-canvasser-quote-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="cvPrefQuoteModalTitle">
    <div class="cv-canvasser-quote-modal-backdrop" id="cvPrefQuoteModalBackdrop"></div>
    <div class="cv-canvasser-quote-modal-card">
        <div class="cv-canvasser-quote-modal-header">
            <h3 id="cvPrefQuoteModalTitle">Add / Edit Preferred Quote</h3>
            <button type="button" class="cv-canvasser-quote-modal-close" id="cvPrefQuoteModalClose" aria-label="Close">&times;</button>
        </div>
        <p class="cv-quote-modal-line-name" id="cvPrefQuoteModalLineName"></p>
        <input type="hidden" id="cvPrefQuoteModalLineId" value="">

        <div class="cv-quote-modal-existing" id="cvPrefQuoteModalExistingWrap">
            <div class="cv-quote-modal-existing-label">Your preferred quotes for this item:</div>
            <div id="cvPrefQuoteModalExistingQuotes"></div>
        </div>

        <hr class="cv-quote-modal-divider">
        <div class="cv-quote-modal-form">
            <div class="cv-quote-modal-form-title">Add / update a preferred quote</div>
            <div class="field-group">
                <label for="cvPrefQuoteModalSupplierBtn">Supplier <em style="color:#b91c1c">*</em></label>
                <div class="cv-quote-modal-supplier-dropdown" id="cvPrefQuoteModalSupplierDropdown">
                    <button type="button" id="cvPrefQuoteModalSupplierBtn" class="supplier-dropdown-btn cv-quote-modal-supplier-btn">
                        <span id="cvPrefQuoteModalSupplierText">Select supplier…</span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <input type="hidden" id="cvPrefQuoteModalSupplierId" value="">
                    <div id="cvPrefQuoteModalSupplierList" class="supplier-dropdown-list cv-quote-modal-supplier-list"></div>
                </div>
            </div>
            <div class="field-group">
                <label for="cvPrefQuoteModalPrice">Unit Price (PHP) <em style="color:#b91c1c">*</em></label>
                <input type="number" id="cvPrefQuoteModalPrice" min="0" step="0.01" placeholder="0.00" autocomplete="off">
            </div>
            <div class="field-group">
                <label for="cvPrefQuoteModalBenefits">Benefits / Notes <span style="color:#64748b;font-weight:400;">(optional)</span></label>
                <textarea id="cvPrefQuoteModalBenefits" rows="2" placeholder="e.g. Includes VAT, free delivery, warranty…"></textarea>
            </div>
        </div>
        <div class="cv-canvasser-quote-modal-actions">
            <button type="button" class="btn-secondary" id="cvPrefQuoteModalCancel">Cancel</button>
            <button type="button" class="btn-submit" id="cvPrefQuoteModalSave"><i class="fas fa-check" aria-hidden="true"></i> Save preferred quote</button>
        </div>
    </div>
</div>
<?php endif; ?>

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
            <label class="canvasser-supplier-field"><span>TIN <span class="cv-field-optional">(optional)</span></span><input type="text" id="canvasserNewSupplierTin" name="tin" maxlength="20" placeholder="e.g. 123-456-789-000" autocomplete="off"></label>
        </form>
        <div class="canvasser-supplier-modal-actions">
            <button type="button" class="btn-secondary" data-close-canvasser-supplier-modal>Cancel</button>
            <button type="button" id="canvasserNewSupplierSubmit" class="btn-submit">Save supplier</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (($isOwner || $isRequesterOwnedCanvass) && !$verifierChainLocked && !$isReviewerCanvassReadonly): ?>
<div id="cvPrefSupModal" class="confirm-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="cvPrefSupModalTitle">
    <div class="confirm-modal-backdrop" id="cvPrefSupModalBackdrop"></div>
    <div class="confirm-modal-card" style="max-width:480px;width:92%;">
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
                <label for="cvPrefSupTin">TIN <span class="cv-field-optional">(optional)</span></label>
                <input type="text" id="cvPrefSupTin" maxlength="20" placeholder="e.g. 123-456-789-000" autocomplete="off">
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
            <div class="field-group">
                <label for="cvPrefSupAddress">Address / location</label>
                <input type="text" id="cvPrefSupAddress" maxlength="255" placeholder="Street, building, mall branch, etc." autocomplete="street-address">
            </div>
            <div class="field-group" style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
                <div>
                    <label for="cvPrefSupCity">City</label>
                    <input type="text" id="cvPrefSupCity" maxlength="50" autocomplete="address-level2">
                </div>
                <div>
                    <label for="cvPrefSupCountry">Country</label>
                    <input type="text" id="cvPrefSupCountry" maxlength="50" autocomplete="country-name">
                </div>
            </div>
            <div class="field-group">
                <label for="cvPrefSupPostal">Postal code</label>
                <input type="text" id="cvPrefSupPostal" maxlength="20" autocomplete="postal-code">
            </div>
        </div>
        <div class="confirm-modal-actions">
            <button type="button" id="cvPrefSupModalCancel" class="confirm-btn confirm-btn-cancel">Cancel</button>
            <button type="button" id="cvPrefSupModalSave" class="confirm-btn confirm-btn-ok">Save</button>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="cvQuotePhotoLightbox" class="cv-quote-photo-lightbox hidden" role="dialog" aria-modal="true" aria-label="Quotation photo preview" aria-hidden="true">
    <button type="button" class="cv-quote-photo-lightbox-close" id="cvQuotePhotoLightboxClose" aria-label="Close preview">&times;</button>
    <img id="cvQuotePhotoLightboxImg" class="cv-quote-photo-lightbox-img" src="" alt="Quotation photo preview">
</div>

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
    'editable'  => ($isOwner || $isRequesterOwnedCanvass) && !$verifierChainLocked && !$isReviewerCanvassReadonly,
    'requestId' => $requestId,
    'api'       => '../../app/api/canvass_detail.php',
    'isRequester' => $isRequesterOwnedCanvass || $isOwner,  
]); ?>;
</script>
<script src="../assets/js/dean_canvass_form.js"></script>
<?php if ($showCanvassPricingOverview): ?>
<script src="../assets/js/gsd_canvass_pricing_overview.js"></script>
<?php endif; ?>
<?php if ($showComptrollerPricingOverview && $comptrollerPricingOverview): ?>
<script>
window.CWIRMS_COMPTROLLER_PRICING = <?php echo json_encode([
    'requestId' => $requestId,
    'readonly' => !$pricingOverviewInteractive,
    'interactive' => $pricingOverviewInteractive,
    'viewerRole' => $pricingOverviewViewerRole,
    'comptrollerCompStatus' => $comptrollerCompStatus,
    'currency' => $comptrollerPricingOverview['currency'] ?? 'PHP',
    'lines' => $comptrollerPricingOverview['lines'] ?? [],
    'show_discount_column' => !empty($comptrollerPricingOverview['show_discount_column']),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
</script>
<script src="../assets/js/comptroller_pricing_overview.js"></script>
<?php endif; ?>
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
