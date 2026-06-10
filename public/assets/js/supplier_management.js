document.addEventListener('DOMContentLoaded', () => {
    // ==================== DOM Elements ====================
    const supplierTableBody = document.getElementById('supplierTableBody');
    
    const supplierSearchInput = document.getElementById('supplierSearchInput');
    const prevSupplierBtn = document.getElementById('prevSupplierBtn');
    const nextSupplierBtn = document.getElementById('nextSupplierBtn');
    const supplierPageInfo = document.getElementById('supplierPageInfo');

    const ITEMS_PER_PAGE = 10;
    
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
    const supplierImagePreview = document.getElementById('supplierImagePreview');
    const supplierImagePlaceholder = document.getElementById('supplierImagePlaceholder');
    
    // Delete modal
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const deleteConfirmBackdrop = document.getElementById('deleteConfirmBackdrop');
    const closeDeleteModal = document.getElementById('closeDeleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const deleteSupplierName = document.getElementById('deleteSupplierName');

    const supplierSuccessModal = document.getElementById('supplierSuccessModal');
    const supplierSuccessBackdrop = document.getElementById('supplierSuccessBackdrop');
    const supplierSuccessTitle = document.getElementById('supplierSuccessTitle');
    const supplierSuccessMessage = document.getElementById('supplierSuccessMessage');
    const supplierSuccessOk = document.getElementById('supplierSuccessOk');
    
    let currentDeleteId = null;
    let allSuppliers = [];
    let filteredSuppliers = [];
    let currentPage = 1;

    // ==================== SUCCESS MODAL ====================
    function openSupplierSuccessModal(title, message) {
        if (supplierSuccessTitle) {
            supplierSuccessTitle.textContent = title;
        }
        if (supplierSuccessMessage) {
            supplierSuccessMessage.textContent = message;
        }
        supplierSuccessModal?.classList.add('is-open');
        supplierSuccessModal?.setAttribute('aria-hidden', 'false');
        supplierSuccessOk?.focus();
    }

    function closeSupplierSuccessModal() {
        supplierSuccessModal?.classList.remove('is-open');
        supplierSuccessModal?.setAttribute('aria-hidden', 'true');
    }

    supplierSuccessBackdrop?.addEventListener('click', closeSupplierSuccessModal);
    supplierSuccessOk?.addEventListener('click', closeSupplierSuccessModal);

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

    function normalizePhoneInputValue(raw) {
        return String(raw || '').replace(/\D/g, '').slice(0, 11);
    }

    function isValidSupplierEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function validateSupplierForm() {
        const supplierName = supplierNameInput.value.trim();
        const contactPerson = contactPersonInput.value.trim();
        const email = emailInput.value.trim();
        const phone = normalizePhoneInputValue(phoneNumberInput.value);
        const address = addressInput.value.trim();
        const city = cityInput.value.trim();
        const country = countryInput.value.trim();
        const status = statusInput.value.trim();

        if (!supplierName) {
            return 'Supplier name is required';
        }
        if (!status || !['Active', 'Inactive'].includes(status)) {
            return 'Status is required';
        }
        if (!contactPerson) {
            return 'Contact person is required';
        }
        if (!email) {
            return 'Email is required';
        }
        if (!isValidSupplierEmail(email)) {
            return 'Enter a valid email address (e.g. supplier@example.com)';
        }
        if (!phone) {
            return 'Phone number is required';
        }
        if (!/^\d{11}$/.test(phone)) {
            return 'Phone number must contain exactly 11 digits (e.g. 09123456789)';
        }
        if (!address) {
            return 'Street address is required';
        }
        if (!city) {
            return 'City is required';
        }
        if (!country) {
            return 'Country is required';
        }

        return null;
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
                filteredSuppliers = allSuppliers;
                currentPage = 1;
                applySupplierView();
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

    function updatePaginationUI(totalRecords, totalPages) {
        if (!supplierPageInfo || !prevSupplierBtn || !nextSupplierBtn) return;

        if (totalRecords === 0) {
            supplierPageInfo.textContent = 'Page 1 of 1 (0 records)';
            prevSupplierBtn.disabled = true;
            nextSupplierBtn.disabled = true;
            return;
        }

        supplierPageInfo.textContent = `Page ${currentPage} of ${totalPages} (${totalRecords} records)`;
        prevSupplierBtn.disabled = currentPage <= 1;
        nextSupplierBtn.disabled = currentPage >= totalPages;
    }

    function applySupplierView() {
        const totalRecords = filteredSuppliers.length;
        const totalPages = Math.max(1, Math.ceil(totalRecords / ITEMS_PER_PAGE));
        currentPage = Math.min(Math.max(currentPage, 1), totalPages);

        if (totalRecords === 0) {
            supplierTableBody.innerHTML = '<tr><td colspan="9" class="loading-cell">No supplier added yet</td></tr>';
            updatePaginationUI(0, 1);
            return;
        }

        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        const pageItems = filteredSuppliers.slice(start, start + ITEMS_PER_PAGE);
        renderSuppliers(pageItems, start);
        updatePaginationUI(totalRecords, totalPages);
    }

    // ==================== RENDER SUPPLIERS ====================
    function renderSuppliers(suppliers, recordOffset = 0) {
        supplierTableBody.innerHTML = '';

        suppliers.forEach((supplier, index) => {
            const tr = document.createElement('tr');
            const recordNum = recordOffset + index + 1;
            const stripeMod = recordNum % 3;

            if (stripeMod === 2) {
                tr.classList.add('supplier-row-stripe--alt');
            } else if (stripeMod === 0) {
                tr.classList.add('supplier-row-stripe--green');
            }

            const imageHtml = supplier.supplier_image 
                ? `<img src="../${escapeHtml(supplier.supplier_image)}" alt="${escapeHtml(supplier.supplier_name)}" class="table-image">`
                : '<div class="table-image-placeholder"><i class="fas fa-image"></i></div>';
            
            tr.innerHTML = `
                <td>${recordOffset + index + 1}</td>
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

        const spacerCount = ITEMS_PER_PAGE - suppliers.length;
        for (let i = 0; i < spacerCount; i++) {
            const spacerRow = document.createElement('tr');
            spacerRow.className = 'supplier-row-spacer';
            spacerRow.setAttribute('aria-hidden', 'true');
            spacerRow.innerHTML = '<td colspan="9"></td>';
            supplierTableBody.appendChild(spacerRow);
        }
    }

    // ==================== SEARCH FUNCTIONALITY ====================
    function searchAndFilterSuppliers() {
        const searchTerm = (supplierSearchInput.value || '').toLowerCase().trim();

        if (!searchTerm) {
            filteredSuppliers = allSuppliers;
        } else {
            filteredSuppliers = allSuppliers.filter(supplier => {
                const name = (supplier.supplier_name || '').toLowerCase();
                const email = (supplier.email || '').toLowerCase();
                const city = (supplier.city || '').toLowerCase();

                return name.includes(searchTerm) || email.includes(searchTerm) || city.includes(searchTerm);
            });
        }

        currentPage = 1;
        applySupplierView();
    }

    supplierSearchInput?.addEventListener('input', searchAndFilterSuppliers);

    prevSupplierBtn?.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage -= 1;
            applySupplierView();
        }
    });

    nextSupplierBtn?.addEventListener('click', () => {
        const totalPages = Math.max(1, Math.ceil(filteredSuppliers.length / ITEMS_PER_PAGE));
        if (currentPage < totalPages) {
            currentPage += 1;
            applySupplierView();
        }
    });

    function setSupplierImagePreview(src) {
        if (!supplierImagePreview || !supplierImagePlaceholder) return;

        if (src) {
            supplierImagePreview.src = src;
            supplierImagePreview.hidden = false;
            supplierImagePlaceholder.hidden = true;
        } else {
            supplierImagePreview.removeAttribute('src');
            supplierImagePreview.hidden = true;
            supplierImagePlaceholder.hidden = false;
        }
    }

    // ==================== MODAL HANDLING ====================
    addSupplierBtn.addEventListener('click', () => {
        resetForm();
        document.getElementById('modalTitle').textContent = 'Add Supplier';
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
    });

    function closeDeleteConfirmModal() {
        deleteConfirmModal?.classList.remove('is-open');
        deleteConfirmModal?.setAttribute('aria-hidden', 'true');
        currentDeleteId = null;
        if (confirmDeleteBtn) {
            confirmDeleteBtn.disabled = false;
        }
    }

    // ==================== FORM RESET ====================
    function resetForm() {
        supplierForm.reset();
        supplierIdInput.value = '';
        setSupplierImagePreview(null);
    }

    tinInput?.addEventListener('input', () => {
        const formatted = formatTinInputValue(tinInput.value);
        if (tinInput.value !== formatted) {
            tinInput.value = formatted;
        }
    });

    phoneNumberInput?.addEventListener('input', () => {
        const normalized = normalizePhoneInputValue(phoneNumberInput.value);
        if (phoneNumberInput.value !== normalized) {
            phoneNumberInput.value = normalized;
        }
    });

    // ==================== IMAGE PREVIEW ====================
    supplierImageInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) {
            setSupplierImagePreview(null);
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            setSupplierImagePreview(event.target.result);
        };
        reader.readAsDataURL(file);
    });

    // ==================== ADD/EDIT SUPPLIER ====================
    supplierForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const isEdit = !!supplierIdInput.value;

        const validationError = validateSupplierForm();
        if (validationError) {
            showToast(validationError, 'error');
            return;
        }

        const form = new FormData(supplierForm);
        form.set('phone_number', normalizePhoneInputValue(phoneNumberInput.value));
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
                supplierModal.classList.remove('show');
                loadSuppliers();
                openSupplierSuccessModal(
                    isEdit ? 'Supplier Updated' : 'Supplier Added',
                    data.message || (isEdit ? 'Supplier updated successfully.' : 'Supplier added successfully.')
                );
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
                phoneNumberInput.value = normalizePhoneInputValue(supplier.phone_number || '');
                emailInput.value = supplier.email || '';
                addressInput.value = supplier.address || '';
                cityInput.value = supplier.city || '';
                countryInput.value = supplier.country || '';
                postalCodeInput.value = supplier.postal_code || '';
                if (tinInput) {
                    tinInput.value = supplier.tin || '';
                }
                statusInput.value = supplier.status || 'Active';

                setSupplierImagePreview(
                    supplier.supplier_image ? `../${supplier.supplier_image}` : null
                );

                document.getElementById('modalTitle').textContent = 'Edit Supplier';
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
        if (!supplier) return;

        currentDeleteId = supplierId;
        deleteSupplierName.textContent = supplier.supplier_name || 'this supplier';
        deleteConfirmModal?.classList.add('is-open');
        deleteConfirmModal?.setAttribute('aria-hidden', 'false');
        cancelDeleteBtn?.focus();
    }

    deleteConfirmBackdrop?.addEventListener('click', closeDeleteConfirmModal);
    closeDeleteModal?.addEventListener('click', closeDeleteConfirmModal);
    cancelDeleteBtn?.addEventListener('click', closeDeleteConfirmModal);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && deleteConfirmModal?.classList.contains('is-open')) {
            closeDeleteConfirmModal();
        }
        if (e.key === 'Escape' && supplierSuccessModal?.classList.contains('is-open')) {
            closeSupplierSuccessModal();
        }
    });

    // ==================== DELETE SUPPLIER ====================
    confirmDeleteBtn?.addEventListener('click', async () => {
        if (!currentDeleteId) return;

        confirmDeleteBtn.disabled = true;

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
                closeDeleteConfirmModal();
                loadSuppliers();
                openSupplierSuccessModal(
                    'Supplier Deleted',
                    data.message || 'Supplier deleted successfully.'
                );
            } else {
                showToast(data.message, 'error');
                confirmDeleteBtn.disabled = false;
            }
        } catch (err) {
            console.error(err);
            showToast('Error deleting supplier', 'error');
            confirmDeleteBtn.disabled = false;
        }
    });

    // ==================== INITIAL LOAD ====================
    loadSuppliers();
});
