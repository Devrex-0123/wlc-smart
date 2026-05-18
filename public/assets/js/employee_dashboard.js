// ============= TOAST FUNCTION =============
function showToast(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    const div = document.createElement('div');
    div.className = `toast ${type}`;
    div.textContent = msg;
    container.appendChild(div);
    setTimeout(() => div.remove(), 4000);
}

// ============= LOAD ASSIGNED INVENTORY =============
async function loadAssignedInventory() {
    try {
        const response = await fetch('../../app/api/employee_inventory.php', {
            method: 'POST',
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success && data.inventory) {
            displayInventory(data.inventory);
            updateStatistics(data.inventory);
        } else {
            showToast('Failed to load inventory', 'error');
        }
    } catch (err) {
        console.error('Error loading inventory:', err);
        showToast('Error loading inventory', 'error');
    }
}

// ============= DISPLAY INVENTORY TABLE =============
function displayInventory(inventory) {
    const tableBody = document.getElementById('inventoryTableBody');
    tableBody.innerHTML = '';

    if (inventory.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align:center;padding:50px;color:#64748b;">
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No inventory items assigned to you yet.</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    inventory.forEach((inv, index) => {
        const statusClass = (inv.status || 'Available').toLowerCase().replace(/\s+/g, '');
        const conditionClass = (inv.condition_status || '').toLowerCase().replace(/\s+/g, '');
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${index + 1}</td>
            <td><strong>${inv.name || inv.item_name || '-'}</strong></td>
            <td>${inv.item_code || '-'}</td>
            <td>${inv.office_name || '-'}</td>
            <td>${inv.quantity}</td>
            <td><span class="condition-badge ${conditionClass}">${inv.condition_status || '-'}</span></td>
            <td><span class="status-badge ${statusClass}">${inv.status || 'Available'}</span></td>
            <td>
                <button class="view-btn" data-id="${inv.inventory_id}" 
                    data-name="${inv.name || ''}"
                    data-item="${inv.item_name || ''}"
                    data-code="${inv.item_code || ''}"
                    data-facility="${inv.office_name || ''}"
                    data-quantity="${inv.quantity}"
                    data-condition="${inv.condition_status || ''}"
                    data-status="${inv.status || 'Available'}"
                    data-date="${inv.acquisition_date || ''}"
                    data-remarks="${inv.remarks || ''}"
                    data-photo="${inv.photo_url || ''}"
                    title="View Details" style="padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">
                    <i class="fas fa-eye"></i> View
                </button>
            </td>
        `;
        tableBody.appendChild(tr);
    });

    // Add view button event listeners
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            showDetailModal(btn.dataset);
        });
    });
}

// ============= SHOW DETAIL MODAL =============
function showDetailModal(data) {
    document.getElementById('detailName').textContent = data.name || '-';
    document.getElementById('detailLocation').textContent = data.facility || '-';
    document.getElementById('detailItemCode').textContent = data.code || '-';
    document.getElementById('detailItemType').textContent = data.item || '-';
    document.getElementById('detailQuantity').textContent = data.quantity || '-';
    document.getElementById('detailCondition').textContent = data.condition || '-';
    document.getElementById('detailStatus').textContent = data.status || '-';
    document.getElementById('detailDate').textContent = data.date ? new Date(data.date).toLocaleDateString() : '-';
    document.getElementById('detailRemarks').textContent = data.remarks || 'No remarks';

    // Handle photo
    if (data.photo) {
        document.getElementById('detailPhoto').src = '../../app/api/public/' + data.photo;
        document.getElementById('detailPhoto').style.display = 'block';
        document.getElementById('detailPhotoPlaceholder').style.display = 'none';
    } else {
        document.getElementById('detailPhoto').style.display = 'none';
        document.getElementById('detailPhotoPlaceholder').style.display = 'flex';
    }

    // Load components
    loadInventoryComponents(data.id);

    document.getElementById('detailModal').style.display = 'flex';
}

// ============= LOAD INVENTORY COMPONENTS =============
async function loadInventoryComponents(inventoryId) {
    try {
        const formData = new FormData();
        formData.append('action', 'get_components');
        formData.append('inventory_id', inventoryId);

        const response = await fetch('../../app/api/inventory_management.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });
        const data = await response.json();

        const componentsList = document.getElementById('detailComponentsList');
        componentsList.innerHTML = '';

        if (data.success && data.components && data.components.length > 0) {
            data.components.forEach(comp => {
                const compDiv = document.createElement('div');
                compDiv.style.cssText = `
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 1.5rem;
                    background: white;
                `;

                let photoHTML = '';
                if (comp.photo_url && comp.photo_url.trim()) {
                    photoHTML = `<img src="../../app/api/public/${comp.photo_url}" alt="${comp.item_name}" style="width: 100px; height: 100px; border-radius: 8px; object-fit: cover;" />`;
                } else {
                    photoHTML = `<div style="width: 100px; height: 100px; border-radius: 8px; background: linear-gradient(135deg, #3b82f6, #60a5fa); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">IMG</div>`;
                }

                compDiv.innerHTML = `
                    <div style="display: flex; gap: 1.5rem; align-items: flex-start;">
                        <div>
                            ${photoHTML}
                        </div>
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 0.5rem 0; color: #1e293b;">${comp.item_name || 'Component'}</h4>
                            <p style="margin: 0.5rem 0; color: #64748b; font-size: 0.9rem;">
                                <strong>Code:</strong> ${comp.code || '-'}
                            </p>
                            <p style="margin: 0.5rem 0; color: #64748b; font-size: 0.9rem;">
                                <strong>Quantity:</strong> ${comp.quantity || 1}
                            </p>
                        </div>
                    </div>
                `;
                componentsList.appendChild(compDiv);
            });
        } else {
            componentsList.innerHTML = '<p style="text-align: center; color: #94a3b8; padding: 2rem;">No components added to this item</p>';
        }
    } catch (err) {
        console.error('Error loading components:', err);
    }
}

// ============= UPDATE STATISTICS =============
function updateStatistics(inventory) {
    let good = 0, fair = 0, poor = 0;

    inventory.forEach(inv => {
        const condition = (inv.condition_status || '').toLowerCase();
        if (condition === 'good') good++;
        else if (condition === 'fair') fair++;
        else if (condition === 'poor') poor++;
    });

    document.getElementById('assignedCount').textContent = inventory.length;
    document.getElementById('goodCount').textContent = good;
    document.getElementById('fairCount').textContent = fair;
    document.getElementById('poorCount').textContent = poor;
}

// ============= INITIALIZE =============
document.addEventListener('DOMContentLoaded', () => {
    loadAssignedInventory();
});
