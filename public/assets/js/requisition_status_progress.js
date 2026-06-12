/**
 * Requisition progress view (client-side only).
 * Expects sessionStorage key imrms_req_progress_{request_id} set from the list page.
 */

const STORAGE_PREFIX = 'imrms_req_progress_';
const FORM_VIEWS_PREFIX = 'imrms_form_views_';
const ADMIN_API = '../../app/api/admin_requisition.php';
const ADMIN_LIST_API = '../../app/api/admin_requisition.php?action=list_requests';
const DEAN_LIST_API = '../../app/api/dean_requisition.php?action=list_requests';
const PRESIDENT_LIST_API = '../../app/api/president/requests.php?action=list_requests';
const COMPTROLLER_LIST_API = '../../app/api/comptroller.php?action=list_requests';
const PURCHASE_ORDER_API = '../../app/api/purchase_order.php';

let progressRecord = null;

const STEP_DEFS = [
    { icon: 'fa-file-signature', title: 'Submit Request Form', desc: 'Requester submits the requisition form.' },
    { icon: 'fa-user-check', title: 'Validate Request Form', desc: 'Inventory Manager validates and accepts/rejects the requisition form.' },
    { icon: 'fa-table-list', title: 'Canvass Form', desc: 'After acceptance, requester enters detailed components, specifications, and prices.' },
    { icon: 'fa-clipboard-check', title: 'Validate Canvass Form', desc: 'Staff review of the canvass (when your workflow enables this step).' },
    { icon: 'fa-file-circle-plus', title: 'Purchase Requisition Form', desc: 'Purchase requisition form is prepared.' },
    { icon: 'fa-list-check', title: 'Validate Purchase Requisition', desc: 'Purchase requisition form is validated.' },
    { icon: 'fa-file-invoice-dollar', title: 'Purchase Order', desc: 'Purchase order document is prepared.' },
    { icon: 'fa-check-double', title: 'Validate Purchase Order', desc: 'Purchase order is validated and approved.' },
    { icon: 'fa-truck-fast', title: 'Delivery / Receiving', desc: 'Item is for delivery, pending receipt, or already received.' },
    { icon: 'fa-circle-check', title: 'Completed', desc: 'Request cycle is completed.' },
];

function getQueryRequestId() {
    const params = new URLSearchParams(window.location.search);
    const raw = params.get('rid') || params.get('request_id');
    const n = raw ? parseInt(raw, 10) : NaN;
    return Number.isFinite(n) && n > 0 ? n : null;
}

/** Stable numeric id for links (session rows always have request_id; this handles older cache / edge cases). */
function numericRequestId(record) {
    if (!record || typeof record !== 'object') {
        return null;
    }
    const n = Number(record.request_id);
    if (Number.isFinite(n) && n > 0) {
        return n;
    }
    const id = record.id;
    if (id == null || id === '') {
        return null;
    }
    const s = String(id).trim();
    const m = s.match(/^REQ-0*(\d+)$/i);
    if (m) {
        return parseInt(m[1], 10);
    }
    const m2 = s.match(/(\d+)/);
    return m2 ? parseInt(m2[1], 10) : null;
}

function readRootConfig(root) {
    if (!root) {
        return {
            readonly: false,
            backHref: 'requisition_management.php',
            backAriaLabel: 'Back',
            deanFlow: false,
            progressFrom: '',
            viewer: '',
        };
    }
    const readonly = root.dataset.readonly === '1' || root.dataset.readonly === 'true';
    const backHref = root.dataset.backHref || 'requisition_management.php';
    const backAriaLabel = root.dataset.backAriaLabel || 'Back';
    const deanFlow = root.dataset.deanFlow === '1' || root.dataset.deanFlow === 'true';
    const progressFrom = root.dataset.progressFrom || '';
    const viewer = root.dataset.viewer || '';
    return { readonly, backHref, backAriaLabel, deanFlow, progressFrom, viewer };
}

function loadRecord(requestId) {
    if (requestId == null) return null;
    try {
        const raw = sessionStorage.getItem(STORAGE_PREFIX + String(requestId));
        if (!raw) return null;
        return JSON.parse(raw);
    } catch {
        return null;
    }
}

