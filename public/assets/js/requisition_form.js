const itemNameSuggestions = document.getElementById('itemNameSuggestions');
const requestedItemsBody = document.getElementById('requestedItemsBody');
const rfAddItemBtn = document.getElementById('rfAddItemBtn');
const addSupplierBtn = document.getElementById('addSupplierBtn');
const supplierDropdown = document.getElementById('supplierDropdown');
const supplierDropdownBtn = document.getElementById('supplierDropdownBtn');
const supplierDropdownList = document.getElementById('supplierDropdownList');
const supplierSelectedText = document.getElementById('supplierSelectedText');
const supplierDropdownPreview = document.getElementById('supplierDropdownPreview');
const supplierTable = document.getElementById('supplierTable');
const submitRequisitionBtn = document.getElementById('submitRequisitionBtn');
const rfFormFooterBar = document.getElementById('rfFormFooterBar');

function setSubmitRequisitionBtnLabel(label) {
    if (!submitRequisitionBtn) {
        return;
    }
    submitRequisitionBtn.innerHTML = `<i class="fas fa-paper-plane" aria-hidden="true"></i> ${label}`;
}
const formToast = document.getElementById('formToast');
const officeSelect = document.getElementById('officeSelect');
const facilitySelect = document.getElementById('facilitySelect');
const requestDateInput = document.getElementById('requestDate');
const requestMessageInput = document.getElementById('requestMessage');
const requestPurposeInput = document.getElementById('requestPurpose');
const requesterNameInput = document.getElementById('requesterName');
const facultyRoleInput = document.getElementById('facultyRole');
const canvasSection = document.getElementById('canvasSection');
const confirmModal = document.getElementById('confirmModal');
const confirmMessage = document.getElementById('confirmMessage');
const confirmCancelBtn = document.getElementById('confirmCancelBtn');
const confirmOkBtn = document.getElementById('confirmOkBtn');

const formPageConfig = window.IMRMS_REQ_FORM_CONFIG || {
    viewOnly: false,
    requestId: 0,
    detailApi: 'dean',
    isCanvasserView: false,
    isInventoryManagerView: false,
    inventoryApproveApi: null,
    canvasserApproveApi: null,
    isComptrollerView: false,
    comptrollerApproveApi: null,
    isGsdView: false,
    gsdApproveApi: null,
    isGsdCanvasAssigneeUi: false,
    isPresidentView: false,
    presidentApproveApi: null,
    canvassBannerEligible: false,
};

let gsdCanvasAssigneesCache = null;
const gsdAssigneesLiveRef = { list: [] };

function asObjectArray(raw) {
    if (Array.isArray(raw)) {
        return raw;
    }
    if (raw && typeof raw === 'object') {
        return Object.values(raw);
    }
    return [];
}

function normalizeSupplierPrices(prices) {
    if (!prices || typeof prices !== 'object') {
        return {};
    }
    const out = {};
    Object.keys(prices).forEach((k) => {
        const idx = Number(k);
        if (!Number.isNaN(idx)) {
            out[idx] = prices[k];
        }
    });
    return out;
}

const RF_DEFAULT_ITEM_ROWS = 4;

function createEmptyRequestedItem() {
    return {
        item_id: null,
        name: '',
        brand: '',
        model: '',
        specification: '',
        group_label: '',
        category: '',
        quantity: 1,
        unit_type: 'piece',
    };
}

function createDefaultRequestedItems(count = RF_DEFAULT_ITEM_ROWS) {
    return Array.from({ length: count }, () => createEmptyRequestedItem());
}

const state = {
    availableItems: [],
    availableSuppliers: [],
    facilities: [],
    requestedItems: createDefaultRequestedItems(),
    selectedSuppliers: [],
    selectedSupplierId: null,
    defaultOfficeId: '',
    defaultDate: requestDateInput.value || '',
    editRequestId: null,
    viewOnly: false,
    requisitionStatus: 'pending',
    /** When true, canvasser cannot edit suppliers/prices (canvas accept/reject recorded). */
    canvasserMatrixLocked: false,
    /** When true, dean cannot edit requisition body (G.S.D. / comptroller / president decided). */
    deanEditLocked: false,
    /** Latest request_approval-shaped object from detail payloads (for inventory lock, etc.). */
    approval: null,
    /** Tracks if form has unsaved changes since last successful save */
    hasUnsavedChanges: false,
};

/** Supplier matrix: editable on dean create/edit, or canvasser review while canvas step is open. */
function matrixRowsAreEditable() {
    if (state.deanEditLocked) {
        return false;
    }
    if (!state.viewOnly) {
        return true;
    }
    return Boolean(formPageConfig.isCanvasserView && !state.canvasserMatrixLocked);
}

function syncCanvasserMatrixEditLock(approval) {
    if (!formPageConfig.isCanvasserView) {
        return;
    }
    const st = String((approval && approval.canvas_status) || 'pending').trim().toLowerCase();
    const finished = st === 'accept' || st === 'reject';
    state.canvasserMatrixLocked = finished;
    if (addSupplierBtn) {
        addSupplierBtn.style.display = finished ? 'none' : '';
    }
    if (supplierDropdownBtn) {
        supplierDropdownBtn.disabled = finished;
    }
    const saveQuotesBtn = document.getElementById('canvasserSaveQuotesBtn');
    if (saveQuotesBtn) {
        saveQuotesBtn.disabled = finished;
    }
    const regBtn = document.getElementById('canvasserRegisterSupplierBtn');
    if (regBtn) {
        regBtn.style.display = finished ? 'none' : '';
        regBtn.disabled = finished;
    }
    renderSupplierTable();
}

function syncCanvasSectionVisibility() {
    if (!canvasSection) {
        return;
    }
    // Supplier matrix exists only for assigned canvasser review (not on the main requisition form).
    canvasSection.style.display = '';
}

function syncCanvassContinueBanner() {
    const wrap = document.getElementById('canvassContinueBanner');
    const link = document.getElementById('canvassContinueLink');
    if (!wrap || !link || !formPageConfig.canvassBannerEligible) {
        return;
    }
    const id = state.editRequestId || formPageConfig.requestId || 0;
    const ok =
        id > 0 && state.requisitionStatus === 'accept' && !state.deanEditLocked;
    wrap.hidden = !ok;
    if (ok) {
        link.href = `dean_canvass_form.php?request_id=${encodeURIComponent(String(id))}`;
    }
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}

function getDetailViewApiUrl(requestId) {
    const base =
        formPageConfig.detailApi === 'admin'
            ? '../../app/api/admin_requisition.php'
            : '../../app/api/dean_requisition.php';
    return `${base}?action=get_request_detail_view&request_id=${encodeURIComponent(String(requestId))}`;
}

function getBootstrapApiUrl() {
    return formPageConfig.detailApi === 'admin'
        ? '../../app/api/admin_requisition.php?action=bootstrap'
        : '../../app/api/dean_requisition.php?action=bootstrap';
}

function approvalStepIsAccept(value) {
    return String(value || '').trim().toLowerCase() === 'accept';
}

function approvalStepIsReject(value) {
    return String(value || '').trim().toLowerCase() === 'reject';
}

/** Mirrors PHP requisitionVerifierChainLocked: canvasser or downstream verifier decided. */
function verifierChainLockedFromApproval(appr) {
    if (!appr || typeof appr !== 'object') {
        return false;
    }
    for (const key of ['canvas_status', 'gsd_status', 'comp_status', 'pres_status']) {
        const v = String(appr[key] || '')
            .trim()
            .toLowerCase();
        if (v === 'accept' || v === 'reject') {
            return true;
        }
    }
    return false;
}

/**
 * Approval strip: all four steps use request_approval.*_status when present (accept = green only).
 * Legacy: no approval object falls back to requisition status heuristics.
 */
function applyApprovalFromPayload(data) {
    const status = String(data.status || '').trim();
    const appr = data.approval;
    const hasApprovalObj = appr && typeof appr === 'object';
    state.approval = hasApprovalObj ? { ...appr } : null;
    if (hasApprovalObj) {
        state.requisitionStatus = String(appr.requisition_status || 'pending').trim().toLowerCase() || 'pending';
    }
    const card = document.querySelector('.approval-card');
    if (!card) {
        syncCanvasSectionVisibility();
        syncRequisitionReviewerDisplay(appr || null);
        if (hasApprovalObj) {
            syncCanvasAssigneeNameDisplay(appr);
        }
        return;
    }
    const roles = card.querySelectorAll('.approval-role');
    syncCanvasSectionVisibility();
    syncRequisitionReviewerDisplay(appr || null);

    roles.forEach((role, i) => {
        const circle = role.querySelector('.circle-icon');
        if (!circle) {
            return;
        }
        let step = 'inactive';
        if (hasApprovalObj) {
            let raw;
            if (i === 0) {
                raw = appr.requisition_status;
            } else if (i === 1) {
                raw = appr.gsd_status;
            } else if (i === 2) {
                raw = appr.comp_status;
            } else if (i === 3) {
                raw = appr.pres_status;
            }
            if (approvalStepIsAccept(raw)) {
                step = 'active';
            } else if (approvalStepIsReject(raw)) {
                step = 'rejected';
            }
        } else {
            let doneCount = 1;
            if (status === 'Ongoing') {
                doneCount = 3;
            } else if (status === 'Completed') {
                doneCount = 4;
            }
            step = i < doneCount ? 'active' : 'inactive';
        }
        circle.classList.remove('active', 'inactive', 'rejected');
        circle.classList.add(step);
        const icon = circle.querySelector('i');
        if (icon) {
            icon.className = step === 'rejected' ? 'fas fa-xmark' : 'fas fa-check';
        }
    });
    if (hasApprovalObj) {
        syncCanvasAssigneeNameDisplay(appr);
    }
}

