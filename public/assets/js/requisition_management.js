const API = '../../app/api/admin_requisition.php';

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

let requests = [];
const reqPerPage = 5;
let currentReqPage = 1;

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
        const reqStr = (r.requester || '').toLowerCase();
        const deptStr = (r.office || '').toLowerCase();
        const hay = `${r.id} ${r.items.join(' ')} ${r.suppliers.join(' ')} ${reqStr} ${deptStr}`.toLowerCase();
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
                <td colspan="9" style="text-align:center;padding:2rem;color:#64748b;">No requests to display.</td>
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
        const esc = (s) =>
            String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/"/g, '&quot;');
        const rowNum = start + i + 1;
        return `
        <tr>
            <td>${rowNum}</td>
            <td><strong>${esc(r.id)}</strong></td>
            <td>${new Date(r.date).toLocaleDateString()}</td>
            <td>${esc(r.requester || '—')}</td>
            <td>${esc(r.office || '—')}</td>
            <td>${esc(r.items.join(', '))}</td>
            <td>${r.suppliers.length}</td>
            <td><span class="status-pill ${statusClass(r.status)}">${esc(r.status)}</span></td>
            <td>
                <div class="actions-cell">
                    <button type="button" class="edit status-btn" data-id="${esc(r.id)}" title="View workflow and status">
                        <i class="fas fa-bars-progress"></i> Status
                    </button>
                    <button type="button" class="delete" data-id="${esc(r.id)}" title="Delete Request">
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
        const response = await fetch(`${API}?action=list_requests`, {
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
    const statusBtn = e.target.closest('.status-btn');
    if (statusBtn) {
        const record = requests.find((r) => r.id === statusBtn.dataset.id);
        if (record) {
            try {
                sessionStorage.setItem(REQ_PROGRESS_KEY + String(record.request_id), JSON.stringify(record));
            } catch (err) {
                showToast('Could not open progress view.', 'error');
                return;
            }
            window.location.href = `requisition_status_progress.php?rid=${encodeURIComponent(String(record.request_id))}`;
        }
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
            const response = await fetch(API, {
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
