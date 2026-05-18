document.addEventListener('DOMContentLoaded', () => {

const officeGrid = document.getElementById('officeGrid');
const officeTableBody = document.getElementById('officeTableBody');
const officeTablePanel = document.getElementById('officeTablePanel');
const deptLayoutToggleBtn = document.getElementById('deptLayoutToggleBtn');
const facilityTableBody = document.getElementById('facilityTableBody');
const officesView = document.getElementById('officesView');
const facilitiesView = document.getElementById('facilitiesView');
const breadcrumbHome = document.getElementById('breadcrumb-home');
const breadcrumbFacility = document.getElementById('breadcrumb-facility');
const breadcrumbFacilityText = document.getElementById('breadcrumb-facility-text');

// Search and sort inputs
const officeSearchInput = document.getElementById('officeSearchInput');
const officeSortDropdown = document.getElementById('officeSortDropdown');
const facilitySearchInput = document.getElementById('facilitySearchInput');
const facilitySortDropdown = document.getElementById('facilitySortDropdown');

// Office modal elements
const officeModal = document.getElementById('officeModal');
const closeOfficeModal = document.getElementById('closeOfficeModal');
const addOfficeBtn = document.getElementById('addOfficeBtn');
const officeForm = document.getElementById('officeForm');
const officeIdInput = document.getElementById('office_id');
const officeNameInput = document.getElementById('office_name');
const officeTypeInput = document.getElementById('office_type');
const headSelect = document.getElementById('head_user_id');

// Facility modal elements
const facilityModal = document.getElementById('facilityModal');
const closeFacilityModal = document.getElementById('closeFacilityModal');
const addFacilityBtn = document.getElementById('addFacilityBtn');
const facilityForm = document.getElementById('facilityForm');
const facilityIdInput = document.getElementById('facility_id');
const facilityDeptIdInput = document.getElementById('facility_office_id');
const facilityBuilding = document.getElementById('facility_building');
const facilityCode = document.getElementById('facility_code');
const facilityFloor = document.getElementById('facility_floor');
const facilityLaboratory = document.getElementById('facility_laboratory');
const facilityRoom = document.getElementById('facility_room');
const facilityType = document.getElementById('facility_type');

let currentOffice = null;
let allOffices = [];
let allFacilities = [];
let suppressUrlStateWrite = true;
/** 'grid' | 'table' — offices list only; facilities stay table (default: table) */
let officeListMode = 'table';
const DEPARTMENTS_PER_PAGE = 5;
let officePage = 1;
let officeTotalPages = 1;
let officeSearchDebounce = null;

function updateUrlState(mode = 'replace') {
    if (suppressUrlStateWrite) return;
    const params = new URLSearchParams(window.location.search);
    const view = facilitiesView.classList.contains('hidden') ? 'offices' : 'facilities';
    params.set('view', view);
    if (officeSearchInput?.value?.trim()) params.set('dept_q', officeSearchInput.value.trim());
    else params.delete('dept_q');
    if (officeSortDropdown?.value) params.set('dept_sort', officeSortDropdown.value);
    else params.delete('dept_sort');
    if (facilitySearchInput?.value?.trim()) params.set('fac_q', facilitySearchInput.value.trim());
    else params.delete('fac_q');
    if (facilitySortDropdown?.value) params.set('fac_sort', facilitySortDropdown.value);
    else params.delete('fac_sort');
    if (currentOffice && currentOffice.id) {
        params.set('office_id', String(currentOffice.id));
        params.set('office_name', currentOffice.name || '');
    } else {
        params.delete('office_id');
        params.delete('office_name');
    }
    if (officeListMode === 'grid') {
        params.set('dept_layout', 'grid');
    } else {
        params.delete('dept_layout');
    }
    if (view === 'offices') {
        if (officePage > 1) params.set('dept_page', String(officePage));
        else params.delete('dept_page');
    } else {
        params.delete('dept_page');
    }
    const next = `${window.location.pathname}?${params.toString()}`;
    if (mode === 'push') {
        window.history.pushState({ imrmsFacilityHierarchy: true }, '', next);
    } else {
        window.history.replaceState({ imrmsFacilityHierarchy: true }, '', next);
    }
}

function readUrlState() {
    const params = new URLSearchParams(window.location.search);
    return {
        view: params.get('view') || 'offices',
        officeId: params.get('office_id') || '',
        officeName: params.get('office_name') || '',
        officeSearch: params.get('dept_q') || '',
        officeSort: params.get('dept_sort') || '',
        facilitySearch: params.get('fac_q') || '',
        facilitySort: params.get('fac_sort') || '',
        deptLayout: params.get('dept_layout') === 'grid' ? 'grid' : 'table',
        deptPage: Math.max(1, parseInt(params.get('dept_page') || '1', 10) || 1),
    };
}

function syncDeptLayoutToggleUi() {
    if (!deptLayoutToggleBtn) return;
    const icon = deptLayoutToggleBtn.querySelector('.dept-layout-toggle-icon');
    if (officeListMode === 'table') {
        if (icon) {
            icon.className = 'fas fa-th-large dept-layout-toggle-icon';
        }
        const tip = 'Show offices as card grid';
        deptLayoutToggleBtn.setAttribute('title', tip);
        deptLayoutToggleBtn.setAttribute('data-tooltip', tip);
        deptLayoutToggleBtn.setAttribute('aria-label', tip);
    } else {
        if (icon) {
            icon.className = 'fas fa-table dept-layout-toggle-icon';
        }
        const tip = 'Show offices as table';
        deptLayoutToggleBtn.setAttribute('title', tip);
        deptLayoutToggleBtn.setAttribute('data-tooltip', tip);
        deptLayoutToggleBtn.setAttribute('aria-label', tip);
    }
}

function applyOfficeListMode(mode, options = {}) {
    const next = mode === 'grid' ? 'grid' : 'table';
    officeListMode = next;
    if (officeGrid) officeGrid.classList.toggle('hidden', next === 'table');
    if (officeTablePanel) officeTablePanel.classList.toggle('hidden', next === 'grid');
    syncDeptLayoutToggleUi();
    if (!options.skipUrl) {
        updateUrlState('replace');
    }
}

function showToast(msg, type='success'){
    const container = document.getElementById('toastContainer');
    const div = document.createElement('div');
    div.className = `toast ${type}`;
    div.textContent = msg;
    container.appendChild(div);
    setTimeout(()=>div.remove(),4000);
}

async function loadUsersForHead(){
    if (!headSelect) return;
    try {
        const res = await fetch('../../app/api/get_users.php', { method: 'GET', credentials: 'include' });
        const data = await res.json();
        headSelect.innerHTML = '<option value="">-- None --</option>';
        if(data.success && Array.isArray(data.users)){
            data.users.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.user_id;
                opt.textContent = u.email;
                headSelect.appendChild(opt);
            });
        }
    } catch(err){ console.error(err); }
}