function syncRequisitionReviewerDisplay(appr) {
    const el = document.getElementById('requisitionReviewedByDisplay');
    if (!el) {
        return;
    }
    const reviewer = appr && appr.requisition_reviewed_by ? String(appr.requisition_reviewed_by).trim() : '';
    el.textContent = reviewer || 'INVENTORY MANAGER';
}

function syncCanvasAssigneeNameDisplay(appr) {
    const el = document.getElementById('canvasAssigneeNameDisplay');
    if (!el) {
        return;
    }
    const name = appr && appr.canvassed_by ? String(appr.canvassed_by).trim() : '';
    el.textContent = name || '—';
    if (name) {
        el.setAttribute('title', name);
    } else {
        el.removeAttribute('title');
    }
}

function applyApprovalFromStatus(statusRaw) {
    applyApprovalFromPayload({ status: statusRaw, approval: null });
}

function comptrollerApproveApiBase() {
    return formPageConfig.comptrollerApproveApi || '';
}
function inventoryApproveApiBase() {
    return formPageConfig.inventoryApproveApi || '';
}

function canvasserApproveApiBase() {
    return formPageConfig.canvasserApproveApi || '';
}

function gsdApproveApiBase() {
    return formPageConfig.gsdApproveApi || '';
}

async function fetchGsdCanvasAssigneesList() {
    const base = gsdApproveApiBase();
    if (!base) {
        return [];
    }
    if (gsdCanvasAssigneesCache) {
        return gsdCanvasAssigneesCache;
    }
    const url = `${base}?action=list_canvas_assignees`;
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

async function postSaveGsdCanvasAssignee(requestId, userId) {
    const base = gsdApproveApiBase();
    if (!base || !requestId || !userId) {
        return;
    }
    const body = new URLSearchParams();
    body.set('action', 'save_canvas_assignee');
    body.set('request_id', String(requestId));
    body.set('canvas_assignee_user_id', String(userId));
    try {
        const res = await fetch(base, {
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

function updateGsdCanvasAssigneeFieldState(approval, assignees) {
    const els = getGsdCanvasAssigneeEls();
    if (!els.input || !formPageConfig.isGsdCanvasAssigneeUi) {
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

function bindGsdCanvasAssigneePickerEventsOnce(requestId) {
    const els = getGsdCanvasAssigneeEls();
    if (!els.input || !formPageConfig.isGsdCanvasAssigneeUi) {
        return;
    }
    if (els.input.dataset.imrmsGsdAssigneeBound === '1') {
        return;
    }
    els.input.dataset.imrmsGsdAssigneeBound = '1';

    const getAssignees = () => (Array.isArray(gsdAssigneesLiveRef.list) ? gsdAssigneesLiveRef.list : []);

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
            postSaveGsdCanvasAssignee(requestId, uid);
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

function initGsdCanvasAssigneePicker(requestId, approval, assignees) {
    if (!formPageConfig.isGsdCanvasAssigneeUi) {
        return;
    }
    gsdAssigneesLiveRef.list = assignees;
    updateGsdCanvasAssigneeFieldState(approval, assignees);
    bindGsdCanvasAssigneePickerEventsOnce(requestId);
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
            (a.email || '').toLowerCase().startsWith(t + '@')
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

function presidentApproveApiBase() {
    return formPageConfig.presidentApproveApi || '';
}

async function fetchComptrollerApproval(requestId) {
    const base = comptrollerApproveApiBase();
    if (!base || !requestId) {
        return null;
    }
    const url = `${base}?action=get_approval_status&request_id=${encodeURIComponent(String(requestId))}`;
    const res = await fetch(url, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) {
        return null;
    }
    return data.approval || null;
}

async function fetchCanvasserApproval(requestId) {
    const base = canvasserApproveApiBase();
    if (!base || !requestId) {
        return null;
    }
    const url = `${base}?action=get_approval_status&request_id=${encodeURIComponent(String(requestId))}`;
    const res = await fetch(url, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) {
        return null;
    }
    return data.approval || null;
}

function setCanvasserApprovalButtonsState(approval) {
    const doneBtn = document.getElementById('canvasserDoneBtn');
    const canvasserUndoBtn = document.getElementById('canvasserUndoBtn');
    const st = String((approval && approval.canvas_status) || 'pending').trim().toLowerCase();
    const finished = st === 'accept' || st === 'reject';

    if (doneBtn) {
        doneBtn.textContent = 'Done';
        doneBtn.disabled = finished;
        if (canvasserUndoBtn) {
            canvasserUndoBtn.style.display = finished ? 'inline-flex' : 'none';
        }
        syncCanvasserMatrixEditLock(approval);
        return;
    }

    const approveBtn = document.getElementById('comptrollerApproveBtn');
    const rejectBtn = document.getElementById('comptrollerRejectBtn');
    const undoBtn = document.getElementById('comptrollerUndoBtn');
    if (!approveBtn || !rejectBtn) {
        return;
    }
    approveBtn.disabled = false;
    rejectBtn.disabled = false;
    approveBtn.textContent = st === 'accept' ? 'Approved' : 'Approve';
    rejectBtn.textContent = st === 'reject' ? 'Rejected' : 'Reject';
    if (undoBtn) {
        undoBtn.style.display = st === 'accept' || st === 'reject' ? 'inline-flex' : 'none';
    }
}

function setComptrollerApprovalButtonsState(approval) {
    const approveBtn = document.getElementById('comptrollerApproveBtn');
    const rejectBtn = document.getElementById('comptrollerRejectBtn');
    const undoBtn = document.getElementById('comptrollerUndoBtn');
    if (!approveBtn || !rejectBtn) {
        return;
    }
    const st = String((approval && approval.comp_status) || 'pending').trim();
    approveBtn.disabled = false;
    rejectBtn.disabled = false;
    approveBtn.textContent = st === 'accept' ? 'Approved' : 'Approve';
    rejectBtn.textContent = st === 'reject' ? 'Rejected' : 'Reject';
    if (undoBtn) {
        undoBtn.style.display = st === 'accept' || st === 'reject' ? 'inline-flex' : 'none';
    }
}

async function fetchGsdApproval(requestId) {
    const base = gsdApproveApiBase();
    if (!base || !requestId) {
        return null;
    }
    const url = `${base}?action=get_approval_status&request_id=${encodeURIComponent(String(requestId))}`;
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
    if (!approveBtn || !rejectBtn) {
        return;
    }
    const st = String((approval && approval.gsd_status) || 'pending').trim();
    approveBtn.disabled = false;
    rejectBtn.disabled = false;
    approveBtn.textContent = st === 'accept' ? 'Verified' : 'Verify';
    rejectBtn.textContent = st === 'reject' ? 'Rejected' : 'Reject';
    if (undoBtn) {
        undoBtn.style.display = st === 'accept' || st === 'reject' ? 'inline-flex' : 'none';
    }
}

async function fetchPresidentApproval(requestId) {
    const base = presidentApproveApiBase();
    if (!base || !requestId) {
        return null;
    }
    const url = `${base}?action=get_approval_status&request_id=${encodeURIComponent(String(requestId))}`;
    const res = await fetch(url, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) {
        return null;
    }
    return data.approval || null;
}

function setPresidentApprovalButtonsState(approval) {
    const approveBtn = document.getElementById('comptrollerApproveBtn');
    const rejectBtn = document.getElementById('comptrollerRejectBtn');
    const undoBtn = document.getElementById('comptrollerUndoBtn');
    if (!approveBtn || !rejectBtn) {
        return;
    }
    const st = String((approval && approval.pres_status) || 'pending').trim();
    approveBtn.disabled = false;
    rejectBtn.disabled = false;
    approveBtn.textContent = st === 'accept' ? 'Approved' : 'Approve';
    rejectBtn.textContent = st === 'reject' ? 'Rejected' : 'Reject';
    if (undoBtn) {
        undoBtn.style.display = st === 'accept' || st === 'reject' ? 'inline-flex' : 'none';
    }
}

async function initPresidentApprovalActions(requestId) {
    if (!formPageConfig.isPresidentView || !presidentApproveApiBase()) {
        return;
    }
    const approval = await fetchPresidentApproval(requestId);
    setPresidentApprovalButtonsState(approval);

    const approveBtn = document.getElementById('comptrollerApproveBtn');
    const rejectBtn = document.getElementById('comptrollerRejectBtn');
    const undoBtn = document.getElementById('comptrollerUndoBtn');

    const postApproval = async (presStatus) => {
        const base = presidentApproveApiBase();
        const body = new URLSearchParams();
        body.set('action', 'set_pres_approval');
        body.set('request_id', String(requestId));
        body.set('pres_status', presStatus);
        try {
            const res = await fetch(base, {
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
            const next = await fetchPresidentApproval(requestId);
            applyApprovalFromPayload({
                status: data.requisition_status || '',
                approval: next
                    ? {
                          canvas_status: next.canvas_status,
                          gsd_status: next.gsd_status,
                          comp_status: next.comp_status,
                          pres_status: next.pres_status,
                      }
                    : {
                          canvas_status: null,
                          gsd_status: null,
                          comp_status: null,
                          pres_status: null,
                      },
            });
            setPresidentApprovalButtonsState(next);
        } catch {
            showToast('Network error.', 'error');
        }
    };

    if (approveBtn && !approveBtn.dataset.imrmsPresBound) {
        approveBtn.dataset.imrmsPresBound = '1';
        approveBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Approve this request as President? The line status will be set to Ongoing.'
            );
            if (!ok) return;
            await postApproval('accept');
        });
    }
    if (rejectBtn && !rejectBtn.dataset.imrmsPresBound) {
        rejectBtn.dataset.imrmsPresBound = '1';
        rejectBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Reject this request as President? The line status will be set to Ongoing.'
            );
            if (!ok) return;
            await postApproval('reject');
        });
    }
    if (undoBtn && !undoBtn.dataset.imrmsPresBound) {
        undoBtn.dataset.imrmsPresBound = '1';
        undoBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Undo your presidential decision? Approval will reset to pending and the request status will return to Pending.'
            );
            if (!ok) return;
            await postApproval('pending');
        });
    }
}

async function initGsdApprovalActions(requestId) {
    if (!formPageConfig.isGsdView || !gsdApproveApiBase()) {
        return;
    }
    const approval = await fetchGsdApproval(requestId);
    setGsdApprovalButtonsState(approval);

    let assignees = [];
    if (formPageConfig.isGsdCanvasAssigneeUi) {
        assignees = await fetchGsdCanvasAssigneesList();
        initGsdCanvasAssigneePicker(requestId, approval, assignees);
    }

    const approveBtn = document.getElementById('comptrollerApproveBtn');
    const rejectBtn = document.getElementById('comptrollerRejectBtn');
    const undoBtn = document.getElementById('comptrollerUndoBtn');

    const postApproval = async (gsdStatus) => {
        if (gsdStatus === 'accept' && formPageConfig.isGsdCanvasAssigneeUi) {
            const fresh = await fetchGsdApproval(requestId);
            const canvasSt = String((fresh && fresh.canvas_status) || 'pending').trim().toLowerCase();
            const canvasDone = canvasSt === 'accept' || canvasSt === 'reject';
            if (!canvasDone) {
                const uid = resolveGsdCanvasAssigneeUserId(gsdAssigneesLiveRef.list, fresh);
                if (!uid || uid <= 0) {
                    showToast('Choose a office staff member for canvassing before verifying.', 'error');
                    return;
                }
                const hid = document.getElementById('gsdCanvasAssigneeUserId');
                if (hid) {
                    hid.value = String(uid);
                }
            }
        }

        const base = gsdApproveApiBase();
        const body = new URLSearchParams();
        body.set('action', 'set_gsd_approval');
        body.set('request_id', String(requestId));
        body.set('gsd_status', gsdStatus);
        if (formPageConfig.isGsdCanvasAssigneeUi) {
            const hid = document.getElementById('gsdCanvasAssigneeUserId');
            const uid = hid && hid.value ? parseInt(hid.value, 10) : 0;
            if (uid > 0) {
                body.set('canvas_assignee_user_id', String(uid));
            }
        }
        try {
            const res = await fetch(base, {
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
            const next = await fetchGsdApproval(requestId);
            applyApprovalFromPayload({
                status: data.requisition_status || '',
                approval: next
                    ? {
                          canvas_status: next.canvas_status,
                          gsd_status: next.gsd_status,
                          comp_status: next.comp_status,
                          pres_status: next.pres_status,
                      }
                    : {
                          canvas_status: null,
                          gsd_status: null,
                          comp_status: null,
                          pres_status: null,
                      },
            });
            setGsdApprovalButtonsState(next);
            if (formPageConfig.isGsdCanvasAssigneeUi) {
                gsdCanvasAssigneesCache = null;
                const nextAssignees = await fetchGsdCanvasAssigneesList();
                initGsdCanvasAssigneePicker(requestId, next, nextAssignees);
            }
        } catch {
            showToast('Network error.', 'error');
        }
    };

    if (approveBtn && !approveBtn.dataset.imrmsGsdBound) {
        approveBtn.dataset.imrmsGsdBound = '1';
        approveBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Verify this request at GSD? The line status will be set to Ongoing.'
            );
            if (!ok) return;
            await postApproval('accept');
        });
    }
    if (rejectBtn && !rejectBtn.dataset.imrmsGsdBound) {
        rejectBtn.dataset.imrmsGsdBound = '1';
        rejectBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Reject this request at GSD? The line status will be set to Ongoing.'
            );
            if (!ok) return;
            await postApproval('reject');
        });
    }
    if (undoBtn && !undoBtn.dataset.imrmsGsdBound) {
        undoBtn.dataset.imrmsGsdBound = '1';
        undoBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Undo your GSD decision? Verification will reset to pending and the request status will return to Pending.'
            );
            if (!ok) return;
            await postApproval('pending');
        });
    }
}

