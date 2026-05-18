document.addEventListener('DOMContentLoaded', () => {

const itemsTableBody = document.getElementById('itemsTableBody');
const itemModal = document.getElementById('itemModal');
const closeModal = document.getElementById('closeItemModal');
const addItemBtn = document.getElementById('addItemBtn');
const itemForm = document.getElementById('itemForm');
const itemIdInput = document.getElementById('item_id');
const itemNameInput = document.getElementById('item_name');
const brandInput = document.getElementById('brand');
const modelInput = document.getElementById('model');
const descriptionInput = document.getElementById('description');
const categoryInput = document.getElementById('category');
const unitInput = document.getElementById('unit');
const statusInput = document.getElementById('status');
const supplierPickAdd = document.getElementById('supplier_pick_add');
const supplierAddBtn = document.getElementById('supplier_add_btn');
const supplierChipsWrap = document.getElementById('supplier_chips');
const photoInput = document.getElementById('photo');
const photoPreview = document.getElementById('photoPreview');
const photoPlaceholder = document.getElementById('photoPlaceholder');

// Search and sort inputs
const itemSearchInput = document.getElementById('itemSearchInput');
const itemSortDropdown = document.getElementById('itemSortDropdown');

let allItems = [];
let allSuppliers = [];
/** @type {number[]} */
let modalSupplierIds = [];

// ============== Toast ================
function showToast(msg, type='success'){
    const container = document.getElementById('toastContainer');
    const div = document.createElement('div');
    div.className = `toast ${type}`;
    div.textContent = msg;
    container.appendChild(div);
    setTimeout(()=>div.remove(),4000);
}

function htmlspecialchars(str) {
    if (!str) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return str.replace(/[&<>"']/g, m => map[m]);
}

function syncSupplierAddButtonState() {
    if (!supplierAddBtn || !supplierPickAdd) return;
    supplierAddBtn.disabled = !supplierPickAdd.value;
}

function rebuildSupplierPickDropdown() {
    if (!supplierPickAdd) return;
    supplierPickAdd.innerHTML = '';
    const ph = document.createElement('option');
    ph.value = '';
    ph.textContent = 'Select a supplier to add…';
    supplierPickAdd.appendChild(ph);
    const taken = new Set(modalSupplierIds.map((x) => Number(x)));
    allSuppliers.forEach((supplier) => {
        const id = Number(supplier.supplier_id);
        if (taken.has(id)) return;
        const option = document.createElement('option');
        option.value = String(supplier.supplier_id);
        option.textContent = supplier.supplier_name;
        supplierPickAdd.appendChild(option);
    });
    syncSupplierAddButtonState();
}

function renderSupplierChips() {
    if (!supplierChipsWrap) return;
    if (modalSupplierIds.length === 0) {
        supplierChipsWrap.innerHTML = '';
        return;
    }
    supplierChipsWrap.innerHTML = modalSupplierIds
        .map((id) => {
            const s = allSuppliers.find((x) => Number(x.supplier_id) === Number(id));
            const name = s ? s.supplier_name : `#${id}`;
            const safe = htmlspecialchars(String(name));
            return `<span class="item-supplier-chip" data-sid="${id}"><span>${safe}</span><button type="button" class="item-supplier-chip-remove" aria-label="Remove ${safe}">&times;</button></span>`;
        })
        .join('');
}

function addSupplierFromPicker() {
    if (!supplierPickAdd || !supplierPickAdd.value) {
        showToast('Choose a supplier from the dropdown first.', 'error');
        return;
    }
    const id = Number(supplierPickAdd.value);
    if (Number.isNaN(id) || id <= 0) return;
    if (modalSupplierIds.includes(id)) return;
    modalSupplierIds.push(id);
    supplierPickAdd.value = '';
    rebuildSupplierPickDropdown();
    renderSupplierChips();
}

// ============== Load Suppliers ==============
async function loadSuppliers() {
    try {
        const form = new FormData();
        form.append('action', 'list_suppliers');
        const res = await fetch('../../app/api/supplier_management.php', {
            method: 'POST',
            body: form,
            credentials: 'include'
        });
        const data = await res.json();
        allSuppliers = data.suppliers || [];
        
        rebuildSupplierPickDropdown();
    } catch(err) {
        console.error('Error loading suppliers:', err);
    }
}

loadSuppliers();

supplierPickAdd?.addEventListener('change', () => syncSupplierAddButtonState());
supplierAddBtn?.addEventListener('click', () => addSupplierFromPicker());
supplierChipsWrap?.addEventListener('click', (e) => {
    const btn = e.target.closest('.item-supplier-chip-remove');
    if (!btn || !supplierChipsWrap) return;
    const chip = btn.closest('.item-supplier-chip');
    if (!chip) return;
    const sid = Number(chip.getAttribute('data-sid'));
    if (Number.isNaN(sid)) return;
    modalSupplierIds = modalSupplierIds.filter((x) => x !== sid);
    rebuildSupplierPickDropdown();
    renderSupplierChips();
});

// ============== Photo Preview Handler ==============
photoInput?.addEventListener('change', () => {
    const file = photoInput.files[0];
    if (!file) {
        if (photoPreview && photoPlaceholder) {
            photoPreview.src = '';
            photoPreview.style.display = 'none';
            photoPlaceholder.style.display = 'flex';
        }
        return;
    }

    const reader = new FileReader();
    reader.onload = e => {
        if (photoPreview && photoPlaceholder) {
            photoPreview.src = e.target.result;
            photoPreview.style.display = 'block';
            photoPlaceholder.style.display = 'none';
        }
    };
    reader.readAsDataURL(file);
});

// ============== Load Items ================
async function loadItems(){
    try {
        const form = new FormData();
        form.append('action','list');
        const res = await fetch('../../app/api/item_management.php',{
            method:'POST',
            body: form,
            credentials:'include'
        });
        const data = await res.json();
        allItems = data.items || [];
        
        // Clear search and sort when reloading
        itemSearchInput.value = '';
        itemSortDropdown.value = '';
        
        renderItemTable(allItems);
    } catch(err){
        console.error(err);
        itemsTableBody.innerHTML=`<tr><td colspan="10" style="text-align:center;padding:50px;color:#ef4444;">Error loading items</td></tr>`;
    }
}

// ============== Render Item Table ==============
function renderItemTable(items) {
    itemsTableBody.innerHTML = '';
    if(!items || items.length === 0){
        itemsTableBody.innerHTML=`<tr><td colspan="10" style="text-align:center;padding:50px;color:#64748b;">No items found.</td></tr>`;
        return;
    }
    
    items.forEach((item, index) => {
        const statusClass = (item.status || 'Active').toLowerCase().replace(/\s+/g, '');
        const photoHtml = item.photo_url 
            ? `<img src="../${htmlspecialchars(item.photo_url)}" alt="${htmlspecialchars(item.item_name)}" class="item-photo-thumb">`
            : `<div class="item-photo-placeholder">📦</div>`;
        
        const sidList = Array.isArray(item.supplier_ids) ? item.supplier_ids : [];
        const supplierNames = sidList
            .map((id) => allSuppliers.find((s) => String(s.supplier_id) === String(id))?.supplier_name)
            .filter(Boolean);
        const supplierName = supplierNames.length ? supplierNames.join(', ') : '-';
        
        const tr = document.createElement('tr');
        tr.innerHTML=`
            <td>${index+1}</td>
            <td class="photo-cell">${photoHtml}</td>
            <td>${htmlspecialchars(item.item_name)}</td>
            <td>${htmlspecialchars(item.brand || '-')}</td>
            <td>${htmlspecialchars(item.model || '-')}</td>
            <td>${htmlspecialchars(item.category || '-')}</td>
            <td>${htmlspecialchars(supplierName)}</td>
            <td>${htmlspecialchars(item.unit || '-')}</td>
            <td><span class="status-badge ${statusClass}">${item.status || 'Active'}</span></td>
            <td>
                <button class="action-btn edit" data-id="${item.item_id}" 
                    data-name="${htmlspecialchars(item.item_name)}" 
                    data-brand="${htmlspecialchars(item.brand || '')}" 
                    data-model="${htmlspecialchars(item.model || '')}" 
                    data-description="${htmlspecialchars(item.description || '')}" 
                    data-category="${htmlspecialchars(item.category || '')}" 
                    data-unit="${htmlspecialchars(item.unit || '')}" 
                    data-status="${htmlspecialchars(item.status || '')}"
                    data-supplier-ids="${(item.supplier_ids || []).join(',')}"
                    data-photo="${htmlspecialchars(item.photo_url || '')}"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete" data-id="${item.item_id}"><i class="fas fa-trash"></i></button>
            </td>
        `;
        itemsTableBody.appendChild(tr);
    });
}

loadItems();

// ============== Modal Handling ================
addItemBtn.addEventListener('click', () => {
    document.getElementById('modalTitle').textContent = 'Add Item';
    itemForm.reset();
    itemIdInput.value = '';
    photoInput.value = '';
    modalSupplierIds = [];
    rebuildSupplierPickDropdown();
    renderSupplierChips();
    if (photoPreview && photoPlaceholder) {
        photoPreview.src = '';
        photoPreview.style.display = 'none';
        photoPlaceholder.style.display = 'flex';
        photoPlaceholder.textContent = '+';
    }
    itemModal.classList.add('show');
});

closeModal.addEventListener('click', () => itemModal.classList.remove('show'));
window.addEventListener('click', e => { 
    if(e.target === itemModal) itemModal.classList.remove('show'); 
});

// ============== Add/Edit ================
itemForm.addEventListener('submit', async e => {
    e.preventDefault();
    const formData = new FormData(itemForm);
    Array.from(formData.keys()).forEach((k) => {
        if (k === 'supplier_ids[]' || k.startsWith('supplier_ids')) {
            formData.delete(k);
        }
    });
    modalSupplierIds.forEach((id) => formData.append('supplier_ids[]', String(id)));
    formData.append('action', itemIdInput.value ? 'edit' : 'add');
    try {
        const res = await fetch('../../app/api/item_management.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        const data = await res.json();
        if(data.success){
            showToast(data.message);
            itemModal.classList.remove('show');
            loadItems();
        } else {
            showToast(data.message || 'Error', 'error');
        }
    } catch(err) {
        console.error(err);
        showToast('Error saving item', 'error');
    }
});

// ============== Edit/Delete Buttons ================
itemsTableBody.addEventListener('click', e => {
    const editBtn = e.target.closest('.edit');
    const deleteBtn = e.target.closest('.delete');

    if(editBtn){
        document.getElementById('modalTitle').textContent = 'Edit Item';
        itemIdInput.value = editBtn.dataset.id;
        itemNameInput.value = editBtn.dataset.name;
        brandInput.value = editBtn.dataset.brand;
        modelInput.value = editBtn.dataset.model;
        descriptionInput.value = editBtn.dataset.description;
        categoryInput.value = editBtn.dataset.category;
        unitInput.value = editBtn.dataset.unit;
        statusInput.value = editBtn.dataset.status;
        const rawIds = (editBtn.dataset.supplierIds || '').trim();
        const parsed = rawIds
            ? rawIds.split(',').map((x) => Number(x.trim())).filter((n) => !Number.isNaN(n) && n > 0)
            : [];
        modalSupplierIds = [...new Set(parsed)];
        rebuildSupplierPickDropdown();
        renderSupplierChips();
        photoInput.value = '';
        
        // Set photo preview for existing item
        const photoUrl = editBtn.dataset.photo;
        if (photoPreview && photoPlaceholder) {
            if (photoUrl) {
                photoPreview.src = `../${photoUrl}`;
                photoPreview.style.display = 'block';
                photoPlaceholder.style.display = 'none';
            } else {
                photoPreview.src = '';
                photoPreview.style.display = 'none';
                photoPlaceholder.style.display = 'flex';
                photoPlaceholder.textContent = '📦';
            }
        }
        
        itemModal.classList.add('show');
    }

    if(deleteBtn){
        if(confirm('Delete this item?')){
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('item_id', deleteBtn.dataset.id);
            fetch('../../app/api/item_management.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            }).then(res => res.json()).then(data => {
                showToast(data.message || (data.success ? 'Item deleted' : 'Error'), data.success ? 'success' : 'error');
                if(data.success) loadItems();
            }).catch(err => {
                console.error(err);
                showToast('Error deleting item', 'error');
            });
        }
    }
});

// ============== Search & Sort Functionality ================
function searchAndFilterItems() {
    const searchTerm = (itemSearchInput.value || '').toLowerCase().trim();
    const sortOption = itemSortDropdown.value;

    let filteredItems = allItems.filter(item => {
        if (!searchTerm) return true;
        const name = (item.item_name || '').toLowerCase();
        const brand = (item.brand || '').toLowerCase();
        const category = (item.category || '').toLowerCase();
        
        return name.includes(searchTerm) || brand.includes(searchTerm) || category.includes(searchTerm);
    });

    // Apply sorting
    if (sortOption) {
        filteredItems = sortItems(filteredItems, sortOption);
    }

    renderItemTable(filteredItems);
}

function sortItems(items, sortOption) {
    const itemsCopy = [...items];

    switch(sortOption) {
        case 'name-asc':
            return itemsCopy.sort((a, b) => (a.item_name || '').localeCompare(b.item_name || ''));
        case 'name-desc':
            return itemsCopy.sort((a, b) => (b.item_name || '').localeCompare(a.item_name || ''));
        case 'brand-asc':
            return itemsCopy.sort((a, b) => (a.brand || '').localeCompare(b.brand || ''));
        case 'brand-desc':
            return itemsCopy.sort((a, b) => (b.brand || '').localeCompare(a.brand || ''));
        case 'category-asc':
            return itemsCopy.sort((a, b) => (a.category || '').localeCompare(b.category || ''));
        case 'category-desc':
            return itemsCopy.sort((a, b) => (b.category || '').localeCompare(a.category || ''));
        case 'status-asc':
            return itemsCopy.sort((a, b) => (a.status || '').localeCompare(b.status || ''));
        case 'status-desc':
            return itemsCopy.sort((a, b) => (b.status || '').localeCompare(a.status || ''));
        default:
            return itemsCopy;
    }
}

// Event listeners for search and sort
itemSearchInput?.addEventListener('input', searchAndFilterItems);
itemSortDropdown?.addEventListener('change', searchAndFilterItems);

});