/** Track which forms have been viewed for a given request */
function getViewedForms(requestId) {
    if (requestId == null) return new Set();
    try {
        const raw = localStorage.getItem(FORM_VIEWS_PREFIX + String(requestId));
        return new Set(raw ? JSON.parse(raw) : []);
    } catch {
        return new Set();
    }
}

/** Mark a form as viewed */
function markFormViewed(requestId, formType) {
    if (requestId == null || !formType) return;
    const viewed = getViewedForms(requestId);
    viewed.add(formType);
    try {
        localStorage.setItem(FORM_VIEWS_PREFIX + String(requestId), JSON.stringify([...viewed]));
    } catch {
        console.warn('Could not save form view status');
    }
}

/** Check if a form has been viewed */
function isFormViewed(requestId, formType) {
    return getViewedForms(requestId).has(formType);
}

/** Create indicator for unviewed forms */
function createUnviewedIndicator() {
    const indicator = document.createElement('span');
    indicator.className = 'form-unviewed-indicator';
    indicator.title = 'New form - not yet viewed';
    return indicator;
}

function statusClass(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'pending') return 'pending';
    if (s === 'ongoing') return 'ongoing';
    if (s === 'completed') return 'completed';
    return 'pending';
}

function pctFromCurrentIndex(currentIndex) {
    if (currentIndex == null) {
        return 100;
    }
    // Fill through the current milestone (index is 0-based); matches 10 evenly spaced steps.
    const n = STEP_DEFS.length;
    return Math.max(0, Math.min(100, Math.round(((currentIndex + 1) / n) * 100)));
}

/** Inventory manager has accepted the initial requisition (requester may open canvass sheet). */
function inventoryRequisitionAccepted(record) {
    const rq = String((record && record.requisition_status) || '').trim().toLowerCase();
    return rq === 'accept';
}

/** Canvasser (or equivalent) has submitted accept/reject on the canvass step. */
function canvasReviewerDecisionRecorded(record) {
    const canvas = String((record && record.canvas_status) || '').trim().toLowerCase();
    return canvas === 'accept' || canvas === 'reject';
}

/** G.S.D. (General Services) verification on the canvass (separate from whether the canvass form itself is accepted). */
function gsdDecision(record) {
    const g = String((record && record.gsd_status) || '').trim().toLowerCase();
    if (g === '' || g === 'pending') {
        return null;
    }
    if (g === 'accept' || g === 'reject') {
        return g;
    }
    return null;
}

/** G.S.D., Comptroller, and President have all accepted the canvass verification chain. */
function canvassFullyVerified(record) {
    return (
        gsdDecision(record) === 'accept' &&
        String((record && record.comp_status) || '')
            .trim()
            .toLowerCase() === 'accept' &&
        String((record && record.pres_status) || '')
            .trim()
            .toLowerCase() === 'accept'
    );
}

/** Canvass sheet (abstract) formally accepted by the assigned canvasser. */
function canvasCanvassAccepted(record) {
    return String((record && record.canvas_status) || '').trim().toLowerCase() === 'accept';
}

function prStatusLower(record, key) {
    return String((record && record[key]) || '').trim().toLowerCase();
}

/** Inventory and President have both accepted the purchase requisition. */
function purchaseRequisitionFullyAccepted(record) {
    return (
        prStatusLower(record, 'pr_inv_status') === 'accept' &&
        prStatusLower(record, 'pr_pres_status') === 'accept'
    );
}

/** Requisition form is available once the request exists. */
function canOpenRequisitionForm(record) {
    return numericRequestId(record) != null;
}

/** Canvass opens after requisition acceptance (or staff review when canvass has a decision). */
function canOpenCanvassForm(record, config) {
    const reqNum = numericRequestId(record);
    if (reqNum == null) return false;
    const rqLc = String(record.requisition_status || '').trim().toLowerCase();
    if (rqLc === 'reject') return false;
    if (inventoryRequisitionAccepted(record)) return true;
    const invOrComp = config.viewer === 'inventory' || config.viewer === 'comptroller';
    const canvasLc = String(record.canvas_status || '').trim().toLowerCase();
    return invOrComp && (canvasLc === 'accept' || canvasLc === 'reject');
}

