const requestTableBody = document.getElementById('requestTableBody');
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const sortDropdown = document.getElementById('sortDropdown');
const totalCount = document.getElementById('totalCount');
const pendingCount = document.getElementById('pendingCount');
const ongoingCount = document.getElementById('ongoingCount');
const completedCount = document.getElementById('completedCount');
const prevReqBtn = document.getElementById('prevReqBtn');
const nextReqBtn = document.getElementById('nextReqBtn');
const reqPageInfo = document.getElementById('reqPageInfo');

const confirmModal = document.getElementById('confirmModal');
const confirmText = document.getElementById('confirmText');
const confirmCancel = document.getElementById('confirmCancel');
const confirmOk = document.getElementById('confirmOk');

const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');

const REQ_PROGRESS_KEY = 'imrms_req_progress_';
const REQ_VIEWS_PREFIX = 'imrms_request_views_';

let requests = [];
const reqPerPage = 5;
let currentReqPage = 1;

/** Get timestamp when request was last viewed */
function getRequestViewedTime(requestId) {
    if (requestId == null) return null;
    try {
        const raw = localStorage.getItem(REQ_VIEWS_PREFIX + String(requestId));
        return raw ? parseInt(raw, 10) : null;
    } catch {
        return null;
    }
}

/** Mark a request as viewed */
function markRequestViewed(requestId) {
    if (requestId == null) return;
    try {
        localStorage.setItem(REQ_VIEWS_PREFIX + String(requestId), String(Date.now()));
    } catch {
        console.warn('Could not save request view status');
    }
}

/** Check if request is unviewed */
function isRequestUnviewed(requestId) {
    return getRequestViewedTime(requestId) === null;
}

/** Check if request was changed since last viewed */
function isRequestChanged(record) {
    if (!record || record.request_id == null) return false;
    const viewedTime = getRequestViewedTime(record.request_id);
    if (viewedTime === null) return false; // Unviewed, doesn't matter if changed
    
    // Check if request has updated_at timestamp and if it's newer than view time
    if (record.updated_at) {
        const updatedTime = new Date(record.updated_at).getTime();
        return updatedTime > viewedTime;
    }
    return false;
}

/** Check if request needs attention (unviewed or changed) */
function requestNeedsAttention(record) {
    return isRequestUnviewed(record.request_id) || isRequestChanged(record);
}

function verifierHasDecidedOnRequest(record) {
    if (!record || typeof record !== 'object') {
        return false;
    }
    for (const k of ['canvas_status', 'gsd_status', 'comp_status', 'pres_status']) {
        const v = String(record[k] || '')
            .trim()
            .toLowerCase();
        if (v === 'accept' || v === 'reject') {
            return true;
        }
    }
    return false;
}

function canEditRequest(record) {
    if (!record || typeof record !== 'object') {
        return false;
    }
    if (verifierHasDecidedOnRequest(record)) {
        return false;
    }
    if (record.status === 'Pending') {
        return true;
    }
    const rq = String(record.requisition_status || '').trim().toLowerCase();
    return record.status === 'Ongoing' && rq === 'accept';
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 2800);
}

function openConfirm(message) {
    return new Promise((resolve) => {
        confirmText.textContent = message;
        confirmModal.style.display = 'flex';

        const close = (value) => {
            confirmModal.style.display = 'none';
            confirmCancel.removeEventListener('click', onCancel);
            confirmOk.removeEventListener('click', onOk);
            resolve(value);
        };
        const onCancel = () => close(false);
        const onOk = () => close(true);

        confirmCancel.addEventListener('click', onCancel);
        confirmOk.addEventListener('click', onOk);
    });
}

function updateStats(data) {
    totalCount.textContent = data.length;
    pendingCount.textContent = data.filter((r) => r.status === 'Pending').length;
    ongoingCount.textContent = data.filter((r) => r.status === 'Ongoing').length;
    completedCount.textContent = data.filter((r) => r.status === 'Completed').length;
}

function statusClass(status) {
    return status.toLowerCase();
}

function filteredData() {
    const q = searchInput.value.trim().toLowerCase();
    const status = statusFilter.value;
    const data = requests.filter((r) => {
        const matchesStatus = status === 'all' || r.status === status;
        const hay = `${r.id} ${r.items.join(' ')} ${r.suppliers.join(' ')}`.toLowerCase();
        const matchesQuery = q === '' || hay.includes(q);
        return matchesStatus && matchesQuery;
    });

    const sort = sortDropdown.value;
    if (sort === 'entry-asc') {
        data.sort((a, b) => (a.request_id || 0) - (b.request_id || 0));
    } else if (sort === 'entry-desc') {
        data.sort((a, b) => (b.request_id || 0) - (a.request_id || 0));
    }

    return data;
}

