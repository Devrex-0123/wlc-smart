/**
 * President verifier — requisition list; View opens canvass sheet with president approve/reject.
 */
(function () {
    const API = '../../app/api/president/requests.php';

    const requestTableBody = document.getElementById('pvRequestTableBody');
    const searchInput = document.getElementById('pvReqSearch');
    const statusFilter = document.getElementById('pvReqStatus');
    const sortDropdown = document.getElementById('pvReqSort');
    const totalCount = document.getElementById('pvTotalCount');
    const pendingCount = document.getElementById('pvPendingCount');
    const ongoingCount = document.getElementById('pvOngoingCount');
    const completedCount = document.getElementById('pvCompletedCount');
    const prevReqBtn = document.getElementById('pvPrevReqBtn');
    const nextReqBtn = document.getElementById('pvNextReqBtn');
    const reqPageInfo = document.getElementById('pvReqPageInfo');

    let requests = [];
    const reqPerPage = 5;
    let currentReqPage = 1;

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

    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function statusClass(status) {
        return String(status || 'pending').toLowerCase();
    }

    function updateStats(data) {
        if (!totalCount) return;
        totalCount.textContent = data.length;
        pendingCount.textContent = data.filter((r) => r.status === 'Pending').length;
        ongoingCount.textContent = data.filter((r) => r.status === 'Ongoing').length;
        completedCount.textContent = data.filter((r) => r.status === 'Completed').length;
    }

    function filteredData() {
        const q = (searchInput && searchInput.value.trim().toLowerCase()) || '';
        const status = (statusFilter && statusFilter.value) || 'all';
        const data = requests.filter((r) => {
            const matchesStatus = status === 'all' || r.status === status;
            const reqStr = (r.requester || '').toLowerCase();
            const deptStr = (r.office || '').toLowerCase();
            const hay = `${r.id} ${(r.items || []).join(' ')} ${(r.suppliers || []).join(' ')} ${reqStr} ${deptStr}`.toLowerCase();
            const matchesQuery = q === '' || hay.includes(q);
            return matchesStatus && matchesQuery;
        });

        const sort = sortDropdown && sortDropdown.value;
        if (sort === 'entry-asc') {
            data.sort((a, b) => (a.request_id || 0) - (b.request_id || 0));
        } else if (sort === 'entry-desc') {
            data.sort((a, b) => (b.request_id || 0) - (a.request_id || 0));
        }

        return data;
    }

    function renderTable() {
        if (!requestTableBody) return;
        const data = filteredData();
        updateStats(requests);
        const totalPages = Math.max(1, Math.ceil(data.length / reqPerPage));
        if (currentReqPage > totalPages) {
            currentReqPage = totalPages;
        }

        if (!data.length) {
            requestTableBody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align:center;padding:2rem;color:#64748b;">No requests to display.</td>
                </tr>`;
            if (reqPageInfo) reqPageInfo.textContent = 'Page 1 of 1';
            if (prevReqBtn) prevReqBtn.disabled = true;
            if (nextReqBtn) nextReqBtn.disabled = true;
            return;
        }

        const start = (currentReqPage - 1) * reqPerPage;
        const pageRows = data.slice(start, start + reqPerPage);

        requestTableBody.innerHTML = pageRows
            .map((r, i) => {
                const dateStr = r.date ? new Date(r.date).toLocaleDateString() : '—';
                const rowNum = start + i + 1;
                const needsAttention = requestNeedsAttention(r);
                const rowClass = needsAttention ? ' class="request-row-highlight"' : '';
                const indicatorHtml = needsAttention ? '<span class="request-indicator" title="New or updated request"></span>' : '';
                return `
        <tr${rowClass}>
            <td>${rowNum}</td>
            <td><strong>${esc(r.id)}</strong></td>
            <td>${esc(dateStr)}</td>
            <td>${esc(r.requester || '—')}</td>
            <td>${esc(r.office || '—')}</td>
            <td>${esc((r.items || []).join(', '))}</td>
            <td><span class="status-pill ${statusClass(r.status)}">${esc(r.status || '—')}</span></td>
            <td>
                <div class="actions-cell">
                    <button type="button" class="edit pv-view-form-btn" data-request-id="${esc(String(r.request_id))}" title="View request form">
                        <i class="fas fa-file-lines"></i> View${indicatorHtml}
                    </button>
                </div>
            </td>
        </tr>`;
            })
            .join('');

        if (reqPageInfo) reqPageInfo.textContent = `Page ${currentReqPage} of ${totalPages}`;
        if (prevReqBtn) prevReqBtn.disabled = currentReqPage <= 1;
        if (nextReqBtn) nextReqBtn.disabled = currentReqPage >= totalPages;
    }

    if (requestTableBody) {
        requestTableBody.addEventListener('click', (e) => {
            const btn = e.target.closest('.pv-view-form-btn');
            if (!btn) return;
            const rid = btn.dataset.requestId;
            if (!rid) return;
            // Mark request as viewed
            markRequestViewed(parseInt(rid, 10));
            // Remove indicator if present
            const indicator = btn.querySelector('.request-indicator');
            if (indicator) {
                indicator.remove();
            }
            window.location.href =
                'dean_canvass_form.php?from=president&request_id=' +
                encodeURIComponent(String(rid));
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            currentReqPage = 1;
            renderTable();
        });
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', () => {
            currentReqPage = 1;
            renderTable();
        });
    }
    if (sortDropdown) {
        sortDropdown.addEventListener('change', () => {
            currentReqPage = 1;
            renderTable();
        });
    }

    if (prevReqBtn) {
        prevReqBtn.addEventListener('click', () => {
            if (currentReqPage > 1) {
                currentReqPage--;
                renderTable();
            }
        });
    }
    if (nextReqBtn) {
        nextReqBtn.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(filteredData().length / reqPerPage));
            if (currentReqPage < totalPages) {
                currentReqPage++;
                renderTable();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
        if (!requestTableBody) return;
        try {
            const res = await fetch(`${API}?action=list_requests`, { credentials: 'include' });
            const data = await res.json();
            if (!data.success || !Array.isArray(data.requests)) {
                requestTableBody.innerHTML = `
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2rem;color:#b91c1c;">${esc(data.message || 'Could not load requests.')}</td>
                    </tr>`;
                updateStats([]);
                return;
            }
            requests = data.requests;
            currentReqPage = 1;
            renderTable();
        } catch {
            requestTableBody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align:center;padding:2rem;color:#b91c1c;">Network error.</td>
                </tr>`;
            updateStats([]);
        }
    });
})();
