// ========== INVENTORY HIERARCHY NAVIGATION ==========
// This file handles the office -> facility -> inventory hierarchical view

document.addEventListener('DOMContentLoaded', () => {

    // DOM Elements
    const officesView = document.getElementById('officesView');
    const facilitiesView = document.getElementById('facilitiesView');
    const inventoryView = document.getElementById('inventoryView');
    const officeTableBody = document.getElementById('officeTableBody');
    const facilityTableBody = document.getElementById('facilityTableBody');
    const breadcrumbHome = document.getElementById('breadcrumb-home');
    const breadcrumbFacility = document.getElementById('breadcrumb-facility');
    const breadcrumbFacilityText = document.getElementById('breadcrumb-facility-text');
    const breadcrumbInventory = document.getElementById('breadcrumb-inventory');
    const facilityViewTitle = document.getElementById('facilityViewTitle');

    function setBreadcrumb(el, display) { if (el) el.style.display = display; }
    function setBreadcrumbText(el, text) { if (el) el.textContent = text; }

    function escHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function filterComponentsForTableSubrows(components) {
        if (!Array.isArray(components) || components.length === 0) return [];
        const ids = components.map((c) => Number(c.component_id)).filter((n) => Number.isFinite(n));
        if (ids.length === 0) return components;
        const minId = Math.min(...ids);
        return components.filter((c) => Number(c.component_id) !== minId);
    }

    // State variables
    let currentOfficeId = null;
    let currentOfficeName = null;
    let currentFacilityId = null;
    let suppressUrlStateWrite = true;

    // Offices list (server-paginated)
    const DEPARTMENTS_PER_PAGE = 5;
    let officesData = [];
    let officePage = 1;
    let officeTotalPages = 1;
    let officeTotal = 0;
    let currentSearch = '';
    let currentSort = 'total-desc';

    // Facilities list (client-side paginated)
    const FACILITIES_PER_PAGE = 5;
    let facilitiesData = [];
    let facilityPage = 1;
    let facilityTotalPages = 1;

    // Inventory list (client-side paginated)
    const INVENTORY_PER_PAGE = 5;
    let inventoryData = []; // [{ inv, parts: [] }, ...]
    let inventoryPage = 1;
    let inventoryTotalPages = 1;

    function updateUrlState(mode = 'replace') {
        if (suppressUrlStateWrite) return;
        const params = new URLSearchParams(window.location.search);
        let view = 'offices';
        if (inventoryView.style.display === 'block') {
            view = 'inventory';
        } else if (facilitiesView.style.display === 'block') {
            view = 'facilities';
        }
        params.set('view', view);
        if (currentOfficeId) params.set('dept_id', String(currentOfficeId));
        else params.delete('dept_id');
        if (currentOfficeName) params.set('dept_name', String(currentOfficeName));
        else params.delete('dept_name');
        if (currentFacilityId) params.set('facility_id', String(currentFacilityId));
        else params.delete('facility_id');
        if (currentSearch) params.set('q', String(currentSearch));
        else params.delete('q');
        if (currentSort) params.set('sort', String(currentSort));
        else params.delete('sort');
        if (view === 'offices') {
            if (officePage > 1) params.set('dept_page', String(officePage));
            else params.delete('dept_page');
        } else {
            params.delete('dept_page');
        }
        const next = `${window.location.pathname}?${params.toString()}`;
        if (mode === 'push') {
            window.history.pushState({ imrmsHierarchy: true }, '', next);
        } else {
            window.history.replaceState({ imrmsHierarchy: true }, '', next);
        }
    }

    function readUrlState() {
        const params = new URLSearchParams(window.location.search);
        return {
            view: params.get('view') || 'offices',
            deptId: params.get('dept_id') || '',
            deptName: params.get('dept_name') || '',
            facilityId: params.get('facility_id') || '',
            q: params.get('q') || '',
            sort: params.get('sort') || 'total-desc',
            deptPage: Math.max(1, parseInt(params.get('dept_page') || '1', 10) || 1),
        };
    }

    // ============= INITIALIZATION =============
    function init() {
        const state = readUrlState();
        currentSearch = state.q || '';
        currentSort = state.sort || 'total-desc';
        officePage = state.deptPage;

        // Apply URL view immediately to avoid flash of default Offices view.
        if (state.view === 'facilities' || state.view === 'inventory') {
            officesView.style.display = 'none';
            facilitiesView.style.display = 'block';
            inventoryView.style.display = 'none';
            setBreadcrumb(breadcrumbFacility, 'block');
            setBreadcrumbText(breadcrumbFacilityText, state.deptName || 'Office');
            if (state.view === 'inventory') {
                setBreadcrumb(breadcrumbInventory, 'block');
            } else {
                setBreadcrumb(breadcrumbInventory, 'none');
            }
        } else {
            officesView.style.display = 'block';
            facilitiesView.style.display = 'none';
            inventoryView.style.display = 'none';
            setBreadcrumb(breadcrumbFacility, 'none');
            setBreadcrumb(breadcrumbInventory, 'none');
        }

        setupFilterControls();
        setupOfficePagination();
        setupFacilityPagination();
        loadOffices().then(async () => {
            if (state.view === 'facilities' && state.deptId) {
                const matched = officesData.find((d) => String(d.office_id) === String(state.deptId));
                const deptName = matched ? matched.office_name : (state.deptName || 'Office');
                await showFacilitiesView(state.deptId, deptName);
                suppressUrlStateWrite = false;
                updateUrlState('replace');
                return;
            }
            if (
                state.view === 'inventory' &&
                state.deptId &&
                state.facilityId
            ) {
                const matched = officesData.find((d) => String(d.office_id) === String(state.deptId));
                const deptName = matched ? matched.office_name : (state.deptName || 'Office');
                await showFacilitiesView(state.deptId, deptName);
                await viewFacilityInventory(state.facilityId, 'Current Facility');
            }
            suppressUrlStateWrite = false;
            updateUrlState('replace');
        });
        setupBreadcrumbNavigation();
        window.addEventListener('popstate', () => {
            applyStateFromUrl(true);
        });
    }

    async function applyStateFromUrl(fromPop = false) {
        const state = readUrlState();
        const prevSuppress = suppressUrlStateWrite;
        suppressUrlStateWrite = true;
        try {
            if (state.view === 'inventory' && state.deptId && state.facilityId) {
                const matched = officesData.find((d) => String(d.office_id) === String(state.deptId));
                const deptName = matched ? matched.office_name : (state.deptName || 'Office');
                await showFacilitiesView(state.deptId, deptName, { historyMode: fromPop ? 'none' : 'replace' });
                await viewFacilityInventory(state.facilityId, 'Current Facility', { historyMode: fromPop ? 'none' : 'replace' });
                return;
            }
            if (state.view === 'facilities' && state.deptId) {
                const matched = officesData.find((d) => String(d.office_id) === String(state.deptId));
                const deptName = matched ? matched.office_name : (state.deptName || 'Office');
                await showFacilitiesView(state.deptId, deptName, { historyMode: fromPop ? 'none' : 'replace' });
                return;
            }
            currentSearch = state.q || '';
            currentSort = state.sort || 'total-desc';
            const searchInputPop = document.getElementById('officeSearch');
            const sortBtnPop = document.getElementById('officeSortBtn');
            if (searchInputPop) searchInputPop.value = currentSearch;
            if (sortBtnPop) {
                let labelPop = 'Sort: TOTAL ▼';
                switch (currentSort) {
                    case 'name-asc': labelPop = 'Sort: Name ▲'; break;
                    case 'name-desc': labelPop = 'Sort: Name ▼'; break;
                    case 'total-desc': labelPop = 'Sort: TOTAL ▼'; break;
                    case 'total-asc': labelPop = 'Sort: TOTAL ▲'; break;
                    default: break;
                }
                sortBtnPop.textContent = labelPop;
                sortBtnPop.dataset.sort = currentSort;
            }
            showOfficesView({
                historyMode: fromPop ? 'none' : 'replace',
                resetPage: false,
                page: state.deptPage,
            });
        } finally {
            suppressUrlStateWrite = prevSuppress;
            if (!fromPop) {
                updateUrlState('replace');
            }
        }
    }

    // Setup search and sort controls
    function setupFilterControls() {
        const searchInput = document.getElementById('officeSearch');
        const searchBtn = document.getElementById('officeSearchBtn');
        const sortBtn = document.getElementById('officeSortBtn');

        if (!searchInput || !searchBtn || !sortBtn) return;
        searchInput.value = currentSearch;

        // Trigger search when user presses Enter
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                currentSearch = searchInput.value.trim();
                loadOffices(1);
            }
        });

        // Trigger search on button click
        searchBtn.addEventListener('click', () => {
            currentSearch = searchInput.value.trim();
            loadOffices(1);
        });

        // Cycle sort options on click
        const sortOptions = ['name-asc','name-desc','total-desc','total-asc'];
        sortBtn.addEventListener('click', () => {
            const idx = sortOptions.indexOf(currentSort);
            const next = sortOptions[(idx + 1) % sortOptions.length];
            currentSort = next;
            // Update button label
            let label = 'Sort';
            switch(currentSort){
                case 'name-asc': label = 'Sort: Name ▲'; break;
                case 'name-desc': label = 'Sort: Name ▼'; break;
                case 'total-desc': label = 'Sort: TOTAL ▼'; break;
                case 'total-asc': label = 'Sort: TOTAL ▲'; break;
            }
            sortBtn.textContent = label;
            sortBtn.dataset.sort = currentSort;
            loadOffices(1);
        });
        // Initialize sort label from persisted value.
        let label = 'Sort';
        switch(currentSort){
            case 'name-asc': label = 'Sort: Name ▲'; break;
            case 'name-desc': label = 'Sort: Name ▼'; break;
            case 'total-desc': label = 'Sort: TOTAL ▼'; break;
            case 'total-asc': label = 'Sort: TOTAL ▲'; break;
        }
        sortBtn.textContent = label;
        sortBtn.dataset.sort = currentSort;
    }

    function updateOfficePagination() {
        const prev = document.getElementById('deptPrevPageBtn');
        const next = document.getElementById('deptNextPageBtn');
        const info = document.getElementById('deptPageInfo');
        const pageNum = document.getElementById('deptPageNum');
        if (!prev || !next) return;
        prev.disabled = officePage <= 1;
        next.disabled = officePage >= officeTotalPages;
        if (pageNum) pageNum.textContent = officePage;
        if (info) {
            const from = officeTotal === 0 ? 0 : (officePage - 1) * DEPARTMENTS_PER_PAGE + 1;
            const to = Math.min(officePage * DEPARTMENTS_PER_PAGE, officeTotal);
            info.textContent = `Showing ${from} to ${to} of ${officeTotal} offices`;
        }
    }

    function setupOfficePagination() {
        const prev = document.getElementById('deptPrevPageBtn');
        const next = document.getElementById('deptNextPageBtn');
        if (!prev || !next) return;
        prev.addEventListener('click', () => {
            if (officePage > 1) loadOffices(officePage - 1);
        });
        next.addEventListener('click', () => {
            if (officePage < officeTotalPages) loadOffices(officePage + 1);
        });
    }

    // Render current page (search/sort/paging from server)
    function renderOffices(){
        officeTableBody.innerHTML = '';
        const list = Array.isArray(officesData) ? officesData : [];
        const rowBase = (officePage - 1) * DEPARTMENTS_PER_PAGE;

        if(list.length === 0){
            officeTableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:50px;color:#64748b;">No offices found.</td></tr>`;
            updateOfficePagination();
            updateUrlState('replace');
            return;
        }

        list.forEach((dept, idx) => {
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            tr.className = 'office-row';
            const total = Number(dept.lab_count || 0) + Number(dept.room_count || 0);
            tr.innerHTML = `
                <td>${rowBase + idx + 1}</td>
                <td><strong>${dept.office_name}</strong></td>
                <td><span class="stat-badge stat-badge--labs">${dept.lab_count}</span></td>
                <td><span class="stat-badge stat-badge--rooms">${dept.room_count}</span></td>
                <td><span class="stat-badge stat-badge--total">${total}</span></td>
                <td><span class="stat-badge stat-badge--inventory">${dept.total_inventory}</span></td>
                <td>
                    <button class="action-btn view-facilities" data-dept-id="${dept.office_id}" data-dept-name="${dept.office_name}" title="View Rooms & Labs">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </td>
            `;
            tr.addEventListener('click', (e) => {
                if (!e.target.closest('.action-btn')) {
                    viewOfficeFacilities(dept.office_id, dept.office_name);
                }
            });
            officeTableBody.appendChild(tr);
        });
        addSpacerRows(officeTableBody, 7, list.length, DEPARTMENTS_PER_PAGE);
        updateOfficePagination();
        updateUrlState('replace');
    }

    // ============= BREADCRUMB NAVIGATION =============
    function setupBreadcrumbNavigation() {
        if (breadcrumbHome) {
            breadcrumbHome.addEventListener('click', () => {
                showOfficesView();
            });
        }

        if (breadcrumbFacility) {
            breadcrumbFacility.addEventListener('click', () => {
                if (currentOfficeId) {
                    showFacilitiesView(currentOfficeId, currentOfficeName);
                }
            });
        }
    }

    // ============= LOAD DEPARTMENTS =============
    async function loadOffices(page) {
        if (typeof page === 'number' && !Number.isNaN(page)) {
            officePage = Math.max(1, page);
        }
        try {
            const formData = new FormData();
            formData.append('action', 'get_offices');
            formData.append('page', String(officePage));
            formData.append('per_page', String(DEPARTMENTS_PER_PAGE));
            formData.append('q', currentSearch);
            formData.append('sort', currentSort);

            const res = await fetch('../../app/api/inventory_management.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const data = await res.json();
            officesData = (data.success && Array.isArray(data.offices)) ? data.offices : [];
            officeTotal = data.success ? Number(data.total) || 0 : 0;
            officeTotalPages = data.success ? Math.max(1, Number(data.total_pages) || 1) : 1;
            if (data.success && data.page) {
                officePage = Math.max(1, Number(data.page));
            }
            if (officePage > officeTotalPages) {
                officePage = officeTotalPages;
                return loadOffices(officePage);
            }
            renderOffices();
        } catch (err) {
            console.error(err);
            officeTableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:50px;color:#ef4444;">Error loading offices</td></tr>`;
            updateOfficePagination();
        }
    }

    // ============= VIEW FACILITIES BY DEPARTMENT =============
    async function viewOfficeFacilities(deptId, deptName, options = {}) {
        currentOfficeId = deptId;
        currentOfficeName = deptName;
        const historyMode = options.historyMode || 'push';
        updateUrlState(historyMode);
        showFacilitiesView(deptId, deptName, { historyMode });
    }

    function addSpacerRows(tbody, colSpan, rowsRendered, perPage) {
        const needed = perPage - rowsRendered;
        for (let i = 0; i < needed; i++) {
            const tr = document.createElement('tr');
            tr.className = 'list-row-spacer';
            tr.innerHTML = `<td colspan="${colSpan}"></td>`;
            tbody.appendChild(tr);
        }
    }

    function renderFacilitiesPage() {
        facilityTableBody.innerHTML = '';
        const total = facilitiesData.length;
        facilityTotalPages = total > 0 ? Math.ceil(total / FACILITIES_PER_PAGE) : 1;
        if (facilityPage > facilityTotalPages) facilityPage = facilityTotalPages;

        const start = (facilityPage - 1) * FACILITIES_PER_PAGE;
        const slice = facilitiesData.slice(start, start + FACILITIES_PER_PAGE);

        if (slice.length === 0) {
            facilityTableBody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:50px;color:#64748b;">No rooms or labs found in this office.</td></tr>`;
        } else {
            slice.forEach((fac, idx) => {
                const facilityName = fac.laboratory || fac.room || fac.office_name || 'Unnamed';
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';
                tr.className = 'facility-row';
                tr.innerHTML = `
                    <td>${start + idx + 1}</td>
                    <td>${fac.building || '-'}</td>
                    <td>${fac.code || '-'}</td>
                    <td>${fac.floor || '-'}</td>
                    <td>${fac.laboratory || '-'}</td>
                    <td>${fac.room || '-'}</td>
                    <td>${fac.type || '-'}</td>
                    <td><span class="inventory-badge">${fac.total_inventory} items</span></td>
                    <td>
                        <button class="action-btn view-inventory" data-facility-id="${fac.facility_id}" data-facility-name="${facilityName}" title="View Inventory">
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </td>
                `;
                tr.addEventListener('click', (e) => {
                    if (!e.target.closest('.action-btn')) {
                        viewFacilityInventory(fac.facility_id, facilityName);
                    }
                });
                facilityTableBody.appendChild(tr);
            });
            addSpacerRows(facilityTableBody, 9, slice.length, FACILITIES_PER_PAGE);
        }

        const prevBtn = document.getElementById('facilityPrevPageBtn');
        const nextBtn = document.getElementById('facilityNextPageBtn');
        const pageNum = document.getElementById('facilityPageNum');
        const pageInfo = document.getElementById('facilityPageInfo');
        if (prevBtn) prevBtn.disabled = facilityPage <= 1;
        if (nextBtn) nextBtn.disabled = facilityPage >= facilityTotalPages;
        if (pageNum) pageNum.textContent = facilityPage;
        if (pageInfo) {
            const from = total === 0 ? 0 : start + 1;
            const to = Math.min(start + FACILITIES_PER_PAGE, total);
            pageInfo.textContent = `Showing ${from} to ${to} of ${total} facilities`;
        }
    }

    function setupFacilityPagination() {
        const prevBtn = document.getElementById('facilityPrevPageBtn');
        const nextBtn = document.getElementById('facilityNextPageBtn');
        const backBtn = document.getElementById('facilityBackBtn');
        if (prevBtn) prevBtn.addEventListener('click', () => {
            if (facilityPage > 1) { facilityPage--; renderFacilitiesPage(); }
        });
        if (nextBtn) nextBtn.addEventListener('click', () => {
            if (facilityPage < facilityTotalPages) { facilityPage++; renderFacilitiesPage(); }
        });
        if (backBtn) backBtn.addEventListener('click', () => {
            showOfficesView();
        });

        const inventoryBackBtn = document.getElementById('inventoryBackBtn');
        if (inventoryBackBtn) inventoryBackBtn.addEventListener('click', () => {
            const addBtn = document.getElementById('addInventoryBtn');
            if (addBtn) addBtn.classList.add('hidden');
            showFacilitiesView(currentOfficeId, currentOfficeName);
        });

        const invPrevBtn = document.getElementById('inventoryPrevPageBtn');
        const invNextBtn = document.getElementById('inventoryNextPageBtn');
        if (invPrevBtn) invPrevBtn.addEventListener('click', () => {
            if (inventoryPage > 1) { inventoryPage--; renderInventoryPage(); }
        });
        if (invNextBtn) invNextBtn.addEventListener('click', () => {
            if (inventoryPage < inventoryTotalPages) { inventoryPage++; renderInventoryPage(); }
        });
    }

    async function showFacilitiesView(deptId, deptName, options = {}) {
        currentOfficeId = deptId;
        currentOfficeName = deptName;
        const historyMode = options.historyMode || 'replace';
        try {
            const formData = new FormData();
            formData.append('action', 'get_facilities_by_office');
            if (deptId !== undefined && deptId !== null && String(deptId).trim() !== '') {
                formData.append('office_id', String(deptId));
            }
            formData.append('office_name', deptName != null ? String(deptName) : '');

            const res = await fetch('../../app/api/inventory_management.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const data = await res.json();
            facilitiesData = (data.success && Array.isArray(data.facilities)) ? data.facilities : [];
            facilityPage = 1;

            // Update title and breadcrumb
            if (facilityViewTitle) facilityViewTitle.textContent = `Rooms and Laboratory in ${deptName}`;
            setBreadcrumbText(breadcrumbFacilityText, deptName);
            setBreadcrumb(breadcrumbFacility, 'block');
            setBreadcrumb(breadcrumbInventory, 'none');

            // Show facilities view
            officesView.style.display = 'none';
            inventoryView.style.display = 'none';
            facilitiesView.style.display = 'block';
            if (historyMode !== 'none') {
                updateUrlState(historyMode);
            }

            renderFacilitiesPage();

            if (historyMode !== 'none') {
                updateUrlState('replace');
            }
        } catch (err) {
            console.error(err);
            facilityTableBody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:50px;color:#ef4444;">Error loading facilities</td></tr>`;
        }
    }

    // ============= VIEW INVENTORY BY FACILITY =============
    function renderInventoryPage() {
        const inventoryTableBody = document.getElementById('inventoryTableBody');
        if (!inventoryTableBody) return;
        inventoryTableBody.innerHTML = '';

        const total = inventoryData.length;
        inventoryTotalPages = Math.max(1, Math.ceil(total / INVENTORY_PER_PAGE));
        inventoryPage = Math.min(inventoryPage, inventoryTotalPages);
        const start = (inventoryPage - 1) * INVENTORY_PER_PAGE;
        const slice = inventoryData.slice(start, start + INVENTORY_PER_PAGE);

        if (total === 0) {
            inventoryTableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:50px;color:#64748b;">No inventory items found in this facility.</td></tr>`;
        } else {
            slice.forEach((item, idx) => {
                const inv = item.inv;
                const rowNum = start + idx + 1;
                const statusClass = (inv.status || 'Available').toLowerCase().replace(/\s+/g, '');
                const conditionClass = (inv.condition_status || '').toLowerCase().replace(/\s+/g, '');
                const nm = escHtml(inv.name || inv.item_name || '—');
                const primaryPart = escHtml(inv.item_name || '—');
                const tr = document.createElement('tr');
                tr.className = 'inventory-table-parent-row';
                tr.innerHTML = `
                    <td>${rowNum}</td>
                    <td>${nm}</td>
                    <td>${primaryPart}</td>
                    <td>${escHtml(inv.item_code || '—')}</td>
                    <td>${escHtml(inv.quantity)}</td>
                    <td><span class="condition-badge ${conditionClass}">${escHtml(inv.condition_status || '—')}</span></td>
                    <td><span class="status-badge ${statusClass}">${escHtml(inv.status || 'Available')}</span></td>
                    <td>
                        <button class="action-btn view" data-id="${inv.inventory_id}" title="View details"><i class="fas fa-eye"></i></button>
                        <button class="action-btn edit" data-id="${inv.inventory_id}"
                            data-name="${inv.name || ''}"
                            data-itemcode="${inv.item_code || ''}"
                            data-facility="${inv.facility_id}"
                            data-date="${inv.acquisition_date || ''}"
                            data-remarks="${inv.remarks || ''}"
                            data-request_id="${inv.request_id ?? 0}"
                            data-assigned_user="${inv.user_id || ''}" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="action-btn delete" data-id="${inv.inventory_id}" data-name="${escHtml(inv.name || inv.item_name || '')}" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                `;
                inventoryTableBody.appendChild(tr);

                item.parts.forEach(comp => {
                    const ccond = (comp.condition_status || '').toLowerCase().replace(/\s+/g, '');
                    const cstat = (comp.status || 'Available').toLowerCase().replace(/\s+/g, '');
                    const ctr = document.createElement('tr');
                    ctr.className = 'inventory-table-part-row';
                    ctr.innerHTML = `
                        <td>—</td>
                        <td>—</td>
                        <td>${escHtml(comp.item_name || '—')}</td>
                        <td>${escHtml(comp.code || '—')}</td>
                        <td>${escHtml(comp.quantity ?? 1)}</td>
                        <td><span class="condition-badge ${ccond}">${escHtml(comp.condition_status || '—')}</span></td>
                        <td><span class="status-badge ${cstat}">${escHtml(comp.status || '—')}</span></td>
                        <td></td>
                    `;
                    inventoryTableBody.appendChild(ctr);
                });
            });

            // Spacer rows for consistent card height
            const needed = INVENTORY_PER_PAGE - slice.length;
            for (let i = 0; i < needed; i++) {
                const tr = document.createElement('tr');
                tr.className = 'list-row-spacer';
                tr.innerHTML = `<td colspan="8"></td>`;
                inventoryTableBody.appendChild(tr);
            }
        }

        const prevBtn = document.getElementById('inventoryPrevPageBtn');
        const nextBtn = document.getElementById('inventoryNextPageBtn');
        const pageNum = document.getElementById('inventoryPageNum');
        const pageInfo = document.getElementById('inventoryPageInfo');
        if (prevBtn) prevBtn.disabled = inventoryPage <= 1;
        if (nextBtn) nextBtn.disabled = inventoryPage >= inventoryTotalPages;
        if (pageNum) pageNum.textContent = inventoryPage;
        if (pageInfo) {
            const from = total === 0 ? 0 : start + 1;
            const to = Math.min(start + INVENTORY_PER_PAGE, total);
            pageInfo.textContent = `Showing ${from} to ${to} of ${total} items`;
        }
    }

    async function viewFacilityInventory(facilityId, facilityName, options = {}) {
        currentFacilityId = facilityId;
        const historyMode = options.historyMode || 'push';
        const addBtn = document.getElementById('addInventoryBtn');

        try {
            const formData = new FormData();
            formData.append('action', 'get_inventory_by_facility');
            formData.append('facility_id', facilityId);

            const res = await fetch('../../app/api/inventory_management.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const data = await res.json();

            // Pre-fetch components for all inventory items
            inventoryData = [];
            if (data.success && data.inventory.length > 0) {
                for (const inv of data.inventory) {
                    let parts = [];
                    try {
                        const compForm = new FormData();
                        compForm.append('action', 'get_components');
                        compForm.append('inventory_id', inv.inventory_id);
                        const compRes = await fetch('../../app/api/inventory_management.php', { method: 'POST', body: compForm, credentials: 'include' });
                        const compData = await compRes.json();
                        parts = filterComponentsForTableSubrows(compData.components || []);
                    } catch (e) { console.error('Error loading components', e); }
                    inventoryData.push({ inv, parts });
                }
            }

            inventoryPage = 1;

            // Update title
            const inventoryViewTitle = document.getElementById('inventoryViewTitle');
            if (inventoryViewTitle) inventoryViewTitle.textContent = `Inventory in ${facilityName}`;

            // Update breadcrumb
            setBreadcrumb(breadcrumbInventory, 'block');

            // Show inventory view, hide add button initially then show
            officesView.style.display = 'none';
            facilitiesView.style.display = 'none';
            inventoryView.style.display = 'block';
            if (addBtn) addBtn.classList.remove('hidden');
            if (historyMode !== 'none') updateUrlState(historyMode);

            renderInventoryPage();

            if (historyMode !== 'none') updateUrlState('replace');
        } catch (err) {
            console.error(err);
            const inventoryTableBody = document.getElementById('inventoryTableBody');
            if (inventoryTableBody) inventoryTableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:50px;color:#ef4444;">Error loading inventory</td></tr>`;
        }
    }

    // ============= SHOW/HIDE VIEWS =============
    function showOfficesView(options = {}) {
        const historyMode = options.historyMode || 'replace';
        if (options.resetPage === false && typeof options.page === 'number') {
            officePage = Math.max(1, options.page);
        } else if (options.resetPage !== false) {
            officePage = 1;
        }
        officesView.style.display = 'block';
        facilitiesView.style.display = 'none';
        inventoryView.style.display = 'none';
        const addBtn = document.getElementById('addInventoryBtn');
        if (addBtn) addBtn.classList.add('hidden');
        setBreadcrumb(breadcrumbFacility, 'none');
        setBreadcrumb(breadcrumbInventory, 'none');
        currentOfficeId = null;
        currentFacilityId = null;
        if (historyMode !== 'none') {
            updateUrlState(historyMode);
        }
        loadOffices();
    }

    // ============= EVENT DELEGATION FOR ACTION BUTTONS =============
    document.addEventListener('click', (e) => {
        const viewFacilitiesBtn = e.target.closest('.view-facilities');
        const viewInventoryBtn = e.target.closest('.view-inventory');

        if (viewFacilitiesBtn) {
            const deptId = viewFacilitiesBtn.dataset.deptId;
            const deptName = viewFacilitiesBtn.dataset.deptName;
            viewOfficeFacilities(deptId, deptName);
        }

        if (viewInventoryBtn) {
            const facilityId = viewInventoryBtn.dataset.facilityId;
            const facilityName = viewInventoryBtn.dataset.facilityName;
            viewFacilityInventory(facilityId, facilityName);
        }
    });

    // Export functions for use by inventory_management.js
    window.inventoryHierarchy = {
        viewFacilityInventory,
        showOfficesView,
        getCurrentFacilityId: () => currentFacilityId,
        getCurrentOfficeId: () => currentOfficeId
    };

    // Initialize
    init();
});