function initCanvasserSupplierUi() {
    if (!formPageConfig.isCanvasserView) {
        return;
    }
    const clearContactBtn = document.getElementById('clearSupplierContactBtn');
    if (clearContactBtn && !clearContactBtn.dataset.imrmsBound) {
        clearContactBtn.dataset.imrmsBound = '1';
        clearContactBtn.addEventListener('click', () => {
            setSupplierPickerHighlight(null);
            if (supplierDropdownBtn) {
                supplierDropdownBtn.focus();
            }
        });
    }

    const modal = document.getElementById('canvasserNewSupplierModal');
    if (!modal || modal.dataset.imrmsSupplierUiBound === '1') {
        return;
    }
    modal.dataset.imrmsSupplierUiBound = '1';

    const regBtn = document.getElementById('canvasserRegisterSupplierBtn');
    if (regBtn) {
        regBtn.addEventListener('click', () => openCanvasserNewSupplierModal());
    }
    const submitBtn = document.getElementById('canvasserNewSupplierSubmit');
    if (submitBtn) {
        submitBtn.addEventListener('click', () => submitCanvasserNewSupplier());
    }
    modal.querySelectorAll('[data-close-canvasser-supplier-modal]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            closeCanvasserNewSupplierModal();
        });
    });
    const form = document.getElementById('canvasserNewSupplierForm');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            submitCanvasserNewSupplier();
        });
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeCanvasserNewSupplierModal();
        }
    });
}

async function initCanvasserApprovalActions(requestId) {
    if (!formPageConfig.isCanvasserView || !canvasserApproveApiBase()) {
        return;
    }
    initCanvasserSupplierUi();
    const approval = await fetchCanvasserApproval(requestId);
    syncCanvasAssigneeNameDisplay(approval);
    setCanvasserApprovalButtonsState(approval);

    const doneBtn = document.getElementById('canvasserDoneBtn');
    const canvasserUndoBtn = document.getElementById('canvasserUndoBtn');
    const approveBtn = document.getElementById('comptrollerApproveBtn');
    const rejectBtn = document.getElementById('comptrollerRejectBtn');
    const undoBtn = document.getElementById('comptrollerUndoBtn');

    const postApproval = async (canvasStatus) => {
        const base = canvasserApproveApiBase();
        const body = new URLSearchParams();
        body.set('action', 'set_canvas_approval');
        body.set('request_id', String(requestId));
        body.set('canvas_status', canvasStatus);
        try {
            const res = await fetch(base, {
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
            const next = await fetchCanvasserApproval(requestId);
            applyApprovalFromPayload({
                status: data.requisition_status || '',
                approval: next
                    ? {
                          canvas_status: next.canvas_status,
                          gsd_status: next.gsd_status,
                          comp_status: next.comp_status,
                          pres_status: next.pres_status,
                      }
                    : {
                          canvas_status: null,
                          gsd_status: null,
                          comp_status: null,
                          pres_status: null,
                      },
            });
            setCanvasserApprovalButtonsState(next);
            syncCanvasAssigneeNameDisplay(next);
        } catch {
            showToast('Network error.', 'error');
        }
    };

    if (doneBtn && !doneBtn.dataset.imrmsCanvasBound) {
        doneBtn.dataset.imrmsCanvasBound = '1';
        doneBtn.addEventListener('click', async () => {
            if (doneBtn.disabled) {
                return;
            }
            const ok = await showConfirmModal(
                'Mark canvassing as done? The canvass step will show complete and the line status will be set to Ongoing.'
            );
            if (!ok) return;
            await postApproval('accept');
        });
    }
    if (canvasserUndoBtn && !canvasserUndoBtn.dataset.imrmsCanvasUndoBound) {
        canvasserUndoBtn.dataset.imrmsCanvasUndoBound = '1';
        canvasserUndoBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Undo your canvass decision? The canvass step will reset and the request status will return to Pending.'
            );
            if (!ok) return;
            await postApproval('pending');
        });
    }
    if (approveBtn && !approveBtn.dataset.imrmsCanvasBound) {
        approveBtn.dataset.imrmsCanvasBound = '1';
        approveBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Do you want to approve this request as canvasser? The line status will be set to Ongoing.'
            );
            if (!ok) return;
            await postApproval('accept');
        });
    }
    if (rejectBtn && !rejectBtn.dataset.imrmsCanvasBound) {
        rejectBtn.dataset.imrmsCanvasBound = '1';
        rejectBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Do you want to reject this request as canvasser? The line status will be set to Ongoing.'
            );
            if (!ok) return;
            await postApproval('reject');
        });
    }
    if (undoBtn && !undoBtn.dataset.imrmsCanvasBound) {
        undoBtn.dataset.imrmsCanvasBound = '1';
        undoBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Undo your canvasser decision? Approval will reset to pending and the request status will return to Pending.'
            );
            if (!ok) return;
            await postApproval('pending');
        });
    }

    const saveQuotesBtn = document.getElementById('canvasserSaveQuotesBtn');
    if (saveQuotesBtn && !saveQuotesBtn.dataset.imrmsCanvasSaveBound) {
        saveQuotesBtn.dataset.imrmsCanvasSaveBound = '1';
        saveQuotesBtn.addEventListener('click', async () => {
            if (state.canvasserMatrixLocked || saveQuotesBtn.disabled) {
                return;
            }
            if (state.selectedSuppliers.length === 0) {
                showToast('Add at least one supplier to the matrix.', 'error');
                return;
            }
            const base = canvasserApproveApiBase();
            const body = new URLSearchParams();
            body.set('action', 'save_canvas_quotations');
            body.set('request_id', String(requestId));
            body.set('suppliers', JSON.stringify(state.selectedSuppliers));
            try {
                const res = await fetch(base, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                    credentials: 'include',
                });
                const data = await res.json();
                if (!data.success) {
                    showToast(data.message || 'Could not save suppliers.', 'error');
                    return;
                }
                showToast(data.message || 'Saved.');
            } catch {
                showToast('Network error.', 'error');
            }
        });
    }
}

