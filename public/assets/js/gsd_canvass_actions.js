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

    const gsdAssigneesLiveRef = { list: [] };
    let gsdCanvasAssigneesCache = null;

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
            input: document.getElementById('gsdCanvasAssigneeInput'),
            hidden: document.getElementById('gsdCanvasAssigneeUserId'),
            list: document.getElementById('gsdCanvasAssigneeSuggestions'),
        };
    }

    async function postSaveSuggestedSupplierItem(canvassDetailId, supplierId, selectionSource) {
        if (!requestId || !canvassDetailId || !supplierId) {
            return;
        }
        const body = new URLSearchParams();
        body.set('action', 'save_suggested_supplier_item');
        body.set('request_id', String(requestId));
        body.set('canvass_detail_id', String(canvassDetailId));
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
            }
        } catch {
            showToast('Network error saving suggested supplier for item.', 'error');
        }
    }

    async function postClearSuggestedSupplierItem(canvassDetailId) {
        if (!requestId || !canvassDetailId) {
            return;
        }
        const body = new URLSearchParams();
        body.set('action', 'clear_suggested_supplier_item');
        body.set('request_id', String(requestId));
        body.set('canvass_detail_id', String(canvassDetailId));
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
            const canvassDetailId = parseInt(radio.dataset.canvassDetailId || '0', 10);
            if (sid > 0) {
                postSaveSuggestedSupplierItem(canvassDetailId, sid, radio.dataset.selectionSource || 'canvassed');
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
    }

    async function postSaveGsdCanvasAssignee(uid) {
        if (!requestId || !uid) {
            return;
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
            }
        } catch {
            showToast('Network error saving assignee.', 'error');
        }
    }

    function syncGsdCanvasAssigneeFromApproval(approval, assignees) {
        const els = getGsdCanvasAssigneeEls();
        if (!els.input) {
            return;
        }
        const cb = approval && approval.canvassed_by ? String(approval.canvassed_by) : '';
        if (!cb) {
            return;
        }
        if (els.input.value.trim() === '' || els.input.value.trim() === cb) {
            els.input.value = cb;
        }
        const hit = assignees.find((a) => (a.label || '').toLowerCase() === cb.toLowerCase());
        if (hit && els.hidden) {
            els.hidden.value = String(hit.user_id);
        }
    }

    function updateGsdCanvasAssigneeFieldState(approval, assignees) {
        const els = getGsdCanvasAssigneeEls();
        if (!els.input) {
            return;
        }
        const canvasSt = String((approval && approval.canvas_status) || 'pending').trim().toLowerCase();
        const canvasDone = canvasSt === 'accept' || canvasSt === 'reject';

        if (canvasDone) {
            els.input.disabled = true;
            if (els.hidden) {
                els.hidden.value = '';
            }
            const cb = (approval && approval.canvassed_by) || '';
            if (cb) {
                els.input.value = cb;
            }
            if (els.list) {
                els.list.hidden = true;
                els.list.innerHTML = '';
            }
            return;
        }

        els.input.disabled = false;
        if (!(approval && approval.canvassed_by)) {
            els.input.value = '';
            if (els.hidden) {
                els.hidden.value = '';
            }
        } else {
            syncGsdCanvasAssigneeFromApproval(approval, assignees);
        }
    }

    function bindGsdCanvasAssigneePickerEventsOnce(assigneesRef) {
        const els = getGsdCanvasAssigneeEls();
        if (!els.input || els.input.dataset.imrmsGsdAssigneeBound === '1') {
            return;
        }
        els.input.dataset.imrmsGsdAssigneeBound = '1';

        const getAssignees = () => (Array.isArray(assigneesRef.list) ? assigneesRef.list : []);

        const onFilter = () => {
            if (els.input.disabled) {
                return;
            }
            renderGsdCanvasAssigneeSuggestions(els.input.value, getAssignees());
        };

        els.input.addEventListener('input', onFilter);
        els.input.addEventListener('focus', onFilter);

        els.list?.addEventListener('mousedown', (e) => {
            const li = e.target.closest('li[data-user-id]');
            if (!li || els.input.disabled) {
                return;
            }
            e.preventDefault();
            const uid = Number(li.dataset.userId);
            const row = getAssignees().find((a) => Number(a.user_id) === uid);
            const label = row ? row.label : '';
            selectGsdCanvasAssignee(uid, label);
            if (uid > 0) {
                postSaveGsdCanvasAssignee(uid);
            }
        });

        document.addEventListener('click', (e) => {
            if (!els.list || !els.input) {
                return;
            }
            if (els.input.contains(e.target) || els.list.contains(e.target)) {
                return;
            }
            els.list.hidden = true;
        });
    }

    function initGsdCanvasAssigneePicker(approval, assignees) {
        gsdAssigneesLiveRef.list = assignees;
        updateGsdCanvasAssigneeFieldState(approval, assignees);
        bindGsdCanvasAssigneePickerEventsOnce(gsdAssigneesLiveRef);
    }

    function resolveGsdCanvasAssigneeUserId(assignees, approval) {
        const els = getGsdCanvasAssigneeEls();
        let uid = els.hidden && els.hidden.value ? parseInt(els.hidden.value, 10) : 0;
        if (uid > 0) {
            return uid;
        }
        const t = (els.input && els.input.value.trim().toLowerCase()) || '';
        if (!t) {
            return 0;
        }
        const hit = assignees.find(
            (a) =>
                (a.label || '').toLowerCase() === t ||
                (a.email || '').toLowerCase() === t ||
                (a.email || '').toLowerCase().startsWith(`${t}@`)
        );
        if (hit) {
            return Number(hit.user_id);
        }
        const cb = approval && approval.canvassed_by ? String(approval.canvassed_by).toLowerCase() : '';
        if (cb && cb === t) {
            const h2 = assignees.find((a) => (a.label || '').toLowerCase() === cb);
            return h2 ? Number(h2.user_id) : 0;
        }
        return 0;
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
        const canvasSt = String((approval && approval.canvas_status) || 'pending').trim().toLowerCase();
        const canvasDone = canvasSt === 'accept' || canvasSt === 'reject';
        const gsdDone = st === 'accept' || st === 'reject';
        approveBtn.disabled = false;
        rejectBtn.disabled = false;
        approveBtn.textContent = st === 'accept'
            ? 'Verified'
            : 'Verify';
        rejectBtn.textContent = st === 'reject' ? 'Rejected' : 'Reject';
        approveBtn.title = '';
        if (hintEl) {
            if (!canvasDone && !gsdDone) {
                hintEl.textContent = 'Next step: assign canvasser and wait for canvassing to be completed before you verify.';
                hintEl.className = 'gsd-verify-hint gsd-verify-hint-pending';
            } else if (st === 'accept') {
                hintEl.textContent = 'Already verified at GSD. Use Undo decision if you need to reopen.';
                hintEl.className = 'gsd-verify-hint gsd-verify-hint-done';
            } else {
                hintEl.textContent = 'Canvass is complete. Review suggested suppliers per item, then verify.';
                hintEl.className = 'gsd-verify-hint gsd-verify-hint-ready';
            }
        }
        if (undoBtn) {
            undoBtn.style.display = st === 'accept' || st === 'reject' ? 'inline-flex' : 'none';
        }
    }

    async function init() {
        const approval = await fetchGsdApproval();
        setGsdApprovalButtonsState(approval);

        const assignees = await fetchGsdCanvasAssigneesList();
        initGsdCanvasAssigneePicker(approval, assignees);

        const approveBtn = document.getElementById('comptrollerApproveBtn');
        const rejectBtn = document.getElementById('comptrollerRejectBtn');
        const undoBtn = document.getElementById('comptrollerUndoBtn');
        const canvassedCards = document.getElementById('cvCanvassedCards');
        bindSuggestedSupplierMatrix(canvassedCards);
        bindSuggestedSupplierMatrix(document.getElementById('cvPreferredCards'));

        const postApproval = async (gsdStatus) => {
            if (gsdStatus === 'accept') {
                const fresh = await fetchGsdApproval();
                const canvasSt = String((fresh && fresh.canvas_status) || 'pending').trim().toLowerCase();
                const canvasDone = canvasSt === 'accept' || canvasSt === 'reject';
                if (!canvasDone) {
                    showToast('Canvasser must complete canvassing before GSD can verify.', 'error');
                    return;
                }
            }

            const body = new URLSearchParams();
            body.set('action', 'set_gsd_approval');
            body.set('request_id', String(requestId));
            body.set('gsd_status', gsdStatus);
            const hid = document.getElementById('gsdCanvasAssigneeUserId');
            const uid = hid && hid.value ? parseInt(hid.value, 10) : 0;
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
                showToast(data.message || 'Saved.');
                window.location.reload();
            } catch {
                showToast('Network error.', 'error');
            }
        };

        if (approveBtn && !approveBtn.dataset.imrmsGsdCvBound) {
            approveBtn.dataset.imrmsGsdCvBound = '1';
            approveBtn.addEventListener('click', async () => {
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