/** Purchase requisition opens after the canvass verification chain is complete. */
function canOpenPurchaseRequisitionForm(record) {
    return numericRequestId(record) != null && canvassFullyVerified(record);
}

/** Purchase order opens after purchase requisition approval and a PO record exists. */
function canOpenPurchaseOrderForm(record) {
    const reqNum = numericRequestId(record);
    if (reqNum == null) return false;
    const poId = Number(record.purchase_order_id || 0);
    return purchaseRequisitionFullyAccepted(record) && poId > 0;
}

function buildFormLinkRow({ label, formType, href, enabled, requestId }) {
    const unviewed =
        enabled && requestId != null && !isFormViewed(requestId, formType)
            ? '<span class="form-unviewed-indicator" title="New form - not yet viewed"></span>'
            : '';
    const disabledCls = enabled ? '' : ' rsp-form-view-disabled';
    const ariaDisabled = enabled ? '' : ' aria-disabled="true"';
    const linkHref = enabled ? href : '#';
    return `<li class="rsp-form-link-row">
                <span class="rsp-form-link-text">${label}${unviewed}</span>
                <a href="${linkHref}" class="rsp-form-view${disabledCls}" data-form-type="${formType}"${ariaDisabled}>View</a>
            </li>`;
}

async function ensurePurchaseOrderForProgress(requestId, record) {
    if (requestId == null || !record || !purchaseRequisitionFullyAccepted(record)) {
        return record;
    }
    try {
        const body = new URLSearchParams({
            action: 'ensure_for_request',
            request_id: String(requestId),
        });
        const res = await fetch(PURCHASE_ORDER_API, {
            method: 'POST',
            credentials: 'include',
            body,
        });
        const data = await res.json();
        if (!data.success || !data.data) {
            return record;
        }
        const merged = {
            ...record,
            purchase_order_id: Number(data.data.id || 0) || null,
            purchase_order_number: String(data.data.po_number || ''),
            purchase_order_status: String(data.data.status || record.purchase_order_status || 'pending'),
        };
        try {
            sessionStorage.setItem(STORAGE_PREFIX + String(requestId), JSON.stringify(merged));
        } catch {
            /* ignore */
        }
        return merged;
    } catch {
        return record;
    }
}

function purchaseOrderExists(record) {
    return Number(record?.purchase_order_id || 0) > 0;
}

function poStatusLower(record) {
    return String((record && record.purchase_order_status) || '').trim().toLowerCase();
}

/**
 * Purchase flow after canvass is fully verified (0-based STEP_DEFS indices):
 * 4 PR form · 5 validate PR · 6 generate PO · 7 president validates PO · 8 delivery.
 */
function currentIndexAfterCanvasForPurchaseFlow(record) {
    if (purchaseRequisitionFullyAccepted(record)) {
        if (purchaseOrderExists(record)) {
            if (poStatusLower(record) === 'approved') {
                return 8;
            }
            return 7;
        }
        return 6;
    }
    const inv = prStatusLower(record, 'pr_inv_status');
    const pres = prStatusLower(record, 'pr_pres_status');
    if (inv === 'accept' || pres === 'accept') {
        return 5;
    }
    return 5;
}

/**
 * Maps request/approval values to the detailed workflow stages.
 * After canvass: advances by purchase requisition verifiers (pr_inv_status / pr_pres_status).
 */
function getStepState(record) {
    const s = String((record && record.status) || '').trim();
    const rq = String((record && record.requisition_status) || '').trim().toLowerCase();

    if (s === 'Completed') {
        return { currentIndex: null, pct: 100, fillPct: 100 };
    }
    if (rq === 'reject') {
        const idx = 1;
        const pct = pctFromCurrentIndex(idx);
        return { currentIndex: idx, pct, fillPct: pct };
    }
    if (!inventoryRequisitionAccepted(record)) {
        const idx = 1;
        const pct = pctFromCurrentIndex(idx);
        return { currentIndex: idx, pct, fillPct: pct };
    }
    if (canvassFullyVerified(record)) {
        const prIdx = currentIndexAfterCanvasForPurchaseFlow(record);
        const pct = pctFromCurrentIndex(prIdx);
        return { currentIndex: prIdx, pct, fillPct: pct };
    }
    if (canvasCanvassAccepted(record)) {
        const idx = 3;
        const pct = pctFromCurrentIndex(idx);
        return { currentIndex: idx, pct, fillPct: pct };
    }
    const gsd = gsdDecision(record);
    if (gsd === 'reject') {
        const idx = 3;
        const pct = pctFromCurrentIndex(idx);
        return { currentIndex: idx, pct, fillPct: pct };
    }
    const canvasLc = String((record && record.canvas_status) || '').trim().toLowerCase();
    if (canvasLc === 'reject') {
        const idx = 3;
        const pct = pctFromCurrentIndex(idx);
        return { currentIndex: idx, pct, fillPct: pct };
    }
    if (canvasReviewerDecisionRecorded(record)) {
        const idx = 3;
        const pct = pctFromCurrentIndex(idx);
        return { currentIndex: idx, pct, fillPct: pct };
    }
    const idx = 2;
    const pct = pctFromCurrentIndex(idx);
    return { currentIndex: idx, pct, fillPct: pct };
}

