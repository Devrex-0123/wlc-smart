const API = '../../app/api/department_requisition.php';

const requestTableBody = document.getElementById('requestTableBody');
const searchInput = document.getElementById('searchInput');
const totalCount = document.getElementById('totalCount');
const pendingCount = document.getElementById('pendingCount');
const rejectedCount = document.getElementById('rejectedCount');
const completedCount = document.getElementById('completedCount');
const prevReqBtn = document.getElementById('prevReqBtn');
const nextReqBtn = document.getElementById('nextReqBtn');
const reqPageInfo = document.getElementById('reqPageInfo');
const reqPageNum = document.getElementById('reqPageNum');

const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
const pageScope = document.querySelector('[data-req-scope]')?.dataset.reqScope ?? 'all';
const filterChips = document.querySelectorAll('.req-filter-chip');

const REQ_PROGRESS_KEY = 'imrms_req_progress_';
const REQ_VIEWS_PREFIX = 'dept_request_views_';

let requests = [];
const reqPerPage = 5;
let currentReqPage = 1;
let activeStatusFilter = 'all';

function getRequestViewedTime(requestId) {
    if (requestId == null) return null;
    try {
        const raw = localStorage.getItem(REQ_VIEWS_PREFIX + String(requestId));
        return raw ? parseInt(raw, 10) : null;
    } catch {
        return null;
    }
}

function markRequestViewed(requestId) {
    if (requestId == null) return;
    try {
        localStorage.setItem(REQ_VIEWS_PREFIX + String(requestId), String(Date.now()));
    } catch {
        console.warn('Could not save request view status');
    }
}

function isRequestUnviewed(requestId) {
    return getRequestViewedTime(requestId) === null;
}

function isRequestChanged(record) {
    if (!record || record.request_id == null) return false;
    const viewedTime = getRequestViewedTime(record.request_id);
    if (viewedTime === null) return false;
    if (record.updated_at) {
        const updatedTime = new Date(record.updated_at).getTime();
        return updatedTime > viewedTime;
    }
    return false;
}

function requestNeedsAttention(record) {
    return isRequestUnviewed(record.request_id) || isRequestChanged(record);
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 2800);
}

function isRejected(request) {
    return String(request?.requisition_status || '').toLowerCase() === 'reject';
}

/** Awaiting inventory manager validation (not yet accepted). */
function isAwaitingInventoryReview(request) {
    const rq = String(request?.requisition_status || 'pending').trim().toLowerCase();
    return rq === 'pending' || rq === '';
}

function isPendingRequisition(request) {
    return isAwaitingInventoryReview(request);
}

function matchesPageScope(request) {
    if (pageScope === 'management') {
        return isAwaitingInventoryReview(request);
    }
    if (pageScope === 'workflow') {
        return !isAwaitingInventoryReview(request);
    }
    return true;
}

function matchesStatusFilter(request, filter) {
    if (filter === 'all') return true;
    if (filter === 'ongoing') return request.status === 'Ongoing' && !isRejected(request);
    if (filter === 'rejected') return isRejected(request);
    if (filter === 'completed') return request.status === 'Completed' && !isRejected(request);
    return true;
}

