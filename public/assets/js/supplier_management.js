document.addEventListener('DOMContentLoaded', () => {
    // ==================== DOM Elements ====================
    const supplierTableBody = document.getElementById('supplierTableBody');
    
    // Search and sort inputs
    const supplierSearchInput = document.getElementById('supplierSearchInput');
    const supplierSortDropdown = document.getElementById('supplierSortDropdown');
    
    // Modal elements
    const supplierModal = document.getElementById('supplierModal');
    const closeSupplierModal = document.getElementById('closeSupplierModal');
    const addSupplierBtn = document.getElementById('addSupplierBtn');
    const supplierForm = document.getElementById('supplierForm');
    const cancelBtn = document.getElementById('cancelBtn');
    
    // Form inputs
    const supplierIdInput = document.getElementById('supplier_id');
    const supplierNameInput = document.getElementById('supplier_name');
    const contactPersonInput = document.getElementById('contact_person');
    const phoneNumberInput = document.getElementById('phone_number');
    const emailInput = document.getElementById('supplier_email');
    const addressInput = document.getElementById('supplier_address');
    const cityInput = document.getElementById('supplier_city');
    const countryInput = document.getElementById('supplier_country');
    const postalCodeInput = document.getElementById('postal_code');
    const tinInput = document.getElementById('supplier_tin');
    const statusInput = document.getElementById('supplier_status');
    const supplierImageInput = document.getElementById('supplier_image');
    const imagePreview = document.getElementById('imagePreview');
    
    // Delete modal
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const deleteSupplierName = document.getElementById('deleteSupplierName');
    
    let currentDeleteId = null;
    let allSuppliers = [];

    // ==================== TOAST NOTIFICATION ====================
    function showToast(msg, type = 'success') {
        const container = document.getElementById('toastContainer');
        const div = document.createElement('div');
        div.className = `toast ${type}`;
        div.textContent = msg;
        container.appendChild(div);
        setTimeout(() => div.remove(), 4000);
    }

    function formatTinInputValue(raw) {
        const digits = String(raw || '').replace(/\D/g, '').slice(0, 12);
        const parts = [];
        for (let i = 0; i < digits.length; i += 3) {
            parts.push(digits.slice(i, i + 3));
        }
        return parts.join('-');
    }

    // ==================== ESCAPE HTML ====================
    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    // ==================== LOAD SUPPLIERS ====================
    async function loadSuppliers() {
        supplierTableBody.innerHTML = '<tr><td colspan="9" class="loading-cell">Loading Suppliers...</td></tr>';
        
        try {
            const form = new FormData();
            form.append('action', 'list_suppliers');
            
            const res = await fetch('../../app/api/supplier_management.php', {
                method: 'POST',
                body: form,
                credentials: 'include'
            });
            
            const data = await res.json();
            
            if (data.success) {
                allSuppliers = data.suppliers;
                renderSuppliers(allSuppliers);
            } else {
                supplierTableBody.innerHTML = '<tr><td colspan="9" class="loading-cell">Failed to load suppliers</td></tr>';
                showToast('Failed to load suppliers', 'error');
            }
        } catch (err) {
            console.error(err);
            supplierTableBody.innerHTML = '<tr><td colspan="9" class="loading-cell">Error loading suppliers</td></tr>';
            showToast('Error loading suppliers', 'error');
        }
    }

    // ==================== RENDER SUPPLIERS ====================
    function renderSuppliers(suppliers) {
        supplierTableBody.innerHTML = '';
        
        if (suppliers.length === 0) {
            supplierTableBody.innerHTML = '<tr><td colspan="9" class="loading-cell">No supplier added yet</td></tr>';
            return;
        }

        suppliers.forEach((supplier, index) => {
            const tr = document.createElement('tr');
            const imageHtml = supplier.supplier_image 
                ? `<img src="../${escapeHtml(supplier.supplier_image)}" alt="${escapeHtml(supplier.supplier_name)}" class="table-image">`
                : '<div class="table-image-placeholder"><i class="fas fa-image"></i></div>';
            
            tr.innerHTML = `
                <td>${index + 1}</td>
                <td class="col-image">
                    <div class="image-cell">
                        ${imageHtml}
                    </div>
                </td>
                <td class="col-name">${escapeHtml(supplier.supplier_name)}</td>
                <td class="col-contact">${escapeHtml(supplier.contact_person || '-')}</td>
                <td class="col-email">${escapeHtml(supplier.email || '-')}</td>
                <td class="col-phone">${escapeHtml(supplier.phone_number || '-')}</td>
                <td class="col-city">${escapeHtml(supplier.city || '-')}</td>
                <td class="col-status">
                    <span class="status-badge ${supplier.status === 'Active' ? 'active' : 'inactive'}">
                        ${escapeHtml(supplier.status)}
                    </span>
                </td>
                <td class="col-action">
                    <button class="action-btn edit" data-id="${supplier.supplier_id}" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="action-btn delete" data-id="${supplier.supplier_id}" title="Delete"><i class="fas fa-trash"></i></button>
                </td>
            `;
            supplierTableBody.appendChild(tr);
        });

        // Add event listeners to action buttons
        document.querySelectorAll('.action-btn.edit').forEach(btn => {
            btn.addEventListener('click', () => editSupplier(btn.dataset.id));
        });

        document.querySelectorAll('.action-btn.delete').forEach(btn => {
            btn.addEventListener('click', () => showDeleteConfirm(btn.dataset.id));
        });
    }

    // ==================== SEARCH AND SORT FUNCTIONALITY ====================
    function searchAndFilterSuppliers() {
        const searchTerm = (supplierSearchInput.value || '').toLowerCase().trim();
        const sortOption = supplierSortDropdown.value;

        // If both search and sort are empty, show all suppliers
        if (!searchTerm && !sortOption) {
            renderSuppliers(allSuppliers);
            return;
        }

        // Filter by search term
        let filteredSuppliers = allSuppliers.filter(supplier => {
            if (!searchTerm) return true;
            const name = (supplier.supplier_name || '').toLowerCase();
            const email = (supplier.email || '').toLowerCase();
            const city = (supplier.city || '').toLowerCase();
            
            return name.includes(searchTerm) || email.includes(searchTerm) || city.includes(searchTerm);
        });

        // Apply sorting
        if (sortOption) {
            filteredSuppliers = sortSuppliers(filteredSuppliers, sortOption);
        }

        renderSuppliers(filteredSuppliers);
    }

    function sortSuppliers(suppliers, sortOption) {
        const supCopy = [...suppliers];

        switch(sortOption) {
            case 'name-asc':
                return supCopy.sort((a, b) => (a.supplier_name || '').localeCompare(b.supplier_name || ''));
            case 'name-desc':
                return supCopy.sort((a, b) => (b.supplier_name || '').localeCompare(a.supplier_name || ''));
            case 'email-asc':
                return supCopy.sort((a, b) => (a.email || '').localeCompare(b.email || ''));
            case 'email-desc':
                return supCopy.sort((a, b) => (b.email || '').localeCompare(a.email || ''));
            case 'city-asc':
                return supCopy.sort((a, b) => (a.city || '').localeCompare(b.city || ''));
            case 'city-desc':
                return supCopy.sort((a, b) => (b.city || '').localeCompare(a.city || ''));
            case 'status-asc':
                return supCopy.sort((a, b) => (a.status || '').localeCompare(b.status || ''));
            case 'status-desc':
                return supCopy.sort((a, b) => (b.status || '').localeCompare(a.status || ''));
            default:
                return supCopy;
        }
    }

    // ==================== EVENT LISTENERS FOR SEARCH AND SORT ====================
    supplierSearchInput?.addEventListener('input', searchAndFilterSuppliers);
    supplierSortDropdown?.addEventListener('change', searchAndFilterSuppliers);

    // ==================== MODAL HANDLING ====================
    addSupplierBtn.addEventListener('click', () => {
        resetForm();
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add Supplier';
        supplierModal.classList.add('show');
    });

    closeSupplierModal.addEventListener('click', () => {
        supplierModal.classList.remove('show');
    });

    cancelBtn.addEventListener('click', () => {
        supplierModal.classList.remove('show');
    });

    window.addEventListener('click', (e) => {
        if (e.target === supplierModal) {
            supplierModal.classList.remove('show');
        }
        if (e.target === deleteConfirmModal) {
            deleteConfirmModal.classList.remove('show');
        }
    });

    // ==================== FORM RESET ====================
    function resetForm() {
        supplierForm.reset();
        supplierIdInput.value = '';
        imagePreview.innerHTML = '<i class="fas fa-image"></i><p>No image selected</p>';
    }

    tinInput?.addEventListener('input', () => {
        const formatted = formatTinInputValue(tinInput.value);
        if (tinInput.value !== formatted) {
            tinInput.value = formatted;
        }
    });

    // ==================== IMAGE PREVIEW ====================
    supplierImageInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (event) => {
                imagePreview.innerHTML = `<img src="${event.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        }
    });

    // ==================== ADD/EDIT SUPPLIER ====================
    supplierForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const isEdit = !!supplierIdInput.value;
        
        // Validate required fields
        if (!supplierNameInput.value.trim()) {
            showToast('Supplier name is required', 'error');
            return;
        }

        const form = new FormData(supplierForm);
        form.append('action', isEdit ? 'edit_supplier' : 'add_supplier');
        if (isEdit) {
            form.append('supplier_id', supplierIdInput.value);
        }

        try {
            const res = await fetch('../../app/api/supplier_management.php', {
                method: 'POST',
                body: form,
                credentials: 'include'
            });

            const data = await res.json();

            if (data.success) {
                showToast(data.message, 'success');
                supplierModal.classList.remove('show');
                loadSuppliers();
            } else {
                showToast(data.message, 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Error saving supplier', 'error');
        }
    });

    // ==================== EDIT SUPPLIER ====================
    async function editSupplier(supplierId) {
        try {
            const form = new FormData();
            form.append('action', 'get_supplier');
            form.append('supplier_id', supplierId);

            const res = await fetch('../../app/api/supplier_management.php', {
                method: 'POST',
                body: form,
                credentials: 'include'
            });

            const data = await res.json();

            if (data.success && data.supplier) {
                const supplier = data.supplier;
                
                // Populate form
                supplierIdInput.value = supplier.supplier_id;
                supplierNameInput.value = supplier.supplier_name || '';
                contactPersonInput.value = supplier.contact_person || '';
                phoneNumberInput.value = supplier.phone_number || '';
                emailInput.value = supplier.email || '';
                addressInput.value = supplier.address || '';
                cityInput.value = supplier.city || '';
                countryInput.value = supplier.country || '';
                postalCodeInput.value = supplier.postal_code || '';
                if (tinInput) {
                    tinInput.value = supplier.tin || '';
                }
                statusInput.value = supplier.status || 'Active';

                // Handle image preview
                if (supplier.supplier_image) {
                    imagePreview.innerHTML = `<img src="../${escapeHtml(supplier.supplier_image)}" alt="Preview">`;
                } else {
                    imagePreview.innerHTML = '<i class="fas fa-image"></i><p>No image selected</p>';
                }

                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Supplier';
                supplierModal.classList.add('show');
            } else {
                showToast('Failed to load supplier', 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Error loading supplier', 'error');
        }
    }

    // ==================== DELETE CONFIRMATION ====================
    function showDeleteConfirm(supplierId) {
        const supplier = allSuppliers.find(s => s.supplier_id == supplierId);
        if (supplier) {
            currentDeleteId = supplierId;
            deleteSupplierName.textContent = escapeHtml(supplier.supplier_name);
            deleteConfirmModal.classList.add('show');
        }
    }

    cancelDeleteBtn.addEventListener('click', () => {
        deleteConfirmModal.classList.remove('show');
        currentDeleteId = null;
    });

    // ==================== DELETE SUPPLIER ====================
    confirmDeleteBtn.addEventListener('click', async () => {
        if (!currentDeleteId) return;

        try {
            const form = new FormData();
            form.append('action', 'delete_supplier');
            form.append('supplier_id', currentDeleteId);

            const res = await fetch('../../app/api/supplier_management.php', {
                method: 'POST',
                body: form,
                credentials: 'include'
            });

            const data = await res.json();

            if (data.success) {
                showToast(data.message, 'success');
                deleteConfirmModal.classList.remove('show');
                currentDeleteId = null;
                loadSuppliers();
            } else {
                showToast(data.message, 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Error deleting supplier', 'error');
        }
    });

    // ==================== INITIAL LOAD ====================
    loadSuppliers();
});