async function initComptrollerApprovalActions(requestId) {
    if (!formPageConfig.isComptrollerView || !comptrollerApproveApiBase()) {
        return;
    }
    const approval = await fetchComptrollerApproval(requestId);
    setComptrollerApprovalButtonsState(approval);

    const approveBtn = document.getElementById('comptrollerApproveBtn');
    const rejectBtn = document.getElementById('comptrollerRejectBtn');
    const undoBtn = document.getElementById('comptrollerUndoBtn');

    const postApproval = async (compStatus) => {
        const base = comptrollerApproveApiBase();
        const body = new URLSearchParams();
        body.set('action', 'set_comptroller_approval');
        body.set('request_id', String(requestId));
        body.set('comp_status', compStatus);
        try {
            const res = await fetch(base, {
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
            const next = await fetchComptrollerApproval(requestId);
            applyApprovalFromPayload({
                status: data.requisition_status || '',
                approval: next
                    ? {
                          canvas_status: next.canvas_status,
                          gsd_status: next.gsd_status,
                          comp_status: next.comp_status,
                          pres_status: next.pres_status,
                      }
                    : {
                          canvas_status: null,
                          gsd_status: null,
                          comp_status: null,
                          pres_status: null,
                      },
            });
            setComptrollerApprovalButtonsState(next);
        } catch {
            showToast('Network error.', 'error');
        }
    };

    if (approveBtn && !approveBtn.dataset.imrmsCompBound) {
        approveBtn.dataset.imrmsCompBound = '1';
        approveBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Do you want to continue to approve this request? The line status will be set to Ongoing.'
            );
            if (!ok) return;
            await postApproval('accept');
        });
    }
    if (rejectBtn && !rejectBtn.dataset.imrmsCompBound) {
        rejectBtn.dataset.imrmsCompBound = '1';
        rejectBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Do you want to reject this request? The line status will be set to Ongoing.'
            );
            if (!ok) return;
            await postApproval('reject');
        });
    }
    if (undoBtn && !undoBtn.dataset.imrmsCompBound) {
        undoBtn.dataset.imrmsCompBound = '1';
        undoBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal(
                'Undo your comptroller decision? Approval will reset to pending and the request status will return to Pending.'
            );
            if (!ok) return;
            await postApproval('pending');
        });
    }
}

async function fetchInventoryReview(requestId) {
    const base = inventoryApproveApiBase();
    if (!base || !requestId) {
        return null;
    }
    const url = `${base}?action=get_requisition_review&request_id=${encodeURIComponent(String(requestId))}`;
    const res = await fetch(url, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) {
        return null;
    }
    return data.review || null;
}

function setInventoryReviewButtonsState(review, approval) {
    const approveBtn = document.getElementById('comptrollerApproveBtn');
    const rejectBtn = document.getElementById('comptrollerRejectBtn');
    const undoBtn = document.getElementById('comptrollerUndoBtn');
    const rejectReasonInput = document.getElementById('inventoryRejectReason');
    if (!approveBtn || !rejectBtn) {
        return;
    }
    const appr = approval !== undefined ? approval : state.approval;
    const locked = verifierChainLockedFromApproval(appr);
    const st = String((review && review.requisition_status) || 'pending').trim().toLowerCase();
    approveBtn.textContent = st === 'accept' ? 'Accepted' : 'Accept requisition';
    rejectBtn.textContent = st === 'reject' ? 'Rejected' : 'Reject';
    approveBtn.disabled = locked;
    rejectBtn.disabled = locked;
    if (rejectReasonInput) {
        rejectReasonInput.disabled = locked;
    }
    if (undoBtn) {
        if (locked) {
            undoBtn.style.display = 'none';
            undoBtn.disabled = true;
        } else {
            undoBtn.disabled = false;
            undoBtn.style.display = st === 'accept' || st === 'reject' ? 'inline-flex' : 'none';
        }
    }
}

async function initInventoryReviewActions(requestId) {
    if (!formPageConfig.isInventoryManagerView || !inventoryApproveApiBase()) {
        return;
    }
    const review = await fetchInventoryReview(requestId);
    const rejectReasonInput = document.getElementById('inventoryRejectReason');
    if (review && rejectReasonInput) {
        rejectReasonInput.value = review.requisition_note || '';
    }
    setInventoryReviewButtonsState(review);

    const approveBtn = document.getElementById('comptrollerApproveBtn');
    const rejectBtn = document.getElementById('comptrollerRejectBtn');
    const undoBtn = document.getElementById('comptrollerUndoBtn');
    const postReview = async (requisitionStatus) => {
        const base = inventoryApproveApiBase();
        const body = new URLSearchParams();
        body.set('action', 'set_requisition_review');
        body.set('request_id', String(requestId));
        body.set('requisition_status', requisitionStatus);
        const note = rejectReasonInput ? rejectReasonInput.value.trim() : '';
        if (requisitionStatus === 'reject') {
            if (!note) {
                showToast('Please add a rejection reason.', 'error');
                return;
            }
            body.set('requisition_note', note);
        } else if (note) {
            body.set('requisition_note', note);
        }
        try {
            const res = await fetch(base, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'include',
            });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not save review.', 'error');
                return;
            }
            showToast(data.message || 'Saved.');
            const next = await fetchInventoryReview(requestId);
            state.requisitionStatus = String((next && next.requisition_status) || 'pending').trim().toLowerCase();
            syncCanvasSectionVisibility();
            const prevAppr = state.approval && typeof state.approval === 'object' ? { ...state.approval } : {};
            applyApprovalFromPayload({
                status: data.requisition_status_label || '',
                approval: {
                    ...prevAppr,
                    requisition_status: next ? next.requisition_status : null,
                    requisition_note: next ? next.requisition_note : null,
                    requisition_reviewed_by: next ? next.requisition_reviewed_by : null,
                    requisition_reviewed_at: next ? next.requisition_reviewed_at : null,
                },
            });
            if (next && rejectReasonInput) {
                rejectReasonInput.value = next.requisition_note || '';
            }
            setInventoryReviewButtonsState(next, state.approval);
        } catch {
            showToast('Network error.', 'error');
        }
    };
    if (approveBtn && !approveBtn.dataset.imrmsInvBound) {
        approveBtn.dataset.imrmsInvBound = '1';
        approveBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal('Accept this requisition? If accepted, canvass form will be enabled.');
            if (!ok) return;
            await postReview('accept');
        });
    }
    if (rejectBtn && !rejectBtn.dataset.imrmsInvBound) {
        rejectBtn.dataset.imrmsInvBound = '1';
        rejectBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal('Reject this requisition? Requester can edit and resubmit.');
            if (!ok) return;
            await postReview('reject');
        });
    }
    if (undoBtn && !undoBtn.dataset.imrmsInvBound) {
        undoBtn.dataset.imrmsInvBound = '1';
        undoBtn.addEventListener('click', async () => {
            const ok = await showConfirmModal('Reset inventory manager decision to pending?');
            if (!ok) return;
            await postReview('pending');
        });
    }
}

function showMessageCallout(msg) {
    const callout = document.getElementById('rfMessageCallout');
    const calloutText = document.getElementById('rfMessageCalloutText');
    if (!callout || !calloutText) return;
    const trimmed = (msg || '').trim();
    if (trimmed) {
        calloutText.textContent = trimmed;
        callout.hidden = false;
    } else {
        callout.hidden = true;
    }
}

function applyViewOnlyMode() {
    state.viewOnly = true;
    const card = document.querySelector('.requisition-card');
    if (card) {
        card.classList.add('view-only');
    }
    officeSelect.disabled = true;
    facilitySelect.disabled = true;
    requestDateInput.disabled = true;
    if (requestMessageInput) requestMessageInput.disabled = true;
    if (requestPurposeInput) {
        requestPurposeInput.disabled = true;
    }
    if (requesterNameInput) {
        requesterNameInput.disabled = true;
    }
    if (facultyRoleInput) {
        facultyRoleInput.disabled = true;
    }
    if (rfAddItemBtn) {
        rfAddItemBtn.style.display = 'none';
    }
    renderItems();
    if (formPageConfig.isCanvasserView) {
        state.canvasserMatrixLocked = false;
        if (addSupplierBtn) {
            addSupplierBtn.style.display = '';
        }
        if (supplierDropdownBtn) {
            supplierDropdownBtn.disabled = false;
        }
    } else {
        if (addSupplierBtn) {
            addSupplierBtn.style.display = 'none';
        }
        if (supplierDropdownBtn) {
            supplierDropdownBtn.disabled = true;
        }
    }
    if (submitRequisitionBtn) {
        submitRequisitionBtn.style.display = 'none';
    }
    if (rfFormFooterBar) {
        rfFormFooterBar.style.display = 'none';
    }
}

function showToast(message, type = 'success') {
    formToast.textContent = message;
    formToast.className = `toast ${type}`;
    formToast.style.display = 'block';
    const ms = type === 'info' ? 9000 : 3000;
    setTimeout(() => {
        formToast.style.display = 'none';
    }, ms);
}