async function refreshDeanProgressRecordFromApi(requestId) {
    try {
        const res = await fetch(DEAN_LIST_API, { credentials: 'include' });
        const data = await res.json();
        if (!data.success || !Array.isArray(data.requests)) {
            return null;
        }
        const fresh = data.requests.find((r) => Number(r.request_id) === Number(requestId));
        if (!fresh) {
            return null;
        }
        try {
            sessionStorage.setItem(STORAGE_PREFIX + String(requestId), JSON.stringify(fresh));
        } catch {
            /* ignore */
        }
        return fresh;
    } catch {
        return null;
    }
}

async function refreshAdminProgressRecordFromApi(requestId) {
    try {
        const res = await fetch(ADMIN_LIST_API, { credentials: 'include' });
        const data = await res.json();
        if (!data.success || !Array.isArray(data.requests)) {
            return null;
        }
        const fresh = data.requests.find((r) => Number(r.request_id) === Number(requestId));
        if (!fresh) {
            return null;
        }
        try {
            sessionStorage.setItem(STORAGE_PREFIX + String(requestId), JSON.stringify(fresh));
        } catch {
            /* ignore */
        }
        return fresh;
    } catch {
        return null;
    }
}

async function refreshPresidentProgressRecordFromApi(requestId) {
    try {
        const res = await fetch(PRESIDENT_LIST_API, { credentials: 'include' });
        const data = await res.json();
        if (!data.success || !Array.isArray(data.requests)) {
            return null;
        }
        const fresh = data.requests.find((r) => Number(r.request_id) === Number(requestId));
        if (!fresh) {
            return null;
        }
        try {
            sessionStorage.setItem(STORAGE_PREFIX + String(requestId), JSON.stringify(fresh));
        } catch {
            /* ignore */
        }
        return fresh;
    } catch {
        return null;
    }
}

async function refreshComptrollerProgressRecordFromApi(requestId) {
    try {
        const res = await fetch(COMPTROLLER_LIST_API, { credentials: 'include' });
        const data = await res.json();
        if (!data.success || !Array.isArray(data.requests)) {
            return null;
        }
        const fresh = data.requests.find((r) => Number(r.request_id) === Number(requestId));
        if (!fresh) {
            return null;
        }
        try {
            sessionStorage.setItem(STORAGE_PREFIX + String(requestId), JSON.stringify(fresh));
        } catch {
            /* ignore */
        }
        return fresh;
    } catch {
        return null;
    }
}

/** Align list/session row with live inventory decision (JOIN/list can be stale). */
async function enrichAdminProgressRecord(requestId, record) {
    if (!record || requestId == null) {
        return record;
    }
    try {
        const res = await fetch(
            `${ADMIN_API}?action=get_requisition_review&request_id=${encodeURIComponent(String(requestId))}`,
            { credentials: 'include' }
        );
        const data = await res.json();
        if (!data.success || !data.review) {
            return record;
        }
        const rs = String(data.review.requisition_status ?? '').trim();
        if (rs === '') {
            return record;
        }
        const merged = { ...record, requisition_status: rs };
        try {
            sessionStorage.setItem(STORAGE_PREFIX + String(requestId), JSON.stringify(merged));
        } catch {
            /* ignore */
        }
        return merged;
    } catch {
        return record;
    }
}

