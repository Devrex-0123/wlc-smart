/**
 * GSD verify / reject / undo on canvass sheet (dean_canvass_form.php?from=gsd).
 */
(function () {
    const cfg = window.IMRMS_GSD_CANVASS;
    if (!cfg || !cfg.requestId || !cfg.gsdApi) {
        return;
    }

    const requestId = cfg.requestId;
    const gsdApi = cfg.gsdApi;

    const UNDO_WINDOW_MS = 24 * 60 * 60 * 1000;
    let undoHideTimer = null;

    function undoWindowRemainingMs(timestampStr) {
        if (!timestampStr) return 0;
        const decided = new Date(timestampStr.replace(' ', 'T'));
        if (isNaN(decided.getTime())) return 0;
        return Math.max(0, UNDO_WINDOW_MS - (Date.now() - decided.getTime()));
    }

    const gsdAssigneesLiveRef = { list: [] };
    let gsdCanvasAssigneesCache = null;
    let gsdApprovalState = null;
    let assigneePickForced = false;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
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

    async function fetchGsdCanvasAssigneesList() {
        if (gsdCanvasAssigneesCache) {
            return gsdCanvasAssigneesCache;
        }
        const url = `${gsdApi}?action=list_canvas_assignees`;
        const res = await fetch(url, { credentials: 'include' });
        const data = await res.json();
        if (!data.success || !Array.isArray(data.assignees)) {
            return [];
        }
        gsdCanvasAssigneesCache = data.assignees;
        return gsdCanvasAssigneesCache;
    }

    function getGsdCanvasAssigneeEls() {
        return {
            root: document.getElementById('gsdAssigneeInApprovalWrap'),
            input: document.getElementById('gsdCanvasAssigneeInput'),
            hidden: document.getElementById('gsdCanvasAssigneeUserId'),
            list: document.getElementById('gsdCanvasAssigneeSuggestions'),
            pickWrap: document.getElementById('gsdAssigneePickWrap'),
            assignedWrap: document.getElementById('gsdAssigneeAssignedWrap'),
            assignedName: document.getElementById('gsdAssigneeAssignedName'),
            assignBtn: document.getElementById('gsdCanvasAssignBtn'),
            changeBtn: document.getElementById('gsdCanvasAssigneeChangeBtn'),
            pickHint: document.getElementById('gsdAssigneePickHint'),
        };
    }

    function isAssigneePickMode() {
        const { root } = getGsdCanvasAssigneeEls();
        return Boolean(root && root.classList.contains('gsd-assignee-mode-pick'));
    }

    function isCanvasDone(approval) {
        const canvasSt = String((approval && approval.canvas_status) || 'pending').trim().toLowerCase();
        return canvasSt === 'accept' || canvasSt === 'reject';
    }

    function hasSavedAssignee(approval) {
        const label = approval && approval.canvassed_by ? String(approval.canvassed_by).trim() : '';
        const uid =
            approval && approval.canvas_assignee_user_id
                ? parseInt(String(approval.canvas_assignee_user_id), 10)
                : 0;
        return label !== '' && uid > 0;
    }

    function resolveAssigneeUserId(approval, assignees) {
        const aid = approval && approval.canvas_assignee_user_id
            ? parseInt(String(approval.canvas_assignee_user_id), 10)
            : 0;
        if (aid > 0) {
            return aid;
        }
        const cb = approval && approval.canvassed_by ? String(approval.canvassed_by).trim().toLowerCase() : '';
        if (!cb) {
            return 0;
        }
        const hit = assignees.find((a) => (a.label || '').trim().toLowerCase() === cb);
        return hit ? Number(hit.user_id) : 0;
    }

    function updateAssignButtonState() {
        const { assignBtn, hidden } = getGsdCanvasAssigneeEls();
        if (!assignBtn) {
            return;
        }
        const uid = hidden && hidden.value ? parseInt(hidden.value, 10) : 0;
        assignBtn.disabled = !(uid > 0);
    }

    function showPickMode(clearPending) {
        const els = getGsdCanvasAssigneeEls();
        if (els.root) {
            els.root.classList.add('gsd-assignee-mode-pick');
            els.root.classList.remove('gsd-assignee-mode-assigned');
        }
        if (clearPending) {
            if (els.hidden) {
                els.hidden.value = '';
            }
            if (els.input) {
                els.input.value = '';
            }
        }
        if (els.input) {
            els.input.disabled = false;
            els.input.removeAttribute('aria-disabled');
        }
        if (els.assignBtn) {
            els.assignBtn.hidden = false;
        }
        if (els.pickHint) {
            els.pickHint.hidden = false;
        }
        if (els.changeBtn) {
            els.changeBtn.hidden = true;
        }
        if (els.list) {
            els.list.hidden = true;
            els.list.innerHTML = '';
        }
        updateAssignButtonState();
    }

    function showAssignedMode(label, allowChange) {
        const els = getGsdCanvasAssigneeEls();
        if (els.root) {
            els.root.classList.remove('gsd-assignee-mode-pick');
            els.root.classList.add('gsd-assignee-mode-assigned');
        }
        if (els.assignedName) {
            els.assignedName.textContent = label || '';
        }
        if (els.changeBtn) {
            els.changeBtn.hidden = !allowChange;
        }
        if (els.list) {
            els.list.hidden = true;
            els.list.innerHTML = '';
        }
    }

    function syncCanvasserApprovalDetail(label) {
        const detail = document.getElementById('cvApprCanvasserDetail');
        if (!detail || !label) {
            return;
        }
        detail.textContent = label;
        detail.setAttribute('title', label);
    }

    async function postSaveSuggestedSupplierItem(lineOrDetailId, supplierId, selectionSource) {
        if (!requestId || !lineOrDetailId || !supplierId) {
            return;
        }
        const body = new URLSearchParams();
        body.set('action', 'save_suggested_supplier_item');
        body.set('request_id', String(requestId));
        body.set('requisition_line_id', String(lineOrDetailId));
        body.set('canvass_detail_id', String(lineOrDetailId));
        body.set('suggested_supplier_id', String(supplierId));
        body.set('selection_source', selectionSource === 'preferred' ? 'preferred' : 'canvassed');
        try {
            const res = await fetch(gsdApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'include',
            });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not save suggested supplier for item.', 'error');
                return;
            }
            if (typeof window.__cwirmsRefreshGsdReviewView === 'function') {
                window.__cwirmsRefreshGsdReviewView();
            }
        } catch {
            showToast('Network error saving suggested supplier for item.', 'error');
        }
    }

    async function postClearSuggestedSupplierItem(lineOrDetailId) {
        if (!requestId || !lineOrDetailId) {
            return;
        }
        const body = new URLSearchParams();
        body.set('action', 'clear_suggested_supplier_item');
        body.set('request_id', String(requestId));
        body.set('requisition_line_id', String(lineOrDetailId));
        body.set('canvass_detail_id', String(lineOrDetailId));
        try {
            const res = await fetch(gsdApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'include',
            });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not clear suggested supplier for item.', 'error');
            }
        } catch {
            showToast('Network error clearing suggested supplier for item.', 'error');
        }
    }

    window.__imrmsClearSuggestedSupplierItem = postClearSuggestedSupplierItem;

    function bindSuggestedSupplierMatrix(root) {
        if (!root || root.dataset.imrmsGsdSuggestedBound) {
            return;
        }
        root.dataset.imrmsGsdSuggestedBound = '1';
        root.addEventListener('change', (e) => {
            const radio = e.target.closest('.cv-suggested-item-radio');
            if (!radio) {
                return;
            }
            const sid = parseInt(radio.value || '0', 10);
            const lineId = parseInt(
                radio.dataset.requisitionLineId || radio.dataset.canvassDetailId || '0',
                10
            );
            if (sid > 0) {
                postSaveSuggestedSupplierItem(lineId, sid, radio.dataset.selectionSource || 'canvassed');
            }
        });
    }

    function renderGsdCanvasAssigneeSuggestions(filterText, assignees) {
        const { list, input } = getGsdCanvasAssigneeEls();
        if (!list || !input) {
            return;
        }
        const q = filterText.trim().toLowerCase();
        const match = (a) => {
            if (!q) {
                return true;
            }
            const lab = (a.label || '').toLowerCase();
            const em = (a.email || '').toLowerCase();
            const role = (a.role || '').toLowerCase();
            return lab.includes(q) || em.includes(q) || role.includes(q);
        };
        const rows = assignees.filter(match).slice(0, 8);
        if (!rows.length) {
            list.hidden = true;
            list.innerHTML = '';
            return;
        }
        list.innerHTML = rows
            .map(
                (a) =>
                    `<li role="option" tabindex="-1" data-user-id="${String(Number(a.user_id))}">
                    <span class="assignee-line-primary">${escapeHtml(a.label || '')}</span>
                    <span class="assignee-line-meta">${escapeHtml(a.role || '')} · ${escapeHtml(a.email || '')}</span>
                </li>`
            )
            .join('');
        list.hidden = false;
    }

    function selectGsdCanvasAssignee(userId, label) {
        const { input, hidden, list } = getGsdCanvasAssigneeEls();
        if (hidden) {
            hidden.value = userId ? String(userId) : '';
        }
        if (input && label != null) {
            input.value = label;
        }
        if (list) {
            list.hidden = true;
            list.innerHTML = '';
        }
        updateAssignButtonState();
    }

    async function postSaveGsdCanvasAssignee(uid) {
        if (!requestId || !uid) {
            return null;
        }
        const body = new URLSearchParams();
        body.set('action', 'save_canvas_assignee');
        body.set('request_id', String(requestId));
        body.set('canvas_assignee_user_id', String(uid));
        try {
            const res = await fetch(gsdApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'include',
            });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not save assignee.', 'error');
                return null;
            }
            showToast(data.message || 'Canvasser assigned.');
            return data;
        } catch {
            showToast('Network error saving assignee.', 'error');
            return null;
        }
    }

    function renderAssigneeUi(approval, assignees) {
        const els = getGsdCanvasAssigneeEls();
        if (!els.input) {
            return;
        }

        const canvasDone = isCanvasDone(approval);
        const saved = hasSavedAssignee(approval);
        const label = saved ? String(approval.canvassed_by).trim() : '';

        if (canvasDone) {
            showAssignedMode(label, false);
            if (els.hidden) {
                els.hidden.value = '';
            }
            return;
        }

        if (saved && !assigneePickForced) {
            const uid = resolveAssigneeUserId(approval, assignees);
            if (els.hidden && uid > 0) {
                els.hidden.value = String(uid);
            }
            showAssignedMode(label, true);
            return;
        }

        showPickMode(assigneePickForced);
    }

    function bindGsdCanvasAssigneePickerEventsOnce(assigneesRef) {
        const els = getGsdCanvasAssigneeEls();
        if (!els.input || els.input.dataset.imrmsGsdAssigneeBound === '1') {
            return;
        }
        els.input.dataset.imrmsGsdAssigneeBound = '1';

        const getAssignees = () => (Array.isArray(assigneesRef.list) ? assigneesRef.list : []);

        const onFilter = () => {
            if (!isAssigneePickMode() || els.input.disabled) {
                return;
            }
            renderGsdCanvasAssigneeSuggestions(els.input.value, getAssignees());
        };

        els.input.addEventListener('input', () => {
            if (!isAssigneePickMode()) {
                return;
            }
            if (els.hidden) {
                els.hidden.value = '';
            }
            updateAssignButtonState();
            onFilter();
        });
        els.input.addEventListener('focus', onFilter);
        els.input.addEventListener('click', onFilter);

        els.list?.addEventListener('mousedown', (e) => {
            const li = e.target.closest('li[data-user-id]');
            if (!li || !isAssigneePickMode() || els.input.disabled) {
                return;
            }
            e.preventDefault();
            const uid = Number(li.dataset.userId);
            const row = getAssignees().find((a) => Number(a.user_id) === uid);
            const pickLabel = row ? row.label : '';
            selectGsdCanvasAssignee(uid, pickLabel);
        });

        els.assignBtn?.addEventListener('click', async () => {
            const uid = els.hidden && els.hidden.value ? parseInt(els.hidden.value, 10) : 0;
            if (uid <= 0) {
                return;
            }
            els.assignBtn.disabled = true;
            const data = await postSaveGsdCanvasAssignee(uid);
            els.assignBtn.disabled = false;
            if (!data) {
                updateAssignButtonState();
                return;
            }
            assigneePickForced = false;
            gsdApprovalState = {
                ...(gsdApprovalState || {}),
                canvassed_by: data.canvassed_by || (els.input && els.input.value) || '',
                canvas_assignee_user_id: data.canvas_assignee_user_id || uid,
                canvas_status: (gsdApprovalState && gsdApprovalState.canvas_status) || 'pending',
            };
            renderAssigneeUi(gsdApprovalState, getAssignees());
            syncCanvasserApprovalDetail(String(gsdApprovalState.canvassed_by || '').trim());
        });

        els.changeBtn?.addEventListener('click', () => {
            assigneePickForced = true;
            showPickMode(true);
            els.input?.focus();
        });

        document.addEventListener('pointerdown', (e) => {
            if (!els.list || !els.root) {
                return;
            }
            if (els.root.contains(e.target)) {
                return;
            }
            els.list.hidden = true;
        });
    }

    function initGsdCanvasAssigneePicker(approval, assignees) {
        gsdAssigneesLiveRef.list = assignees;
        renderAssigneeUi(approval, assignees);
        bindGsdCanvasAssigneePickerEventsOnce(gsdAssigneesLiveRef);
    }

    function syncGsdAssigneeUiFromApproval(approval) {
        gsdApprovalState = approval && typeof approval === 'object' ? approval : null;
        if (!assigneePickForced) {
            renderAssigneeUi(gsdApprovalState, gsdAssigneesLiveRef.list || []);
        }
    }

    window.__imrmsGsdAssigneeSyncApproval = syncGsdAssigneeUiFromApproval;

    function resolveGsdCanvasAssigneeUserId(assignees, approval) {
        const els = getGsdCanvasAssigneeEls();
        let uid = els.hidden && els.hidden.value ? parseInt(els.hidden.value, 10) : 0;
        if (uid > 0) {
            return uid;
        }
        return resolveAssigneeUserId(approval, assignees);
    }

    async function fetchGsdApproval() {
        const url = `${gsdApi}?action=get_approval_status&request_id=${encodeURIComponent(String(requestId))}`;
        const res = await fetch(url, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            return null;
        }
        return data.approval || null;
    }

    function setGsdApprovalButtonsState(approval) {
        const approveBtn = document.getElementById('comptrollerApproveBtn');
        const rejectBtn = document.getElementById('comptrollerRejectBtn');
        const undoBtn = document.getElementById('comptrollerUndoBtn');
        const hintEl = document.getElementById('gsdVerifyHint');
        if (!approveBtn || !rejectBtn) {
            return;
        }
        const st = String((approval && approval.gsd_status) || 'pending').trim();
        const gsdDone = st === 'accept' || st === 'reject';
        const awardsReady = typeof window.__cwirmsGsdAllAwardsReady === 'function'
            ? window.__cwirmsGsdAllAwardsReady()
            : false;
        approveBtn.textContent = st === 'accept' ? 'Verified' : 'Verify';
        rejectBtn.textContent = st === 'reject' ? 'Rejected' : 'Reject';
        approveBtn.title = '';
        clearTimeout(undoHideTimer);
        const remaining = gsdDone
            ? undoWindowRemainingMs(approval && approval.verified_at)
            : 0;
        const withinWindow = remaining > 0;
        approveBtn.style.display = withinWindow ? 'none' : '';
        rejectBtn.style.display = withinWindow ? 'none' : '';
        approveBtn.disabled = withinWindow ? false : (gsdDone ? false : !awardsReady);
        rejectBtn.disabled = false;
        if (hintEl) {
            if (st === 'accept') {
                hintEl.textContent = withinWindow
                    ? 'Already verified at GSD. Use Undo decision if you need to reopen.'
                    : 'Already verified at GSD.';
                hintEl.className = 'gsd-verify-hint gsd-verify-hint-done';
            } else if (!gsdDone) {
                hintEl.textContent = 'Select a quote for every line item, then click Verify.';
                hintEl.className = 'gsd-verify-hint gsd-verify-hint-pending';
            }
        }
        if (undoBtn) {
            undoBtn.style.display = withinWindow ? 'inline-flex' : 'none';
            undoBtn.classList.toggle('undo-btn--decided', withinWindow);
            if (withinWindow) {
                undoHideTimer = setTimeout(() => {
                    undoBtn.style.display = 'none';
                    undoBtn.classList.remove('undo-btn--decided');
                    approveBtn.style.display = '';
                    rejectBtn.style.display = '';
                    if (hintEl && st === 'accept') {
                        hintEl.textContent = 'Already verified at GSD.';
                    }
                }, remaining);
            }
        }
    }

    async function init() {
        const approval = await fetchGsdApproval();
        gsdApprovalState = approval;
        assigneePickForced = false;
        setGsdApprovalButtonsState(approval);
        window.__cwirmsUpdateGsdVerifyButtonState?.();

        const assignees = await fetchGsdCanvasAssigneesList();
        initGsdCanvasAssigneePicker(approval, assignees);

        const approveBtn = document.getElementById('comptrollerApproveBtn');
        const rejectBtn = document.getElementById('comptrollerRejectBtn');
        const undoBtn = document.getElementById('comptrollerUndoBtn');
        const canvassedCards = document.getElementById('cvCanvassedCards');
        bindSuggestedSupplierMatrix(canvassedCards);
        bindSuggestedSupplierMatrix(document.getElementById('cvPreferredCards'));

        const postApproval = async (gsdStatus) => {
            const body = new URLSearchParams();
            body.set('action', 'set_gsd_approval');
            body.set('request_id', String(requestId));
            body.set('gsd_status', gsdStatus);
            const uid = resolveGsdCanvasAssigneeUserId(assignees, gsdApprovalState || approval);
            if (uid > 0) {
                body.set('canvas_assignee_user_id', String(uid));
            }

            try {
                const res = await fetch(gsdApi, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                    credentials: 'include',
                });
                const data = await res.json();
                if (!data.success) {
                    showToast(data.message || 'Could not save.', 'error');
                    return;
                }
                const successMsg = gsdStatus === 'accept'
                    ? 'Canvass approved. Forwarding to Comptroller.'
                    : (data.message || 'Saved.');
                showToast(successMsg);
                window.location.reload();
            } catch {
                showToast('Network error.', 'error');
            }
        };

        if (approveBtn && !approveBtn.dataset.imrmsGsdCvBound) {
            approveBtn.dataset.imrmsGsdCvBound = '1';
            approveBtn.addEventListener('click', async () => {
                if (typeof window.__cwirmsPrepareGsdVerify === 'function') {
                    const prepared = await window.__cwirmsPrepareGsdVerify();
                    if (!prepared.ok) {
                        showToast(prepared.message || 'Complete supplier selection before verifying.', 'error');
                        window.__cwirmsUpdateGsdVerifyButtonState?.();
                        return;
                    }
                }
                const ok = await showConfirmModal(
                    'Verify this request at GSD? The line status will be set to Ongoing.'
                );
                if (!ok) {
                    return;
                }
                await postApproval('accept');
            });
        }
        if (rejectBtn && !rejectBtn.dataset.imrmsGsdCvBound) {
            rejectBtn.dataset.imrmsGsdCvBound = '1';
            rejectBtn.addEventListener('click', async () => {
                const ok = await showConfirmModal(
                    'Reject this request at GSD? The line status will be set to Ongoing.'
                );
                if (!ok) {
                    return;
                }
                await postApproval('reject');
            });
        }
        if (undoBtn && !undoBtn.dataset.imrmsGsdCvBound) {
            undoBtn.dataset.imrmsGsdCvBound = '1';
            undoBtn.addEventListener('click', async () => {
                const ok = await showConfirmModal(
                    'Undo your GSD decision? Verification will reset to pending and the request status will return to Pending.'
                );
                if (!ok) {
                    return;
                }
                await postApproval('pending');
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
