document.addEventListener('DOMContentLoaded', () => {

const inventoryTableBody = document.getElementById('inventoryTableBody');
const inventoryModal = document.getElementById('inventoryModal');
const componentModal = document.getElementById('componentModal');
const detailModal = document.getElementById('detailModal');
const closeInventoryModal = document.getElementById('closeInventoryModal');
const closeComponentModal = document.getElementById('closeComponentModal');
const closeDetailModal = document.getElementById('closeDetailModal');
const addInventoryBtn = document.getElementById('addInventoryBtn');
const addComponentBtn = document.getElementById('addComponentBtn');
const inventoryForm = document.getElementById('inventoryForm');
const componentForm = document.getElementById('componentForm');

function escHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/** Parent row summarizes the first part; omit that same row from sub-lines (by lowest component_id). */
function filterComponentsForTableSubrows(components) {
    if (!Array.isArray(components) || components.length === 0) return [];
    const ids = components.map((c) => Number(c.component_id)).filter((n) => Number.isFinite(n));
    if (ids.length === 0) return components;
    const minId = Math.min(...ids);
    return components.filter((c) => Number(c.component_id) !== minId);
}

let allInventories = [];
let detailInventoryRecordName = '';

// Form inputs
const inventoryIdInput = document.getElementById('inventory_id');
const requestIdInput = document.getElementById('request_id');
const nameInput = document.getElementById('name');
const itemCodeInput = document.getElementById('item_code');
const facilityIdInput = document.getElementById('facility_id');
const assignedUserIdInput = document.getElementById('assigned_user_id');
const acquisitionDateInput = document.getElementById('acquisition_date');
const remarksInput = document.getElementById('remarks');
const componentsList = document.getElementById('componentsList');
const detailComponentsList = document.getElementById('detailComponentsList');
const imageLightbox = document.getElementById('imageLightbox');
const imageLightboxImg = document.getElementById('imageLightboxImg');
const imageLightboxClose = document.getElementById('imageLightboxClose');

// Component form inputs
const componentIndexInput = document.getElementById('component_index');
const componentItemIdInput = document.getElementById('component_item_id');
const componentCodeInput = document.getElementById('component_code');
const componentQuantityInput = document.getElementById('component_quantity');
const componentConditionInput = document.getElementById('component_condition');
const componentStatusInput = document.getElementById('component_status');
const componentPhotoInput = document.getElementById('component_photo');
const componentPhotoPreview = document.getElementById('componentPhotoPreview');
const componentPhotoPlaceholder = document.getElementById('componentPhotoPlaceholder');

// Store for dropdowns and components
let itemsData = [];
let facilitiesData = [];
let usersData = [];
let currentComponents = [];
let detailComponents = [];
let currentComponentIndex = -1;
let currentDetailInventoryId = null;
let facilitiesMap = {};
let usersMap = {};

// ============= TOAST FUNCTION =============
function showToast(msg, type='success'){
    const container = document.getElementById('toastContainer');
    const div = document.createElement('div');
    div.className = `toast ${type}`;
    div.textContent = msg;
    container.appendChild(div);
    setTimeout(()=>div.remove(),4000);
}

function openImageLightbox(src) {
    if (!src || !imageLightbox || !imageLightboxImg) return;
    imageLightboxImg.src = src;
    imageLightbox.classList.remove('hidden');
    imageLightbox.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeImageLightbox() {
    if (!imageLightbox || !imageLightboxImg) return;
    imageLightbox.classList.add('hidden');
    imageLightboxImg.removeAttribute('src');
    imageLightbox.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function componentListThumbImgHtml(thumbSrc) {
    const safe = String(thumbSrc).replace(/"/g, '&quot;');
    return `<img src="${safe}" alt="" class="component-list-thumb component-list-thumb-zoomable" data-full-src="${safe}" title="Click to enlarge" />`;
}

// ============= PHOTO PREVIEW (catalog part modal only) =============
componentPhotoInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (event) => {
            componentPhotoPreview.src = event.target.result;
            componentPhotoPreview.style.display = 'block';
            componentPhotoPlaceholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
});

// ============= LOAD DROPDOWNS =============
async function loadDropdowns(){
    try {
        // Load Items
        let formData = new FormData();
        formData.append('action','get_items');
        let res = await fetch('../../app/api/inventory_management.php',{
            method:'POST',
            body: formData,
            credentials:'include'
        });
        let data = await res.json();
        if(data.success) itemsData = data.items;

        // Load Facilities
        formData = new FormData();
        formData.append('action','get_facilities');
        res = await fetch('../../app/api/inventory_management.php',{
            method:'POST',
            body: formData,
            credentials:'include'
        });
        data = await res.json();
        if(data.success) {
            facilitiesData = data.facilities;
            // Build facilities map and load users
            facilitiesData.forEach(fac => {
                facilitiesMap[fac.facility_id] = fac;
            });
        }

        // Load Users
        formData = new FormData();
        formData.append('action','get_users');
        res = await fetch('../../app/api/inventory_management.php',{
            method:'POST',
            body: formData,
            credentials:'include'
        });
        data = await res.json();
        if(data.success) {
            usersData = data.users;
            console.log('Users loaded:', usersData); // Debug: Show loaded users
            // Show each user's office_id for debugging
            usersData.forEach(user => {
                console.log(`User: ${user.Email}, office_id: ${user.office_id}, facility_id: ${user.facility_id}`);
                usersMap[user.user_id] = user;
            });
        } else {
            // Provide clearer error info and notify the user
            console.error('Failed to load users:', data);
            showToast('Failed to load users: ' + (data.message || 'Unknown'), 'error');
            // If session expired or unauthorized, redirect to login to re-authenticate
            if (data.message && String(data.message).toLowerCase().includes('unauthorized')) {
                window.location.href = '../../index.php';
            }
        }
    } catch(err){
        console.error(err);
    }
}

// ============= AUTO CODE GENERATION =============
async function generateItemCode() {
    const facilityId = facilityIdInput.value;
    if (!facilityId) {
        document.getElementById('item_code_display').textContent = '-';
        itemCodeInput.value = '';
        return;
    }

    // Get the facility code
    const facility = facilitiesMap[facilityId];
    if (!facility || !facility.code) {
        document.getElementById('item_code_display').textContent = '-';
        itemCodeInput.value = '';
        return;
    }

    // Get the count of existing items in this facility
    try {
        const formData = new FormData();
        formData.append('action', 'get_inventory_count_by_facility');
        formData.append('facility_id', facilityId);
        
        const res = await fetch('../../app/api/inventory_management.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        const data = await res.json();
        
        if (data.success) {
            const count = data.count || 0;
            // Format: FacilityCode + 6 zeros + increment count (starting from 1)
            // e.g., "WLCU01" becomes "WLCU01000001" for first, "WLCU01000002" for second, etc.
            const nextNumber = count + 1;
            const code = facility.code + String(nextNumber).padStart(6, '0');
            document.getElementById('item_code_display').textContent = code;
            itemCodeInput.value = code;
        }
    } catch (err) {
        console.error('Error generating item code:', err);
    }
}

async function generateComponentCode() {
    const facilityId = facilityIdInput.value;
    
    if (!facilityId) {
        componentCodeInput.value = '';
        return;
    }

    const facility = facilitiesMap[facilityId];
    if (!facility || !facility.code) {
        componentCodeInput.value = '';
        return;
    }

    // For components, use the same code as the parent item
    // since components share the same code as their parent
    const parentCode = itemCodeInput.value || document.getElementById('item_code_display').textContent;
    
    if (parentCode && parentCode !== '-') {
        componentCodeInput.value = parentCode;
    } else {
        componentCodeInput.value = '';
    }
}

// ============= LOAD INVENTORY =============
async function loadInventory(){
    try {
        const form = new FormData();
        form.append('action','list');
        const res = await fetch('../../app/api/inventory_management.php',{
            method:'POST',
            body: form,
            credentials:'include'
        });
        const data = await res.json();
        allInventories = data.inventory || [];
        
        renderInventoryTable(allInventories);
    } catch(err){
        console.error(err);
        inventoryTableBody.innerHTML=`<tr><td colspan="8" style="text-align:center;padding:50px;color:#ef4444;">Error loading inventory</td></tr>`;
    }
}

async function renderInventoryTable(inventories) {
    inventoryTableBody.innerHTML = '';
    
    if (!inventories || inventories.length === 0) {
        inventoryTableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:50px;color:#64748b;">No inventory items found.</td></tr>`;
        return;
    }

    let rowNum = 0;
    const invName = (r) => escHtml(r.name || r.item_name || '—');
    const primaryPart = (r) => escHtml(r.item_name || '—');

    for (let index = 0; index < inventories.length; index++) {
        const inv = inventories[index];
        const statusClass = (inv.status || 'Available').toLowerCase().replace(/\s+/g, '');
        const conditionClass = (inv.condition_status || '').toLowerCase().replace(/\s+/g, '');
        rowNum += 1;
        const nm = invName(inv);
        const tr = document.createElement('tr');
        tr.className = 'inventory-table-parent-row';
        tr.innerHTML = `
            <td>${rowNum}</td>
            <td>${nm}</td>
            <td>${primaryPart(inv)}</td>
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
                <button class="action-btn delete" data-id="${inv.inventory_id}" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
        `;
        inventoryTableBody.appendChild(tr);

        try {
            const compForm = new FormData();
            compForm.append('action','get_components');
            compForm.append('inventory_id', inv.inventory_id);
            const compRes = await fetch('../../app/api/inventory_management.php', { method: 'POST', body: compForm, credentials: 'include' });
            const compData = await compRes.json();
            const partRows = filterComponentsForTableSubrows(compData.components || []);
            if(compData.success && partRows.length > 0){
                partRows.forEach(comp => {
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
            }
        } catch(e){ console.error('Error loading components for inventory', e); }
    }
}

// -------- SEARCH & SORT FUNCTIONALITY --------
function sortInventories(inventories, sortOption) {
    const invCopy = [...inventories];

    switch(sortOption) {
        case 'name-asc':
            return invCopy.sort((a, b) => (a.name || a.item_name || '').localeCompare(b.name || b.item_name || ''));
        case 'name-desc':
            return invCopy.sort((a, b) => (b.name || b.item_name || '').localeCompare(a.name || a.item_name || ''));
        case 'code-asc':
            return invCopy.sort((a, b) => (a.item_code || '').localeCompare(b.item_code || ''));
        case 'code-desc':
            return invCopy.sort((a, b) => (b.item_code || '').localeCompare(a.item_code || ''));
        case 'facility-asc':
            return invCopy.sort((a, b) => (a.office_name || '').localeCompare(b.office_name || ''));
        case 'facility-desc':
            return invCopy.sort((a, b) => (b.office_name || '').localeCompare(a.office_name || ''));
        case 'quantity-asc':
            return invCopy.sort((a, b) => (Number(a.quantity || 0)) - (Number(b.quantity || 0)));
        case 'quantity-desc':
            return invCopy.sort((a, b) => (Number(b.quantity || 0)) - (Number(a.quantity || 0)));
        case 'status-asc':
            return invCopy.sort((a, b) => (a.status || '').localeCompare(b.status || ''));
        case 'status-desc':
            return invCopy.sort((a, b) => (b.status || '').localeCompare(a.status || ''));
        default:
            return invCopy;
    }
}

// ============= INVENTORY TABLE CLICK HANDLER (Event Delegation) =============
inventoryTableBody.addEventListener('click', function handleInventoryTableClick(e){
    const viewBtn = e.target.closest('.view');
    const editBtn = e.target.closest('.edit');
    const deleteBtn = e.target.closest('.delete');

    if(viewBtn){
        currentDetailInventoryId = viewBtn.dataset.id;
        loadDetailView(viewBtn.dataset.id).then(() => {
            detailModal.classList.add('show');
        }).catch(err => {
            console.error('Error loading detail view:', err);
        });
    }

    if(editBtn){
        document.getElementById('modalTitle').textContent = 'Edit Inventory';
        inventoryIdInput.value = editBtn.dataset.id;
        nameInput.value = editBtn.dataset.name;
        itemCodeInput.value = editBtn.dataset.itemcode;
        document.getElementById('item_code_display').textContent = editBtn.dataset.itemcode || '-';
        populateFacilityDropdown(editBtn.dataset.facility);
        acquisitionDateInput.value = editBtn.dataset.date;
        remarksInput.value = editBtn.dataset.remarks;
        if (requestIdInput) requestIdInput.value = editBtn.dataset.request_id || '0';

        const facilityId = editBtn.dataset.facility;
        if(facilityId) {
            populateUserDropdown(facilityId, editBtn.dataset.assigned_user);
        }

        loadComponentsForInventory(editBtn.dataset.id).then(() => {
            inventoryModal.classList.add('show');
        });
    }

    if(deleteBtn){
        if(confirm('Are you sure you want to delete this inventory item?')){
            const inventoryId = deleteBtn.dataset.id;
            const form = new FormData();
            form.append('action','delete');
            form.append('inventory_id', inventoryId);
            
            fetch('../../app/api/inventory_management.php',{
                method:'POST',
                body: form,
                credentials:'include'
            })
            .then(res=>res.json())
            .then(data=>{
                if(data.success){ 
                    showToast('Inventory deleted'); 
                    // Reload current facility's inventory if navigating through hierarchy
                    if(window.inventoryHierarchy && window.inventoryHierarchy.getCurrentFacilityId) {
                        const facilityId = window.inventoryHierarchy.getCurrentFacilityId();
                        if(facilityId) {
                            window.inventoryHierarchy.viewFacilityInventory(facilityId, 'Current Facility');
                        } else {
                            loadInventory();
                        }
                    } else {
                        loadInventory();
                    }
                } 
                else showToast('Failed to delete inventory','error');
            })
            .catch(err => {
                console.error('Delete error:', err);
                showToast('Error deleting inventory','error');
            });
        }
    }
});

// Initialize
loadDropdowns();
// Inventory is now loaded through hierarchy navigation
// loadInventory(); -- This is called from inventory_hierarchy.js

// ============= MODAL HANDLING =============
addInventoryBtn.addEventListener('click',()=>{
    resetInventoryForm();
    document.getElementById('modalTitle').textContent = 'Add Inventory';
    populateFacilityDropdown('');
    
    // Pre-select the current facility if navigating from hierarchy
    if(window.inventoryHierarchy && window.inventoryHierarchy.getCurrentFacilityId) {
        const currentFacilityId = window.inventoryHierarchy.getCurrentFacilityId();
        if(currentFacilityId) {
            facilityIdInput.value = currentFacilityId;
            populateUserDropdown(currentFacilityId);
            // Trigger code generation for pre-selected facility
            generateItemCode();
            generateComponentCode();
        }
    }
    
    renderComponents();
    inventoryModal.classList.add('show');
});

// Modal close button event listeners
closeInventoryModal?.addEventListener('click', () => {
    inventoryModal.classList.remove('show');
    resetInventoryForm();
});
closeComponentModal?.addEventListener('click', () => {
    componentModal.classList.remove('show');
    resetComponentForm();
});
closeDetailModal?.addEventListener('click', () => detailModal.classList.remove('show'));

// Close modal when clicking outside of it
window.addEventListener('click', e => {
    if(e.target===inventoryModal) {
        inventoryModal.classList.remove('show');
        resetInventoryForm();
    }
    if(e.target===componentModal) componentModal.classList.remove('show');
    if(e.target===detailModal) detailModal.classList.remove('show');
});

document.querySelector('.inventory-management-container')?.addEventListener('click', (e) => {
    const zoom = e.target.closest('img.component-list-thumb-zoomable');
    if (!zoom) return;
    const src = zoom.dataset.fullSrc || zoom.currentSrc || zoom.src;
    openImageLightbox(src);
});

imageLightbox?.addEventListener('click', (e) => {
    if (e.target === imageLightbox) closeImageLightbox();
});

imageLightboxClose?.addEventListener('click', (e) => {
    e.stopPropagation();
    closeImageLightbox();
});

imageLightboxImg?.addEventListener('click', (e) => e.stopPropagation());

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (!imageLightbox || imageLightbox.classList.contains('hidden')) return;
    closeImageLightbox();
});

// ============= RESET FORMS =============
function resetInventoryForm(){
    inventoryForm.reset();
    inventoryIdInput.value='';
    nameInput.value='';
    itemCodeInput.value='';
    document.getElementById('item_code_display').textContent = '-';
    facilityIdInput.value='';
    assignedUserIdInput.innerHTML = '<option value="">Office default (if dean set one)</option><option value="0">No assignee</option>';
    acquisitionDateInput.value='';
    remarksInput.value='';
    if (requestIdInput) requestIdInput.value = '0';
    currentComponents = [];
    currentDetailInventoryId = null;
}

function resetComponentForm(){
    componentForm.reset();
    componentIndexInput.value='-1';
    componentItemIdInput.value='';
    componentCodeInput.value='';
    componentQuantityInput.value='1';
    if (componentConditionInput) componentConditionInput.value = '';
    if (componentStatusInput) componentStatusInput.value = 'Available';
    componentPhotoInput.value='';
    componentPhotoPreview.src='';
    componentPhotoPreview.style.display='none';
    componentPhotoPlaceholder.style.display='block';
    populateComponentDropdown('');
}

// ============= CODE GENERATION EVENT LISTENERS =============
facilityIdInput.addEventListener('change', () => {
    // Generate codes when facility changes
    generateItemCode();
    generateComponentCode();
    // Also update personnel dropdown for the selected facility
    const facilityId = facilityIdInput.value;
    if(facilityId) {
        populateUserDropdown(facilityId);
    } else {
        assignedUserIdInput.innerHTML = '<option value="">Office default (if dean set one)</option><option value="0">No assignee</option>';
    }
});

// ============= POPULATE DROPDOWNS =============
function populateComponentDropdown(selected=''){
    componentItemIdInput.innerHTML = '<option value="">Select Component Item</option>';
    itemsData.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.item_id;
        opt.textContent = item.item_name;
        if(item.item_id == selected) opt.selected = true;
        componentItemIdInput.appendChild(opt);
    });
}

function populateFacilityDropdown(selected=''){
    facilityIdInput.innerHTML = '<option value="">Select Facility</option>';
    const currentOfficeId = window.inventoryHierarchy?.getCurrentOfficeId
        ? window.inventoryHierarchy.getCurrentOfficeId()
        : null;

    const filteredFacilities = currentOfficeId
        ? facilitiesData.filter(fac => String(fac.office_id) === String(currentOfficeId))
        : facilitiesData;

    filteredFacilities.forEach(fac => {
        const opt = document.createElement('option');
        opt.value = fac.facility_id;

        const spaceLabel = fac.laboratory || fac.room || '';
        const fullLabel = [fac.office_name, spaceLabel, fac.floor].filter(Boolean).join(' - ');

        opt.textContent = fullLabel || fac.office_name;
        opt.title = fullLabel || fac.office_name;
        if(fac.facility_id == selected) opt.selected = true;
        facilityIdInput.appendChild(opt);
    });

    // Ensure selected facility still appears in edge cases (e.g. edit data mismatch)
    if (selected && !Array.from(facilityIdInput.options).some(opt => String(opt.value) === String(selected))) {
        const fallbackFacility = facilitiesData.find(fac => String(fac.facility_id) === String(selected));
        if (fallbackFacility) {
            const fallbackOpt = document.createElement('option');
            fallbackOpt.value = fallbackFacility.facility_id;

            const spaceLabel = fallbackFacility.laboratory || fallbackFacility.room || '';
            const fullLabel = [fallbackFacility.office_name, spaceLabel, fallbackFacility.floor].filter(Boolean).join(' - ');

            fallbackOpt.textContent = fullLabel || fallbackFacility.office_name || 'Selected Facility';
            fallbackOpt.title = fallbackOpt.textContent;
            fallbackOpt.selected = true;
            facilityIdInput.appendChild(fallbackOpt);
        }
    }
}

function populateUserDropdown(facilityId, selected=''){
    assignedUserIdInput.innerHTML = '<option value="">Office default (if dean set one)</option><option value="0">No assignee</option>';
    // facilityId is a facility_id; find its office_id to filter users
    const facility = facilitiesData.find(f => f.facility_id == facilityId);
    const officeId = facility ? facility.office_id : facilityId;
    const facilityUsers = usersData.filter(user => user.office_id == officeId);
    console.log('Filtering users for office', officeId, '| Found:', facilityUsers); // Debug
    facilityUsers.forEach(user => {
        const opt = document.createElement('option');
        opt.value = user.user_id;
        opt.textContent = user.Email || 'Unknown User';
        assignedUserIdInput.appendChild(opt);
    });
    const isAddMode = !inventoryIdInput.value;
    let effectiveSelected = selected;
    if (isAddMode && (effectiveSelected === '' || effectiveSelected === undefined)) {
        const def = facility && facility.default_lab_manager_user_id != null
            ? String(facility.default_lab_manager_user_id)
            : '';
        if (def && facilityUsers.some(u => String(u.user_id) === def)) {
            effectiveSelected = def;
        }
    }
    if (effectiveSelected !== '' && effectiveSelected !== undefined && String(effectiveSelected) !== '0') {
        const opt = Array.from(assignedUserIdInput.options).find(o => String(o.value) === String(effectiveSelected));
        if (opt) {
            assignedUserIdInput.value = String(effectiveSelected);
        }
    } else if (String(effectiveSelected) === '0') {
        assignedUserIdInput.value = '0';
    } else {
        assignedUserIdInput.value = '';
    }
}


// ============= COMPONENT MANAGEMENT =============
function getInventoryIdForPersistedComponents() {
    if (inventoryModal.classList.contains('show') && inventoryIdInput.value) {
        return String(inventoryIdInput.value);
    }
    if (detailModal.classList.contains('show') && currentDetailInventoryId) {
        return String(currentDetailInventoryId);
    }
    return '';
}

addComponentBtn.addEventListener('click', () => {
    if (detailModal.classList.contains('show')) {
        const dc = document.getElementById('detailItemCode');
        if (dc && dc.textContent && dc.textContent.trim() !== '-') {
            const c = dc.textContent.trim();
            itemCodeInput.value = c;
            document.getElementById('item_code_display').textContent = c;
        }
    }
    if (!facilityIdInput.value && (!itemCodeInput.value || itemCodeInput.value === '-')) {
        showToast('Select a facility first so an inventory item code can be generated.', 'error');
        return;
    }
    currentComponentIndex = -1;
    componentIndexInput.value = '-1';
    resetComponentForm();
    populateComponentDropdown('');
    generateComponentCode();
    const modalTitle = document.getElementById('componentModalTitle');
    if (modalTitle) modalTitle.textContent = 'Add catalog part';
    componentModal.classList.add('show');
});

componentForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const componentItemId = componentItemIdInput.value;
    let componentCode = componentCodeInput.value;
    const componentQuantity = componentQuantityInput.value;

    if (!componentItemId || !componentQuantity) {
        showToast('Please select a catalog item and quantity', 'error');
        return;
    }

    if (!componentCode) {
        componentCode = itemCodeInput.value || document.getElementById('item_code_display').textContent;
        if (componentCode === '-') {
            showToast('Select a facility first so the item code is available for this part.', 'error');
            return;
        }
    }

    const itemName = itemsData.find(i => String(i.item_id) === String(componentItemId))?.item_name || '';
    const persistInvId = getInventoryIdForPersistedComponents();
    const componentIdx = parseInt(componentIndexInput.value, 10);
    const fromDetailModal = detailModal.classList.contains('show') && currentDetailInventoryId;

    const closePartModal = () => {
        componentModal.classList.remove('show');
        resetComponentForm();
    };

    if (fromDetailModal) {
        if (componentIdx >= 0 && detailComponents[componentIdx]?.component_id) {
            const componentId = detailComponents[componentIdx].component_id;
            const formData = new FormData();
            formData.append('action', 'update_component');
            formData.append('component_id', componentId);
            formData.append('component_item_id', componentItemId);
            formData.append('code', componentCode);
            formData.append('quantity', componentQuantity);
            formData.append('condition_status', componentConditionInput?.value || '');
            formData.append('status', componentStatusInput?.value || 'Available');
            if (componentPhotoInput.files[0]) {
                formData.append('photo', componentPhotoInput.files[0]);
            }
            try {
                const res = await fetch('../../app/api/inventory_management.php', { method: 'POST', body: formData, credentials: 'include' });
                const data = await res.json();
                if (data.success) {
                    showToast('Part updated');
                    loadDetailComponents(currentDetailInventoryId);
                    closePartModal();
                } else showToast('Failed to update part', 'error');
            } catch (err) {
                console.error(err);
                showToast('Error updating part', 'error');
            }
            return;
        }
        const formData = new FormData();
        formData.append('action', 'add_component');
        formData.append('inventory_id', currentDetailInventoryId);
        formData.append('component_item_id', componentItemId);
        formData.append('code', componentCode);
        formData.append('quantity', componentQuantity);
        formData.append('condition_status', componentConditionInput?.value || '');
        formData.append('status', componentStatusInput?.value || 'Available');
        if (componentPhotoInput.files[0]) {
            formData.append('photo', componentPhotoInput.files[0]);
        }
        try {
            const res = await fetch('../../app/api/inventory_management.php', { method: 'POST', body: formData, credentials: 'include' });
            const data = await res.json();
            if (data.success) {
                showToast('Part added');
                await loadDetailComponents(currentDetailInventoryId);
                closePartModal();
            } else showToast(data.message || 'Failed to add part', 'error');
        } catch (err) {
            console.error(err);
            showToast('Error adding part', 'error');
        }
        return;
    }

    if (persistInvId) {
        const row = componentIdx >= 0 ? currentComponents[componentIdx] : null;
        if (row && row.component_id) {
            const formData = new FormData();
            formData.append('action', 'update_component');
            formData.append('component_id', row.component_id);
            formData.append('component_item_id', componentItemId);
            formData.append('code', componentCode);
            formData.append('quantity', componentQuantity);
            formData.append('condition_status', componentConditionInput?.value || '');
            formData.append('status', componentStatusInput?.value || 'Available');
            if (componentPhotoInput.files[0]) {
                formData.append('photo', componentPhotoInput.files[0]);
            }
            try {
                const res = await fetch('../../app/api/inventory_management.php', { method: 'POST', body: formData, credentials: 'include' });
                const data = await res.json();
                if (data.success) {
                    showToast('Part updated');
                    await loadComponentsForInventory(persistInvId);
                    closePartModal();
                } else showToast('Failed to update part', 'error');
            } catch (err) {
                console.error(err);
                showToast('Error updating part', 'error');
            }
            return;
        }
        const formData = new FormData();
        formData.append('action', 'add_component');
        formData.append('inventory_id', persistInvId);
        formData.append('component_item_id', componentItemId);
        formData.append('code', componentCode);
        formData.append('quantity', componentQuantity);
        formData.append('condition_status', componentConditionInput?.value || '');
        formData.append('status', componentStatusInput?.value || 'Available');
        if (componentPhotoInput.files[0]) {
            formData.append('photo', componentPhotoInput.files[0]);
        }
        try {
            const res = await fetch('../../app/api/inventory_management.php', { method: 'POST', body: formData, credentials: 'include' });
            const data = await res.json();
            if (data.success) {
                showToast('Part added');
                await loadComponentsForInventory(persistInvId);
                closePartModal();
            } else showToast(data.message || 'Failed to add part', 'error');
        } catch (err) {
            console.error(err);
            showToast('Error adding part', 'error');
        }
        return;
    }

    const componentData = {
        item_id: componentItemId,
        item_name: itemName,
        code: componentCode,
        quantity: componentQuantity,
        condition_status: componentConditionInput?.value || '',
        status: componentStatusInput?.value || 'Available',
        photo: componentPhotoInput.files[0] || null,
        photoPreview: componentPhotoPreview.src && componentPhotoPreview.style.display !== 'none' ? componentPhotoPreview.src : null
    };

    const dupIdx = currentComponents.findIndex((c, i) =>
        i !== currentComponentIndex && String(c.item_id) === String(componentItemId));
    if (dupIdx !== -1) {
        showToast('That catalog item is already in the list.', 'error');
        return;
    }

    if (currentComponentIndex >= 0) {
        const prev = currentComponents[currentComponentIndex];
        componentData.component_id = prev?.component_id;
        currentComponents[currentComponentIndex] = componentData;
    } else {
        currentComponents.push(componentData);
    }

    renderComponents();
    showToast(currentComponentIndex >= 0 ? 'Part updated' : 'Part added');
    closePartModal();
});

// ============= RENDER COMPONENTS IN EDIT FORM =============
function getComponentListThumbSrc(comp) {
    const preview = comp.photoPreview;
    if (preview && String(preview).trim() !== '') {
        return String(preview).trim();
    }
    const url = comp.photo_url;
    if (url && String(url).trim() !== '') {
        const u = String(url).trim();
        if (u.startsWith('data:') || u.startsWith('http://') || u.startsWith('https://')) {
            return u;
        }
        return '../../app/api/public/' + u.replace(/^\/+/, '');
    }
    return '';
}

function renderComponents(){
    componentsList.innerHTML = '';
    
    if(currentComponents.length === 0){
        componentsList.innerHTML = '<p style="color: #94a3b8; text-align: center; padding: 2rem;">No catalog parts added yet</p>';
        return;
    }
    
    currentComponents.forEach((comp, idx) => {
        const div = document.createElement('div');
        div.className = 'component-item';
        div.style.cssText = `
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            background: white;
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
        `;

        const thumbSrc = getComponentListThumbSrc(comp);
        const thumbBlock = thumbSrc
            ? componentListThumbImgHtml(thumbSrc)
            : `<div class="component-list-thumb component-list-thumb-fallback" aria-hidden="true">IMG</div>`;

        div.innerHTML = `
            ${thumbBlock}
            <div style="flex: 1; min-width: 0;">
                <p style="margin: 0 0 0.25rem 0; font-weight: 600; color: #1e293b;">${comp.item_name}</p>
                <p style="margin: 0; font-size: 0.9rem; color: #64748b;">Code: ${comp.code} | Qty: ${comp.quantity} | ${escHtml(comp.condition_status || '—')} | ${escHtml(comp.status || '—')}</p>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                <button type="button" class="component-edit-btn" data-index="${idx}" style="padding: 0.5rem 0.75rem; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">Edit</button>
                <button type="button" class="component-delete-btn" data-index="${idx}" style="padding: 0.5rem 0.75rem; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">Delete</button>
            </div>
        `;
        componentsList.appendChild(div);
    });
}

// Component edit/delete handlers in form
componentsList.addEventListener('click', (e) => {
    const editBtn = e.target.closest('.component-edit-btn');
    const deleteBtn = e.target.closest('.component-delete-btn');
    
    if(editBtn){
        const idx = parseInt(editBtn.dataset.index, 10);
        currentComponentIndex = idx;
        componentIndexInput.value = String(idx);
        const comp = currentComponents[idx];

        populateComponentDropdown(comp.item_id);
        componentItemIdInput.value = comp.item_id;
        componentCodeInput.value = comp.code;
        componentQuantityInput.value = comp.quantity || 1;
        if (componentConditionInput) componentConditionInput.value = comp.condition_status || '';
        if (componentStatusInput) componentStatusInput.value = comp.status || 'Available';

        const modalTitle = document.getElementById('componentModalTitle');
        if (modalTitle) modalTitle.textContent = 'Edit catalog part';
        generateComponentCode();
        componentModal.classList.add('show');
    }
    
    if(deleteBtn){
        const idx = parseInt(deleteBtn.dataset.index, 10);
        const component = currentComponents[idx];
        if (!component) return;

        const editingSavedInventory = Boolean(inventoryIdInput.value && String(inventoryIdInput.value).trim());

        if (editingSavedInventory && component.component_id) {
            (async () => {
                const deleteForm = new FormData();
                deleteForm.append('action', 'delete_component');
                deleteForm.append('component_id', String(component.component_id));
                try {
                    const res = await fetch('../../app/api/inventory_management.php', {
                        method: 'POST',
                        body: deleteForm,
                        credentials: 'include'
                    });
                    const data = await res.json();
                    if (!data.success) {
                        showToast(data.message || 'Failed to remove part', 'error');
                        return;
                    }
                    currentComponents.splice(idx, 1);
                    renderComponents();
                    showToast('Part removed');
                } catch (err) {
                    console.error(err);
                    showToast('Error removing part', 'error');
                }
            })();
            return;
        }

        currentComponents.splice(idx, 1);
        renderComponents();
        showToast('Component removed');
    }
});

// ============= ADD/EDIT INVENTORY =============
// Enter in a field would fire submit; only persist when user clicks Save Inventory.
inventoryForm.addEventListener('submit', (e) => {
    e.preventDefault();
});

const saveInventoryBtn = document.getElementById('saveInventoryBtn');
saveInventoryBtn?.addEventListener('click', async () => {
    if(!nameInput.value.trim()){
        showToast('Please enter inventory name','error');
        return;
    }

    if (!inventoryIdInput.value && currentComponents.length < 1) {
        showToast('Add at least one catalog part (e.g. motherboard, monitor).', 'error');
        return;
    }

    if (!inventoryIdInput.value && (!currentComponents.length || !currentComponents[0].item_id)) {
        showToast('Each part must use a catalog item from the list.', 'error');
        return;
    }
    
    const formData = new FormData(inventoryForm);
    formData.append('action', inventoryIdInput.value ? 'edit':'add');
    
    // Only include components when adding, not when editing (components are managed separately)
    let componentsForAPI = [];
    let hasPhotos = false;
    
    if(!inventoryIdInput.value) {
        // Adding new inventory - include components from the form
        componentsForAPI = currentComponents.map(comp => ({
            item_id: comp.item_id,
            item_name: comp.item_name,
            code: comp.code,
            quantity: comp.quantity,
            condition_status: comp.condition_status || '',
            status: comp.status || 'Available'
        }));
        
        // Add component photo files with index-based keys
        currentComponents.forEach((comp, idx) => {
            if(comp.photo) {
                formData.append(`component_photo_${idx}`, comp.photo);
                hasPhotos = true;
            }
        });
    }
    
    formData.append('components', JSON.stringify(componentsForAPI));
    
    try {
        const res = await fetch('../../app/api/inventory_management.php',{
            method:'POST',
            body: formData,
            credentials:'include'
        });
        const data = await res.json();
        if(data.success){
            showToast(data.message);
            inventoryModal.classList.remove('show');
            
            // Reload current facility's inventory if navigating through hierarchy
            if(window.inventoryHierarchy && window.inventoryHierarchy.getCurrentFacilityId) {
                const facilityId = window.inventoryHierarchy.getCurrentFacilityId();
                if(facilityId) {
                    window.inventoryHierarchy.viewFacilityInventory(facilityId, 'Current Facility');
                } else {
                    loadInventory();
                }
            } else {
                loadInventory();
            }
            
            resetInventoryForm();
            currentDetailInventoryId = null;
        } else showToast(data.message,'error');
    } catch(err){
        console.error(err);
        showToast('Error saving inventory','error');
    }
});



// ============= LOAD COMPONENTS FOR INVENTORY =============
async function loadComponentsForInventory(inventoryId){
    try {
        const formData = new FormData();
        formData.append('action', 'get_components');
        formData.append('inventory_id', inventoryId);
        
        const res = await fetch('../../app/api/inventory_management.php',{
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        const data = await res.json();
        
        currentComponents = [];
        if(data.success && data.components){
            data.components.forEach(comp => {
                currentComponents.push({
                    component_id: comp.component_id,
                    item_id: comp.component_item_id,
                    item_name: comp.item_name,
                    code: comp.code,
                    quantity: comp.quantity,
                    condition_status: comp.condition_status || '',
                    status: comp.status || 'Available',
                    photo: null,
                    photo_url: comp.photo_url || '',
                    photoPreview: comp.photo_url ? '../../app/api/public/' + String(comp.photo_url).replace(/^\/+/, '') : null
                });
            });
        }
        
        renderComponents();
        return true;
    } catch(err){
        console.error(err);
        return false;
    }
}

// ============= LOAD DETAIL VIEW =============
async function loadDetailView(inventoryId){
    try {
        // Clear previous components to avoid duplication
        detailComponentsList.innerHTML = '';
        detailComponents = [];
        
        // Build facilities map if not already done
        if(Object.keys(facilitiesMap).length === 0){
            facilitiesData.forEach(fac => {
                facilitiesMap[fac.facility_id] = fac;
            });
        }

        // Fetch inventory details
        const formData = new FormData();
        formData.append('action', 'list');
        const res = await fetch('../../app/api/inventory_management.php',{
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        const data = await res.json();
        
        const inv = data.inventory.find(i => i.inventory_id == inventoryId);
        if(!inv) {
            showToast('Inventory not found', 'error');
            return;
        }

        detailInventoryRecordName = inv.name || inv.item_name || '';

        document.getElementById('detailName').textContent = detailInventoryRecordName || '—';

        const facility = facilitiesMap[inv.facility_id];
        const location = facility
            ? [facility.laboratory || facility.room, facility.building, facility.floor, facility.office_name].filter(Boolean).join(' · ')
            : (inv.office_name || '');
        document.getElementById('detailLocation').textContent = location ? `Location: ${location}` : 'Location: —';

        document.getElementById('detailItemCode').textContent = inv.item_code || '-';
        document.getElementById('detailDate').textContent = inv.acquisition_date ? new Date(inv.acquisition_date).toLocaleDateString() : '-';
        
        // Assigned Personnel
        const assignedUserName = inv.assigned_user_email || '-';
        document.getElementById('detailAssignedUser').textContent = assignedUserName;
        
        document.getElementById('detailRemarks').textContent = inv.remarks || '-';
        
        loadDetailComponents(inventoryId);
        
    } catch(err){
        console.error(err);
        showToast('Error loading inventory details', 'error');
    }
}

// ============= LOAD DETAIL COMPONENTS =============
async function loadDetailComponents(inventoryId){
    try {
        const formData = new FormData();
        formData.append('action', 'get_components');
        formData.append('inventory_id', inventoryId);
        
        const res = await fetch('../../app/api/inventory_management.php',{
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        const data = await res.json();
        
        detailComponents = [];
        if (data.success && data.components) {
            data.components.forEach(comp => {
                detailComponents.push({
                    component_id: comp.component_id,
                    item_id: comp.component_item_id,
                    item_name: comp.item_name,
                    code: comp.code,
                    quantity: comp.quantity,
                    condition_status: comp.condition_status,
                    status: comp.status,
                    photo_url: comp.photo_url
                });
            });
        }
        
        renderDetailComponents();
    } catch(err){
        console.error(err);
    }
}

// ============= RENDER DETAIL COMPONENTS =============
function renderDetailComponents(){
    detailComponentsList.innerHTML = '';

    if(detailComponents.length === 0){
        detailComponentsList.innerHTML = '<p class="text-center text-muted components-empty-msg">No catalog parts for this inventory.</p>';
        return;
    }

    detailComponents.forEach((comp) => {
        const thumbSrc = getComponentListThumbSrc({
            photo_url: comp.photo_url || '',
            photoPreview: null
        });

        const div = document.createElement('div');
        div.className = 'component-item component-item--readonly';
        const thumbBlock = thumbSrc
            ? componentListThumbImgHtml(thumbSrc)
            : `<div class="component-list-thumb component-list-thumb-fallback" aria-hidden="true">IMG</div>`;

        div.innerHTML = `
            ${thumbBlock}
            <div style="flex: 1; min-width: 0;">
                <p style="margin: 0 0 0.25rem 0; font-weight: 600; color: #1e293b;">${escHtml(comp.item_name || '—')}</p>
                <p style="margin: 0; font-size: 0.9rem; color: #64748b;">Code: ${escHtml(comp.code || '—')} | Qty: ${escHtml(String(comp.quantity ?? '—'))} | ${escHtml(comp.condition_status || '—')} | ${escHtml(comp.status || '—')}</p>
            </div>
        `;
        detailComponentsList.appendChild(div);
    });
}

});