function setActiveFilterChip(filter) {
    activeStatusFilter = filter;
    filterChips.forEach((chip) => {
        const isActive = chip.dataset.filter === filter;
        chip.classList.toggle('is-active', isActive);
        chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

function updateStats(data) {
    if (totalCount) totalCount.textContent = data.length;
    if (pendingCount) {
        pendingCount.textContent = pageScope === 'management'
            ? data.filter(isAwaitingInventoryReview).length
            : data.filter((r) => r.status === 'Pending' && !isRejected(r)).length;
    }
    if (rejectedCount) rejectedCount.textContent = data.filter(isRejected).length;
    if (completedCount) {
        completedCount.textContent = data.filter((r) => r.status === 'Completed' && !isRejected(r)).length;
    }
}

function statusClass(status) {
    return status.toLowerCase();
}

function formatTablePageInfo(total, page, perPage) {
    if (total <= 0) return 'Showing 0 to 0 of 0 entries';
    const start = (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);
    const noun = total === 1 ? 'entry' : 'entries';
    return `Showing ${start} to ${end} of ${total} ${noun}`;
}

function updatePaginationUI(totalRecords, totalPages) {
    if (reqPageInfo) {
        reqPageInfo.textContent = formatTablePageInfo(totalRecords, currentReqPage, reqPerPage);
    }
    if (reqPageNum) {
        reqPageNum.textContent = String(currentReqPage);
    }
    prevReqBtn.disabled = currentReqPage <= 1 || totalRecords === 0;
    nextReqBtn.disabled = currentReqPage >= totalPages || totalRecords === 0;
}

function filteredData() {
    const q = (searchInput?.value ?? '').trim().toLowerCase();
    return requests.filter((r) => {
        if (!matchesPageScope(r)) return false;
        if (filterChips.length && !matchesStatusFilter(r, activeStatusFilter)) return false;
        const reqStr = (r.requester || '').toLowerCase();
        const deptStr = (r.office || '').toLowerCase();
        const items = Array.isArray(r.items) ? r.items : [];
        const suppliers = Array.isArray(r.suppliers) ? r.suppliers : [];
        const hay = `${r.id} ${items.join(' ')} ${suppliers.join(' ')} ${reqStr} ${deptStr}`.toLowerCase();
        return q === '' || hay.includes(q);
    });
}

function escHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/"/g, '&quot;');
}

function buildRequestRowHtml(record, rowNum) {
    const needsAttention = requestNeedsAttention(record);
    const rowClass = needsAttention ? ' class="request-row-highlight"' : '';
    const indicatorHtml = needsAttention ? '<span class="request-indicator" title="New or updated request"></span>' : '';
    const items = Array.isArray(record.items) ? record.items : [];
    return `
        <tr${rowClass}>
            <td>${rowNum}</td>
            <td class="req-requisition-cell">
                <div class="req-requisition-stack">
                    <span class="req-requisition-ref">${escHtml(record.id)}</span>
                    <span class="req-requisition-item">${escHtml(items.join(', ') || '—')}</span>
                    <span class="req-requisition-dept">${escHtml(record.office || '—')}</span>
                </div>
            </td>
            <td>${escHtml(record.requester || '—')}</td>
            <td><span class="status-pill ${statusClass(record.status)}">${escHtml(record.status)}</span></td>
            <td>${new Date(record.date).toLocaleDateString()}</td>
            <td>
                <div class="actions-cell">
                    ${pageScope === 'management' ? `
                    <button type="button" class="edit status-btn view-btn" data-id="${escHtml(record.id)}" data-request-id="${record.request_id}" title="View requisition">
                        <i class="fas fa-eye"></i> View${indicatorHtml}
                    </button>` : `
                    <button type="button" class="edit status-btn" data-id="${escHtml(record.id)}" data-request-id="${record.request_id}" title="View workflow and status">
                        <i class="fas fa-bars-progress"></i> Status${indicatorHtml}
                    </button>`}
                </div>
            </td>
        </tr>
    `;
}

function buildEmptyRowHtml() {
    return '<tr class="req-table-empty-row" aria-hidden="true"><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
}

function renderTable() {
    const data = filteredData();
    const statsSource = pageScope === 'management' ? requests.filter(matchesPageScope) : requests;
    updateStats(statsSource);
    const totalPages = Math.max(1, Math.ceil(data.length / reqPerPage));
    if (currentReqPage > totalPages) {
        currentReqPage = totalPages;
    }

    const start = (currentReqPage - 1) * reqPerPage;
    const pageRows = data.slice(start, start + reqPerPage);
    const rowsHtml = [];

    for (let i = 0; i < reqPerPage; i++) {
        if (pageRows[i]) {
            rowsHtml.push(buildRequestRowHtml(pageRows[i], start + i + 1));
        } else {
            rowsHtml.push(buildEmptyRowHtml());
        }
    }

    requestTableBody.innerHTML = rowsHtml.join('');
    requestTableBody.classList.toggle('is-all-placeholder', data.length === 0);
    updatePaginationUI(data.length, totalPages);
}

function progressHref(requestId) {
    const from = pageScope === 'workflow' ? 'workflow' : 'management';
    return `department_requisition_status_progress.php?rid=${encodeURIComponent(String(requestId))}&from=${from}`;
}

async function loadRequests() {
    try {
        const response = await fetch(`${API}?action=list_requests`, {
            credentials: 'include',
        });
        const data = await response.json();
        if (data.success && Array.isArray(data.requests)) {
            requests = data.requests;
            currentReqPage = 1;
            renderTable();
            return;
        }
        showToast(data.message || 'Failed to load requests.', 'error');
    } catch (error) {
        showToast('Error loading requests.', 'error');
    }
}

requestTableBody.addEventListener('click', (e) => {
    const statusBtn = e.target.closest('.status-btn');
    if (!statusBtn) return;

    const record = requests.find((r) => r.id === statusBtn.dataset.id);
    if (!record) return;

    try {
        sessionStorage.setItem(REQ_PROGRESS_KEY + String(record.request_id), JSON.stringify(record));
    } catch (err) {
        showToast('Could not open progress view.', 'error');
        return;
    }

    markRequestViewed(record.request_id);
    const indicator = statusBtn.querySelector('.request-indicator');
    if (indicator) {
        indicator.remove();
    }
    window.location.href = progressHref(record.request_id);
});

if (searchInput) {
    searchInput.addEventListener('input', () => {
        currentReqPage = 1;
        renderTable();
    });
}

filterChips.forEach((chip) => {
    chip.addEventListener('click', () => {
        const nextFilter = chip.dataset.filter || 'all';
        if (nextFilter === activeStatusFilter) return;
        setActiveFilterChip(nextFilter);
        currentReqPage = 1;
        renderTable();
    });
});

prevReqBtn.addEventListener('click', () => {
    if (currentReqPage > 1) {
        currentReqPage--;
        renderTable();
    }
});

nextReqBtn.addEventListener('click', () => {
    const totalPages = Math.max(1, Math.ceil(filteredData().length / reqPerPage));
    if (currentReqPage < totalPages) {
        currentReqPage++;
        renderTable();
    }
});

mobileMenuBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    sidebar.classList.toggle('open');
});

document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});

loadRequests();