function showConfirmModal(message) {
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

function resetFormToDefault() {
    setSupplierPickerHighlight(null);
    if (supplierDropdown) {
        supplierDropdown.classList.remove('open');
    }
    if (requestMessageInput) requestMessageInput.value = '';
    if (requestPurposeInput) {
        requestPurposeInput.value = '';
    }
    requestDateInput.value = state.defaultDate;

    state.requestedItems = createDefaultRequestedItems();
    state.selectedSuppliers = [];
    officeSelect.value = state.defaultOfficeId;
    renderFacilitiesByOffice();
    facilitySelect.value = '';

    renderItems();
    renderSupplierTable();
    syncCanvasSectionVisibility();
}

function fillDatalist(element, values) {
    if (!element) {
        return;
    }
    const unique = Array.from(new Set(values.filter(Boolean)));
    element.innerHTML = unique.map((value) => `<option value="${escapeHtml(value)}"></option>`).join('');
}

function normalizeRequestedItem(raw = {}) {
    return {
        item_id: raw.item_id != null ? Number(raw.item_id) : null,
        name: String(raw.name || ''),
        brand: String(raw.brand || ''),
        model: String(raw.model || ''),
        specification: String(raw.specification || ''),
        group_label: String(raw.group_label || ''),
        category: String(raw.category || ''),
        quantity: Math.max(1, Number(raw.quantity) || 1),
        unit_type: String(raw.unit_type || 'piece'),
    };
}

function serializeRequestedItemsForApi() {
    return state.requestedItems
        .map((item) => normalizeRequestedItem(item))
        .filter((item) => item.name.trim() !== '')
        .map((item) => ({
            item_id: item.item_id,
            name: item.name.trim(),
            brand: item.brand,
            model: item.model,
            specification: item.specification,
            group_label: item.group_label,
            category: item.category,
            quantity: item.quantity,
            unit_type: item.unit_type,
        }));
}

function buildUnitSelect(index, selectedValue, disabled) {
    const current = String(selectedValue || 'piece');
    const units = (window.RF_UNITS && window.RF_UNITS.length)
        ? window.RF_UNITS
        : [{ value: 'piece', label: 'Piece' }, { value: 'unit', label: 'Unit' }, { value: 'set', label: 'Set' }];
    const options = units
        .map((unit) => {
            const selected = unit.value === current ? ' selected' : '';
            return `<option value="${unit.value}"${selected}>${unit.label}</option>`;
        })
        .join('');
    return `<select class="rf-item-field rf-item-field--unit" data-item-field="unit_type" data-item-index="${index}"${disabled ? ' disabled' : ''}>${options}</select>`;
}

function renderItems() {
    if (!requestedItemsBody) {
        return;
    }

    if (state.requestedItems.length === 0) {
        requestedItemsBody.innerHTML = `
            <div class="rf-items-row rf-items-row--empty">No requested items yet.</div>
        `;
        return;
    }

    const disabled = state.viewOnly;

    if (disabled) {
        // View-only mode: group items by group_label; show brand/model/spec inline as meta.
        const chunks = [];
        const groupMap = new Map();

        state.requestedItems.forEach((item, index) => {
            const normalized = normalizeRequestedItem(item);
            const gl = normalized.group_label.trim();
            if (gl) {
                if (!groupMap.has(gl)) {
                    const g = { type: 'group', label: gl, rows: [] };
                    groupMap.set(gl, g);
                    chunks.push(g);
                }
                groupMap.get(gl).rows.push({ normalized, index });
            } else {
                chunks.push({ type: 'item', normalized, index });
            }
        });

        const renderViewRow = (normalized, index, grouped = false) => {
            const metaParts = [normalized.brand, normalized.model, normalized.specification]
                .filter((s) => s.trim() !== '');
            const metaHtml = metaParts.length
                ? `<span class="rf-items-row__meta">${escapeHtml(metaParts.join(' · '))}</span>`
                : '';
            return `
            <div class="rf-items-row${grouped ? ' rf-items-row--grouped' : ''}">
                <span class="rf-items-row__index">${index + 1}</span>
                <span class="rf-items-row__desc-text">${escapeHtml(normalized.name)}${metaHtml}</span>
                <div class="rf-items-row__controls">
                    <span class="rf-items-row__unit">${escapeHtml(normalized.unit_type)}</span>
                    <span class="rf-items-row__qty">${escapeHtml(String(normalized.quantity))}</span>
                </div>
            </div>`;
        };

        requestedItemsBody.innerHTML = chunks
            .map((chunk) => {
                if (chunk.type === 'item') {
                    return renderViewRow(chunk.normalized, chunk.index, false);
                }
                const rowsHtml = chunk.rows
                    .map(({ normalized, index }) => renderViewRow(normalized, index, true))
                    .join('');
                return `
                <div class="rf-items-group">
                    <div class="rf-items-group__header">
                        <i class="fas fa-layer-group" aria-hidden="true"></i>
                        Items grouped under: <strong>${escapeHtml(chunk.label)}</strong>
                    </div>
                    ${rowsHtml}
                </div>`;
            })
            .join('');
        return;
    }

    // Edit mode: main row + details sub-row (brand / model / specification / group_label).
    requestedItemsBody.innerHTML = state.requestedItems
        .map((item, index) => {
            const normalized = normalizeRequestedItem(item);
            return `
            <div class="rf-items-entry" data-item-index="${index}">
                <div class="rf-items-row">
                    <span class="rf-items-row__index">${index + 1}</span>
                    <input type="text" class="rf-item-field rf-items-row__desc" data-item-field="name" data-item-index="${index}"
                        value="${escapeHtml(normalized.name)}" list="itemNameSuggestions" placeholder="e.g. Whiteboard marker" autocomplete="off">
                    <div class="rf-items-row__controls">
                        ${buildUnitSelect(index, normalized.unit_type, false)}
                        <input type="number" class="rf-item-field rf-item-field--qty" data-item-field="quantity" data-item-index="${index}"
                            min="1" step="1" value="${escapeHtml(String(normalized.quantity))}">
                        <button type="button" class="rf-item-remove-btn" data-item-index="${index}" title="Remove item" aria-label="Remove item">
                            <i class="fas fa-trash-can" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="rf-items-details">
                    <label class="rf-items-detail-field">
                        <span class="rf-items-detail-field__label">Brand</span>
                        <input type="text" class="rf-item-field rf-items-detail__input" data-item-field="brand" data-item-index="${index}"
                            value="${escapeHtml(normalized.brand)}" placeholder="e.g. Canon">
                    </label>
                    <label class="rf-items-detail-field">
                        <span class="rf-items-detail-field__label">Model</span>
                        <input type="text" class="rf-item-field rf-items-detail__input" data-item-field="model" data-item-index="${index}"
                            value="${escapeHtml(normalized.model)}" placeholder="e.g. PIXMA G3010">
                    </label>
                    <label class="rf-items-detail-field rf-items-detail-field--spec">
                        <span class="rf-items-detail-field__label">Specification</span>
                        <input type="text" class="rf-item-field rf-items-detail__input" data-item-field="specification" data-item-index="${index}"
                            value="${escapeHtml(normalized.specification)}" placeholder="e.g. Color inkjet, A4">
                    </label>
                    <label class="rf-items-detail-field">
                        <span class="rf-items-detail-field__label">Group name <span class="rf-items-detail-field__hint">(optional)</span></span>
                        <input type="text" class="rf-item-field rf-items-detail__input" data-item-field="group_label" data-item-index="${index}"
                            value="${escapeHtml(normalized.group_label)}" placeholder="e.g. Computer Set">
                    </label>
                </div>
            </div>
            `;
        })
        .join('');
}

function addRequestedItemRow() {
    state.requestedItems.push(normalizeRequestedItem(createEmptyRequestedItem()));
    state.hasUnsavedChanges = true;
    renderItems();
    renderSupplierTable();

    const lastIndex = state.requestedItems.length - 1;
    const nameInput = requestedItemsBody?.querySelector(`input[data-item-field="name"][data-item-index="${lastIndex}"]`);
    if (nameInput) {
        nameInput.focus();
    }
}

function syncRequestedItemFromField(index, field, rawValue) {
    if (!state.requestedItems[index]) {
        return;
    }
    const item = normalizeRequestedItem(state.requestedItems[index]);

    if (field === 'name') {
        const name = String(rawValue || '').trim();
        item.name = name;
        const matchedItem = state.availableItems.find(
            (catalogItem) => catalogItem.item_name.toLowerCase() === name.toLowerCase()
        );
        if (matchedItem) {
            item.item_id = Number(matchedItem.item_id) || null;
            if (!item.category && matchedItem.category) {
                item.category = String(matchedItem.category);
            }
            if (!item.brand && matchedItem.brand) {
                item.brand = String(matchedItem.brand);
            }
        } else if (!name) {
            item.item_id = null;
        }
    } else if (field === 'unit_type') {
        item.unit_type = String(rawValue || 'unit');
    } else if (field === 'quantity') {
        item.quantity = Math.max(1, Number(rawValue) || 1);
    } else if (field === 'brand' || field === 'model' || field === 'specification' || field === 'group_label') {
        item[field] = String(rawValue || '');
    }

    state.requestedItems[index] = item;
    state.hasUnsavedChanges = true;
}

function getSupplierImageUrl(supplierImage) {
    if (!supplierImage) {
        return 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" rx="20" fill="%23dcf5e0"/><text x="50%" y="55%" text-anchor="middle" font-size="16" fill="%231f4f28" font-family="Arial">S</text></svg>';
    }
    if (/^https?:\/\//i.test(supplierImage)) {
        return supplierImage;
    }
    return `../${supplierImage.replace(/^\/+/, '')}`;
}

if (supplierDropdownPreview) {
    supplierDropdownPreview.addEventListener('error', () => {
        supplierDropdownPreview.onerror = null;
        supplierDropdownPreview.src = getSupplierImageUrl('');
    });
}

const supplierAvatarOnError =
    "this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22><rect width=%2240%22 height=%2240%22 rx=%2220%22 fill=%22%23dcf5e0%22/><text x=%2250%25%22 y=%2255%25%22 text-anchor=%22middle%22 font-size=%2216%22 fill=%22%231f4f28%22 font-family=%22Arial%22>S</text></svg>'";

/** Clear or set supplier combobox preview (does not change selectedSuppliers matrix). */
function setSupplierPickerHighlight(supplierId) {
    const id = supplierId != null && supplierId !== '' ? Number(supplierId) : 0;
    if (!id || Number.isNaN(id)) {
        state.selectedSupplierId = null;
        if (supplierDropdownPreview) {
            supplierDropdownPreview.hidden = true;
            supplierDropdownPreview.removeAttribute('src');
            supplierDropdownPreview.alt = '';
        }
        if (supplierSelectedText) {
            supplierSelectedText.textContent = 'Select Supplier';
        }
        updateSupplierContactPanel();
        return;
    }
    const supplier = state.availableSuppliers.find((x) => Number(x.supplier_id) === id);
    state.selectedSupplierId = String(id);
    if (supplier) {
        if (supplierSelectedText) {
            supplierSelectedText.textContent = supplier.supplier_name || 'Supplier';
        }
        if (supplierDropdownPreview) {
            supplierDropdownPreview.src = getSupplierImageUrl(supplier.supplier_image);
            supplierDropdownPreview.alt = supplier.supplier_name || '';
            supplierDropdownPreview.hidden = false;
        }
    } else {
        if (supplierSelectedText) {
            supplierSelectedText.textContent = 'Select Supplier';
        }
        if (supplierDropdownPreview) {
            supplierDropdownPreview.hidden = true;
            supplierDropdownPreview.removeAttribute('src');
            supplierDropdownPreview.alt = '';
        }
    }
    updateSupplierContactPanel();
}

function updateSupplierContactPanel() {
    const panel = document.getElementById('supplierContactPanel');
    if (!panel || !formPageConfig.isCanvasserView) {
        if (panel) {
            panel.hidden = true;
        }
        return;
    }
    const body = document.getElementById('supplierContactPanelBody');
    if (!body) {
        return;
    }
    const id = state.selectedSupplierId ? Number(state.selectedSupplierId) : 0;
    if (!id || Number.isNaN(id)) {
        panel.hidden = true;
        return;
    }
    const s = state.availableSuppliers.find((x) => Number(x.supplier_id) === id);
    if (!s) {
        panel.hidden = true;
        return;
    }
    panel.hidden = false;
    const lines = [];
    if (s.contact_person) {
        lines.push(`<div><span class="sc-label">Contact</span>${escapeHtml(s.contact_person)}</div>`);
    }
    if (s.phone_number) {
        const tel = String(s.phone_number).replace(/\s+/g, '');
        lines.push(
            `<div><span class="sc-label">Phone</span><a href="tel:${escapeHtml(tel)}">${escapeHtml(s.phone_number)}</a></div>`
        );
    }
    if (s.email) {
        lines.push(`<div><span class="sc-label">Email</span><a href="mailto:${escapeHtml(s.email)}">${escapeHtml(s.email)}</a></div>`);
    }
    const addr = [s.address, s.city, s.country, s.postal_code].filter(Boolean).join(', ');
    if (addr) {
        lines.push(`<div><span class="sc-label">Address</span>${escapeHtml(addr)}</div>`);
    }
    if (lines.length === 0) {
        body.innerHTML = '<p class="sc-muted">No contact details on file for this supplier.</p>';
    } else {
        body.innerHTML = lines.join('');
    }
}

function toggleCanvasserNoPreferredHint() {
    const el = document.getElementById('canvasserNoPreferredHint');
    if (!el || !formPageConfig.isCanvasserView) {
        return;
    }
    const show = state.selectedSuppliers.length === 0 && state.requestedItems.length > 0;
    el.hidden = !show;
}

function openCanvasserNewSupplierModal() {
    if (!formPageConfig.isCanvasserView || state.canvasserMatrixLocked) {
        return;
    }
    const modal = document.getElementById('canvasserNewSupplierModal');
    if (!modal) {
        return;
    }
    const form = document.getElementById('canvasserNewSupplierForm');
    if (form) {
        form.reset();
    }
    modal.style.display = 'flex';
}

function closeCanvasserNewSupplierModal() {
    const modal = document.getElementById('canvasserNewSupplierModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function insertSupplierIntoCatalog(supplierRow) {
    if (!supplierRow || supplierRow.supplier_id == null) {
        return;
    }
    const id = Number(supplierRow.supplier_id);
    if (!state.availableSuppliers.some((x) => Number(x.supplier_id) === id)) {
        state.availableSuppliers.push(supplierRow);
        state.availableSuppliers.sort((a, b) =>
            String(a.supplier_name || '').localeCompare(String(b.supplier_name || ''), undefined, { sensitivity: 'base' })
        );
    }
    renderSupplierDropdown();
}

async function submitCanvasserNewSupplier() {
    if (!formPageConfig.isCanvasserView || state.canvasserMatrixLocked) {
        return;
    }
    const rid = Number(formPageConfig.requestId || 0);
    if (!rid) {
        showToast('Missing request.', 'error');
        return;
    }
    const nameEl = document.getElementById('canvasserNewSupplierName');
    const name = nameEl ? nameEl.value.trim() : '';
    if (!name) {
        showToast('Supplier name is required.', 'error');
        return;
    }
    const base = canvasserApproveApiBase();
    if (!base) {
        return;
    }
    const body = new URLSearchParams();
    body.set('action', 'create_supplier');
    body.set('request_id', String(rid));
    body.set('supplier_name', name);
    body.set('contact_person', (document.getElementById('canvasserNewSupplierContact') || {}).value?.trim() || '');
    body.set('phone_number', (document.getElementById('canvasserNewSupplierPhone') || {}).value?.trim() || '');
    body.set('email', (document.getElementById('canvasserNewSupplierEmail') || {}).value?.trim() || '');
    body.set('address', (document.getElementById('canvasserNewSupplierAddress') || {}).value?.trim() || '');
    body.set('city', (document.getElementById('canvasserNewSupplierCity') || {}).value?.trim() || '');
    body.set('country', (document.getElementById('canvasserNewSupplierCountry') || {}).value?.trim() || '');
    body.set('postal_code', (document.getElementById('canvasserNewSupplierPostal') || {}).value?.trim() || '');
    try {
        const res = await fetch(base, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'include',
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Could not save supplier.', 'error');
            return;
        }
        if (data.supplier) {
            insertSupplierIntoCatalog(data.supplier);
            setSupplierPickerHighlight(data.supplier.supplier_id);
        }
        closeCanvasserNewSupplierModal();
        showToast(data.message || 'Supplier registered.');
        updateSupplierContactPanel();
    } catch {
        showToast('Network error.', 'error');
    }
}

function renderSupplierDropdown() {
    if (!supplierDropdownList || !supplierDropdown) {
        return;
    }
    if (!state.availableSuppliers.length) {
        const canRegister =
            formPageConfig.isCanvasserView && !state.canvasserMatrixLocked
                ? '<button type="button" class="supplier-catalog-empty-add" id="canvasserOpenNewSupplierFromEmpty">Register a new supplier</button>'
                : '';
        supplierDropdownList.innerHTML = `<div class="supplier-option-empty">No suppliers in the directory yet. ${canRegister}</div>`;
        const emptyBtn = document.getElementById('canvasserOpenNewSupplierFromEmpty');
        if (emptyBtn) {
            emptyBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                supplierDropdown.classList.remove('open');
                openCanvasserNewSupplierModal();
            });
        }
        return;
    }

    supplierDropdownList.innerHTML = state.availableSuppliers.map((supplier) => `
        <button type="button" class="supplier-option" data-supplier-id="${supplier.supplier_id}">
            <img src="${escapeHtml(getSupplierImageUrl(supplier.supplier_image))}" alt="" class="supplier-option-avatar" onerror="${supplierAvatarOnError}">
            <span class="supplier-option-name">${escapeHtml(supplier.supplier_name)}</span>
        </button>
    `).join('');
}

