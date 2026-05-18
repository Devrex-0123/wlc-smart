/**
 * Dean inventory — facilities for dean's office only, then inventory per facility (read-only).
 */
(function () {
    const cfg = window.DEAN_INVENTORY_CONFIG || {};
    const api = cfg.api || '../../app/api/dean_inventory.php';
    const officeName = cfg.officeName || 'Office';
    let initialFacilityId = Number(cfg.initialFacilityId) || 0;
    if (!Number.isFinite(initialFacilityId) || initialFacilityId < 0) {
        initialFacilityId = 0;
    }

    let facilitiesData = [];
    let inventoryRows = [];
    let currentFacilityId = initialFacilityId;
    let currentFacilityLabel = '';

    const facilitiesBody = document.getElementById('deanFacilitiesBody');
    const facilitySearch = document.getElementById('deanFacilitySearch');
    const facilitiesSection = document.getElementById('deanFacilitiesSection');
    const inventorySection = document.getElementById('deanInventorySection');
    const breadcrumbEl = document.getElementById('deanInvBreadcrumb');
    const facilityHead = document.getElementById('deanInvFacilityHead');

    const tbody = document.getElementById('deanInventoryBody');
    const searchInput = document.getElementById('deanInventorySearch');
    const conditionFilter = document.getElementById('deanInventoryCondition');
    const statusFilter = document.getElementById('deanInventoryStatus');

    const modal = document.getElementById('deanInventoryDetailModal');
    const closeModalBtn = document.getElementById('deanInventoryCloseModal');
    const modalDismiss = document.getElementById('deanInventoryModalDismiss');
    const partsWrap = document.getElementById('deanInvPartsWrap');
    const partsBody = document.getElementById('deanInvPartsBody');

    const detailEls = {
        name: document.getElementById('detailName'),
        code: document.getElementById('detailCode'),
        facility: document.getElementById('detailFacility'),
        qty: document.getElementById('detailQty'),
        condition: document.getElementById('detailCondition'),
        status: document.getElementById('detailStatus'),
    };

    const labManagerSelect = document.getElementById('deanLabManagerSelect');
    const labManagerSave = document.getElementById('deanLabManagerSave');
    const labManagerStatus = document.getElementById('deanLabManagerStatus');

    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }

    function facilityLabel(f) {
        const lab = (f.laboratory || '').trim();
        const room = (f.room || '').trim();
        const b = (f.building || '').trim();
        const code = (f.code || '').trim();
        const primary = lab || room || 'Facility';
        const rest = [b, code, f.floor].filter(Boolean).join(' · ');
        return rest ? `${primary} · ${rest}` : primary;
    }

    function locationFromInventoryRow(row) {
        const lab = (row.laboratory || '').trim();
        const room = (row.room || '').trim();
        const b = (row.building || '').trim();
        const bits = [lab || room, b].filter(Boolean);
        return bits.length ? bits.join(' · ') : '—';
    }

    function renderBreadcrumb() {
        if (!breadcrumbEl) return;
        const base = 'dean_inventory.php';
        if (!currentFacilityId) {
            breadcrumbEl.innerHTML = `
                <span class="dean-bc-item">${escapeHtml(officeName)}</span>
                <span class="dean-bc-sep" aria-hidden="true">/</span>
                <span class="dean-bc-current">Facilities</span>`;
            return;
        }
        breadcrumbEl.innerHTML = `
            <a class="dean-bc-link" href="${base}">${escapeHtml(officeName)}</a>
            <span class="dean-bc-sep" aria-hidden="true">/</span>
            <span class="dean-bc-current">${escapeHtml(currentFacilityLabel || 'Facility')}</span>`;
    }

    async function fetchJson(bodyParams) {
        const fd = new FormData();
        Object.keys(bodyParams).forEach((k) => {
            fd.append(k, bodyParams[k]);
        });
        const res = await fetch(api, { method: 'POST', body: fd, credentials: 'include' });
        return res.json();
    }

    function updateFacilitiesSummary(rows) {
        const list = Array.isArray(rows) ? rows : [];
        const n = list.length;
        let parts = 0;
        let stocked = 0;
        list.forEach((f) => {
            const t = Number(f.total_inventory);
            if (Number.isFinite(t)) {
                parts += t;
                if (t > 0) {
                    stocked += 1;
                }
            }
        });
        const elC = document.getElementById('deanFacilitiesStatCount');
        const elP = document.getElementById('deanFacilitiesStatParts');
        const elS = document.getElementById('deanFacilitiesStatStocked');
        if (elC) {
            elC.textContent = String(n);
        }
        if (elP) {
            elP.textContent = String(Math.round(parts));
        }
        if (elS) {
            elS.textContent = String(stocked);
        }
    }

    async function loadFacilities() {
        if (!facilitiesBody) return;
        facilitiesBody.innerHTML =
            '<tr><td colspan="9" class="dean-inv-loading">Loading facilities…</td></tr>';
        try {
            const data = await fetchJson({ action: 'list_facilities' });
            if (!data.success || !Array.isArray(data.facilities)) {
                facilitiesData = [];
                updateFacilitiesSummary([]);
                facilitiesBody.innerHTML =
                    '<tr><td colspan="9" class="dean-inv-error">Could not load facilities.</td></tr>';
                return;
            }
            facilitiesData = data.facilities;
            renderFacilitiesTable();
        } catch {
            facilitiesData = [];
            updateFacilitiesSummary([]);
            facilitiesBody.innerHTML =
                '<tr><td colspan="9" class="dean-inv-error">Network error.</td></tr>';
        }
    }

    function filteredFacilities() {
        const q = (facilitySearch?.value || '').trim().toLowerCase();
        if (!q) return facilitiesData;
        return facilitiesData.filter((f) => {
            const hay = [
                f.building,
                f.code,
                f.floor,
                f.laboratory,
                f.room,
                f.type,
                f.office_name,
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            return hay.includes(q);
        });
    }

    function renderFacilitiesTable() {
        if (!facilitiesBody) return;
        const rows = filteredFacilities();
        updateFacilitiesSummary(rows);
        facilitiesBody.innerHTML = '';
        if (rows.length === 0) {
            const emptyMsg =
                facilitiesData.length === 0
                    ? 'No facilities for this office.'
                    : 'No facilities match your search.';
            facilitiesBody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:2rem;color:#64748b;">${emptyMsg}</td></tr>`;
            return;
        }
        rows.forEach((f, idx) => {
            const tr = document.createElement('tr');
            tr.className = 'dean-fac-row';
            tr.style.cursor = 'pointer';
            const fid = Number(f.facility_id);
            const total = f.total_inventory != null ? String(f.total_inventory) : '0';
            tr.innerHTML = `
                <td>${idx + 1}</td>
                <td>${escapeHtml(f.building || '—')}</td>
                <td>${escapeHtml(f.code || '—')}</td>
                <td>${escapeHtml(f.floor || '—')}</td>
                <td>${escapeHtml(f.laboratory || '—')}</td>
                <td>${escapeHtml(f.room || '—')}</td>
                <td>${escapeHtml(f.type || '—')}</td>
                <td>${escapeHtml(total)}</td>
                <td class="col-action">
                    <button type="button" class="action-btn view-inventory" data-facility-id="${fid}" title="View inventory in this facility" aria-label="View inventory in this facility">
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </td>
            `;
            tr.addEventListener('click', (e) => {
                if (e.target.closest('button')) {
                    return;
                }
                goToFacility(fid);
            });
            tr.querySelector('button')?.addEventListener('click', (e) => {
                e.stopPropagation();
                goToFacility(fid);
            });
            facilitiesBody.appendChild(tr);
        });
    }

    function goToFacility(fid) {
        window.location.href = `dean_inventory.php?facility_id=${encodeURIComponent(String(fid))}`;
    }

    async function loadFacilityMeta(fid) {
        const data = await fetchJson({ action: 'facility_meta', facility_id: String(fid) });
        if (data.success && data.facility) {
            currentFacilityLabel = facilityLabel(data.facility);
        } else {
            currentFacilityLabel = `Facility #${fid}`;
        }
        if (facilityHead) {
            facilityHead.textContent = `Inventory: ${currentFacilityLabel}`;
        }
    }

    async function loadInventory(fid) {
        if (!tbody) return;
        currentFacilityId = fid;
        tbody.innerHTML = '<tr><td colspan="8" class="dean-inv-loading">Loading inventory…</td></tr>';
        inventoryRows = [];
        try {
            const data = await fetchJson({ action: 'list_inventory', facility_id: String(fid) });
            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="8" class="dean-inv-error">${escapeHtml(
                    data.message || 'Error'
                )}</td></tr>`;
                return;
            }
            inventoryRows = Array.isArray(data.inventory) ? data.inventory : [];
            renderInventory();
        } catch {
            tbody.innerHTML = '<tr><td colspan="8" class="dean-inv-error">Network error.</td></tr>';
        }
    }

    function statusBadgeClass(status) {
        const s = (status || '').toLowerCase();
        if (s.includes('available')) return 'active';
        if (s.includes('use')) return 'active';
        if (s.includes('stored') || s.includes('maintenance')) return 'inactive';
        return 'inactive';
    }

    function filteredItems() {
        const q = (searchInput?.value || '').trim().toLowerCase();
        const cond = conditionFilter?.value || 'all';
        const stat = (statusFilter?.value || 'all').toLowerCase();

        return inventoryRows.filter((row) => {
            const c = (row.condition_status || '').toLowerCase();
            const st = (row.status || 'Available').toLowerCase();
            if (cond !== 'all') {
                if (cond === 'good' && !c.includes('good')) {
                    return false;
                }
                if (cond === 'fair' && !c.includes('fair')) {
                    return false;
                }
            }
            if (stat !== 'all') {
                if (stat === 'available' && st !== 'available') {
                    return false;
                }
                if (stat === 'in use' && !st.includes('use')) {
                    return false;
                }
                if (stat === 'stored' && !st.includes('stored')) {
                    return false;
                }
                if (stat === 'maintenance' && !st.includes('maintenance')) {
                    return false;
                }
            }
            if (!q) {
                return true;
            }
            const hay = `${row.name} ${row.item_name || ''} ${row.item_code || ''} ${locationFromInventoryRow(
                row
            )}`.toLowerCase();
            return hay.includes(q);
        });
    }

    function countStats(rows) {
        const total = rows.length;
        const good = rows.filter((r) =>
            (r.condition_status || '').toLowerCase().includes('good')
        ).length;
        return { total, good, attention: Math.max(0, total - good) };
    }

    function renderInventory() {
        if (!tbody) return;
        const rows = filteredItems();
        const st = countStats(inventoryRows);
        const elT = document.getElementById('deanInvStatTotal');
        const elG = document.getElementById('deanInvStatGood');
        const elA = document.getElementById('deanInvStatAttention');
        if (elT) {
            elT.textContent = String(st.total);
        }
        if (elG) {
            elG.textContent = String(st.good);
        }
        if (elA) {
            elA.textContent = String(st.attention);
        }

        tbody.innerHTML = '';
        if (rows.length === 0) {
            tbody.innerHTML =
                '<tr><td colspan="8" style="text-align:center;padding:2rem;color:#64748b;">No items match or no inventory in this facility.</td></tr>';
            return;
        }
        rows.forEach((row, idx) => {
            const tr = document.createElement('tr');
            const iid = Number(row.inventory_id);
            const partName = row.name || '—';
            const primary = row.item_name || '—';
            const qty = row.quantity != null ? String(row.quantity) : '—';
            const cnd = row.condition_status || '—';
            const stb = row.status || '—';
            tr.innerHTML = `
                <td class="col-num">${idx + 1}</td>
                <td title="${escapeHtml(partName)}">${escapeHtml(partName)}</td>
                <td>${escapeHtml(row.item_code || '—')}</td>
                <td title="${escapeHtml(primary)}">${escapeHtml(primary)}</td>
                <td class="col-qty">${escapeHtml(qty)}</td>
                <td>${escapeHtml(cnd)}</td>
                <td><span class="status-badge ${statusBadgeClass(
                    stb
                )}">${escapeHtml(stb)}</span></td>
                <td class="col-action">
                    <button type="button" class="action-btn view-inventory" data-inventory-id="${iid}" title="View item details" aria-label="View item details">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function closeModal() {
        if (modal) {
            modal.classList.remove('open');
        }
    }

    async function openDetail(row) {
        if (!row) {
            return;
        }
        const iid = row.inventory_id;
        if (partsWrap) {
            partsWrap.hidden = true;
        }
        if (partsBody) {
            partsBody.innerHTML = '';
        }
        detailEls.name.textContent = row.name || '—';
        detailEls.code.textContent = row.item_code || '—';
        detailEls.facility.textContent = currentFacilityLabel || locationFromInventoryRow(row);
        detailEls.qty.textContent = row.item_name
            ? `${row.item_name} (×${row.quantity != null ? row.quantity : '—'})`
            : '—';
        detailEls.condition.textContent = row.condition_status || '—';
        detailEls.status.textContent = row.status || '—';

        try {
            const data = await fetchJson({ action: 'get_components', inventory_id: String(iid) });
            if (data.success && Array.isArray(data.components) && data.components.length > 0) {
                if (partsBody) {
                    data.components.forEach((c) => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${escapeHtml(c.item_name || '—')}</td>
                            <td>${escapeHtml(c.code || '—')}</td>
                            <td>${escapeHtml(c.quantity != null ? String(c.quantity) : '—')}</td>
                            <td>${escapeHtml(c.condition_status || '—')}</td>
                            <td>${escapeHtml(c.status || '—')}</td>
                        `;
                        partsBody.appendChild(tr);
                    });
                }
                if (partsWrap) {
                    partsWrap.hidden = false;
                }
            }
        } catch {
            /* ignore */
        }
        if (modal) {
            modal.classList.add('open');
        }
    }

    async function loadLabManagerSettings() {
        if (!labManagerSelect) {
            return;
        }
        labManagerSelect.disabled = true;
        try {
            const data = await fetchJson({ action: 'get_lab_manager_settings' });
            if (!data.success) {
                labManagerSelect.innerHTML = '<option value="">Could not load settings</option>';
                return;
            }
            const currentId =
                data.default_lab_manager_user_id != null ? String(data.default_lab_manager_user_id) : '';
            const candidates = Array.isArray(data.lab_manager_candidates) ? data.lab_manager_candidates : [];
            labManagerSelect.innerHTML = '';
            const optNone = document.createElement('option');
            optNone.value = '';
            optNone.textContent =
                candidates.length === 0
                    ? '— No Laboratory Manager in this office —'
                    : '— None (inventory manager chooses per item) —';
            labManagerSelect.appendChild(optNone);
            candidates.forEach((c) => {
                const o = document.createElement('option');
                o.value = String(c.user_id);
                o.textContent = c.Email || `User #${c.user_id}`;
                labManagerSelect.appendChild(o);
            });
            if (currentId && Array.from(labManagerSelect.options).some((o) => o.value === currentId)) {
                labManagerSelect.value = currentId;
            } else {
                labManagerSelect.value = '';
            }
        } catch {
            labManagerSelect.innerHTML = '<option value="">Error loading</option>';
        } finally {
            labManagerSelect.disabled = false;
        }
    }

    function setLabManagerMessage(text, isError) {
        if (!labManagerStatus) {
            return;
        }
        if (!text) {
            labManagerStatus.hidden = true;
            labManagerStatus.textContent = '';
            labManagerStatus.classList.remove('is-error');
            return;
        }
        labManagerStatus.hidden = false;
        labManagerStatus.textContent = text;
        labManagerStatus.classList.toggle('is-error', Boolean(isError));
    }

    labManagerSave?.addEventListener('click', async () => {
        if (!labManagerSelect || labManagerSelect.disabled) {
            return;
        }
        setLabManagerMessage('');
        labManagerSave.disabled = true;
        try {
            const fd = new FormData();
            fd.append('action', 'set_lab_manager_settings');
            fd.append('lab_manager_user_id', labManagerSelect.value || '');
            const res = await fetch(api, { method: 'POST', body: fd, credentials: 'include' });
            const data = await res.json();
            if (data.success) {
                setLabManagerMessage(data.message || 'Saved.');
                await loadLabManagerSettings();
            } else {
                setLabManagerMessage(data.message || 'Could not save.', true);
            }
        } catch {
            setLabManagerMessage('Network error.', true);
        } finally {
            labManagerSave.disabled = false;
        }
    });

    function bindInventoryTable() {
        tbody?.addEventListener('click', (e) => {
            const btn = e.target.closest('button.view-inventory[data-inventory-id]');
            if (!btn) {
                return;
            }
            const iid = Number(btn.dataset.inventoryId);
            const row = inventoryRows.find((r) => Number(r.inventory_id) === iid);
            if (row) {
                void openDetail(row);
            }
        });
    }

    function init() {
        void loadLabManagerSettings();
        renderBreadcrumb();
        if (currentFacilityId > 0) {
            if (facilitiesSection) {
                facilitiesSection.hidden = true;
            }
            if (inventorySection) {
                inventorySection.hidden = false;
            }
            void loadFacilityMeta(currentFacilityId).then(() => {
                renderBreadcrumb();
                void loadInventory(currentFacilityId);
            });
        } else {
            if (facilitiesSection) {
                facilitiesSection.hidden = false;
            }
            if (inventorySection) {
                inventorySection.hidden = true;
            }
            void loadFacilities();
        }
        facilitySearch?.addEventListener('input', renderFacilitiesTable);
        searchInput?.addEventListener('input', renderInventory);
        conditionFilter?.addEventListener('change', renderInventory);
        statusFilter?.addEventListener('change', renderInventory);
        bindInventoryTable();
        closeModalBtn?.addEventListener('click', closeModal);
        modalDismiss?.addEventListener('click', closeModal);
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