function esc(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/"/g, '&quot;');
}

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function renderEmpty(root, config) {
    root.innerHTML = `
        <div class="rsp-wrap">
            <div class="rsp-empty">
                <i class="fas fa-folder-open"></i>
                <h2>No request data</h2>
                <p>Open this page from <strong>Requisition Management</strong> using <strong>View</strong> or <strong>Status</strong> on a row.</p>
            </div>
        </div>
    `;
}

function renderApp(root, record, config) {
    progressRecord = record;
    const st = record.status || 'Pending';
    const { currentIndex, pct, fillPct } = getStepState(record);
    const sel = (v) => (st === v ? ' selected' : '');

    const horizontalSteps = STEP_DEFS.map((def, i) => {
        let phase = 'upcoming';
        if (currentIndex === null) {
            phase = 'done';
        } else if (i < currentIndex) {
            phase = 'done';
        } else if (i === currentIndex) {
            phase = 'current';
        }
        return `
            <div class="rsp-step ${phase}" data-step="${i}" data-tooltip="${esc(def.desc)}" title="${esc(def.desc)}" tabindex="0" aria-label="${esc(`${def.title}: ${def.desc}`)}">
                <div class="rsp-step-node" aria-hidden="true">
                    <i class="fas ${def.icon}"></i>
                </div>
                <div class="rsp-step-title">${esc(def.title)}</div>
            </div>
        `;
    }).join('');

    const verticalSteps = STEP_DEFS.map((def, i) => {
        let phase = 'upcoming';
        if (currentIndex === null) {
            phase = 'done';
        } else if (i < currentIndex) {
            phase = 'done';
        } else if (i === currentIndex) {
            phase = 'current';
        }
        return `
            <div class="rsp-vstep ${phase}" data-tooltip="${esc(def.desc)}" title="${esc(def.desc)}" tabindex="0" aria-label="${esc(`${def.title}: ${def.desc}`)}">
                <div class="rsp-vnode"><i class="fas ${def.icon}"></i></div>
                <div class="rsp-vbody">
                    <div class="rsp-step-title">${esc(def.title)}</div>
                </div>
            </div>
        `;
    }).join('');

    const progressSubtitle = config.deanFlow
        ? 'View-only workflow for your request (you cannot change status here).'
        : 'Track the current status and approval progress of this requisition.';

    const updateSection = config.readonly
        ? ''
        : `
                <div class="rsp-hero-section rsp-hero-update">
                    <h3><i class="fas fa-sliders"></i> Update status</h3>
                    <p class="rsp-step-desc" style="margin:0 0 0.85rem 0;">Adjust the workflow stage when needed. The progress view updates after saving.</p>
                    <div class="rsp-update-row">
                        <select id="rspStatusSelect" class="sort-dropdown" aria-label="New status">
                            <option value="Pending"${sel('Pending')}>Pending</option>
                            <option value="Ongoing"${sel('Ongoing')}>Ongoing</option>
                            <option value="Completed"${sel('Completed')}>Completed</option>
                        </select>
                        <button type="button" class="rsp-btn-save" id="rspStatusSave">Save status</button>
                    </div>
                    <p id="rspStatusMsg" class="rsp-status-msg" role="status"></p>
                </div>`;

    const progressFromParam = config.progressFrom === 'status' ? '&progress_from=status' : '';
    const reqNum = numericRequestId(record);
    let requisitionFormFrom = 'progress';
    if (config.viewer === 'president') {
        requisitionFormFrom = 'president';
    } else if (config.viewer === 'inventory') {
        requisitionFormFrom = 'inventory';
    } else if (config.viewer === 'comptroller') {
        requisitionFormFrom = 'comptroller';
    }
    const formHref =
        reqNum != null
            ? 'dean_requisition_form.php?view=1&from=' +
              encodeURIComponent(requisitionFormFrom) +
              '&request_id=' +
              encodeURIComponent(String(reqNum)) +
              progressFromParam
            : '#';
    let canvassFromParam = '';
    if (config.viewer === 'inventory') {
        canvassFromParam = '&from=inventory';
    } else if (config.viewer === 'comptroller') {
        canvassFromParam = '&from=comptroller';
    } else if (config.viewer === 'president') {
        canvassFromParam = '&from=president';
    }
    const canvassHref =
        reqNum != null
            ? 'dean_canvass_form.php?request_id=' +
              encodeURIComponent(String(reqNum)) +
              canvassFromParam +
              progressFromParam
            : '#';
    let purchaseFromParam = 'requisition';
    if (config.viewer === 'inventory') {
        purchaseFromParam = 'inventory';
    } else if (config.viewer === 'comptroller') {
        purchaseFromParam = 'comptroller';
    } else if (config.viewer === 'president') {
        purchaseFromParam = 'president';
    } else if (config.deanFlow) {
        purchaseFromParam = 'progress';
    }
    const purchaseHref =
        reqNum != null
            ? 'purchase_requisition_form.php?request_id=' +
              encodeURIComponent(String(reqNum)) +
              '&from=' +
              encodeURIComponent(purchaseFromParam)
            : '#';
    let purchaseOrderFromParam = purchaseFromParam;
    const poId = Number(record.purchase_order_id || 0);
    const purchaseOrderHref =
        reqNum != null && poId > 0
            ? 'purchase_order_form.php?id=' +
              encodeURIComponent(String(poId)) +
              '&request_id=' +
              encodeURIComponent(String(reqNum)) +
              '&from=' +
              encodeURIComponent(purchaseOrderFromParam)
            : '#';
    const requisitionFormEnabled = canOpenRequisitionForm(record);
    const canvassFormEnabled = canOpenCanvassForm(record, config);
    const purchaseFormEnabled = canOpenPurchaseRequisitionForm(record);
    const purchaseOrderFormEnabled = canOpenPurchaseOrderForm(record);

    const formRows = [
        buildFormLinkRow({
            label: 'Requisition Form',
            formType: 'requisition',
            href: formHref,
            enabled: requisitionFormEnabled,
            requestId: reqNum,
        }),
        buildFormLinkRow({
            label: 'Canvass Sheet / Abstract Of Quotation',
            formType: 'canvass',
            href: canvassHref,
            enabled: canvassFormEnabled,
            requestId: reqNum,
        }),
        buildFormLinkRow({
            label: 'Purchase Requisition Form',
            formType: 'purchase',
            href: purchaseHref,
            enabled: purchaseFormEnabled,
            requestId: reqNum,
        }),
        buildFormLinkRow({
            label: 'Purchase Order Form',
            formType: 'purchase_order',
            href: purchaseOrderHref,
            enabled: purchaseOrderFormEnabled,
            requestId: reqNum,
        }),
    ].join('');

    root.innerHTML = `
        <div class="rsp-wrap">
            <header class="rsp-hero rsp-unified">
                <div class="rsp-hero-intro">
                    <div class="rsp-hero-intro-head">
                        <div class="rsp-hero-intro-text">
                            <h2 class="rsp-page-title">Requisition Progress</h2>
                            <p class="rsp-page-subtitle">${esc(progressSubtitle)}</p>
                        </div>
                        <a href="${esc(config.backHref)}" class="rsp-back rsp-back-card" aria-label="${esc(config.backAriaLabel)}"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>
                </div>
                <div class="rsp-hero-top">
                    <h1 class="rsp-hero-title">${esc(record.id || 'Request')}</h1>
                    <div class="rsp-hero-meta">
                        <span><i class="far fa-calendar"></i> ${esc(formatDate(record.date))}</span>
                        <span><i class="fas fa-user"></i> ${esc(record.requester || '—')}</span>
                        <span><i class="fas fa-building"></i> ${esc(record.office || '—')}</span>
                    </div>
                </div>
                <div class="rsp-hero-section rsp-hero-workflow" aria-label="Request progress">
                    <div class="rsp-progress-label">
                        <span>Workflow</span>
                    </div>
                    <div class="rsp-progress-track">
                        <div class="rsp-progress-fill" id="rspProgressFill" style="width:0%;"></div>
                    </div>
                    <div class="rsp-steps" role="list">
                        ${horizontalSteps}
                    </div>
                    <div class="rsp-steps-vertical" role="list">
                        ${verticalSteps}
                    </div>
                </div>
                <div class="rsp-hero-section rsp-hero-forms">
                    <h3><i class="fas fa-file-lines"></i> Form</h3>
                    <div class="rsp-form-links-inner" aria-label="Requisition and canvass forms">
                        <ul class="rsp-form-links-list">
                            ${formRows}
                        </ul>
                    </div>
                </div>
                ${updateSection}
            </header>
        </div>
    `;

    requestAnimationFrame(() => {
        const fill = document.getElementById('rspProgressFill');
        if (fill) {
            fill.style.width = `${fillPct}%`;
        }
    });

    // Handle form view tracking
    const reqId = numericRequestId(progressRecord);
    if (reqId != null) {
        const formLinks = root.querySelectorAll('.rsp-form-view[data-form-type]');
        formLinks.forEach((link) => {
            link.addEventListener('click', (e) => {
                if (link.classList.contains('rsp-form-view-disabled')) {
                    e.preventDefault();
                    return;
                }
                const formType = link.getAttribute('data-form-type');
                if (formType) {
                    markFormViewed(reqId, formType);
                    // Remove the unviewed indicator
                    const indicator = link.parentElement.querySelector('.form-unviewed-indicator');
                    if (indicator) {
                        indicator.remove();
                    }
                }
            });
        });
    }
}

