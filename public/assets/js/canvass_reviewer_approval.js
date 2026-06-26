/**
 * Comptroller / president verify actions on canvass sheet (dean_canvass_form.php).
 */
(function () {
    const cfg = window.IMRMS_CANVASS_REVIEWER;
    if (!cfg || !cfg.requestId || !cfg.role) {
        return;
    }

    const requestId = cfg.requestId;
    const apiBase = cfg.role === 'president' ? cfg.presidentApi : cfg.comptrollerApi;

    const UNDO_WINDOW_MS = 24 * 60 * 60 * 1000;
    let undoHideTimer = null;

    function undoWindowRemainingMs(timestampStr) {
        if (!timestampStr) return 0;
        const decided = new Date(timestampStr.replace(' ', 'T'));
        if (isNaN(decided.getTime())) return 0;
        return Math.max(0, UNDO_WINDOW_MS - (Date.now() - decided.getTime()));
    }

    if (!apiBase) {
        return;
    }

    function showToast(message, type) {
        const el = document.getElementById('cvFormToast');
        if (!el) {
            return;
        }
        el.textContent = message;
        el.className = `toast ${type || 'success'}`;
        el.style.display = 'block';
        clearTimeout(showToast._t);
        showToast._t = setTimeout(() => {
            el.style.display = 'none';
        }, 3200);
    }

    function showConfirmModal(message) {
        return new Promise((resolve) => {
            const modal = document.getElementById('cvConfirmModal');
            const msgEl = document.getElementById('cvConfirmMessage');
            const ok = document.getElementById('cvConfirmOkBtn');
            const cancel = document.getElementById('cvConfirmCancelBtn');
            if (!modal || !msgEl || !ok || !cancel) {
                resolve(false);
                return;
            }
            msgEl.textContent = message;
            modal.style.display = 'flex';

            const cleanup = (v) => {
                modal.style.display = 'none';
                ok.removeEventListener('click', onOk);
                cancel.removeEventListener('click', onCancel);
                modal.removeEventListener('click', onBackdrop);
                document.removeEventListener('keydown', onEsc);
                resolve(v);
            };
            const onOk = () => cleanup(true);
            const onCancel = () => cleanup(false);
            const onBackdrop = (e) => {
                if (e.target.classList.contains('confirm-modal-backdrop')) {
                    cleanup(false);
                }
            };
            const onEsc = (e) => {
                if (e.key === 'Escape') {
                    cleanup(false);
                }
            };

            ok.addEventListener('click', onOk);
            cancel.addEventListener('click', onCancel);
            modal.addEventListener('click', onBackdrop);
            document.addEventListener('keydown', onEsc);
        });
    }

    async function fetchApproval() {
        const url = `${apiBase}?action=get_approval_status&request_id=${encodeURIComponent(String(requestId))}`;
        const res = await fetch(url, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            return null;
        }
        return data.approval || null;
    }

    function setButtonState(approval) {
        const approveBtn = document.getElementById('comptrollerApproveBtn');
        const rejectBtn = document.getElementById('comptrollerRejectBtn');
        const undoBtn = document.getElementById('comptrollerUndoBtn');
        if (!approveBtn || !rejectBtn) {
            return;
        }
        const key = cfg.role === 'president' ? 'pres_status' : 'comp_status';
        const st = String((approval && approval[key]) || 'pending').trim();
        approveBtn.textContent = st === 'accept' ? 'Approved' : 'Approve';
        rejectBtn.textContent = st === 'reject' ? 'Rejected' : 'Reject';
        clearTimeout(undoHideTimer);
        const decidedAt = cfg.role === 'president'
            ? (approval && approval.approved_at)
            : (approval && approval.checked_at);
        const remaining = (st === 'accept' || st === 'reject')
            ? undoWindowRemainingMs(decidedAt)
            : 0;
        const withinWindow = remaining > 0;
        approveBtn.disabled = false;
        rejectBtn.disabled = false;
        approveBtn.style.display = withinWindow ? 'none' : '';
        rejectBtn.style.display = withinWindow ? 'none' : '';
        if (undoBtn) {
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
        }
    }

    async function refreshStrip() {
        const sync = window.IMRMS_DEAN_CANVASS_SYNC;
        if (sync && typeof sync.refreshApprovalStrip === 'function') {
            await sync.refreshApprovalStrip();
        }
    }

    function wireButtons() {
        const approveBtn = document.getElementById('comptrollerApproveBtn');
        const rejectBtn = document.getElementById('comptrollerRejectBtn');
        const undoBtn = document.getElementById('comptrollerUndoBtn');

        const postApproval = async (statusVal) => {
            const body = new URLSearchParams();
            if (cfg.role === 'president') {
                body.set('action', 'set_pres_approval');
                body.set('pres_status', statusVal);
            } else {
                body.set('action', 'set_comptroller_approval');
                body.set('comp_status', statusVal);
            }
            body.set('request_id', String(requestId));
            try {
                const res = await fetch(apiBase, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                    credentials: 'include',
                });
                const data = await res.json();
                if (!data.success) {
                    showToast(data.message || 'Could not save approval.', 'error');
                    return;
                }
                showToast(data.message || 'Saved.');
                const next = await fetchApproval();
                setButtonState(next);
                if (next) {
                    const sync = window.IMRMS_DEAN_CANVASS_SYNC;
                    if (sync && typeof sync.applyApproval === 'function') {
                        sync.applyApproval(next);
                    }
                }
                const prBanner = document.getElementById('cvPrFlowContextBanner');
                if (prBanner && next && cfg.role !== 'president') {
                    const allAccepted =
                        String(next.gsd_status || '').trim() === 'accept' &&
                        String(next.comp_status || '').trim() === 'accept' &&
                        String(next.pres_status || '').trim() === 'accept';
                    prBanner.hidden = !allAccepted;
                }
            } catch {
                showToast('Network error.', 'error');
            }
        };

        if (approveBtn && !approveBtn.dataset.imrmsCvReviewerBound) {
            approveBtn.dataset.imrmsCvReviewerBound = '1';
            approveBtn.addEventListener('click', async () => {
                const msg =
                    cfg.role === 'president'
                        ? 'Approve this request as President? The line status will be set to Ongoing.'
                        : 'Do you want to continue to approve this request? Review accepted quantities before confirming.';
                const ok = await showConfirmModal(msg);
                if (!ok) {
                    return;
                }
                if (cfg.role === 'comptroller' && window.CWIRMSComptrollerPricing) {
                    if (!window.CWIRMSComptrollerPricing.submitApprovalForm()) {
                        showToast('Review accepted quantities and enter reasons for deferred units.', 'error');
                    }
                    return;
                }
                await postApproval('accept');
            });
        }
        if (rejectBtn && !rejectBtn.dataset.imrmsCvReviewerBound) {
            rejectBtn.dataset.imrmsCvReviewerBound = '1';
            rejectBtn.addEventListener('click', async () => {
                const msg =
                    cfg.role === 'president'
                        ? 'Reject this request as President? The line status will be set to Ongoing.'
                        : 'Do you want to reject this request? The line status will be set to Ongoing.';
                const ok = await showConfirmModal(msg);
                if (!ok) {
                    return;
                }
                await postApproval('reject');
            });
        }
        if (undoBtn && !undoBtn.dataset.imrmsCvReviewerBound) {
            undoBtn.dataset.imrmsCvReviewerBound = '1';
            undoBtn.addEventListener('click', async () => {
                const msg =
                    cfg.role === 'president'
                        ? 'Undo your presidential decision? Approval will reset to pending and the request status will return to Pending.'
                        : 'Undo your comptroller decision? Approval will reset to pending and the request status will return to Pending.';
                const ok = await showConfirmModal(msg);
                if (!ok) {
                    return;
                }
                await postApproval('pending');
            });
        }

        // Sync button visibility immediately on page load (handles 24h window correctly)
        void syncButtonsFromServer();
    }

    async function syncButtonsFromServer() {
        const approval = await fetchApproval();
        setButtonState(approval);
    }

    window.__imrmsCvReviewerAfterLoad = () => {
        void syncButtonsFromServer();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => wireButtons());
    } else {
        wireButtons();
    }
})();
