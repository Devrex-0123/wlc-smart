const statusCards = document.getElementById('statusCards');
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

function updateStats(data) {
    totalCount.textContent = data.length;
    pendingCount.textContent = data.filter((r) => r.status === 'Pending').length;
    ongoingCount.textContent = data.filter((r) => r.status === 'Ongoing').length;
    completedCount.textContent = data.filter((r) => r.status === 'Completed').length;
}

function statusClass(status) {
    return String(status || '').toLowerCase();
}

function requestIndicator(request) {
    const reqStatus = String(request.requisition_status || '').toLowerCase();
    const canvasStatus = String(request.canvas_status || '').toLowerCase();

    if (reqStatus === 'reject') {
        return { label: 'Needs Resubmission', css: 'rejected', icon: '●' };
    }
    if (reqStatus === 'accept' && canvasStatus === 'pending') {
        return { label: 'Accepted — Canvass ready', css: 'accepted', icon: '✓' };
    }
    if (reqStatus === 'accept') {
        return { label: 'Accepted — Processing', css: 'accepted', icon: '✓' };
    }
    if (request.status === 'Ongoing' && canvasStatus === 'pending') {
        return { label: 'In review — Canvass pending', css: 'ongoing', icon: '◈' };
    }
    return null;
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

function renderCards() {
    const data = filteredData();
    updateStats(requests);
    const totalPages = Math.max(1, Math.ceil(data.length / reqPerPage));
    if (currentReqPage > totalPages) {
        currentReqPage = totalPages;
    }

    if (!data.length) {
        statusCards.innerHTML = '<div class="status-card"><p class="status-card-items">No requests to display.</p></div>';
        reqPageInfo.textContent = 'Page 1 of 1';
        prevReqBtn.disabled = true;
        nextReqBtn.disabled = true;
        return;
    }

    const start = (currentReqPage - 1) * reqPerPage;
    const pageRows = data.slice(start, start + reqPerPage);

    statusCards.innerHTML = pageRows.map((r, i) => {
        const rowNum = start + i + 1;
        const itemText = Array.isArray(r.items) && r.items.length ? r.items.slice(0, 3).join(', ') : '—';
        const indicator = requestIndicator(r);
        const indicatorHtml = indicator ? `<span class="status-pill ${indicator.css}"><span class="status-icon">${indicator.icon}</span> ${indicator.label}</span>` : '';

        return `
        <article class="status-card ${indicator?.css === 'rejected' ? 'status-card--alert' : ''}">
            <div class="status-card-top">
                <div>
                    <div class="status-card-id">${rowNum}. ${r.id}</div>
                    <div class="status-card-date">${new Date(r.date).toLocaleDateString()}</div>
                    ${indicatorHtml}
                </div>
                <span class="status-pill ${statusClass(r.status)}">${r.status}</span>
            </div>
            <p class="status-card-items">${itemText}</p>
            <div class="status-card-meta">
                <span class="status-card-meta-text">${r.suppliers.length} supplier row(s)</span>
                <button type="button" class="view-progress" data-id="${r.id}" title="Open status progress">
                    <i class="fas fa-bars-progress"></i> Open
                </button>
            </div>
        </article>
    `;
    }).join('');

    reqPageInfo.textContent = `Page ${currentReqPage} of ${totalPages}`;
    prevReqBtn.disabled = currentReqPage <= 1;
    nextReqBtn.disabled = currentReqPage >= totalPages;
}

async function loadRequests() {
    try {
        const response = await fetch('../../app/api/dean_requisition.php?action=list_requests', {
            credentials: 'include',
        });
        const data = await response.json();
        if (data.success && Array.isArray(data.requests)) {
            requests = data.requests;
            currentReqPage = 1;
            renderCards();
            return;
        }
        showToast(data.message || 'Failed to load requests.', 'error');
    } catch (error) {
        showToast('Error loading requests.', 'error');
    }
}

statusCards.addEventListener('click', (e) => {
    const viewBtn = e.target.closest('.view-progress');
    if (!viewBtn) {
        return;
    }
    const record = requests.find((r) => r.id === viewBtn.dataset.id);
    if (!record) {
        return;
    }
    try {
        sessionStorage.setItem(REQ_PROGRESS_KEY + String(record.request_id), JSON.stringify(record));
    } catch (err) {
        showToast('Could not open progress view.', 'error');
        return;
    }
    window.location.href = `dean_requisition_status_progress.php?rid=${encodeURIComponent(String(record.request_id))}&from=status`;
});

searchInput.addEventListener('input', () => {
    currentReqPage = 1;
    renderCards();
});
statusFilter.addEventListener('change', () => {
    currentReqPage = 1;
    renderCards();
});
sortDropdown.addEventListener('change', () => {
    currentReqPage = 1;
    renderCards();
});

prevReqBtn.addEventListener('click', () => {
    if (currentReqPage > 1) {
        currentReqPage--;
        renderCards();
    }
});

nextReqBtn.addEventListener('click', () => {
    const totalPages = Math.max(1, Math.ceil(filteredData().length / reqPerPage));
    if (currentReqPage < totalPages) {
        currentReqPage++;
        renderCards();
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