async function handleStatusSave(root) {
    const cfg = readRootConfig(root);
    if (cfg.readonly) return;
    if (!progressRecord) return;
    const msg = document.getElementById('rspStatusMsg');
    const btn = document.getElementById('rspStatusSave');
    const sel = document.getElementById('rspStatusSelect');
    if (!msg || !btn || !sel) return;

    btn.disabled = true;
    msg.textContent = '';
    msg.className = 'rsp-status-msg';

    const payload = new URLSearchParams();
    payload.append('action', 'update_status');
    payload.append('request_id', String(progressRecord.request_id));
    payload.append('status', sel.value);

    try {
        const response = await fetch(ADMIN_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString(),
            credentials: 'include',
        });
        const result = await response.json();
        if (!result.success) {
            msg.textContent = result.message || 'Update failed.';
            msg.className = 'rsp-status-msg err';
            btn.disabled = false;
            return;
        }
        progressRecord.status = sel.value;
        try {
            sessionStorage.setItem(STORAGE_PREFIX + String(progressRecord.request_id), JSON.stringify(progressRecord));
        } catch {
            /* ignore */
        }
        renderApp(root, progressRecord, cfg);
    } catch {
        msg.textContent = 'Network error.';
        msg.className = 'rsp-status-msg err';
    }
    const btn2 = document.getElementById('rspStatusSave');
    if (btn2) btn2.disabled = false;
}