/** Room/lab first, then building; facility code is omitted (admin use only). */
function formatFacilityOptionLabel(facility) {
    const roomLab = String(facility.room || facility.laboratory || '').trim();
    const building = String(facility.building || '').trim();
    const label = roomLab && building ? `${roomLab} · ${building}` : roomLab || building || '—';
    return facility.is_new ? `${label} • New` : label;
}

function renderFacilitiesByOffice() {
    const deptId = Number(officeSelect.value || 0);
    const filtered = state.facilities.filter((facility) => Number(facility.office_id) === deptId);
    facilitySelect.innerHTML = '<option value="">Select Location</option>';

    filtered.forEach((facility) => {
        const option = document.createElement('option');
        option.value = facility.facility_id;
        option.textContent = formatFacilityOptionLabel(facility);
        if (facility.is_new) {
            option.dataset.new = '1';
        }
        facilitySelect.appendChild(option);
    });
}

function renderSupplierTable() {
    if (!supplierTable) {
        return;
    }
    const thead = supplierTable.querySelector('thead');
    const tbody = supplierTable.querySelector('tbody');

    const showSupplierActionCol = matrixRowsAreEditable();
    const emptyColspan = showSupplierActionCol ? 3 : 2;

    if (state.selectedSuppliers.length === 0) {
        thead.innerHTML =
            '<tr><th>SUPPLIER</th><th>ITEM 1</th>' + (showSupplierActionCol ? '<th>ACTION</th>' : '') + '</tr>';
        tbody.innerHTML = `<tr><td colspan="${emptyColspan}" class="empty-state">Add supplier and items to build matrix.</td></tr>`;
        toggleCanvasserNoPreferredHint();
        return;
    }

    if (state.requestedItems.length === 0) {
        thead.innerHTML =
            '<tr><th>SUPPLIER</th><th>ITEM 1</th>' + (showSupplierActionCol ? '<th>ACTION</th>' : '') + '</tr>';
        tbody.innerHTML = `<tr><td colspan="${emptyColspan}" class="empty-state">Add items to generate item columns.</td></tr>`;
        toggleCanvasserNoPreferredHint();
        return;
    }

    const headers = [
        'SUPPLIER',
        ...state.requestedItems.map((_, index) => `ITEM ${index + 1}`),
        ...(showSupplierActionCol ? ['ACTION'] : []),
    ];
    thead.innerHTML = `<tr>${headers.map((header) => `<th>${header}</th>`).join('')}</tr>`;

    tbody.innerHTML = state.selectedSuppliers.map((supplier, supplierIndex) => {
        const itemCells = state.requestedItems.map((item, itemIndex) => {
            const value = supplier.prices[itemIndex] ?? '';
            if (!matrixRowsAreEditable()) {
                const display = value !== '' && value != null ? escapeHtml(String(value)) : '—';
                return `<td class="supplier-price-cell">${display}</td>`;
            }
            return `<td><input type="number" min="0" step="0.01" class="supplier-price-input" data-supplier-index="${supplierIndex}" data-item-index="${itemIndex}" value="${escapeHtml(String(value))}" placeholder="Price"></td>`;
        }).join('');

        const actionCell = showSupplierActionCol
            ? `<td>
                <button type="button" class="remove-supplier-btn" data-supplier-index="${supplierIndex}" title="Remove supplier">
                    <i class="fas fa-times"></i>
                </button>
            </td>`
            : '';

        const avatarSrc = escapeHtml(getSupplierImageUrl(supplier.supplier_image));
        const nameInner = formPageConfig.isCanvasserView
            ? `<button type="button" class="supplier-table-contact-name-btn" data-supplier-id="${escapeHtml(
                  String(supplier.supplier_id)
              )}" aria-label="${escapeHtml(`View contact for ${supplier.supplier_name || 'supplier'}`)}"><span class="supplier-table-name">${escapeHtml(
                  supplier.supplier_name
              )}</span><span class="supplier-table-view-contact-hint" aria-hidden="true">View contact</span></button>`
            : `<span class="supplier-table-name">${escapeHtml(supplier.supplier_name)}</span>`;
        return `<tr>
            <td class="supplier-table-name-cell">
                <div class="supplier-table-supplier">
                    <img src="${avatarSrc}" alt="" class="supplier-table-avatar" width="32" height="32" decoding="async" onerror="${supplierAvatarOnError}">
                    ${nameInner}
                </div>
            </td>
            ${itemCells}
            ${actionCell}
        </tr>`;
    }).join('');

    toggleCanvasserNoPreferredHint();
}

