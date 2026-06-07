(function () {
    const cfg = window.IMRMS_PURCHASE_ORDER_CONFIG || {};
    const api = cfg.api || '../../app/api/purchase_order.php';
    const poId = Number(cfg.poId || 0);
    const requestId = Number(cfg.requestId || 0);
    const isPresidentVerifier = Boolean(cfg.isPresidentVerifier);

    const poNumberEl = document.getElementById('poNumber');
    const dateIssuedEl = document.getElementById('poDateIssued');
    const requestedByEl = document.getElementById('poRequestedBy');
    const locationEl = document.getElementById('poLocation');
    const supplierNameEl = document.getElementById('poSupplierName');
    const supplierTinEl = document.getElementById('poSupplierTin');
    const modeOfPaymentEl = document.getElementById('poModeOfPayment');
    const purposeEl = document.getElementById('poPurpose');
    const linesBody = document.getElementById('poLinesBody');
    const grandTotalEl = document.getElementById('poGrandTotal');
    const approveBtn = document.getElementById('poApproveBtn');
    const rejectBtn = document.getElementById('poRejectBtn');
    const undoBtn = document.getElementById('poUndoBtn');
    const presidentStatusEl = document.getElementById('poPresidentStatus');
    const verifierCard = document.querySelector('.po-verifier-card');
    const toast = document.getElementById('poToast');

    let currentPoId = poId > 0 ? poId : 0;

    function showToast(message, type = 'error') {
        if (!toast) {
            return;
        }
        toast.textContent = message;
        toast.className = `toast ${type === 'success' ? 'success' : type === 'info' ? 'info' : 'error'}`;
        toast.style.display = 'block';
        const ms = type === 'success' ? 2800 : 3500;
        window.setTimeout(() => {
            toast.style.display = 'none';
        }, ms);
    }

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = value == null ? '' : String(value);
        return node.innerHTML;
    }

    function formatMoney(amount) {
        const num = Number(amount);
        const safe = Number.isFinite(num) ? num : 0;
        return `PHP ${safe.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;
    }

    function formatTinDisplay(value) {
        const digits = String(value || '').replace(/\D/g, '').slice(0, 12);
        if (!digits) {
            return '';
        }
        const parts = digits.match(/.{1,3}/g) || [];
        return parts.join('-');
    }

    function resolveModeOfPaymentLabel(totalAmount) {
        const total = Number(totalAmount);
        const safe = Number.isFinite(total) ? total : 0;
        return safe <= 1500 ? 'Cash' : 'Cheque';
    }

    function updateModeOfPaymentDisplay(totalAmount) {
        if (!modeOfPaymentEl) {
            return;
        }
        modeOfPaymentEl.value = resolveModeOfPaymentLabel(totalAmount);
    }

    function setReadonlyField(el, value, fallback = '—') {
        if (!el) {
            return;
        }
        const text = String(value ?? '').trim();
        el.value = text !== '' ? text : fallback;
    }

    function setTinField(value) {
        if (!supplierTinEl) {
            return;
        }
        const formatted = formatTinDisplay(value);
        supplierTinEl.value = formatted;
        supplierTinEl.placeholder = '000-000-000-000';
    }

    function renderLines(lines) {
        if (!linesBody) {
            return;
        }

        const rows = Array.isArray(lines) ? lines : [];
        if (!rows.length) {
            linesBody.innerHTML = '<tr class="po-line-row"><td colspan="5" class="po-line-empty">No line items.</td></tr>';
            return;
        }

        linesBody.innerHTML = rows
            .map((line, index) => {
                const description = escapeHtml(line.description || '—');
                const subDescription = escapeHtml(line.sub_description || '—');
                const qty = Number(line.quantity || 0);
                const unitPrice = formatMoney(line.unit_price || 0);
                const amount = formatMoney(line.amount != null ? line.amount : (qty * Number(line.unit_price || 0)));
                return `<tr class="po-line-row" data-line-index="${index}">
                    <td class="po-line-text">${description}</td>
                    <td class="po-line-text">${subDescription}</td>
                    <td class="po-line-num">${qty}</td>
                    <td class="po-line-num">${unitPrice}</td>
                    <td class="po-line-amount-cell"><span class="po-line-amount">${amount}</span></td>
                </tr>`;
            })
            .join('');
    }

    function setVerifierState(record) {
        const approved = Boolean(record.approved_by_president) || record.status === 'approved';
        const rejected = record.status === 'rejected';
        let label = 'Pending';
        if (approved) {
            label = 'Verified';
        } else if (rejected) {
            label = 'Rejected';
        }
        if (presidentStatusEl) {
            presidentStatusEl.textContent = label;
        }
        if (verifierCard) {
            verifierCard.classList.remove('po-verifier-approved', 'po-verifier-rejected');
            if (approved) {
                verifierCard.classList.add('po-verifier-approved');
            } else if (rejected) {
                verifierCard.classList.add('po-verifier-rejected');
            }
        }
        const circle = document.querySelector('#poPresidentVerifier .circle-icon');
        if (circle) {
            circle.classList.toggle('inactive', !approved);
            circle.classList.toggle('active', approved);
        }
    }

    function setActionButtons(record) {
        const status = String(record.status || 'pending').toLowerCase();
        const isPending = status === 'pending';
        const isDecided = status === 'approved' || status === 'rejected';

        if (!isPresidentVerifier) {
            return;
        }

        if (approveBtn) {
            approveBtn.style.display = currentPoId > 0 && isPending ? 'inline-flex' : 'none';
        }
        if (rejectBtn) {
            rejectBtn.style.display = currentPoId > 0 && isPending ? 'inline-flex' : 'none';
        }
        if (undoBtn) {
            undoBtn.style.display = currentPoId > 0 && isDecided ? 'inline-flex' : 'none';
        }
    }

    function populateForm(record) {
        currentPoId = Number(record.id || 0);
        const totalAmount = Number(record.total_amount || 0);

        setReadonlyField(poNumberEl, record.po_number);
        setReadonlyField(dateIssuedEl, record.date_issued || cfg.todayLabel || '');
        setReadonlyField(requestedByEl, record.requested_by || cfg.defaultRequestedBy || '');
        setReadonlyField(locationEl, record.location_facility);
        setReadonlyField(supplierNameEl, record.supplier_name);
        setTinField(record.supplier_tin || '');
        setReadonlyField(purposeEl, record.purpose_of_request);
        updateModeOfPaymentDisplay(totalAmount);

        renderLines(record.lines || []);

        if (grandTotalEl) {
            grandTotalEl.textContent = formatMoney(totalAmount);
        }

        setVerifierState(record);
        setActionButtons(record);
    }

    async function apiPost(action, payload = {}) {
        const body = new FormData();
        body.append('action', action);
        Object.entries(payload).forEach(([key, value]) => {
            if (value === undefined || value === null) {
                return;
            }
            body.append(key, String(value));
        });

        const res = await fetch(api, {
            method: 'POST',
            credentials: 'same-origin',
            body,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Request failed.');
        }
        return data;
    }

    async function loadPurchaseOrder(id) {
        const params = new URLSearchParams({ action: 'fetch', id: String(id) });
        const res = await fetch(`${api}?${params.toString()}`, { credentials: 'same-origin' });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Unable to load purchase order.');
        }
        populateForm(data.data || {});
    }

    async function ensurePurchaseOrderForRequest() {
        if (requestId <= 0) {
            return null;
        }
        const data = await apiPost('ensure_for_request', { request_id: requestId });
        return data.data || null;
    }

    async function handlePresidentAction(action) {
        if (!currentPoId) {
            return;
        }
        try {
            if (approveBtn) {
                approveBtn.disabled = true;
            }
            if (rejectBtn) {
                rejectBtn.disabled = true;
            }
            if (undoBtn) {
                undoBtn.disabled = true;
            }
            const data = await apiPost(action, { id: currentPoId });
            showToast(data.message || 'Updated.', 'success');
            await loadPurchaseOrder(currentPoId);
        } catch (err) {
            showToast(err.message || 'Action failed.');
        } finally {
            if (approveBtn) {
                approveBtn.disabled = false;
            }
            if (rejectBtn) {
                rejectBtn.disabled = false;
            }
            if (undoBtn) {
                undoBtn.disabled = false;
            }
        }
    }

    async function init() {
        if (approveBtn) {
            approveBtn.addEventListener('click', () => handlePresidentAction('approve'));
        }
        if (rejectBtn) {
            rejectBtn.addEventListener('click', () => handlePresidentAction('reject'));
        }
        if (undoBtn) {
            undoBtn.addEventListener('click', () => handlePresidentAction('undo'));
        }

        try {
            if (currentPoId > 0) {
                await loadPurchaseOrder(currentPoId);
                return;
            }

            if (requestId > 0) {
                const created = await ensurePurchaseOrderForRequest();
                if (created && Number(created.id) > 0) {
                    currentPoId = Number(created.id);
                    const url = new URL(window.location.href);
                    url.searchParams.set('id', String(currentPoId));
                    window.history.replaceState({}, '', url.toString());
                    populateForm(created);
                    return;
                }
            }

            if (linesBody) {
                linesBody.innerHTML = '<tr class="po-line-row"><td colspan="5" class="po-line-empty">Purchase order not available.</td></tr>';
            }
        } catch (err) {
            showToast(err.message || 'Unable to load purchase order.');
            if (linesBody) {
                linesBody.innerHTML = '<tr class="po-line-row"><td colspan="5" class="po-line-empty">Unable to load purchase order lines.</td></tr>';
            }
        }
    }

    init();
})();
