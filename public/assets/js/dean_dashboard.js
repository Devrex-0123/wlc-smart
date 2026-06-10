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

async function loadDeanDashboardSummary() {
    try {
        const response = await fetch('../../app/api/dean_requisition.php?action=list_requests', {
            credentials: 'include',
        });
        const data = await response.json();
        if (data.success && Array.isArray(data.requests)) {
            updateSummaryCards(data.requests);
            updatePipeline(data.requests);
            return;
        }
    } catch (error) {
        // Keep zeroed counts on failure.
    }

    updateSummaryCards([]);
    updatePipeline([]);
}

loadDeanDashboardSummary();