let statusActionBound = false;

async function initProgressView() {
    const root = document.getElementById('rspRoot');
    if (!root) return;

    const config = readRootConfig(root);

    if (!statusActionBound && !config.readonly) {
        root.addEventListener('click', (e) => {
            if (!e.target.closest('#rspStatusSave')) return;
            e.preventDefault();
            handleStatusSave(root);
        });
        statusActionBound = true;
    }

    const rid = getQueryRequestId();
    if (rid == null) {
        renderEmpty(root, config);
        return;
    }

    let record = loadRecord(rid);
    if (config.deanFlow) {
        const fresh = await refreshDeanProgressRecordFromApi(rid);
        if (fresh) {
            record = fresh;
        }
    } else if (config.viewer === 'inventory') {
        const adminFresh = await refreshAdminProgressRecordFromApi(rid);
        if (adminFresh) {
            record = adminFresh;
        }
    } else if (config.viewer === 'comptroller') {
        const compFresh = await refreshComptrollerProgressRecordFromApi(rid);
        if (compFresh) {
            record = compFresh;
        }
    } else if (config.viewer === 'president') {
        const presidentFresh = await refreshPresidentProgressRecordFromApi(rid);
        if (presidentFresh) {
            record = presidentFresh;
        }
    }

    if (!record) {
        renderEmpty(root, config);
        return;
    }

    if (config.viewer === 'inventory' || config.viewer === 'comptroller') {
        record = await enrichAdminProgressRecord(rid, record);
    }

    record = await ensurePurchaseOrderForProgress(rid, record);

    renderApp(root, record, config);
}

document.addEventListener('DOMContentLoaded', () => {
    void initProgressView();
});
