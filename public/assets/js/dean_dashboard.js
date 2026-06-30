const deanTotalRequests = document.getElementById('deanTotalRequests');
const deanInProgressCount = document.getElementById('deanInProgressCount');
const deanCompletedCount = document.getElementById('deanCompletedCount');
const deanRejectedCount = document.getElementById('deanRejectedCount');

function isRejected(request) {
    return String(request.requisition_status || '').toLowerCase() === 'reject';
}

function statusIs(value, expected) {
    return String(value || '').trim().toLowerCase() === expected;
}

function setCount(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = String(Math.max(0, Number(value) || 0));
}

function updateSummaryCards(requests) {
    const data = Array.isArray(requests) ? requests : [];
    const rejected = data.filter(isRejected).length;
    const completed = data.filter((r) => r.status === 'Completed' && !isRejected(r)).length;
    const inProgress = data.filter((r) => !isRejected(r) && r.status !== 'Completed').length;

    if (deanTotalRequests) deanTotalRequests.textContent = data.length;
    if (deanInProgressCount) deanInProgressCount.textContent = inProgress;
    if (deanCompletedCount) deanCompletedCount.textContent = completed;
    if (deanRejectedCount) deanRejectedCount.textContent = rejected;
}

function updatePipeline(requests) {
    const data = Array.isArray(requests) ? requests.filter((r) => !isRejected(r)) : [];

    const requestSubmitted = data.length;
    const requestAwaiting = data.filter((r) => statusIs(r.requisition_status, 'pending')).length;

    const canvassSubmitted = data.filter((r) => statusIs(r.requisition_status, 'accept')).length;
    const canvassAwaiting = data.filter((r) =>
        statusIs(r.requisition_status, 'accept') && statusIs(r.canvas_status, 'pending')
    ).length;

    const prSubmitted = data.filter((r) =>
        statusIs(r.requisition_status, 'accept') && statusIs(r.canvas_status, 'accept')
    ).length;
    const prAwaiting = data.filter((r) =>
        statusIs(r.requisition_status, 'accept')
        && statusIs(r.canvas_status, 'accept')
        && (statusIs(r.pr_inv_status, 'pending') || statusIs(r.pr_pres_status, 'pending'))
    ).length;

    const poSubmitted = data.filter((r) =>
        statusIs(r.pr_inv_status, 'accept') && statusIs(r.pr_pres_status, 'accept')
    ).length;
    const poAwaiting = data.filter((r) =>
        statusIs(r.pr_inv_status, 'accept')
        && statusIs(r.pr_pres_status, 'accept')
        && (statusIs(r.comp_status, 'pending') || statusIs(r.pres_status, 'pending'))
    ).length;

    const deliveryTransit = data.filter((r) => r.status === 'Ongoing' && statusIs(r.pres_status, 'accept')).length;
    const deliveryReceiving = data.filter((r) =>
        r.status === 'Ongoing'
        && statusIs(r.pres_status, 'accept')
        && statusIs(r.purchase_order_status, 'pending')
    ).length;

    setCount('deanPipelineRequestSubmitted', requestSubmitted);
    setCount('deanPipelineRequestAwaiting', requestAwaiting);
    setCount('deanPipelineCanvassSubmitted', canvassSubmitted);
    setCount('deanPipelineCanvassAwaiting', canvassAwaiting);
    setCount('deanPipelinePrSubmitted', prSubmitted);
    setCount('deanPipelinePrAwaiting', prAwaiting);
    setCount('deanPipelinePoSubmitted', poSubmitted);
    setCount('deanPipelinePoAwaiting', poAwaiting);
    setCount('deanPipelineDeliveryTransit', deliveryTransit);
    setCount('deanPipelineDeliveryReceiving', deliveryReceiving);
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function deanFormatDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function deanRequestStage(r) {
    const rs = String(r.requisition_status || 'pending').toLowerCase();
    const cs = String(r.canvas_status || 'pending').toLowerCase();
    const pri = String(r.pr_inv_status || 'pending').toLowerCase();
    const prp = String(r.pr_pres_status || 'pending').toLowerCase();
    if (rs !== 'accept') return { tone: 'validation', badge: 'Validation' };
    if (cs !== 'accept') return { tone: 'canvass', badge: 'Canvass' };
    if (pri !== 'accept' || prp !== 'accept') return { tone: 'pr', badge: 'PR Review' };
    if (r.payment_released_at && !r.items_received_at) return { tone: 'delivery', badge: 'Delivery' };
    return { tone: 'po', badge: 'PO' };
}

function renderDeanPendingList(requests) {
    const list = document.getElementById('deanPendingList');
    if (!list) return;

    const pending = (Array.isArray(requests) ? requests : [])
        .filter((r) => !isRejected(r) && r.status !== 'Completed')
        .slice(0, 5);

    if (!pending.length) {
        list.innerHTML = '<li class="dashboard-recent__empty">No pending requests.</li>';
        return;
    }

    list.innerHTML = pending.map((r) => {
        const { tone, badge } = deanRequestStage(r);
        const title = Array.isArray(r.items) && r.items.length ? r.items[0] : 'Requisition';
        const office = String(r.office || '');
        const meta = `${r.id}${office ? ' · ' + office : ''}`;
        const href = `requisition_status_progress.php?rid=${encodeURIComponent(String(r.request_id))}`;

        return `
            <li class="dashboard-recent__item">
                <span class="dashboard-recent__icon dashboard-recent__icon--${tone}" aria-hidden="true">
                    <i class="fas fa-file-lines"></i>
                </span>
                <div class="dashboard-recent__body">
                    <p class="dashboard-recent__title">${escapeHtml(title)}</p>
                    <p class="dashboard-recent__meta">${escapeHtml(meta)}</p>
                </div>
                <a href="${href}" class="dashboard-recent__badge dashboard-recent__badge--${tone}">${escapeHtml(badge)}</a>
            </li>`;
    }).join('');
}

function renderDeanAwaitingReceipt(requests) {
    const list = document.getElementById('deanAwaitingReceiptList');
    if (!list) return;

    const awaiting = (Array.isArray(requests) ? requests : [])
        .filter((r) => r.payment_released_at && !r.items_received_at);

    if (!awaiting.length) {
        list.innerHTML = '<li class="dashboard-recent__empty">No purchase orders awaiting item receipt.</li>';
        return;
    }

    list.innerHTML = awaiting.map((r) => {
        const poNum = escapeHtml(String(r.purchase_order_number || r.id || '—'));
        const office = escapeHtml(String(r.office || '—'));
        const released = escapeHtml(deanFormatDate(r.payment_released_at));
        const href = r.request_id
            ? `requisition_status_progress.php?rid=${encodeURIComponent(String(r.request_id))}`
            : '#';

        return `
            <li class="dashboard-recent__item dashboard-awaiting-receipt__item">
                <span class="dashboard-recent__icon dashboard-recent__icon--delivery" aria-hidden="true">
                    <i class="fas fa-box-open"></i>
                </span>
                <div class="dashboard-recent__body">
                    <p class="dashboard-recent__title">${poNum}</p>
                    <p class="dashboard-recent__meta">${office} · Payment released ${released}</p>
                </div>
                <a href="${href}" class="dashboard-awaiting-receipt__view">
                    View <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </a>
            </li>`;
    }).join('');
}

async function loadDeanDashboardSummary() {
    try {
        const response = await fetch('../../app/api/dean_requisition.php?action=list_requests', {
            credentials: 'include',
        });
        const data = await response.json();
        if (data.success && Array.isArray(data.requests)) {
            updateSummaryCards(data.requests);
            updatePipeline(data.requests);
            renderDeanPendingList(data.requests);
            renderDeanAwaitingReceipt(data.requests);
            return;
        }
    } catch (error) {
        // Keep zeroed counts on failure.
    }

    updateSummaryCards([]);
    updatePipeline([]);
    renderDeanPendingList([]);
    renderDeanAwaitingReceipt([]);
}

loadDeanDashboardSummary();