function setOfficeLoadingState(message) {
    if (officeGrid) {
        officeGrid.innerHTML = `<div class="office-grid-loading">${message}</div>`;
    }
    if (officeTableBody) {
        officeTableBody.innerHTML = `<tr><td colspan="7" class="loading-cell">${message}</td></tr>`;
    }
}

function updateOfficePagination() {
    const prev = document.getElementById('officePrevPageBtn');
    const next = document.getElementById('officeNextPageBtn');
    const info = document.getElementById('officePageInfo');
    if (!prev || !next || !info) return;
    prev.disabled = officePage <= 1;
    next.disabled = officePage >= officeTotalPages;
    info.textContent = `Page ${officePage} of ${officeTotalPages}`;
}

function setupOfficePagination() {
    const prev = document.getElementById('officePrevPageBtn');
    const next = document.getElementById('officeNextPageBtn');
    if (!prev || !next) return;
    prev.addEventListener('click', () => {
        if (officePage > 1) loadOffices(officePage - 1);
    });
    next.addEventListener('click', () => {
        if (officePage < officeTotalPages) loadOffices(officePage + 1);
    });
}

// Load offices (server-paginated search/sort)
async function loadOffices(page) {
    if (typeof page === 'number' && !Number.isNaN(page)) {
        officePage = Math.max(1, page);
    }
    setOfficeLoadingState('Loading Offices...');
    try {
        const form = new FormData();
        form.append('action', 'list_offices');
        form.append('page', String(officePage));
        form.append('per_page', String(DEPARTMENTS_PER_PAGE));
        form.append('q', (officeSearchInput?.value || '').trim());
        form.append('sort', officeSortDropdown?.value || '');
        const res = await fetch('../../app/api/facility_management.php', { method: 'POST', body: form, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            allOffices = data.offices || [];
            officeTotalPages = Math.max(1, Number(data.total_pages) || 1);
            if (data.page) officePage = Math.max(1, Number(data.page));
            if (officePage > officeTotalPages) {
                officePage = officeTotalPages;
                return loadOffices(officePage);
            }
            renderOffices(allOffices);
            updateOfficePagination();
            if (!suppressUrlStateWrite) updateUrlState('replace');
        } else {
            setOfficeLoadingState('Failed to load offices');
            updateOfficePagination();
        }
    } catch (err) {
        console.error(err);
        setOfficeLoadingState('Error loading offices');
        updateOfficePagination();
    }
}

function renderOfficeGrid(offices) {
    if (!officeGrid) return;
    officeGrid.innerHTML = '';
    if(offices.length === 0){
        officeGrid.innerHTML = '<div class="office-grid-loading">No offices found.</div>';
        return;
    }

    const rowBase = (officePage - 1) * DEPARTMENTS_PER_PAGE;
    offices.forEach((dept, index) => {
        const card = document.createElement('article');
        card.classList.add('office-card');
        card.tabIndex = 0;
        card.dataset.id = dept.office_id;
        card.dataset.name = dept.office_name;
        const total = Number(dept.total_labs || 0) + Number(dept.total_rooms || 0);
        const safeName = escapeHtml(dept.office_name);
        const officeType = escapeHtml(dept.type || 'administrative');
        const photo = (dept.photo_url || '').trim();
        const photoHtml = photo
            ? `<img src="../../${escapeHtml(photo)}" alt="${safeName} logo" class="office-card-logo">`
            : `<div class="office-card-logo office-card-logo-fallback"><i class="fas fa-building"></i></div>`;
        card.innerHTML = `
            <div class="office-card-head">
                <span class="office-card-index">#${rowBase + index + 1}</span>
                ${photoHtml}
            </div>
            <h4 class="office-card-title">${safeName}</h4>
            <p class="office-card-type">${officeType}</p>
            <div class="office-card-stats">
                <div class="office-stat"><span class="label">Total Labs</span><strong>${dept.total_labs || 0}</strong></div>
                <div class="office-stat"><span class="label">Total Rooms</span><strong>${dept.total_rooms || 0}</strong></div>
                <div class="office-stat"><span class="label">Total</span><strong>${total}</strong></div>
            </div>
            <div class="office-card-actions">
                <button type="button" class="action-btn view" data-id="${dept.office_id}" data-name="${safeName}">View</button>
                <button type="button" class="action-btn edit" data-id="${dept.office_id}" data-name="${safeName}"><i class="fas fa-edit"></i></button>
                <button type="button" class="action-btn delete" data-id="${dept.office_id}"><i class="fas fa-trash"></i></button>
            </div>
        `;
        officeGrid.appendChild(card);
    });
}

function renderOfficeDataTable(offices) {
    if (!officeTableBody) return;
    officeTableBody.innerHTML = '';
    if (offices.length === 0) {
        officeTableBody.innerHTML = '<tr><td colspan="7" class="loading-cell">No offices found.</td></tr>';
        return;
    }

    const rowBase = (officePage - 1) * DEPARTMENTS_PER_PAGE;
    offices.forEach((dept, index) => {
        const tr = document.createElement('tr');
        tr.classList.add('clickable-row');
        tr.tabIndex = 0;
        tr.dataset.id = dept.office_id;
        tr.dataset.name = dept.office_name;
        const total = Number(dept.total_labs || 0) + Number(dept.total_rooms || 0);
        const safeName = escapeHtml(dept.office_name);
        const officeType = escapeHtml(dept.type || 'administrative');
        const photo = (dept.photo_url || '').trim();
        const thumb = photo
            ? `<img src="../../${escapeHtml(photo)}" alt="" class="dept-table-thumb">`
            : `<span class="dept-table-thumb dept-table-thumb-fallback"><i class="fas fa-building"></i></span>`;
        tr.innerHTML = `
            <td>${rowBase + index + 1}</td>
            <td class="dept-name-cell">${thumb}<span class="dept-table-name">${safeName}</span></td>
            <td>${officeType}</td>
            <td>${dept.total_labs || 0}</td>
            <td>${dept.total_rooms || 0}</td>
            <td>${total}</td>
            <td>
                <button type="button" class="action-btn view" data-id="${dept.office_id}" data-name="${safeName}">View</button>
                <button type="button" class="action-btn edit" data-id="${dept.office_id}" data-name="${safeName}"><i class="fas fa-edit"></i></button>
                <button type="button" class="action-btn delete" data-id="${dept.office_id}"><i class="fas fa-trash"></i></button>
            </td>
        `;
        officeTableBody.appendChild(tr);
    });
}

function renderOffices(offices) {
    if (officeListMode === 'table') {
        renderOfficeDataTable(offices);
        if (officeGrid) officeGrid.innerHTML = '';
    } else {
        renderOfficeGrid(offices);
        if (officeTableBody) officeTableBody.innerHTML = '';
    }
}

function showOfficesView(options = {}) {
    const reload = options.reload !== false;
    const resetPage = options.resetPage !== false;
    if (resetPage) officePage = 1;
    officesView.classList.remove('hidden');
    facilitiesView.classList.add('hidden');
    breadcrumbHome.classList.add('active');
    breadcrumbFacility.classList.add('hidden');
    currentOffice = null;
    if (reload) loadOffices();
    else if (!suppressUrlStateWrite) updateUrlState('replace');
}

function showFacilitiesView(deptId, deptName, options = {}){
    const historyMode = options.historyMode || 'push';
    officesView.classList.add('hidden');
    facilitiesView.classList.remove('hidden');
    breadcrumbHome.classList.remove('active');
    breadcrumbFacility.classList.remove('hidden');
    breadcrumbFacilityText.textContent = deptName;
    currentOffice = { id: deptId, name: deptName };
    if (historyMode !== 'none') {
        updateUrlState(historyMode);
    }
    loadFacilities(deptId);
}

async function loadFacilities(deptId){
    facilityTableBody.innerHTML = '<tr><td colspan="7" class="loading-cell">Loading...</td></tr>';
    try {
        const form = new FormData(); form.append('action','list_facilities'); form.append('office_id', deptId);
        const res = await fetch('../../app/api/facility_management.php',{ method:'POST', body: form, credentials:'include' });
        const data = await res.json();
        if(data.success){
            allFacilities = data.facilities || [];
            searchAndFilterFacilities();
        } else {
            facilityTableBody.innerHTML = '<tr><td colspan="7" class="loading-cell">Failed to load facilities</td></tr>';
        }
    } catch(err){
        console.error(err);
        facilityTableBody.innerHTML = '<tr><td colspan="7" class="loading-cell">Error loading facilities</td></tr>';
    }
}

function renderFacilityTable(facilities) {
    facilityTableBody.innerHTML = '';
    if(facilities.length === 0){
        facilityTableBody.innerHTML = '<tr><td colspan="7" class="loading-cell">No facilities found.</td></tr>';
        return;
    }
    
    facilities.forEach((f, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${index + 1}</td>
            <td>${escapeHtml(f.building || '-')}</td>
            <td>${escapeHtml(f.code || '-')}</td>
            <td>${escapeHtml(f.laboratory || '-')}</td>
            <td>${escapeHtml(f.room || '-')}</td>
            <td>${escapeHtml(f.type || '-')}</td>
            <td>
                <button class="action-btn edit-facility" data-id="${f.facility_id}" data-building="${escapeHtml(f.building||'')}" data-code="${escapeHtml(f.code||'')}" data-floor="${escapeHtml(f.floor||'')}" data-lab="${escapeHtml(f.laboratory||'')}" data-room="${escapeHtml(f.room||'')}" data-type="${escapeHtml(f.type||'')}"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete-facility" data-id="${f.facility_id}"><i class="fas fa-trash"></i></button>
            </td>
        `;
        facilityTableBody.appendChild(tr);
    });
}

// Escape helper
function escapeHtml(str){ if(!str) return ''; return String(str).replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }

// -------- FACILITY SEARCH & SORT --------
function searchAndFilterFacilities() {
    const searchTerm = (facilitySearchInput.value || '').toLowerCase().trim();
    const sortOption = facilitySortDropdown.value;

    let filteredFacilities = allFacilities.filter(facility => {
        if (!searchTerm) return true;
        const building = (facility.building || '').toLowerCase();
        const code = (facility.code || '').toLowerCase();
        const laboratory = (facility.laboratory || '').toLowerCase();
        const room = (facility.room || '').toLowerCase();
        const type = (facility.type || '').toLowerCase();
        
        return building.includes(searchTerm) || code.includes(searchTerm) || 
               laboratory.includes(searchTerm) || room.includes(searchTerm) ||
               type.includes(searchTerm);
    });

    // Apply sorting
    if (sortOption) {
        filteredFacilities = sortFacilities(filteredFacilities, sortOption);
    }

    renderFacilityTable(filteredFacilities);
    updateUrlState();
}

function sortFacilities(facilities, sortOption) {
    const facCopy = [...facilities];

    switch(sortOption) {
        case 'building-asc':
            return facCopy.sort((a, b) => (a.building || '').localeCompare(b.building || ''));
        case 'building-desc':
            return facCopy.sort((a, b) => (b.building || '').localeCompare(a.building || ''));
        case 'code-asc':
            return facCopy.sort((a, b) => (a.code || '').localeCompare(b.code || ''));
        case 'code-desc':
            return facCopy.sort((a, b) => (b.code || '').localeCompare(a.code || ''));
        case 'lab-asc':
            return facCopy.sort((a, b) => (a.laboratory || '').localeCompare(b.laboratory || ''));
        case 'lab-desc':
            return facCopy.sort((a, b) => (b.laboratory || '').localeCompare(a.laboratory || ''));
        case 'room-asc':
            return facCopy.sort((a, b) => (a.room || '').localeCompare(b.room || ''));
        case 'room-desc':
            return facCopy.sort((a, b) => (b.room || '').localeCompare(a.room || ''));
        case 'type-asc':
            return facCopy.sort((a, b) => (a.type || '').localeCompare(b.type || ''));
        case 'type-desc':
            return facCopy.sort((a, b) => (b.type || '').localeCompare(a.type || ''));
        default:
            return facCopy;
    }
}

// Modal handling
addOfficeBtn.addEventListener('click', ()=>{
    officeForm.reset(); officeIdInput.value = ''; document.getElementById('modalTitle').textContent = 'Add Office'; officeModal.classList.add('show');
});
closeOfficeModal.addEventListener('click', ()=> officeModal.classList.remove('show'));
window.addEventListener('click', e => { if(e.target === officeModal) officeModal.classList.remove('show'); });

addFacilityBtn.addEventListener('click', ()=>{
    facilityForm.reset(); facilityIdInput.value = ''; facilityDeptIdInput.value = currentOffice ? currentOffice.id : '';
    document.getElementById('facilityModalTitle').textContent = 'Add Facility'; facilityModal.classList.add('show');
});
closeFacilityModal.addEventListener('click', ()=> facilityModal.classList.remove('show'));
window.addEventListener('click', e => { if(e.target === facilityModal) facilityModal.classList.remove('show'); });

// Office form submit
officeForm.addEventListener('submit', async e=>{
    e.preventDefault();
    if (!officeTypeInput?.value) {
        showToast('Please select an office type.', 'error');
        officeTypeInput?.focus();
        return;
    }
    const form = new FormData(officeForm);
    const isEdit = !!officeIdInput.value;
    form.append('action', isEdit ? 'edit_office' : 'add_office');
    if(isEdit) form.append('office_id', officeIdInput.value);
    try{
        const res = await fetch('../../app/api/facility_management.php',{ method:'POST', body: form, credentials:'include' });
        const data = await res.json();
        if(data.success){ showToast(data.message); officeModal.classList.remove('show'); loadOffices(); }
        else showToast(data.message,'error');
    }catch(err){ console.error(err); showToast('Error saving office','error'); }
});

// Facility form submit
facilityForm.addEventListener('submit', async e=>{
    e.preventDefault();
    if (!facilityType.value) {
        showToast('Please select a facility type.', 'error');
        facilityType.focus();
        return;
    }
    const form = new FormData(facilityForm);
    const isEdit = !!facilityIdInput.value;
    form.append('action', isEdit ? 'edit_facility' : 'add_facility');
    if(isEdit) form.append('facility_id', facilityIdInput.value);
    form.append('office_id', facilityDeptIdInput.value);
    try{
        const res = await fetch('../../app/api/facility_management.php',{ method:'POST', body: form, credentials:'include' });
        const data = await res.json();
        if(data.success){ showToast(data.message); facilityModal.classList.remove('show'); loadFacilities(facilityDeptIdInput.value); loadOffices(); }
        else showToast(data.message,'error');
    }catch(err){ console.error(err); showToast('Error saving facility','error'); }
});

async function handleOfficeActionClick(e) {
    const viewBtn = e.target.closest('.view');
    const editBtn = e.target.closest('.edit');
    const deleteBtn = e.target.closest('.delete');

    if (viewBtn) {
        showFacilitiesView(viewBtn.dataset.id, viewBtn.dataset.name);
    }

    if (editBtn) {
        const id = editBtn.dataset.id;
        const office = allOffices.find((o) => String(o.office_id) === String(id));
        const name = office?.office_name || editBtn.dataset.name || '';
        const type = office?.type || 'administrative';
        officeForm.reset();
        officeIdInput.value = id;
        officeNameInput.value = name;
        if (officeTypeInput) officeTypeInput.value = type;
        document.getElementById('modalTitle').textContent = 'Edit Office';
        officeModal.classList.add('show');
    }

    if (deleteBtn) {
        if (confirm('Delete office? This will fail if office has facilities.')) {
            const form = new FormData();
            form.append('action', 'delete_office');
            form.append('office_id', deleteBtn.dataset.id);
            const res = await fetch('../../app/api/facility_management.php', { method: 'POST', body: form, credentials: 'include' });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                loadOffices();
            } else showToast(data.message, 'error');
        }
    }
}

officeGrid?.addEventListener('click', handleOfficeActionClick);
officeTableBody?.addEventListener('click', handleOfficeActionClick);

// Card / row: open facilities when clicking row body (not buttons)
function handleOfficeNavigateClick(e) {
    if (e.target.closest('button')) return;
    const card = e.target.closest('.office-card');
    const tr = e.target.closest('tr.clickable-row');
    const row = card || tr;
    if (!row) return;
    const id = row.dataset.id;
    const name = row.dataset.name;
    if (id) showFacilitiesView(id, name);
}

function handleOfficeNavigateKeydown(e) {
    if (e.key !== 'Enter') return;
    const card = e.target.closest('.office-card');
    const tr = e.target.closest('tr.clickable-row');
    const row = card || tr;
    if (!row) return;
    const id = row.dataset.id;
    const name = row.dataset.name;
    if (id) showFacilitiesView(id, name);
}

officeGrid?.addEventListener('click', handleOfficeNavigateClick);
officeGrid?.addEventListener('keydown', handleOfficeNavigateKeydown);
officeTableBody?.addEventListener('click', handleOfficeNavigateClick);
officeTableBody?.addEventListener('keydown', handleOfficeNavigateKeydown);

// Facility table actions
facilityTableBody.addEventListener('click', async e=>{
    const editBtn = e.target.closest('.edit-facility');
    const deleteBtn = e.target.closest('.delete-facility');

    if(editBtn){
        facilityForm.reset(); facilityIdInput.value = editBtn.dataset.id; facilityDeptIdInput.value = currentOffice.id;
        facilityBuilding.value = editBtn.dataset.building || ''; facilityCode.value = editBtn.dataset.code || ''; facilityFloor.value = editBtn.dataset.floor || ''; facilityLaboratory.value = editBtn.dataset.lab || ''; facilityRoom.value = editBtn.dataset.room || ''; facilityType.value = editBtn.dataset.type || '';
        document.getElementById('facilityModalTitle').textContent = 'Edit Facility'; facilityModal.classList.add('show');
    }

    if(deleteBtn){
        if(confirm('Delete facility?')){
            const form = new FormData(); form.append('action','delete_facility'); form.append('facility_id', deleteBtn.dataset.id);
            const res = await fetch('../../app/api/facility_management.php',{ method:'POST', body: form, credentials:'include' });
            const data = await res.json();
            if(data.success){ showToast(data.message); loadFacilities(currentOffice.id); loadOffices(); } else showToast(data.message,'error');
        }
    }
});

// Breadcrumb home click
breadcrumbHome.addEventListener('click', ()=> showOfficesView());

// Event listeners for office search and sort (server-side)
officeSearchInput?.addEventListener('input', () => {
    clearTimeout(officeSearchDebounce);
    officeSearchDebounce = setTimeout(() => loadOffices(1), 350);
});
officeSortDropdown?.addEventListener('change', () => loadOffices(1));

// Event listeners for facility search and sort
facilitySearchInput?.addEventListener('input', searchAndFilterFacilities);
facilitySortDropdown?.addEventListener('change', searchAndFilterFacilities);

deptLayoutToggleBtn?.addEventListener('click', () => {
    applyOfficeListMode(officeListMode === 'table' ? 'grid' : 'table');
    renderOffices(allOffices);
});

// Initial load
loadUsersForHead();
setupOfficePagination();
const urlState = readUrlState();
officePage = urlState.deptPage;
if (officeSearchInput) officeSearchInput.value = urlState.officeSearch || '';
if (officeSortDropdown) officeSortDropdown.value = urlState.officeSort || '';
if (facilitySearchInput) facilitySearchInput.value = urlState.facilitySearch || '';
if (facilitySortDropdown) facilitySortDropdown.value = urlState.facilitySort || '';
applyOfficeListMode(urlState.deptLayout === 'grid' ? 'grid' : 'table', { skipUrl: true });
loadOffices().then(() => {
    if (urlState.view === 'facilities' && urlState.officeId) {
        const matched = allOffices.find((d) => String(d.office_id) === String(urlState.officeId));
        const deptName = matched ? matched.office_name : (urlState.officeName || 'Facilities');
        showFacilitiesView(urlState.officeId, deptName, { historyMode: 'none' });
    } else {
        showOfficesView({ reload: false, resetPage: false });
    }
    suppressUrlStateWrite = false;
    updateUrlState('replace');
});

window.addEventListener('popstate', () => {
    const state = readUrlState();
    const prevSuppress = suppressUrlStateWrite;
    suppressUrlStateWrite = true;
    officePage = state.deptPage;
    if (officeSearchInput) officeSearchInput.value = state.officeSearch || '';
    if (officeSortDropdown) officeSortDropdown.value = state.officeSort || '';
    if (facilitySearchInput) facilitySearchInput.value = state.facilitySearch || '';
    if (facilitySortDropdown) facilitySortDropdown.value = state.facilitySort || '';
    applyOfficeListMode(state.deptLayout === 'grid' ? 'grid' : 'table', { skipUrl: true });
    if (state.view === 'facilities' && state.officeId) {
        const matched = allOffices.find((d) => String(d.office_id) === String(state.officeId));
        const deptName = matched ? matched.office_name : (state.officeName || 'Facilities');
        showFacilitiesView(state.officeId, deptName, { historyMode: 'none' });
    } else {
        showOfficesView({ reload: true, resetPage: false });
    }
    suppressUrlStateWrite = prevSuppress;
});

});
