(function () {
    const cfg = window.IMRMS_PURCHASE_REQUISITION_CONFIG || {};
    const requestId = Number(cfg.requestId || 0);
    const api = cfg.api || '../../app/api/purchase_requisition.php';
    const isInventoryVerifier = Boolean(cfg.isInventoryVerifier);
    const isPresidentVerifier = Boolean(cfg.isPresidentVerifier);

    const UNDO_WINDOW_MS = 24 * 60 * 60 * 1000;
    let undoHideTimer = null;

    function undoWindowRemainingMs(timestampStr) {
        if (!timestampStr) return 0;
        const decided = new Date(timestampStr.replace(' ', 'T'));
        if (isNaN(decided.getTime())) return 0;
        return Math.max(0, UNDO_WINDOW_MS - (Date.now() - decided.getTime()));
    }

    const requestNoEl = document.getElementById('prRequestNo');
    const requesterEl = document.getElementById('prRequester');
    const locationEl = document.getElementById('prLocation');
    const requestedAtEl = document.getElementById('prRequestedAt');
    const purposeEl = document.getElementById('prPurpose');
    const tbody = document.getElementById('purchaseReqBody');
    const grandTotalEl = document.getElementById('purchaseReqGrandTotal');
    const invVerifiedStatusEl = document.getElementById('prInvVerifiedStatus');
    const presApprovedStatusEl = document.getElementById('prPresApprovedStatus');
    const backBtn = document.getElementById('purchaseReqBackBtn');
    const toast = document.getElementById('purchaseReqToast');
    const approveBtn = document.getElementById('prApproveBtn');
    const rejectBtn = document.getElementById('prRejectBtn');
    const undoBtn = document.getElementById('prUndoBtn');
    const rejectReasonEl = document.getElementById('prRejectReason');
    const presidentRejectReasonEl = document.getElementById('prPresidentRejectReason');
    const confirmModal = document.getElementById('purchaseConfirmModal');
    const confirmMessage = document.getElementById('purchaseConfirmMessage');
    const confirmCancelBtn = document.getElementById('purchaseConfirmCancelBtn');
    const confirmOkBtn = document.getElementById('purchaseConfirmOkBtn');

    let prRejectReasonArmed = false;

    function getRejectNoteEl() {
        if (isInventoryVerifier) {
            return rejectReasonEl;
        }
        if (isPresidentVerifier) {
            return presidentRejectReasonEl;
        }
        return null;
    }

    function getRejectPanel() {
        const el = getRejectNoteEl();
        return el ? el.closest('.pr-rejection-panel') : null;
    }

    function setRejectPanelArmed(armed) {
        prRejectReasonArmed = armed;
        const el = getRejectNoteEl();
        const panel = getRejectPanel();
        if (panel) {
            panel.classList.toggle('pr-rejection-panel--hidden', !armed);
            panel.classList.toggle('pr-rejection-panel--active', armed);
            panel.setAttribute('aria-hidden', armed ? 'false' : 'true');
        }
    }

    function resetRejectPanel() {
        prRejectReasonArmed = false;
        const el = getRejectNoteEl();
        const panel = getRejectPanel();
        if (panel) {
            panel.classList.add('pr-rejection-panel--hidden');
            panel.classList.remove('pr-rejection-panel--active');
            panel.setAttribute('aria-hidden', 'true');
        }
    }

    function showToast(message, type = 'error') {
        if (!toast) {
            return;
        }
        toast.textContent = message;
        toast.className = `toast ${type === 'success' ? 'success' : type === 'info' ? 'info' : 'error'}`;
        toast.style.display = 'block';
        const ms = type === 'success' ? 2800 : type === 'info' ? 4500 : 3500;
        setTimeout(() => {
            toast.style.display = 'none';
        }, ms);
    }

    function escapeHtml(value) {
        const d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function formatMoney(value) {
        const n = Number(value || 0);
        return `PHP ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function normalizeStatus(value) {
        const s = String(value || '').trim().toLowerCase();
        if (s === 'accept') {
            return 'Verified';
        }
        if (s === 'reject') {
            return 'Rejected';
        }
        return 'Pending';
    }

    function setActionButtonsState(summary) {
        if (!approveBtn || !rejectBtn || !undoBtn) {
            return;
        }
        const s = summary && typeof summary === 'object' ? summary : {};
        const raw = isInventoryVerifier ? s.inventory_status : s.president_status;
        const st = String(raw || '').trim().toLowerCase();
        const decided = st === 'accept' || st === 'reject';
        approveBtn.textContent = st === 'accept' ? 'Accepted' : 'Accept';
        rejectBtn.textContent = st === 'reject' ? 'Rejected' : 'Reject';
        clearTimeout(undoHideTimer);
        const decidedAt = isInventoryVerifier ? s.inv_decided_at : s.pres_decided_at;
        const remaining = decided ? undoWindowRemainingMs(decidedAt) : 0;
        const withinWindow = remaining > 0;
        approveBtn.style.display = withinWindow ? 'none' : '';
        rejectBtn.style.display = withinWindow ? 'none' : '';
        undoBtn.style.display = withinWindow ? 'inline-flex' : 'none';
        undoBtn.classList.toggle('undo-btn--decided', withinWindow);
        if (withinWindow) {
            undoHideTimer = setTimeout(() => {
                undoBtn.style.display = 'none';
                undoBtn.classList.remove('undo-btn--decided');
                approveBtn.style.display = '';
                rejectBtn.style.display = '';
            }, remaining);
        }

        if (isPresidentVerifier && !withinWindow) {
            const invSt = String(s.inventory_status || '').trim().toLowerCase();
            const invApproved = invSt === 'accept';
            approveBtn.disabled = !invApproved;
            rejectBtn.disabled = !invApproved;
            approveBtn.title = invApproved ? '' : 'Inventory Manager must approve first.';
            rejectBtn.title = invApproved ? '' : 'Inventory Manager must approve first.';
        }
    }

    function setVerifierCircle(roleEl, rawStatus) {
        const circle = roleEl.querySelector('.circle-icon');
        if (!circle) {
            return;
        }
        const st = String(rawStatus || '').trim().toLowerCase();
        let step = 'inactive';
        if (st === 'accept') {
            step = 'active';
        } else if (st === 'reject') {
            step = 'rejected';
        }
        circle.classList.remove('active', 'inactive', 'rejected');
        circle.classList.add(step);
        const icon = circle.querySelector('i');
        if (icon) {
            icon.className = step === 'rejected' ? 'fas fa-xmark' : 'fas fa-check';
        }
    }

    function renderApprovalSummary(summary) {
        const s = summary && typeof summary === 'object' ? summary : {};
        if (invVerifiedStatusEl) {
            invVerifiedStatusEl.textContent = normalizeStatus(s.inventory_status);
        }
        if (presApprovedStatusEl) {
            presApprovedStatusEl.textContent = normalizeStatus(s.president_status);
        }
        const prCard = document.querySelector('.purchase-approval-section .approval-card');
        if (prCard) {
            const roles = prCard.querySelectorAll('.approval-role');
            if (roles[0]) {
                setVerifierCircle(roles[0], s.inventory_status);
            }
            if (roles[1]) {
                setVerifierCircle(roles[1], s.president_status);
            }
        }
        setActionButtonsState(s);
    }

    async function postPrVerification(status) {
        const verifier = isInventoryVerifier ? 'inventory' : 'president';
        const noteEl = isInventoryVerifier ? rejectReasonEl : presidentRejectReasonEl;
        const body = new URLSearchParams();
        body.set('action', 'set_pr_verification');
        body.set('request_id', String(requestId));
        body.set('verifier', verifier);
        body.set('pr_status', status);
        if (status === 'reject') {
            const note = noteEl ? noteEl.value.trim() : '';
            if (!note) {
                showToast('Please add a rejection note.', 'info');
                return false;
            }
            body.set('pr_note', note);
        }
        const res = await fetch(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'include',
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Could not save verification.', 'info');
            return false;
        }
        showToast(data.message || 'Saved.', 'success');
        return true;
    }

    async function postDecision(status) {
        if (isInventoryVerifier || isPresidentVerifier) {
            return postPrVerification(status);
        }
        return false;
    }

    function bindVerifierActions() {
        if (!approveBtn || !rejectBtn || !undoBtn) {
            return;
        }
        if (approveBtn.dataset.bound === '1') {
            return;
        }
        approveBtn.dataset.bound = '1';
        rejectBtn.dataset.bound = '1';
        undoBtn.dataset.bound = '1';

        const runAction = async (status, confirmText) => {
            const ok = await showConfirmModal(confirmText);
            if (!ok) {
                return;
            }
            approveBtn.disabled = true;
            rejectBtn.disabled = true;
            undoBtn.disabled = true;
            try {
                const saved = await postDecision(status);
                if (saved) {
                    await load();
                }
            } catch {
                showToast('Network error while saving decision.');
            } finally {
                approveBtn.disabled = false;
                rejectBtn.disabled = false;
                undoBtn.disabled = false;
            }
        };

        approveBtn.addEventListener('click', () => {
            resetRejectPanel();
            runAction('accept', 'Accept this purchase requisition decision?');
        });
        rejectBtn.addEventListener('click', async () => {
            const noteEl = getRejectNoteEl();
            if (!noteEl) {
                return;
            }
            if (!prRejectReasonArmed) {
                setRejectPanelArmed(true);
                noteEl.focus();
                noteEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                showToast('Enter the reason for rejection below, then click Reject again to continue.', 'info');
                return;
            }
            const note = noteEl.value.trim();
            if (!note) {
                noteEl.focus();
                showToast('Please enter a rejection reason.', 'info');
                return;
            }
            const ok = await showConfirmModal('Reject this purchase requisition?');
            if (!ok) {
                return;
            }
            approveBtn.disabled = true;
            rejectBtn.disabled = true;
            undoBtn.disabled = true;
            try {
                const saved = await postPrVerification('reject');
                if (saved) {
                    resetRejectPanel();
                    if (noteEl) {
                        noteEl.value = '';
                    }
                    await load();
                }
            } catch {
                showToast('Network error while saving decision.');
            } finally {
                approveBtn.disabled = false;
                rejectBtn.disabled = false;
                undoBtn.disabled = false;
            }
        });
        undoBtn.addEventListener('click', () => {
            resetRejectPanel();
            runAction('pending', 'Undo your decision and reset to pending?');
        });
    }

    function bindBackButton() {
        if (!backBtn) {
            return;
        }
        backBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const fallback = backBtn.getAttribute('data-fallback-href') || 'dean_requisition_management.php';
            const hasReferrer = typeof document.referrer === 'string' && document.referrer.trim() !== '';
            if (hasReferrer && window.history.length > 1) {
                window.history.back();
                return;
            }
            window.location.href = fallback;
        });
    }

    function showConfirmModal(message) {
        if (!confirmModal || !confirmMessage || !confirmCancelBtn || !confirmOkBtn) {
            return Promise.resolve(false);
        }
        return new Promise((resolve) => {
            confirmMessage.textContent = message;
            confirmModal.style.display = 'flex';

            const handleConfirm = () => {
                cleanup();
                resolve(true);
            };

            const handleCancel = () => {
                cleanup();
                resolve(false);
            };

            const handleBackdrop = (event) => {
                if (event.target.classList.contains('confirm-modal-backdrop')) {
                    handleCancel();
                }
            };

            const handleEsc = (event) => {
                if (event.key === 'Escape') {
                    handleCancel();
                }
            };

            function cleanup() {
                confirmModal.style.display = 'none';
                confirmOkBtn.removeEventListener('click', handleConfirm);
                confirmCancelBtn.removeEventListener('click', handleCancel);
                confirmModal.removeEventListener('click', handleBackdrop);
                document.removeEventListener('keydown', handleEsc);
            }

            confirmOkBtn.addEventListener('click', handleConfirm);
            confirmCancelBtn.addEventListener('click', handleCancel);
            confirmModal.addEventListener('click', handleBackdrop);
            document.addEventListener('keydown', handleEsc);
        });
    }

    function composeDescription(desc) {
        const base = String((desc && desc.name) || '').trim();
        const brand = String((desc && desc.brand) || '').trim();
        const model = String((desc && desc.model) || '').trim();
        const spec = String((desc && desc.specification) || '').trim();
        const meta = [brand, model].filter(Boolean).join(' · ');
        const main = base || '—';
        if (!meta && !spec) {
            return `<div class="pr-desc-main">${escapeHtml(main)}</div>`;
        }
        return `<div class="pr-desc-main">${escapeHtml(main)}</div>${
            meta ? `<div class="pr-desc-meta">${escapeHtml(meta)}</div>` : ''
        }${spec ? `<div class="pr-desc-spec">${escapeHtml(spec)}</div>` : ''}`;
    }

    function renderItems(items) {
        if (!tbody) {
            return;
        }
        if (!Array.isArray(items) || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No suggested supplier lines found yet.</td></tr>';
            return;
        }
        tbody.innerHTML = items.map((row) => {
            const qty = Number(row.qty || 0);
            return `<tr>
                <td>${composeDescription(row.description)}</td>
                <td class="pr-num">${escapeHtml(String(qty))}</td>
                <td>${escapeHtml(row.supplier_name || '—')}</td>
                <td class="pr-num">${escapeHtml(formatMoney(row.unit_price))}</td>
                <td class="pr-num">${escapeHtml(formatMoney(row.amount))}</td>
            </tr>`;
        }).join('');
    }

    async function load() {
        if (requestId <= 0) {
            showToast('Invalid request id.');
            return;
        }
        try {
            const res = await fetch(
                `${api}?action=get&request_id=${encodeURIComponent(String(requestId))}`,
                { credentials: 'include' }
            );
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not load purchase requisition.');
                return;
            }
            if (requestNoEl) {
                requestNoEl.value = `REQ-${String(data.request_id || requestId).padStart(6, '0')}`;
            }
            if (requesterEl && data.requester) {
                requesterEl.value = String(data.requester);
            }
            if (locationEl) {
                locationEl.value = data.location_label || '—';
            }
            if (requestedAtEl) {
                requestedAtEl.value = data.requested_at || '—';
            }
            if (purposeEl) {
                purposeEl.value = data.purpose || '—';
            }
            renderItems(data.items || []);
            if (grandTotalEl) {
                grandTotalEl.textContent = formatMoney(data.grand_total || 0);
            }
            renderApprovalSummary(data.approval_summary || {});
            const requesterLineEl = document.getElementById('prRequesterLine');
            if (requesterLineEl) {
                requesterLineEl.textContent = data.requester || '—';
            }
            resetRejectPanel();
            const noteEl = getRejectNoteEl();
            if (noteEl) {
                noteEl.value = '';
            }
        } catch {
            showToast('Network error while loading purchase requisition.');
        }
    }

    bindBackButton();
    bindVerifierActions();
    load();
})();

