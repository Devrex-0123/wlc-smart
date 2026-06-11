document.addEventListener('DOMContentLoaded', () => {

const officeGrid = document.getElementById('officeGrid');
const officeTableBody = document.getElementById('officeTableBody');
const officeTablePanel = document.getElementById('officeTablePanel');
const facilityTableBody = document.getElementById('facilityTableBody');
const facilityPagination = document.getElementById('facilityPagination');
const facilityPrevPageBtn = document.getElementById('facilityPrevPageBtn');
const facilityNextPageBtn = document.getElementById('facilityNextPageBtn');
const facilityCardSwap = document.getElementById('facilityCardSwap');
const officesView = document.getElementById('officesView');
const facilitiesView = document.getElementById('facilitiesView');
const backToOfficesBtn = document.getElementById('backToOfficesBtn');
const facilityViewTitle = document.getElementById('facilityViewTitle');
const officeDetailLogoImg = document.getElementById('officeDetailLogoImg');
const officeDetailLogoInitials = document.getElementById('officeDetailLogoInitials');
const officeDetailTypeBadge = document.getElementById('officeDetailTypeBadge');
const officeDetailLabs = document.getElementById('officeDetailLabs');
const officeDetailRooms = document.getElementById('officeDetailRooms');
const officeDetailTotal = document.getElementById('officeDetailTotal');

// Search inputs
const officeSearchInput = document.getElementById('officeSearchInput');

// Office modal elements
const officeModal = document.getElementById('officeModal');
const closeOfficeModal = document.getElementById('closeOfficeModal');
const addOfficeBtn = document.getElementById('addOfficeBtn');
const officeForm = document.getElementById('officeForm');
const officeIdInput = document.getElementById('office_id');
const officeNameInput = document.getElementById('office_name');
const officeTypeInput = document.getElementById('office_type');
const officePhotoInput = document.getElementById('office_photo');
const officeModalSubtitle = document.getElementById('officeModalSubtitle');
const saveOfficeBtn = document.getElementById('saveOfficeBtn');
const cancelOfficeBtn = document.getElementById('cancelOfficeBtn');
const officeLogoImage = document.getElementById('officeLogoImage');
const officeLogoPlaceholder = document.getElementById('officeLogoPlaceholder');
const headSelect = document.getElementById('head_user_id');

const fmDeleteModal = document.getElementById('fmDeleteModal');
const fmDeleteBackdrop = document.getElementById('fmDeleteBackdrop');
const fmDeleteClose = document.getElementById('fmDeleteClose');
const fmDeleteCancel = document.getElementById('fmDeleteCancel');
const fmDeleteConfirm = document.getElementById('fmDeleteConfirm');
const fmDeleteTitle = document.getElementById('fmDeleteTitle');
const fmDeleteDesc = document.getElementById('fmDeleteDesc');
const fmDeleteName = document.getElementById('fmDeleteName');
const fmDeleteConfirmLabel = document.getElementById('fmDeleteConfirmLabel');
const fmSuccessModal = document.getElementById('fmSuccessModal');
const fmSuccessBackdrop = document.getElementById('fmSuccessBackdrop');
const fmSuccessTitle = document.getElementById('fmSuccessTitle');
const fmSuccessMessage = document.getElementById('fmSuccessMessage');
const fmSuccessOk = document.getElementById('fmSuccessOk');

let pendingDelete = null;

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
const facilityTypeKind = document.getElementById('facility_type_kind');
const facilityNameInput = document.getElementById('facility_name');
const facilityNewTypeGroup = document.getElementById('facilityNewTypeGroup');
const facilityNewTypeInput = document.getElementById('facility_new_type');
const facilityModalSubtitle = document.getElementById('facilityModalSubtitle');
const cancelFacilityBtn = document.getElementById('cancelFacilityBtn');
const saveFacilityBtn = document.getElementById('saveFacilityBtn');

let localExtraFacilityTypes = [];
try {
    localExtraFacilityTypes = JSON.parse(localStorage.getItem('fm_custom_facility_types') || '[]');
    if (!Array.isArray(localExtraFacilityTypes)) localExtraFacilityTypes = [];
} catch {
    localExtraFacilityTypes = [];
}

let currentOffice = null;
let allOffices = [];
let allFacilities = [];
let suppressUrlStateWrite = true;
/** 'grid' | 'table' — offices list only; facilities stay table (default: table) */
let officeListMode = 'table';
const OFFICES_FETCH_LIMIT = 200;
const DEPARTMENTS_PER_PAGE = 5;
const FACILITIES_PER_PAGE = 5;
const OFFICE_SECTIONS = [
    { key: 'academic', title: 'Academic Departments', icon: 'fa-graduation-cap', emptyText: 'No academic departments found.' },
    { key: 'administrative', title: 'Administrative Offices', icon: 'fa-building', emptyText: 'No administrative offices found.' },
    { key: 'executive', title: 'Executive Offices', icon: 'fa-crown', emptyText: 'No executive offices found.' },
];
let activeOfficeTab = 'academic';
let officePage = 1;
let officeTotalPages = 1;
let facilityPage = 1;
let facilityTotalPages = 1;
let officeSearchDebounce = null;
const officeTypeTabButtons = () => Array.from(document.querySelectorAll('[data-office-tab]'));

function isDetailCardView() {
    return facilityCardSwap?.dataset.view === 'detail';
}

function setCardSwapView(mode) {
    const next = mode === 'detail' ? 'detail' : 'list';
    if (facilityCardSwap) facilityCardSwap.dataset.view = next;
    if (officesView) {
        const showList = next === 'list';
        officesView.classList.toggle('is-active', showList);
        officesView.setAttribute('aria-hidden', showList ? 'false' : 'true');
        officesView.hidden = !showList;
    }
    if (facilitiesView) {
        const showDetail = next === 'detail';
        facilitiesView.classList.toggle('is-active', showDetail);
        facilitiesView.setAttribute('aria-hidden', showDetail ? 'false' : 'true');
        facilitiesView.hidden = !showDetail;
    }
}

function updateUrlState(mode = 'replace') {
    if (suppressUrlStateWrite) return;
    const params = new URLSearchParams(window.location.search);
    const view = isDetailCardView() ? 'facilities' : 'offices';
    params.set('view', view);
    if (officeSearchInput?.value?.trim()) params.set('dept_q', officeSearchInput.value.trim());
    else params.delete('dept_q');
    params.delete('dept_sort');
    params.delete('fac_q');
    params.delete('fac_sort');
    if (currentOffice && currentOffice.id) {
        params.set('office_id', String(currentOffice.id));
        params.set('office_name', currentOffice.name || '');
    } else {
        params.delete('office_id');
        params.delete('office_name');
    }
    params.delete('dept_layout');
    if (view === 'offices') {
        if (activeOfficeTab && activeOfficeTab !== 'academic') params.set('dept_tab', activeOfficeTab);
        else params.delete('dept_tab');
        if (officePage > 1) params.set('dept_page', String(officePage));
        else params.delete('dept_page');
    } else {
        params.delete('dept_tab');
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
        deptTab: normalizeOfficeType(params.get('dept_tab') || '') || 'academic',
        deptPage: Math.max(1, parseInt(params.get('dept_page') || '1', 10) || 1),
    };
}

function getActiveOfficeSection() {
    return OFFICE_SECTIONS.find((section) => section.key === activeOfficeTab) || OFFICE_SECTIONS[0];
}

function getOfficesForActiveTab() {
    return allOffices.filter((dept) => {
        const type = normalizeOfficeType(dept.type);
        const bucket = type || 'academic';
        return bucket === activeOfficeTab;
    });
}

function syncOfficeTabUi() {
    officeTypeTabButtons().forEach((btn) => {
        const isActive = btn.dataset.officeTab === activeOfficeTab;
        btn.classList.toggle('is-active', isActive);
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
}

function switchOfficeTab(tabKey, options = {}) {
    const nextTab = normalizeOfficeType(tabKey) || 'academic';
    const validTab = OFFICE_SECTIONS.some((section) => section.key === nextTab) ? nextTab : 'academic';
    activeOfficeTab = validTab;
    if (options.resetPage !== false) officePage = 1;
    syncOfficeTabUi();
    renderOffices(allOffices);
    if (!options.skipUrl && !suppressUrlStateWrite) updateUrlState('replace');
}

function normalizeOfficeType(type) {
    const value = String(type ?? '').trim().toLowerCase();
    if (value === 'academics' || value === 'academic') return 'academic';
    if (value === 'administrative') return 'administrative';
    if (value === 'executive') return 'executive';
    return '';
}

function formatOfficeTypeLabel(type) {
    const raw = String(type ?? '').trim();
    if (!raw) return '—';
    const key = normalizeOfficeType(raw);
    if (key === 'academic') return 'Academic';
    if (key === 'administrative') return 'Administrative';
    if (key === 'executive') return 'Executive';
    return raw;
}

function renderOfficeTypeBadge(type) {
    const key = normalizeOfficeType(type);
    if (!key) {
        return '<span class="office-type-badge office-type-badge--unknown">—</span>';
    }
    const label = formatOfficeTypeLabel(type);
    return `<span class="office-type-badge office-type-badge--${key}">${escapeHtml(label)}</span>`;
}

function applyOfficeListMode() {
    officeListMode = 'table';
    if (officeGrid) officeGrid.classList.add('hidden');
    if (officeTablePanel) officeTablePanel.classList.remove('hidden');
}

function formatOfficeSummaryMeta(summary) {
    const parts = [];
    if (summary.academic > 0) parts.push(`${summary.academic} Academic`);
    if (summary.administrative > 0) parts.push(`${summary.administrative} Administrative`);
    if (summary.executive > 0) parts.push(`${summary.executive} Executive`);
    return parts.length ? parts.join(' · ') : 'No offices yet';
}

function renderFacilitySummary(summary) {
    const officesEl = document.getElementById('facilitySummaryOffices');
    const officesMetaEl = document.getElementById('facilitySummaryOfficesMeta');
    const labsEl = document.getElementById('facilitySummaryLabs');
    const roomsEl = document.getElementById('facilitySummaryRooms');
    const totalEl = document.getElementById('facilitySummaryTotal');
    if (!officesEl || !officesMetaEl || !labsEl || !roomsEl || !totalEl) return;
    officesEl.textContent = String(summary.total_offices ?? 0);
    officesMetaEl.textContent = formatOfficeSummaryMeta(summary);
    labsEl.textContent = String(summary.total_labs ?? 0);
    roomsEl.textContent = String(summary.total_rooms ?? 0);
    totalEl.textContent = String(summary.total_facilities ?? 0);
}

async function loadFacilitySummary() {
    try {
        const form = new FormData();
        form.append('action', 'get_summary');
        const res = await fetch('../../app/api/facility_management.php', { method: 'POST', body: form, credentials: 'include' });
        const data = await res.json();
        if (data.success && data.summary) {
            renderFacilitySummary(data.summary);
        }
    } catch (err) {
        console.error(err);
    }
}

function openFmSuccessModal(title, message) {
    if (fmSuccessTitle) fmSuccessTitle.textContent = title;
    if (fmSuccessMessage) fmSuccessMessage.textContent = message;
    fmSuccessModal?.classList.add('is-open');
    fmSuccessModal?.setAttribute('aria-hidden', 'false');
    fmSuccessOk?.focus();
}

function closeFmSuccessModal() {
    fmSuccessModal?.classList.remove('is-open');
    fmSuccessModal?.setAttribute('aria-hidden', 'true');
}

function closeFmDeleteModal() {
    fmDeleteModal?.classList.remove('is-open');
    fmDeleteModal?.setAttribute('aria-hidden', 'true');
    pendingDelete = null;
    if (fmDeleteConfirm) fmDeleteConfirm.disabled = false;
}

function openFmDeleteModal(type, id, name) {
    pendingDelete = { type, id: String(id), name: name || '' };
    const isOffice = type === 'office';
    if (fmDeleteTitle) {
        fmDeleteTitle.textContent = isOffice ? 'Delete this department?' : 'Delete this facility?';
    }
    if (fmDeleteDesc) {
        fmDeleteDesc.textContent = isOffice
            ? 'This will permanently remove the department from your list. Departments with facilities cannot be deleted.'
            : 'This will permanently remove the facility from this department. This action cannot be undone.';
    }
    if (fmDeleteName) fmDeleteName.textContent = name || (isOffice ? 'this department' : 'this facility');
    if (fmDeleteConfirmLabel) {
        fmDeleteConfirmLabel.textContent = 'Delete';
    }
    fmDeleteModal?.classList.add('is-open');
    fmDeleteModal?.setAttribute('aria-hidden', 'false');
    fmDeleteCancel?.focus();
}

async function confirmFmDelete() {
    if (!pendingDelete || !fmDeleteConfirm) return;
    fmDeleteConfirm.disabled = true;
    const { type, id } = pendingDelete;
    try {
        const form = new FormData();
        form.append('action', type === 'office' ? 'delete_office' : 'delete_facility');
        form.append(type === 'office' ? 'office_id' : 'facility_id', id);
        const res = await fetch('../../app/api/facility_management.php', { method: 'POST', body: form, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            closeFmDeleteModal();
            if (type === 'office') {
                loadOffices();
                loadFacilitySummary();
            } else if (currentOffice?.id) {
                loadFacilities(currentOffice.id);
                loadOffices();
                loadFacilitySummary();
            }
            openFmSuccessModal(
                type === 'office' ? 'Department Deleted' : 'Facility Deleted',
                data.message || (type === 'office' ? 'Department deleted successfully.' : 'Facility deleted successfully.')
            );
        } else {
            showToast(data.message, 'error');
            fmDeleteConfirm.disabled = false;
        }
    } catch (err) {
        console.error(err);
        showToast(type === 'office' ? 'Error deleting department' : 'Error deleting facility', 'error');
        fmDeleteConfirm.disabled = false;
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

function appendOfficeSpacerRows(count) {
    if (!officeTableBody || count <= 0) return;
    for (let i = 0; i < count; i++) {
        const spacerRow = document.createElement('tr');
        spacerRow.className = 'office-row-spacer';
        spacerRow.setAttribute('aria-hidden', 'true');
        spacerRow.innerHTML = '<td colspan="7"></td>';
        officeTableBody.appendChild(spacerRow);
    }
}

function appendFacilitySpacerRows(count) {
    if (!facilityTableBody || count <= 0) return;
    for (let i = 0; i < count; i++) {
        const spacerRow = document.createElement('tr');
        spacerRow.className = 'facility-row-spacer';
        spacerRow.setAttribute('aria-hidden', 'true');
        spacerRow.innerHTML = '<td colspan="7"></td>';
        facilityTableBody.appendChild(spacerRow);
    }
}

function updateOfficePagination(totalItems, totalPages) {
    officeTotalPages = Math.max(1, totalPages || 1);
    const prev = document.getElementById('officePrevPageBtn');
    const next = document.getElementById('officeNextPageBtn');
    const pagination = document.getElementById('officePagination');
    if (!prev || !next) return;

    const showPagination = totalItems > DEPARTMENTS_PER_PAGE;
    if (pagination) pagination.hidden = !showPagination;
    prev.disabled = !showPagination || officePage <= 1;
    next.disabled = !showPagination || officePage >= officeTotalPages || totalItems === 0;
}

function setupOfficePagination() {
    const prev = document.getElementById('officePrevPageBtn');
    const next = document.getElementById('officeNextPageBtn');
    if (!prev || !next) return;
    prev.addEventListener('click', () => {
        if (officePage > 1) {
            officePage -= 1;
            renderOffices(allOffices);
            if (!suppressUrlStateWrite) updateUrlState('replace');
        }
    });
    next.addEventListener('click', () => {
        if (officePage < officeTotalPages) {
            officePage += 1;
            renderOffices(allOffices);
            if (!suppressUrlStateWrite) updateUrlState('replace');
        }
    });
}

function setupOfficeTypeTabs() {
    officeTypeTabButtons().forEach((btn) => {
        btn.addEventListener('click', () => {
            switchOfficeTab(btn.dataset.officeTab || 'academic');
        });
    });
}

function setOfficeLoadingState(message) {
    if (officeGrid) {
        officeGrid.innerHTML = `<div class="office-grid-loading">${message}</div>`;
    }
    if (officeTableBody) {
        officeTableBody.innerHTML = `<tr><td colspan="7" class="loading-cell office-status-row">${message}</td></tr>`;
        appendOfficeSpacerRows(DEPARTMENTS_PER_PAGE - 1);
    }
}

// Load offices (search filters server-side; tab + page handled client-side)
async function loadOffices(options = {}) {
    if (options.resetPage !== false) officePage = 1;
    setOfficeLoadingState('Loading Offices...');
    try {
        const form = new FormData();
        form.append('action', 'list_offices');
        form.append('page', '1');
        form.append('per_page', String(OFFICES_FETCH_LIMIT));
        form.append('q', (officeSearchInput?.value || '').trim());
        const res = await fetch('../../app/api/facility_management.php', { method: 'POST', body: form, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            allOffices = data.offices || [];
            renderOffices(allOffices);
            if (!suppressUrlStateWrite) updateUrlState('replace');
        } else {
            setOfficeLoadingState('Failed to load offices');
            updateOfficePagination(0, 1);
        }
    } catch (err) {
        console.error(err);
        setOfficeLoadingState('Error loading offices');
        updateOfficePagination(0, 1);
    }
}

function renderOfficeGrid(offices) {
    if (!officeGrid) return;
    officeGrid.innerHTML = '';
    if(offices.length === 0){
        officeGrid.innerHTML = '<div class="office-grid-loading">No offices found.</div>';
        return;
    }

    offices.forEach((dept, index) => {
        const card = document.createElement('article');
        card.classList.add('office-card');
        card.tabIndex = 0;
        card.dataset.id = dept.office_id;
        card.dataset.name = dept.office_name;
        const total = Number(dept.total_labs || 0) + Number(dept.total_rooms || 0);
        const safeName = escapeHtml(dept.office_name);
        const officeType = escapeHtml(formatOfficeTypeLabel(dept.type));
        const photo = (dept.photo_url || '').trim();
        const photoHtml = photo
            ? `<img src="../../${escapeHtml(photo)}" alt="${safeName} logo" class="office-card-logo">`
            : `<div class="office-card-logo office-card-logo-fallback"><i class="fas fa-building"></i></div>`;
        card.innerHTML = `
            <div class="office-card-head">
                <span class="office-card-index">#${index + 1}</span>
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
                <button type="button" class="action-btn delete" data-id="${dept.office_id}" data-name="${safeName}"><i class="fas fa-trash"></i></button>
            </div>
        `;
        officeGrid.appendChild(card);
    });
}

function buildOfficeDataRow(dept, rowNum) {
    const tr = document.createElement('tr');
    tr.classList.add('clickable-row');
    tr.tabIndex = 0;
    tr.dataset.id = dept.office_id;
    tr.dataset.name = dept.office_name;
    const total = Number(dept.total_labs || 0) + Number(dept.total_rooms || 0);
    const safeName = escapeHtml(dept.office_name);
    const photo = (dept.photo_url || '').trim();
    const thumb = photo
        ? `<img src="../../${escapeHtml(photo)}" alt="" class="dept-table-thumb">`
        : `<span class="dept-table-thumb dept-table-thumb-fallback"><i class="fas fa-building"></i></span>`;
    tr.innerHTML = `
        <td>${rowNum}</td>
        <td class="dept-name-cell"><span class="dept-name-inner">${thumb}<span class="dept-table-name">${safeName}</span></span></td>
        <td>${renderOfficeTypeBadge(dept.type)}</td>
        <td>${dept.total_labs || 0}</td>
        <td>${dept.total_rooms || 0}</td>
        <td>${total}</td>
        <td class="office-action-cell">
            <button type="button" class="action-btn view" data-id="${dept.office_id}" data-name="${safeName}">View</button>
            <button type="button" class="action-btn edit" data-id="${dept.office_id}" data-name="${safeName}"><i class="fas fa-edit"></i></button>
            <button type="button" class="action-btn delete" data-id="${dept.office_id}" data-name="${safeName}"><i class="fas fa-trash"></i></button>
        </td>
    `;
    return tr;
}

function renderOfficeDataTable() {
    if (!officeTableBody) return;
    officeTableBody.innerHTML = '';
    const section = getActiveOfficeSection();
    const filtered = getOfficesForActiveTab();
    const totalPages = Math.max(1, Math.ceil(filtered.length / DEPARTMENTS_PER_PAGE) || 1);

    if (officePage > totalPages) officePage = totalPages;
    if (officePage < 1) officePage = 1;

    if (filtered.length === 0) {
        officeTableBody.innerHTML = `<tr><td colspan="7" class="loading-cell office-status-row">${section.emptyText}</td></tr>`;
        appendOfficeSpacerRows(DEPARTMENTS_PER_PAGE - 1);
        updateOfficePagination(0, 1);
        return;
    }

    const start = (officePage - 1) * DEPARTMENTS_PER_PAGE;
    const pageItems = filtered.slice(start, start + DEPARTMENTS_PER_PAGE);

    pageItems.forEach((dept, index) => {
        officeTableBody.appendChild(buildOfficeDataRow(dept, start + index + 1));
    });

    appendOfficeSpacerRows(DEPARTMENTS_PER_PAGE - pageItems.length);
    updateOfficePagination(filtered.length, totalPages);
}

function renderOffices(offices) {
    if (officeListMode === 'table') {
        renderOfficeDataTable();
        if (officeGrid) officeGrid.innerHTML = '';
    } else {
        renderOfficeGrid(offices);
        if (officeTableBody) officeTableBody.innerHTML = '';
    }
}

function showOfficesView(options = {}) {
    const reload = options.reload !== false;
    setCardSwapView('list');
    currentOffice = null;
    if (reload) loadOffices();
    else if (!suppressUrlStateWrite) updateUrlState('replace');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function getOfficeInitials(name) {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return String(name || '—').replace(/\s+/g, '').substring(0, 2).toUpperCase() || '—';
}

function renderOfficeDetailHeader(office) {
    const name = office?.office_name || office?.name || 'Department';
    if (facilityViewTitle) facilityViewTitle.textContent = name;
    const photo = (office?.photo_url || '').trim();
    if (photo && officeDetailLogoImg) {
        officeDetailLogoImg.src = `../../${photo}`;
        officeDetailLogoImg.hidden = false;
        if (officeDetailLogoInitials) officeDetailLogoInitials.hidden = true;
    } else {
        if (officeDetailLogoImg) {
            officeDetailLogoImg.hidden = true;
            officeDetailLogoImg.removeAttribute('src');
        }
        if (officeDetailLogoInitials) {
            officeDetailLogoInitials.textContent = getOfficeInitials(name);
            officeDetailLogoInitials.hidden = false;
        }
    }
    if (officeDetailTypeBadge) {
        officeDetailTypeBadge.innerHTML = renderOfficeTypeBadge(office?.type);
    }
}

function updateOfficeDetailStats(facilities) {
    const list = facilities || [];
    const labCount = list.filter((f) => String(f.laboratory || '').trim() !== '').length;
    const roomCount = list.filter((f) => String(f.room || '').trim() !== '').length;
    const total = list.length;
    if (officeDetailLabs) officeDetailLabs.textContent = String(labCount);
    if (officeDetailRooms) officeDetailRooms.textContent = String(roomCount);
    if (officeDetailTotal) officeDetailTotal.textContent = String(total);
}

function getFacilityDisplayName(facility) {
    return String(facility?.laboratory || facility?.room || '').trim() || '—';
}

function getFacilityKindLabel(facility) {
    if (String(facility?.laboratory || '').trim()) return 'Laboratory';
    if (String(facility?.room || '').trim()) return 'Room';
    return facility?.type || '—';
}

function renderFacilityKindBadge(facility) {
    const label = getFacilityKindLabel(facility);
    if (!label || label === '—') return '—';
    const isRoom = label === 'Room';
    const cls = isRoom ? 'facility-kind-badge--room' : 'facility-kind-badge--lab';
    return `<span class="facility-kind-badge ${cls}"><span class="facility-kind-badge__dot" aria-hidden="true"></span>${escapeHtml(label)}</span>`;
}

function renderFacilityCodeBadge(code) {
    const value = String(code || '').trim();
    if (!value) return '—';
    return `<span class="facility-code-badge">${escapeHtml(value)}</span>`;
}

function showFacilitiesView(deptId, deptName, options = {}) {
    const historyMode = options.historyMode || 'push';
    const office = allOffices.find((d) => String(d.office_id) === String(deptId)) || {
        office_id: deptId,
        office_name: deptName,
        type: '',
        photo_url: '',
    };
    currentOffice = {
        id: deptId,
        name: office.office_name || deptName,
        type: office.type,
        photo_url: office.photo_url,
    };
    renderOfficeDetailHeader(office);
    setCardSwapView('detail');
    if (historyMode !== 'none') {
        updateUrlState(historyMode);
    }
    loadFacilities(deptId);
}

async function loadFacilities(deptId) {
    if (!facilityTableBody) return;
    facilityTableBody.innerHTML = '<tr><td colspan="7" class="loading-cell">Loading...</td></tr>';
    try {
        const form = new FormData();
        form.append('action', 'list_facilities');
        form.append('office_id', deptId);
        const res = await fetch('../../app/api/facility_management.php', { method: 'POST', body: form, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            allFacilities = data.facilities || [];
            facilityPage = 1;
            updateOfficeDetailStats(allFacilities);
            renderFacilityTable(allFacilities);
        } else {
            facilityTableBody.innerHTML = '<tr><td colspan="7" class="loading-cell">Failed to load facilities</td></tr>';
            updateOfficeDetailStats([]);
            updateFacilityPaginationControls(0);
        }
    } catch (err) {
        console.error(err);
        facilityTableBody.innerHTML = '<tr><td colspan="7" class="loading-cell">Error loading facilities</td></tr>';
        updateOfficeDetailStats([]);
        updateFacilityPaginationControls(0);
    }
}

function updateFacilityPaginationControls(totalItems) {
    facilityTotalPages = Math.max(1, Math.ceil(totalItems / FACILITIES_PER_PAGE) || 1);
    if (facilityPage > facilityTotalPages) facilityPage = facilityTotalPages;
    if (facilityPage < 1) facilityPage = 1;

    const showPagination = totalItems > FACILITIES_PER_PAGE;
    if (facilityPagination) facilityPagination.hidden = !showPagination;
    if (facilityPrevPageBtn) facilityPrevPageBtn.disabled = !showPagination || facilityPage <= 1;
    if (facilityNextPageBtn) facilityNextPageBtn.disabled = !showPagination || facilityPage >= facilityTotalPages;
}

function renderFacilityTable(facilities) {
    if (!facilityTableBody) return;
    facilityTableBody.innerHTML = '';
    const list = facilities || [];
    updateFacilityPaginationControls(list.length);

    if (list.length === 0) {
        facilityTableBody.innerHTML = '<tr><td colspan="7" class="loading-cell office-status-row">No facilities found.</td></tr>';
        return;
    }

    const start = (facilityPage - 1) * FACILITIES_PER_PAGE;
    const pageItems = list.slice(start, start + FACILITIES_PER_PAGE);

    pageItems.forEach((f, index) => {
        const tr = document.createElement('tr');
        const facilityLabel = [getFacilityDisplayName(f), f.building, f.code].filter((v) => v && v !== '—').join(' · ') || 'this facility';
        const safeLabel = escapeHtml(facilityLabel);
        tr.innerHTML = `
            <td class="facility-index-cell">${start + index + 1}</td>
            <td class="facility-name-cell">${escapeHtml(getFacilityDisplayName(f))}</td>
            <td>${renderFacilityKindBadge(f)}</td>
            <td>${renderFacilityCodeBadge(f.code)}</td>
            <td class="facility-muted-cell">${escapeHtml(f.building || '—')}</td>
            <td class="facility-muted-cell">${escapeHtml(f.floor || '—')}</td>
            <td class="office-action-cell">
                <div class="facility-action-group">
                    <button type="button" class="action-btn edit-facility facility-edit-btn" data-id="${f.facility_id}" aria-label="Edit facility"><i class="fas fa-edit" aria-hidden="true"></i></button>
                    <button type="button" class="action-btn delete-facility facility-delete-btn" data-id="${f.facility_id}" data-name="${safeLabel}" aria-label="Delete facility"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
                </div>
            </td>
        `;
        facilityTableBody.appendChild(tr);
    });
    appendFacilitySpacerRows(FACILITIES_PER_PAGE - pageItems.length);
}

// Escape helper
function escapeHtml(str){ if(!str) return ''; return String(str).replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }

function collectKnownFacilityTypes() {
    const types = new Set(['Laboratory', 'Room']);
    allFacilities.forEach((f) => {
        const t = String(f.type || '').trim();
        if (t) types.add(t);
    });
    localExtraFacilityTypes.forEach((t) => {
        if (t) types.add(t);
    });
    return Array.from(types);
}

function populateFacilityTypeKindSelect() {
    if (!facilityTypeKind) return;
    const customs = collectKnownFacilityTypes().filter((t) => t !== 'Laboratory' && t !== 'Room');
    facilityTypeKind.innerHTML = `
        <option value="">Select facility type</option>
        <option value="Laboratory">Laboratory</option>
        <option value="Room">Room</option>
        ${customs.map((t) => `<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`).join('')}
        <option value="__new__">+ Add new type...</option>
    `;
}

function onFacilityTypeKindChange() {
    if (!facilityTypeKind || !facilityNameInput) return;
    const value = facilityTypeKind.value;
    const isNew = value === '__new__';
    if (facilityNewTypeGroup) facilityNewTypeGroup.classList.toggle('hidden', !isNew);
    if (isNew) {
        facilityNameInput.disabled = false;
        facilityNameInput.placeholder = 'e.g. Hall A';
        facilityNewTypeInput?.focus();
        return;
    }
    if (!value) {
        facilityNameInput.disabled = true;
        facilityNameInput.placeholder = 'Select a type first';
        facilityNameInput.value = '';
        return;
    }
    facilityNameInput.disabled = false;
    if (value === 'Laboratory') facilityNameInput.placeholder = 'e.g. Skills Lab';
    else if (value === 'Room') facilityNameInput.placeholder = 'e.g. Lecture Room 201';
    else facilityNameInput.placeholder = 'e.g. Hall A';
}

function applyFacilityFormHiddenFields() {
    const kind = facilityTypeKind?.value || '';
    const name = (facilityNameInput?.value || '').trim();
    let type = '';
    if (facilityLaboratory) facilityLaboratory.value = '';
    if (facilityRoom) facilityRoom.value = '';
    if (kind === 'Laboratory') {
        type = 'Laboratory';
        if (facilityLaboratory) facilityLaboratory.value = name;
    } else if (kind === 'Room') {
        type = 'Room';
        if (facilityRoom) facilityRoom.value = name;
    } else if (kind === '__new__') {
        type = (facilityNewTypeInput?.value || '').trim();
        if (facilityRoom) facilityRoom.value = name;
        if (type && !localExtraFacilityTypes.includes(type)) {
            localExtraFacilityTypes.push(type);
            localStorage.setItem('fm_custom_facility_types', JSON.stringify(localExtraFacilityTypes));
        }
    } else if (kind) {
        type = kind;
        if (facilityRoom) facilityRoom.value = name;
    }
    if (facilityType) facilityType.value = type;
}

function setFacilityModalMode(isEdit) {
    const titleEl = document.getElementById('facilityModalTitle');
    if (titleEl) titleEl.textContent = isEdit ? 'Edit facility' : 'Add facility';
    if (facilityModalSubtitle) {
        facilityModalSubtitle.textContent = isEdit
            ? 'Update this room or lab.'
            : 'Add a room or lab to this office.';
    }
    if (saveFacilityBtn) saveFacilityBtn.textContent = isEdit ? 'Save changes' : 'Add facility';
}

function closeFacilityModalView() {
    facilityModal?.classList.remove('show');
}

function openAddFacilityModal() {
    facilityForm?.reset();
    if (facilityIdInput) facilityIdInput.value = '';
    if (facilityDeptIdInput) facilityDeptIdInput.value = currentOffice ? currentOffice.id : '';
    if (facilityLaboratory) facilityLaboratory.value = '';
    if (facilityRoom) facilityRoom.value = '';
    if (facilityType) facilityType.value = '';
    populateFacilityTypeKindSelect();
    if (facilityTypeKind) facilityTypeKind.value = '';
    if (facilityNewTypeInput) facilityNewTypeInput.value = '';
    onFacilityTypeKindChange();
    setFacilityModalMode(false);
    facilityModal?.classList.add('show');
}

function openEditFacilityModal(facility) {
    if (!facility) return;
    facilityForm?.reset();
    if (facilityIdInput) facilityIdInput.value = facility.facility_id;
    if (facilityDeptIdInput) facilityDeptIdInput.value = facility.office_id || currentOffice?.id || '';
    if (facilityBuilding) facilityBuilding.value = facility.building || '';
    if (facilityCode) facilityCode.value = facility.code || '';
    if (facilityFloor) facilityFloor.value = facility.floor || '';
    populateFacilityTypeKindSelect();

    const labName = String(facility.laboratory || '').trim();
    const roomName = String(facility.room || '').trim();
    const type = String(facility.type || '').trim();
    let kind = type;
    if (type === 'Laboratory') kind = 'Laboratory';
    else if (type === 'Room') kind = 'Room';

    if (facilityTypeKind) facilityTypeKind.value = kind;
    if (facilityNewTypeGroup) facilityNewTypeGroup.classList.add('hidden');
    if (facilityNewTypeInput) facilityNewTypeInput.value = '';
    onFacilityTypeKindChange();
    if (facilityNameInput) facilityNameInput.value = labName || roomName;
    if (facilityLaboratory) facilityLaboratory.value = labName;
    if (facilityRoom) facilityRoom.value = roomName;
    if (facilityType) facilityType.value = type;
    setFacilityModalMode(true);
    facilityModal?.classList.add('show');
}

let officeLogoObjectUrl = null;

function setOfficeLogoPreview(src) {
    if (!officeLogoImage || !officeLogoPlaceholder) return;
    if (src) {
        officeLogoImage.src = src;
        officeLogoImage.hidden = false;
        officeLogoPlaceholder.hidden = true;
    } else {
        officeLogoImage.hidden = true;
        officeLogoImage.removeAttribute('src');
        officeLogoPlaceholder.hidden = false;
    }
}

function clearOfficeLogoObjectUrl() {
    if (officeLogoObjectUrl) {
        URL.revokeObjectURL(officeLogoObjectUrl);
        officeLogoObjectUrl = null;
    }
}

function setOfficeModalMode(isEdit, office = null) {
    const titleEl = document.getElementById('modalTitle');
    if (titleEl) titleEl.textContent = isEdit ? 'Edit office' : 'Add office';
    if (officeModalSubtitle) {
        officeModalSubtitle.textContent = isEdit
            ? 'Update this office, then manage its rooms and labs.'
            : 'Create an office, then add its rooms and labs.';
    }
    if (saveOfficeBtn) saveOfficeBtn.textContent = isEdit ? 'Save changes' : 'Save office';
    clearOfficeLogoObjectUrl();
    if (isEdit && office?.photo_url) {
        setOfficeLogoPreview(`../../${office.photo_url}`);
    } else {
        setOfficeLogoPreview(null);
    }
}

function closeOfficeModalView() {
    officeModal.classList.remove('show');
    clearOfficeLogoObjectUrl();
}

// Modal handling
addOfficeBtn.addEventListener('click', () => {
    officeForm.reset();
    officeIdInput.value = '';
    setOfficeModalMode(false);
    officeModal.classList.add('show');
});
closeOfficeModal.addEventListener('click', closeOfficeModalView);
cancelOfficeBtn?.addEventListener('click', closeOfficeModalView);
window.addEventListener('click', e => { if (e.target === officeModal) closeOfficeModalView(); });

officePhotoInput?.addEventListener('change', () => {
    clearOfficeLogoObjectUrl();
    const file = officePhotoInput.files?.[0];
    if (file) {
        officeLogoObjectUrl = URL.createObjectURL(file);
        setOfficeLogoPreview(officeLogoObjectUrl);
    } else {
        setOfficeLogoPreview(null);
    }
});

addFacilityBtn?.addEventListener('click', openAddFacilityModal);
closeFacilityModal?.addEventListener('click', closeFacilityModalView);
cancelFacilityBtn?.addEventListener('click', closeFacilityModalView);
facilityTypeKind?.addEventListener('change', onFacilityTypeKindChange);
window.addEventListener('click', (e) => { if (e.target === facilityModal) closeFacilityModalView(); });

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
        if (data.success) {
            closeOfficeModalView();
            loadOffices();
            loadFacilitySummary();
            openFmSuccessModal(
                isEdit ? 'Office Updated' : 'Office Added',
                data.message || (isEdit ? 'Office updated successfully.' : 'Office added successfully.')
            );
        } else {
            showToast(data.message, 'error');
        }
    }catch(err){ console.error(err); showToast('Error saving office','error'); }
});

// Facility form submit
facilityForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const kind = facilityTypeKind?.value || '';
    if (!kind) {
        showToast('Please select a facility type.', 'error');
        facilityTypeKind?.focus();
        return;
    }
    if (kind === '__new__' && !(facilityNewTypeInput?.value || '').trim()) {
        showToast('Please enter a new type name.', 'error');
        facilityNewTypeInput?.focus();
        return;
    }
    if (!(facilityNameInput?.value || '').trim()) {
        showToast('Please enter a facility name.', 'error');
        facilityNameInput?.focus();
        return;
    }
    applyFacilityFormHiddenFields();
    if (!facilityType?.value) {
        showToast('Please select a facility type.', 'error');
        return;
    }
    const form = new FormData(facilityForm);
    const isEdit = !!facilityIdInput?.value;
    form.append('action', isEdit ? 'edit_facility' : 'add_facility');
    if (isEdit) form.append('facility_id', facilityIdInput.value);
    form.append('office_id', facilityDeptIdInput.value);
    try {
        const res = await fetch('../../app/api/facility_management.php', { method: 'POST', body: form, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            closeFacilityModalView();
            loadFacilities(facilityDeptIdInput.value);
            loadOffices();
            loadFacilitySummary();
            openFmSuccessModal(
                isEdit ? 'Facility Updated' : 'Facility Added',
                data.message || (isEdit ? 'Facility updated successfully.' : 'Facility added successfully.')
            );
        } else {
            showToast(data.message, 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Error saving facility', 'error');
    }
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
        const type = office?.type || '';
        officeForm.reset();
        officeIdInput.value = id;
        officeNameInput.value = name;
        if (officeTypeInput) officeTypeInput.value = type;
        setOfficeModalMode(true, office);
        officeModal.classList.add('show');
    }

    if (deleteBtn) {
        const office = allOffices.find((o) => String(o.office_id) === String(deleteBtn.dataset.id));
        const name = office?.office_name || deleteBtn.dataset.name || '';
        openFmDeleteModal('office', deleteBtn.dataset.id, name);
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
facilityTableBody?.addEventListener('click', (e) => {
    const editBtn = e.target.closest('.edit-facility');
    if (editBtn) {
        const facility = allFacilities.find((f) => String(f.facility_id) === String(editBtn.dataset.id));
        if (facility) openEditFacilityModal(facility);
        return;
    }

    const deleteBtn = e.target.closest('.delete-facility');
    if (deleteBtn) {
        const facility = allFacilities.find((f) => String(f.facility_id) === String(deleteBtn.dataset.id));
        const label = facility
            ? [getFacilityDisplayName(facility), facility.building, facility.code].filter((v) => v && v !== '—').join(' · ') || facility.code
            : deleteBtn.dataset.name || '';
        openFmDeleteModal('facility', deleteBtn.dataset.id, label);
    }
});

fmDeleteBackdrop?.addEventListener('click', closeFmDeleteModal);
fmDeleteClose?.addEventListener('click', closeFmDeleteModal);
fmDeleteCancel?.addEventListener('click', closeFmDeleteModal);
fmDeleteConfirm?.addEventListener('click', confirmFmDelete);
fmSuccessBackdrop?.addEventListener('click', closeFmSuccessModal);
fmSuccessOk?.addEventListener('click', closeFmSuccessModal);

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (fmDeleteModal?.classList.contains('is-open')) closeFmDeleteModal();
    else if (fmSuccessModal?.classList.contains('is-open')) closeFmSuccessModal();
    else if (facilityModal?.classList.contains('show')) closeFacilityModalView();
});

backToOfficesBtn?.addEventListener('click', () => showOfficesView());

facilityPrevPageBtn?.addEventListener('click', () => {
    if (facilityPage > 1) {
        facilityPage -= 1;
        renderFacilityTable(allFacilities);
    }
});

facilityNextPageBtn?.addEventListener('click', () => {
    if (facilityPage < facilityTotalPages) {
        facilityPage += 1;
        renderFacilityTable(allFacilities);
    }
});

// Event listeners for office search and sort (server-side)
officeSearchInput?.addEventListener('input', () => {
    clearTimeout(officeSearchDebounce);
    officeSearchDebounce = setTimeout(() => loadOffices({ resetPage: true }), 350);
});
// Initial load
loadUsersForHead();
setupOfficePagination();
setupOfficeTypeTabs();
const urlState = readUrlState();
activeOfficeTab = urlState.deptTab;
officePage = urlState.deptPage;
if (officeSearchInput) officeSearchInput.value = urlState.officeSearch || '';
applyOfficeListMode();
syncOfficeTabUi();
setCardSwapView('list');
loadFacilitySummary();
loadOffices().then(() => {
    if (urlState.view === 'facilities' && urlState.officeId) {
        const matched = allOffices.find((d) => String(d.office_id) === String(urlState.officeId));
        const deptName = matched ? matched.office_name : (urlState.officeName || 'Facilities');
        showFacilitiesView(urlState.officeId, deptName, { historyMode: 'none' });
    } else {
        showOfficesView({ reload: false });
    }
    suppressUrlStateWrite = false;
    updateUrlState('replace');
});

window.addEventListener('popstate', () => {
    const state = readUrlState();
    const prevSuppress = suppressUrlStateWrite;
    suppressUrlStateWrite = true;
    if (officeSearchInput) officeSearchInput.value = state.officeSearch || '';
    activeOfficeTab = state.deptTab;
    officePage = state.deptPage;
    applyOfficeListMode();
    syncOfficeTabUi();
    if (state.view === 'facilities' && state.officeId) {
        const matched = allOffices.find((d) => String(d.office_id) === String(state.officeId));
        const deptName = matched ? matched.office_name : (state.officeName || 'Facilities');
        showFacilitiesView(state.officeId, deptName, { historyMode: 'none' });
    } else {
        showOfficesView({ reload: true });
    }
    suppressUrlStateWrite = prevSuppress;
});

});