function removeItemAt(index) {
    state.requestedItems.splice(index, 1);
    state.selectedSuppliers.forEach((supplier) => {
        const newPrices = {};
        state.requestedItems.forEach((_, newIdx) => {
            const oldIdx = newIdx >= index ? newIdx + 1 : newIdx;
            if (supplier.prices[oldIdx] !== undefined) {
                newPrices[newIdx] = supplier.prices[oldIdx];
            }
        });
        supplier.prices = newPrices;
    });
    state.hasUnsavedChanges = true;
}

async function loadBootstrapData() {
    const response = await fetch(getBootstrapApiUrl(), { credentials: 'include' });
    const data = await response.json();

    if (!data.success) {
        throw new Error(data.message || 'Failed to load requisition form data.');
    }

    state.availableItems = data.items || [];
    state.availableSuppliers = data.suppliers || [];
    state.facilities = data.facilities || [];

    const deptOptions = (data.offices || []).map((office) =>
        `<option value="${office.office_id}">${office.office_name}</option>`
    ).join('');
    officeSelect.insertAdjacentHTML('beforeend', deptOptions);

    if (data.user?.office_id) {
        state.defaultOfficeId = String(data.user.office_id);
        officeSelect.value = state.defaultOfficeId;
    }
    officeSelect.disabled = true;
    renderFacilitiesByOffice();

    renderSupplierDropdown();

    fillDatalist(itemNameSuggestions, state.availableItems.map((item) => item.item_name));
}

async function loadRequestForEdit(requestId) {
    const response = await fetch(
        `../../app/api/dean_requisition.php?action=get_request_detail&request_id=${encodeURIComponent(requestId)}`,
        { credentials: 'include' }
    );
    const data = await response.json();
    if (!data.success) {
        showToast(data.message || 'Could not load this request for editing.', 'error');
        return;
    }

    state.deanEditLocked = false;
    state.editRequestId = data.edit_request_id;
    officeSelect.value = String(data.office_id);
    renderFacilitiesByOffice();
    facilitySelect.value = String(data.facility_id);
    requestDateInput.value = data.request_date || requestDateInput.value;
    state.defaultDate = requestDateInput.value;
    if (requestMessageInput) requestMessageInput.value = data.message || '';
    showMessageCallout(data.message || '');
    if (requestPurposeInput) {
        requestPurposeInput.value = data.purpose || '';
    }

    state.requestedItems = asObjectArray(data.items).map((it) => normalizeRequestedItem(it));

    state.selectedSuppliers = asObjectArray(data.suppliers).map((s) => {
        const full = state.availableSuppliers.find((x) => Number(x.supplier_id) === Number(s.supplier_id));
        const prices = normalizeSupplierPrices(s.prices);
        return {
            supplier_id: s.supplier_id,
            supplier_name: full ? full.supplier_name : s.supplier_name,
            supplier_image: full ? (full.supplier_image || '') : (s.supplier_image || ''),
            prices
        };
    });

    const formTitle = document.getElementById('requisitionFormTitle');
    if (formTitle) {
        formTitle.textContent = 'REQUISITION FORM';
    }
    setSubmitRequisitionBtnLabel('Update & Submit Request');

    state.requisitionStatus = String((data.approval && data.approval.requisition_status) || 'pending').trim().toLowerCase();
    applyApprovalFromPayload({
        status: data.status || '',
        approval: data.approval || null,
    });
    if (data.dean_edit_locked) {
        state.deanEditLocked = true;
        applyViewOnlyMode();
        showToast(
            'This requisition is locked: the canvasser or a verifier (G.S.D., comptroller, or president) has already recorded a decision. You can review it but not change it.',
            'info'
        );
    }
    syncCanvasSectionVisibility();
    syncCanvassContinueBanner();
    renderItems();
    renderSupplierTable();
    setSupplierPickerHighlight(null);
}

async function loadRequestForView(requestId) {
    const response = await fetch(getDetailViewApiUrl(requestId), { credentials: 'include' });
    if (!response.ok) {
        showToast('Could not load this requisition.', 'error');
        return;
    }
    let data;
    try {
        data = await response.json();
    } catch {
        showToast('Invalid response from server.', 'error');
        return;
    }
    if (!data.success) {
        showToast(data.message || 'Could not load this requisition.', 'error');
        return;
    }

    state.editRequestId = null;
    if (data.office_id != null && String(data.office_id) !== '') {
        officeSelect.value = String(data.office_id);
    }
    renderFacilitiesByOffice();
    if (data.facility_id != null && String(data.facility_id) !== '') {
        facilitySelect.value = String(data.facility_id);
    }
    requestDateInput.value = data.request_date || requestDateInput.value;
    state.defaultDate = requestDateInput.value;
    if (requestMessageInput) requestMessageInput.value = data.message || '';
    showMessageCallout(data.message || '');
    if (requestPurposeInput) {
        requestPurposeInput.value = data.purpose || '';
    }

    if (data.requester_display && requesterNameInput) {
        requesterNameInput.value = data.requester_display;
    }
    if (data.requester_role != null && data.requester_role !== '' && facultyRoleInput) {
        facultyRoleInput.value = data.requester_role;
    }
    const requesterEmailInput = document.getElementById('requesterEmail');
    if (data.requester_email != null && data.requester_email !== '' && requesterEmailInput) {
        requesterEmailInput.value = data.requester_email;
    }
    const requesterContactInput = document.getElementById('requesterContact');
    if (data.requester_contact != null && data.requester_contact !== '' && requesterContactInput) {
        requesterContactInput.value = data.requester_contact;
    }

    state.requestedItems = asObjectArray(data.items).map((it) => normalizeRequestedItem(it));

    state.selectedSuppliers = asObjectArray(data.suppliers).map((s) => {
        const full = state.availableSuppliers.find((x) => Number(x.supplier_id) === Number(s.supplier_id));
        const prices = normalizeSupplierPrices(s.prices);
        return {
            supplier_id: s.supplier_id,
            supplier_name: full ? full.supplier_name : s.supplier_name,
            supplier_image: full ? (full.supplier_image || '') : (s.supplier_image || ''),
            prices,
        };
    });

    const formTitle = document.getElementById('requisitionFormTitle');
    if (formTitle) {
        formTitle.textContent = 'REQUISITION FORM';
    }

    if (data.approval && typeof data.approval === 'object') {
        applyApprovalFromPayload(data);
    } else {
        applyApprovalFromStatus(data.status);
    }
    state.deanEditLocked = Boolean(data.dean_edit_locked);
    if (formPageConfig.viewOnly) {
        applyViewOnlyMode();
    }

    renderItems();
    renderSupplierTable();
    setSupplierPickerHighlight(null);

    if (formPageConfig.isCanvasserView) {
        await initCanvasserApprovalActions(requestId);
    } else if (formPageConfig.isInventoryManagerView) {
        await initInventoryReviewActions(requestId);
    } else if (formPageConfig.isComptrollerView) {
        await initComptrollerApprovalActions(requestId);
    } else if (formPageConfig.isGsdView) {
        await initGsdApprovalActions(requestId);
    } else     if (formPageConfig.isPresidentView) {
        await initPresidentApprovalActions(requestId);
    }
    syncCanvassContinueBanner();
}

officeSelect.addEventListener('change', () => {
    state.hasUnsavedChanges = true;
    renderFacilitiesByOffice();
});

// Track dirty state for all form inputs
[facilitySelect, requestDateInput, requestMessageInput, requestPurposeInput].forEach((el) => {
    if (el) {
        el.addEventListener('input', () => {
            state.hasUnsavedChanges = true;
        });
        el.addEventListener('change', () => {
            state.hasUnsavedChanges = true;
        });
    }
});

const requesterContactField = document.getElementById('requesterContact');
if (requesterContactField) {
    requesterContactField.addEventListener('input', () => {
        const digits = requesterContactField.value.replace(/\D/g, '').slice(0, 11);
        requesterContactField.value = digits;
    });
}