function renderTable() {
    const data = filteredData();
    updateStats(requests);
    const totalPages = Math.max(1, Math.ceil(data.length / reqPerPage));
    if (currentReqPage > totalPages) {
        currentReqPage = totalPages;
    }

    if (!data.length) {
        requestTableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center;padding:2rem;color:#64748b;">No requests to display.</td>
            </tr>
        `;
        reqPageInfo.textContent = 'Page 1 of 1';
        prevReqBtn.disabled = true;
        nextReqBtn.disabled = true;
        return;
    }

    const start = (currentReqPage - 1) * reqPerPage;
    const pageRows = data.slice(start, start + reqPerPage);

    requestTableBody.innerHTML = pageRows.map((r, i) => {
        const editable = canEditRequest(r);
        const editTitle = editable ? 'Edit request' : 'Can edit after requisition acceptance only';
        const rowNum = start + i + 1;
        const needsAttention = requestNeedsAttention(r);
        const rowClass = needsAttention ? ' class="request-row-highlight"' : '';
        const indicatorHtml = needsAttention ? '<span class="request-indicator" title="New or updated request"></span>' : '';
        return `
        <tr${rowClass}>
            <td>${rowNum}</td>
            <td><strong>${r.id}</strong></td>
            <td>${new Date(r.date).toLocaleDateString()}</td>
            <td>${r.items.join(', ')}</td>
            <td>${r.suppliers.length}</td>
            <td><span class="status-pill ${statusClass(r.status)}">${r.status}</span></td>
            <td>
                <div class="actions-cell">
                    <button type="button" class="view-progress" data-id="${r.id}" data-request-id="${r.request_id}" title="View status and workflow (read-only)">
                        <i class="fas fa-eye"></i> View${indicatorHtml}
                    </button>
                    <button type="button" class="edit" data-id="${r.id}" title="${editTitle}" ${editable ? '' : 'disabled'}>
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button type="button" class="delete" data-id="${r.id}" title="Delete Request">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </td>
        </tr>
    `;
    }).join('');

    reqPageInfo.textContent = `Page ${currentReqPage} of ${totalPages}`;
    prevReqBtn.disabled = currentReqPage <= 1;
    nextReqBtn.disabled = currentReqPage >= totalPages;
}

async function loadRequests() {
    try {
        const response = await fetch('../../app/api/dean_requisition.php?action=list_requests', {
            credentials: 'include'
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

requestTableBody.addEventListener('click', async (e) => {
    const viewBtn = e.target.closest('.view-progress');
    if (viewBtn) {
        const record = requests.find((r) => r.id === viewBtn.dataset.id);
        if (record) {
            try {
                sessionStorage.setItem(REQ_PROGRESS_KEY + String(record.request_id), JSON.stringify(record));
            } catch (err) {
                showToast('Could not open progress view.', 'error');
                return;
            }
            // Mark request as viewed
            markRequestViewed(record.request_id);
            // Remove indicator from button if present
            const indicator = viewBtn.querySelector('.request-indicator');
            if (indicator) {
                indicator.remove();
            }
            window.location.href = `dean_requisition_status_progress.php?rid=${encodeURIComponent(String(record.request_id))}`;
        }
        return;
    }

    const editBtn = e.target.closest('.edit');
    if (editBtn) {
        const record = requests.find((r) => r.id === editBtn.dataset.id);
        if (!record) {
            return;
        }
        if (!canEditRequest(record)) {
            if (verifierHasDecidedOnRequest(record)) {
                showToast(
                    'This requisition is locked: a verifier has already recorded a decision. Open it as view-only from progress or the list.',
                    'error'
                );
            } else {
                showToast('You can edit pending or inventory-approved requisitions.', 'error');
            }
            return;
        }
        window.location.href = `dean_requisition_form.php?from=requisition&edit=${encodeURIComponent(record.request_id)}`;
        return;
    }

    const deleteBtn = e.target.closest('.delete');
    if (deleteBtn) {
        const ok = await openConfirm('Delete this requisition?');
        if (!ok) return;
        const target = requests.find((r) => r.id === deleteBtn.dataset.id);
        if (!target) return;

        const payload = new URLSearchParams();
        payload.append('action', 'delete_request');
        payload.append('request_id', String(target.request_id));

        try {
            const response = await fetch('../../app/api/dean_requisition.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString(),
                credentials: 'include'
            });
            const result = await response.json();
            if (!result.success) {
                showToast(result.message || 'Delete failed.', 'error');
                return;
            }
            requests = requests.filter((r) => r.id !== deleteBtn.dataset.id);
            renderTable();
            showToast('Request deleted.');
        } catch (error) {
            showToast('Delete request failed.', 'error');
        }
    }
});

searchInput.addEventListener('input', () => {
    currentReqPage = 1;
    renderTable();
});
statusFilter.addEventListener('change', () => {
    currentReqPage = 1;
    renderTable();
});
sortDropdown.addEventListener('change', () => {
    currentReqPage = 1;
    renderTable();
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
