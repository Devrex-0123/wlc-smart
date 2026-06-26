document.addEventListener('DOMContentLoaded', () => {

    // ── DOM elements ──
    const unitTableBody   = document.getElementById('unitTableBody');
    const unitSearchInput = document.getElementById('unitSearchInput');
    const prevUnitBtn     = document.getElementById('prevUnitBtn');
    const nextUnitBtn     = document.getElementById('nextUnitBtn');
    const unitPageInfo    = document.getElementById('unitPageInfo');
    const unitPageNum     = document.getElementById('unitPageNum');
    const addUnitBtn      = document.getElementById('addUnitBtn');

    // Add/Edit modal
    const unitModal       = document.getElementById('unitModal');
    const closeUnitModal  = document.getElementById('closeUnitModal');
    const unitForm        = document.getElementById('unitForm');
    const modalTitle      = document.getElementById('unitModalTitle');
    const unitIdInput     = document.getElementById('unit_id');
    const unitNameInput   = document.getElementById('unit_name');
    const unitAbbrInput   = document.getElementById('unit_abbreviation');
    const unitDescInput   = document.getElementById('unit_description');

    // Delete modal
    const deleteModal     = document.getElementById('unitDeleteModal');
    const deleteBackdrop  = document.getElementById('unitDeleteBackdrop');
    const closeDeleteBtn  = document.getElementById('closeUnitDeleteModal');
    const cancelDeleteBtn = document.getElementById('cancelUnitDeleteBtn');
    const confirmDeleteBtn= document.getElementById('confirmUnitDeleteBtn');
    const deleteUnitName  = document.getElementById('deleteUnitName');

    // Success modal
    const successModal    = document.getElementById('unitSuccessModal');
    const successBackdrop = document.getElementById('unitSuccessBackdrop');
    const successTitle    = document.getElementById('unitSuccessTitle');
    const successMessage  = document.getElementById('unitSuccessMessage');
    const successOkBtn    = document.getElementById('unitSuccessOk');

    const API = '../../app/api/unit_management.php';
    const ITEMS_PER_PAGE = 10;

    let allUnits        = [];
    let filteredUnits   = [];
    let currentPage     = 1;
    let currentDeleteId = null;

    // ── Utilities ──
    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showToast(msg, type = 'success') {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const div = document.createElement('div');
        div.className = `toast ${type}`;
        div.textContent = msg;
        container.appendChild(div);
        setTimeout(() => div.remove(), 3800);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    // ── Success modal ──
    function openSuccessModal(title, message) {
        if (successTitle)   successTitle.textContent   = title;
        if (successMessage) successMessage.textContent = message;
        successModal?.classList.add('is-open');
        successModal?.setAttribute('aria-hidden', 'false');
        successOkBtn?.focus();
    }

    function closeSuccessModal() {
        successModal?.classList.remove('is-open');
        successModal?.setAttribute('aria-hidden', 'true');
    }

    successBackdrop?.addEventListener('click', closeSuccessModal);
    successOkBtn?.addEventListener('click', closeSuccessModal);

    // ── Add/Edit modal ──
    function openUnitModal(unit = null) {
        unitForm?.reset();
        if (unit) {
            modalTitle.textContent  = 'Edit Unit';
            unitIdInput.value       = unit.unit_id;
            unitNameInput.value     = unit.unit_name;
            unitAbbrInput.value     = unit.unit_abbreviation;
            unitDescInput.value     = unit.unit_description ?? '';
        } else {
            modalTitle.textContent = 'Add Unit';
            unitIdInput.value      = '';
        }
        unitModal?.classList.add('show');
        unitNameInput?.focus();
    }

    function closeModal() {
        unitModal?.classList.remove('show');
    }

    addUnitBtn?.addEventListener('click', () => openUnitModal());
    closeUnitModal?.addEventListener('click', closeModal);

    // Close on backdrop click
    unitModal?.addEventListener('click', (e) => {
        if (e.target === unitModal) closeModal();
    });

    // ── Form submit ──
    unitForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const id   = unitIdInput.value.trim();
        const name = unitNameInput.value.trim();
        const abbr = unitAbbrInput.value.trim();
        const desc = unitDescInput.value.trim();

        if (!name) { showToast('Unit name is required.', 'error'); unitNameInput?.focus(); return; }
        if (!abbr) { showToast('Abbreviation is required.', 'error'); unitAbbrInput?.focus(); return; }

        const fd = new FormData();
        fd.append('action', id ? 'edit' : 'add');
        if (id) fd.append('unit_id', id);
        fd.append('unit_name', name);
        fd.append('unit_abbreviation', abbr);
        fd.append('unit_description', desc);

        try {
            const res  = await fetch(API, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                closeModal();
                await loadUnits();
                openSuccessModal(id ? 'Unit Updated' : 'Unit Added', data.message);
            } else {
                showToast(data.message || 'An error occurred.', 'error');
            }
        } catch {
            showToast('Network error. Please try again.', 'error');
        }
    });

    // ── Delete modal ──
    function openDeleteModal(unitId, unitName) {
        currentDeleteId = unitId;
        if (deleteUnitName) deleteUnitName.textContent = unitName;
        deleteModal?.classList.add('is-open');
        deleteModal?.setAttribute('aria-hidden', 'false');
        confirmDeleteBtn?.focus();
    }

    function closeDeleteModal() {
        deleteModal?.classList.remove('is-open');
        deleteModal?.setAttribute('aria-hidden', 'true');
        currentDeleteId = null;
    }

    deleteBackdrop?.addEventListener('click', closeDeleteModal);
    closeDeleteBtn?.addEventListener('click', closeDeleteModal);
    cancelDeleteBtn?.addEventListener('click', closeDeleteModal);

    confirmDeleteBtn?.addEventListener('click', async () => {
        if (!currentDeleteId) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('unit_id', currentDeleteId);
        try {
            const res  = await fetch(API, { method: 'POST', body: fd });
            const data = await res.json();
            closeDeleteModal();
            if (data.success) {
                await loadUnits();
                openSuccessModal('Unit Deleted', data.message);
            } else {
                showToast(data.message || 'Could not delete unit.', 'error');
            }
        } catch {
            showToast('Network error. Please try again.', 'error');
        }
    });

    // ── Table rendering ──
    function applyFilter() {
        const q = (unitSearchInput?.value ?? '').toLowerCase().trim();
        filteredUnits = q
            ? allUnits.filter(u =>
                u.unit_name.toLowerCase().includes(q) ||
                u.unit_abbreviation.toLowerCase().includes(q) ||
                (u.unit_description ?? '').toLowerCase().includes(q)
              )
            : [...allUnits];
        currentPage = 1;
        renderTable();
    }

    function renderTable() {
        if (!unitTableBody) return;

        const total      = filteredUnits.length;
        const totalPages = Math.max(1, Math.ceil(total / ITEMS_PER_PAGE));
        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        const page  = filteredUnits.slice(start, start + ITEMS_PER_PAGE);

        if (page.length === 0) {
            unitTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:50px;color:#64748b;">No units found.</td></tr>`;
        } else {
            unitTableBody.innerHTML = page.map((u, i) => `
                <tr>
                    <td>${start + i + 1}</td>
                    <td>${escapeHtml(u.unit_name)}</td>
                    <td><span class="unit-abbr-pill">${escapeHtml(u.unit_abbreviation)}</span></td>
                    <td>${u.unit_description ? escapeHtml(u.unit_description) : '<span style="color:#94a3b8">—</span>'}</td>
                    <td>${formatDate(u.created_at)}</td>
                    <td>
                        <button class="action-btn edit unit-edit-btn"
                                data-id="${escapeHtml(String(u.unit_id))}"
                                data-name="${escapeHtml(u.unit_name)}"
                                data-abbr="${escapeHtml(u.unit_abbreviation)}"
                                data-desc="${escapeHtml(u.unit_description ?? '')}"
                                title="Edit ${escapeHtml(u.unit_name)}">
                            <i class="fas fa-pen" aria-hidden="true"></i>
                        </button>
                        <button class="action-btn delete unit-delete-btn"
                                data-id="${escapeHtml(String(u.unit_id))}"
                                data-name="${escapeHtml(u.unit_name)}"
                                title="Delete ${escapeHtml(u.unit_name)}">
                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // Pagination info
        const showing = page.length === 0 ? 0 : start + 1;
        const showEnd = start + page.length;
        if (unitPageInfo) unitPageInfo.textContent = `Showing ${showing} to ${showEnd} of ${total} unit${total !== 1 ? 's' : ''}`;
        if (unitPageNum)  unitPageNum.textContent  = currentPage;
        if (prevUnitBtn)  prevUnitBtn.disabled     = currentPage <= 1;
        if (nextUnitBtn)  nextUnitBtn.disabled     = currentPage >= totalPages;
    }

    // Delegated click for action buttons
    unitTableBody?.addEventListener('click', (e) => {
        const editBtn   = e.target.closest('.unit-edit-btn');
        const deleteBtn = e.target.closest('.unit-delete-btn');

        if (editBtn) {
            openUnitModal({
                unit_id:           editBtn.dataset.id,
                unit_name:         editBtn.dataset.name,
                unit_abbreviation: editBtn.dataset.abbr,
                unit_description:  editBtn.dataset.desc,
            });
        }

        if (deleteBtn) {
            openDeleteModal(deleteBtn.dataset.id, deleteBtn.dataset.name);
        }
    });

    // Pagination
    prevUnitBtn?.addEventListener('click', () => {
        if (currentPage > 1) { currentPage--; renderTable(); }
    });
    nextUnitBtn?.addEventListener('click', () => {
        const totalPages = Math.max(1, Math.ceil(filteredUnits.length / ITEMS_PER_PAGE));
        if (currentPage < totalPages) { currentPage++; renderTable(); }
    });

    // Search
    unitSearchInput?.addEventListener('input', applyFilter);

    // ── Load data ──
    async function loadUnits() {
        if (unitTableBody) {
            unitTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:50px;color:#64748b;">Loading units...</td></tr>`;
        }
        try {
            const fd = new FormData();
            fd.append('action', 'list');
            const res  = await fetch(API, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                allUnits = data.units ?? [];
                applyFilter();
            } else {
                if (unitTableBody) {
                    unitTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:50px;color:#64748b;">Failed to load units.</td></tr>`;
                }
                showToast(data.message || 'Failed to load units.', 'error');
            }
        } catch {
            if (unitTableBody) {
                unitTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:50px;color:#64748b;">Network error. Please refresh.</td></tr>`;
            }
            showToast('Network error while loading units.', 'error');
        }
    }

    loadUnits();
});