if (rfAddItemBtn) {
    rfAddItemBtn.addEventListener('click', (event) => {
        event.preventDefault();
        if (state.viewOnly) {
            return;
        }
        addRequestedItemRow();
    });
}

if (requestedItemsBody) {
    requestedItemsBody.addEventListener('input', (event) => {
        const field = event.target.closest('.rf-item-field');
        if (!field || state.viewOnly) {
            return;
        }
        const index = Number(field.dataset.itemIndex);
        const fieldName = field.dataset.itemField;
        if (Number.isNaN(index) || !fieldName) {
            return;
        }
        if (fieldName === 'name') {
            if (state.requestedItems[index]) {
                state.requestedItems[index].name = String(field.value || '');
                state.hasUnsavedChanges = true;
            }
            return;
        }
        // Fast-path for simple text detail fields — no re-render needed during typing.
        if (fieldName === 'brand' || fieldName === 'model' || fieldName === 'specification' || fieldName === 'group_label') {
            if (state.requestedItems[index]) {
                state.requestedItems[index][fieldName] = String(field.value || '');
                state.hasUnsavedChanges = true;
            }
            return;
        }
        syncRequestedItemFromField(index, fieldName, field.value);
        if (fieldName === 'quantity') {
            renderSupplierTable();
        }
    });

    requestedItemsBody.addEventListener('change', (event) => {
        const field = event.target.closest('.rf-item-field');
        if (!field || state.viewOnly) {
            return;
        }
        const index = Number(field.dataset.itemIndex);
        const fieldName = field.dataset.itemField;
        if (Number.isNaN(index) || !fieldName) {
            return;
        }
        const previousItem = normalizeRequestedItem(state.requestedItems[index] || {});
        syncRequestedItemFromField(index, fieldName, field.value);
        if (fieldName === 'name') {
            const updatedItem = normalizeRequestedItem(state.requestedItems[index] || {});
            if (updatedItem.item_id !== previousItem.item_id) {
                renderItems();
                renderSupplierTable();
                return;
            }
        }
        if (fieldName === 'unit_type') {
            renderSupplierTable();
        }
    });

    requestedItemsBody.addEventListener('click', async (event) => {
        const removeBtn = event.target.closest('.rf-item-remove-btn');
        if (!removeBtn || state.viewOnly) {
            return;
        }
        const itemIndex = Number(removeBtn.dataset.itemIndex);
        if (Number.isNaN(itemIndex)) {
            return;
        }
        const confirmed = await showConfirmModal('Remove this item from the request?');
        if (!confirmed) {
            return;
        }
        removeItemAt(itemIndex);
        renderItems();
        renderSupplierTable();
        showToast('Item removed.');
    });
}

if (addSupplierBtn) {
    addSupplierBtn.addEventListener('click', (event) => {
        event.preventDefault();
        const supplierId = Number(state.selectedSupplierId || 0);
        if (!supplierId) {
            showToast('Please choose a supplier first.', 'error');
            return;
        }

        const supplier = state.availableSuppliers.find((entry) => Number(entry.supplier_id) === supplierId);
        if (!supplier) {
            showToast('Selected supplier does not exist.', 'error');
            return;
        }

        const existing = state.selectedSuppliers.some((entry) => Number(entry.supplier_id) === supplierId);
        if (existing) {
            showToast('Supplier already added.', 'error');
            return;
        }

        state.selectedSuppliers.push({
            supplier_id: supplier.supplier_id,
            supplier_name: supplier.supplier_name,
            supplier_image: supplier.supplier_image || '',
            prices: {}
        });

        renderSupplierTable();
        showToast('Supplier added.');
    });
}

if (supplierDropdownBtn && supplierDropdown) {
    supplierDropdownBtn.addEventListener('click', () => {
        supplierDropdown.classList.toggle('open');
    });
}

if (supplierDropdownList && supplierDropdown) {
    supplierDropdownList.addEventListener('click', (event) => {
        const option = event.target.closest('.supplier-option');
        if (!option) {
            return;
        }

        setSupplierPickerHighlight(option.dataset.supplierId);
        supplierDropdown.classList.remove('open');
    });
}

document.addEventListener('click', (event) => {
    if (!supplierDropdown) {
        return;
    }
    if (!supplierDropdown.contains(event.target)) {
        supplierDropdown.classList.remove('open');
    }
});

if (supplierTable) {
    supplierTable.addEventListener('input', (event) => {
        const target = event.target;
        if (!target.classList.contains('supplier-price-input')) {
            return;
        }
        const supplierIndex = Number(target.dataset.supplierIndex);
        const itemIndex = Number(target.dataset.itemIndex);
        if (Number.isNaN(supplierIndex) || Number.isNaN(itemIndex)) {
            return;
        }
        state.selectedSuppliers[supplierIndex].prices[itemIndex] = target.value;
    });

    supplierTable.addEventListener('click', async (event) => {
        const removeBtn = event.target.closest('.remove-supplier-btn');
        if (removeBtn) {
            const supplierIndex = Number(removeBtn.dataset.supplierIndex);
            if (Number.isNaN(supplierIndex)) {
                return;
            }
            const confirmed = await showConfirmModal('Remove this supplier row from the table?');
            if (!confirmed) {
                return;
            }
            state.selectedSuppliers.splice(supplierIndex, 1);
            renderSupplierTable();
            showToast('Supplier removed.');
            return;
        }

        if (!formPageConfig.isCanvasserView) {
            return;
        }
        const contactBtn = event.target.closest('.supplier-table-contact-name-btn');
        if (!contactBtn) {
            return;
        }
        const sid = contactBtn.getAttribute('data-supplier-id');
        if (!sid) {
            return;
        }
        setSupplierPickerHighlight(sid);
    });
}

if (submitRequisitionBtn) {
submitRequisitionBtn.addEventListener('click', async (event) => {
    event.preventDefault();

    if (formPageConfig.isComptrollerView) {
        showToast('Use Approve or Reject to record your decision.', 'error');
        return;
    }
    if (formPageConfig.isCanvasserView) {
        showToast('Use Done to complete canvassing.', 'error');
        return;
    }
    if (formPageConfig.isInventoryManagerView) {
        showToast('Use Accept requisition or Reject to record your decision.', 'error');
        return;
    }
    if (formPageConfig.isGsdView) {
        showToast('Use Verify or Reject to record your decision.', 'error');
        return;
    }

    if (state.viewOnly) {
        return;
    }

    if (!officeSelect.value || !facilitySelect.value || !requestDateInput.value) {
        showToast('Please set office, location, and date.', 'error');
        return;
    }
    const itemsToSubmit = serializeRequestedItemsForApi();
    if (itemsToSubmit.length === 0) {
        showToast('Please add at least one requested item with a description.', 'error');
        return;
    }
    if (!requestPurposeInput || !requestPurposeInput.value.trim()) {
        showToast('Please add the purpose of the request.', 'error');
        return;
    }
    const isEdit = state.editRequestId != null;
    const confirmed = await showConfirmModal(isEdit ? 'Submit this requisition?\nIt will no longer be editable.' : 'Submit this requisition now?');
    if (!confirmed) {
        return;
    }

    const payload = new URLSearchParams();
    if (isEdit) {
        // For existing drafts: save changes and mark as submitted in one request
        payload.append('action', 'save_draft');
        payload.append('request_id', String(state.editRequestId));
        payload.append('submission_status', 'submitted');
    } else {
        // For new forms: submit directly (sets submission_status to submitted)
        payload.append('action', 'submit');
    }
    payload.append('office_id', officeSelect.value);
    payload.append('facility_id', facilitySelect.value);
    payload.append('request_date', requestDateInput.value);
    payload.append('message', requestMessageInput ? requestMessageInput.value.trim() : '');
    payload.append('purpose', requestPurposeInput.value.trim());
    payload.append('urgent_note', '');
    payload.append('items', JSON.stringify(itemsToSubmit));
    payload.append('suppliers', JSON.stringify(state.selectedSuppliers));


    const requesterName = requesterNameInput ? requesterNameInput.value.trim() : '';
    const requesterEmail = document.getElementById('requesterEmail') ? document.getElementById('requesterEmail').value.trim() : '';
    const requesterContact = document.getElementById('requesterContact') ? document.getElementById('requesterContact').value.trim() : '';
    
    payload.append('requester_name', requesterName);
    payload.append('requester_email', requesterEmail);
    payload.append('requester_contact', requesterContact);

    try {
        const response = await fetch('../../app/api/dean_requisition.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString(),
            credentials: 'include'
        });
        const result = await response.json();
        if (!result.success) {
            showToast(result.message || 'Failed to submit requisition.', 'error');
            return;
        }

        showToast('Requisition submitted successfully.');
        window.location.href = 'dean_requisition_management.php';
    } catch (error) {
        showToast('Submission error. Please try again.', 'error');
    }
});
}


async function handleRequisitionFormExit(event) {
    if (!state.viewOnly && state.hasUnsavedChanges) {
        event.preventDefault();
        const confirmed = await showConfirmModal('Discard changes and cancel?');
        if (confirmed) {
            if (event.currentTarget && event.currentTarget.href) {
                window.location.href = event.currentTarget.href;
            } else {
                window.history.back();
            }
        }
    }
}

document.querySelectorAll('.requisition-close-btn, .rf-form-cancel-link').forEach((btn) => {
    btn.addEventListener('click', handleRequisitionFormExit);
});

renderItems();
renderSupplierTable();
syncCanvasSectionVisibility();
loadBootstrapData()
    .then(() => {
        if (formPageConfig.requestId > 0) {
            return loadRequestForView(formPageConfig.requestId);
        }
        const params = new URLSearchParams(window.location.search);
        const editId = params.get('edit');
        if (editId) {
            return loadRequestForEdit(editId);
        }
        return null;
    })
    .catch((error) => {
        showToast(error.message || 'Failed to load form data.', 'error');
    });