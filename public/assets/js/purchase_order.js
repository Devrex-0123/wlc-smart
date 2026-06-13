(function () {
    const cfg = window.IMRMS_PURCHASE_ORDER_CONFIG || {};
    const api = cfg.api || '../../app/api/purchase_order.php';
    const poId = Number(cfg.poId || 0);
    const requestId = Number(cfg.requestId || 0);
    const isComptroller = Boolean(cfg.isComptroller);
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
    let currentGrossAmount = 0;
    let taxRowCounter = 0;

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

        currentGrossAmount = totalAmount;
        if (isComptroller) {
            setComptrollerTaxSectionVisible(record);
            if (isPresidentApproved(record)) {
                updateTaxGrossDisplay(totalAmount);
                updateTaxBadge(Boolean(record.tax_computed));
                void loadTaxRecord();
            }
        }
    }

    function isPresidentApproved(record) {
        const status = String((record && record.status) || '').trim().toLowerCase();
        return Boolean(record && record.approved_by_president) || status === 'approved';
    }

    function setComptrollerTaxSectionVisible(record) {
        const approved = isPresidentApproved(record);
        if (taxDivider) {
            taxDivider.hidden = !approved;
        }
        if (taxSection) {
            taxSection.hidden = !approved;
        }
        if (taxPendingNotice) {
            taxPendingNotice.hidden = approved;
        }
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

    const taxDivider = document.getElementById('comptroller-divider');
    const taxPendingNotice = document.getElementById('poTaxPendingNotice');
    const taxSection = document.getElementById('comptroller-section');
    const taxRowsBody = document.getElementById('poTaxRowsBody');
    const taxBreakdownDeductions = document.getElementById('poTaxBreakdownDeductions');
    const taxGrossEl = document.getElementById('poTaxGrossAmount');
    const taxNetEl = document.getElementById('poTaxNetPayable');
    const taxNotesEl = document.getElementById('poTaxNotes');
    const taxStatusBadge = document.getElementById('poTaxStatusBadge');
    const taxDraftBtn = document.getElementById('poTaxDraftBtn');
    const taxFinalizeBtn = document.getElementById('poTaxFinalizeBtn');
    const taxReopenBtn = document.getElementById('poTaxReopenBtn');
    const taxDraftRow = document.getElementById('poTaxDraftRow');
    const taxFinalizedPanel = document.getElementById('poTaxFinalizedPanel');
    const taxDraftSavedHint = document.getElementById('poTaxDraftSavedHint');
    const taxFinalizedAtEl = document.getElementById('poTaxFinalizedAt');
    const taxQuickAdd = document.querySelector('.comptroller-tax-quick-add');
    const confirmModal = document.getElementById('poConfirmModal');
    const confirmMessage = document.getElementById('poConfirmMessage');
    const confirmCancelBtn = document.getElementById('poConfirmCancelBtn');
    const confirmOkBtn = document.getElementById('poConfirmOkBtn');

    const EWT_TRANSACTION_TYPES = [
        { key: 'purchase_of_goods', label: 'Purchase of goods', rate: 0.01 },
        { key: 'purchase_of_services', label: 'Purchase of services', rate: 0.02 },
        { key: 'professional_fees', label: 'Professional fees', rate: 0.05 },
        { key: 'professional_fees_high', label: 'Professional fees (high income)', rate: 0.1 },
    ];
    const STANDARD_EWT_RATES = [0.01, 0.02, 0.05, 0.1];

    let addEwtBtn = null;
    let addVatBtn = null;
    let addOtherBtn = null;
    let addDeductionBtn = null;

    /** @type {'draft' | 'finalized'} */
    let currentTaxStatus = 'draft';
    let lastTaxFinalizedAt = null;
    let taxDraftSaving = false;
    let taxFinalizeSaving = false;
    let taxReopenSaving = false;

    function showConfirmModal(message) {
        if (!confirmModal || !confirmMessage || !confirmCancelBtn || !confirmOkBtn) {
            return Promise.resolve(window.confirm(message));
        }
        return new Promise((resolve) => {
            confirmMessage.textContent = message;
            confirmModal.style.display = 'flex';

            const handleConfirm = () => {
                cleanup();
                resolve(true);
            };
            const handleCancel = () => {
                cleanup();
                resolve(false);
            };
            const handleBackdrop = (event) => {
                if (event.target.classList.contains('confirm-modal-backdrop')) {
                    handleCancel();
                }
            };
            const handleEsc = (event) => {
                if (event.key === 'Escape') {
                    handleCancel();
                }
            };

            function cleanup() {
                confirmModal.style.display = 'none';
                confirmOkBtn.removeEventListener('click', handleConfirm);
                confirmCancelBtn.removeEventListener('click', handleCancel);
                confirmModal.removeEventListener('click', handleBackdrop);
                document.removeEventListener('keydown', handleEsc);
            }

            confirmOkBtn.addEventListener('click', handleConfirm);
            confirmCancelBtn.addEventListener('click', handleCancel);
            confirmModal.addEventListener('click', handleBackdrop);
            document.addEventListener('keydown', handleEsc);
        });
    }

    function formatSavedAtLabel(isoOrDate) {
        const date = isoOrDate instanceof Date ? isoOrDate : new Date(isoOrDate || Date.now());
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        return date.toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    }

    function formatFinalizedAtLabel(isoOrDate) {
        const label = formatSavedAtLabel(isoOrDate);
        return label ? `Finalized ${label}` : '';
    }

    function setTaxFormReadOnly(readOnly) {
        if (taxNotesEl) {
            taxNotesEl.readOnly = readOnly;
            taxNotesEl.classList.toggle('is-readonly', readOnly);
        }
        [addEwtBtn, addVatBtn, addOtherBtn, addDeductionBtn].forEach((btn) => {
            if (btn) {
                btn.disabled = readOnly;
            }
        });
        if (taxQuickAdd) {
            taxQuickAdd.classList.toggle('is-disabled', readOnly);
        }
        if (!taxRowsBody) {
            return;
        }
        taxRowsBody.querySelectorAll('input, select, textarea, button').forEach((el) => {
            if (el.classList.contains('po-tax-remove-btn')) {
                el.disabled = readOnly;
                return;
            }
            if (el.tagName === 'BUTTON') {
                el.disabled = readOnly;
                return;
            }
            el.disabled = readOnly;
            if (readOnly) {
                el.setAttribute('readonly', 'readonly');
            } else {
                el.removeAttribute('readonly');
            }
        });
    }

    function applyTaxWorkflowUi(options = {}) {
        const finalized = currentTaxStatus === 'finalized';
        if (taxDraftRow) {
            taxDraftRow.hidden = finalized;
        }
        if (taxFinalizedPanel) {
            taxFinalizedPanel.hidden = !finalized;
        }
        if (taxDraftBtn) {
            taxDraftBtn.disabled = taxDraftSaving || taxFinalizeSaving || finalized;
        }
        if (taxFinalizeBtn) {
            taxFinalizeBtn.disabled = taxDraftSaving || taxFinalizeSaving || finalized;
            taxFinalizeBtn.innerHTML = taxFinalizeSaving
                ? '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Finalizing...'
                : '<i class="fas fa-lock" aria-hidden="true"></i> Finalize &amp; save';
        }
        if (taxDraftBtn) {
            taxDraftBtn.innerHTML = taxDraftSaving
                ? '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Saving...'
                : '<i class="fas fa-floppy-disk" aria-hidden="true"></i> Save as draft';
        }
        if (taxReopenBtn) {
            taxReopenBtn.disabled = taxReopenSaving || !finalized;
            taxReopenBtn.innerHTML = taxReopenSaving
                ? '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Reopening...'
                : '<i class="fas fa-lock-open" aria-hidden="true"></i> Reopen for edit';
        }
        if (finalized && taxFinalizedAtEl) {
            const at = options.finalizedAt || lastTaxFinalizedAt;
            taxFinalizedAtEl.textContent = at ? formatFinalizedAtLabel(at) : '';
        }
        setTaxFormReadOnly(finalized);
        updateTaxBadge(Boolean(options.taxComputed));
    }

    function roundMoney(value) {
        return Math.round(Number(value) * 100) / 100;
    }

    function resolveEwtTypeByLabel(label) {
        const text = String(label || '').trim().toLowerCase();
        return (
            EWT_TRANSACTION_TYPES.find(
                (item) =>
                    item.label.toLowerCase() === text ||
                    item.key === text ||
                    item.key === String(label || '').trim()
            ) || EWT_TRANSACTION_TYPES[1]
        );
    }

    function updateTaxGrossDisplay(amount) {
        if (taxGrossEl) {
            taxGrossEl.textContent = formatMoney(amount);
        }
    }

    function updateTaxBadge(computed) {
        if (!taxStatusBadge) {
            return;
        }
        if (currentTaxStatus === 'finalized') {
            taxStatusBadge.textContent = 'Finalized';
            taxStatusBadge.classList.remove('comptroller-tax-badge--pending');
            taxStatusBadge.classList.add('comptroller-tax-badge--computed', 'comptroller-tax-badge--finalized');
            return;
        }
        if (computed) {
            taxStatusBadge.textContent = 'Draft saved';
            taxStatusBadge.classList.remove('comptroller-tax-badge--pending', 'comptroller-tax-badge--finalized');
            taxStatusBadge.classList.add('comptroller-tax-badge--computed');
            return;
        }
        taxStatusBadge.textContent = 'Pending computation';
        taxStatusBadge.classList.remove('comptroller-tax-badge--computed', 'comptroller-tax-badge--finalized');
        taxStatusBadge.classList.add('comptroller-tax-badge--pending');
    }

    function normalizeTaxTypeKey(taxType) {
        const lc = String(taxType || '').trim().toLowerCase();
        if (lc === 'ewt') {
            return 'ewt';
        }
        if (lc === 'vat withholding' || lc === 'vat') {
            return 'vat';
        }
        return 'other';
    }

    function formatRateLabel(rate) {
        const pct = Number(rate) * 100;
        if (!Number.isFinite(pct)) {
            return '—';
        }
        const rounded = Math.round(pct * 100) / 100;
        return `${rounded % 1 === 0 ? rounded.toFixed(0) : rounded.toFixed(2)}%`;
    }

    function buildEwtTransactionOptions(selectedKey) {
        return EWT_TRANSACTION_TYPES.map((item) => {
            const selected =
                item.key === selectedKey ||
                item.label.toLowerCase() === String(selectedKey || '').trim().toLowerCase();
            return `<option value="${escapeHtml(item.key)}"${selected ? ' selected' : ''}>${escapeHtml(item.label)}</option>`;
        }).join('');
    }

    function getEwtRateFromRow(row) {
        const pctInput = row.querySelector('.po-tax-rate-pct-input');
        const pct = Number(pctInput ? pctInput.value : 0);
        return Number.isFinite(pct) ? pct / 100 : 0;
    }

    function getEwtDefaultRate(row) {
        const select = row.querySelector('.po-tax-transaction-select');
        const key = select ? select.value : 'purchase_of_services';
        const match = EWT_TRANSACTION_TYPES.find((item) => item.key === key);
        return match ? match.rate : 0.02;
    }

    function syncEwtRateOverrideState(row) {
        const defaultRate = getEwtDefaultRate(row);
        const currentRate = getEwtRateFromRow(row);
        const overridden = Math.abs(currentRate - defaultRate) > 0.0001;
        row.dataset.rateOverride = overridden ? '1' : '0';
    }

    function isVatApplicable(row) {
        const supplierYes = row.querySelector('.po-vat-supplier-yes');
        const exemptNo = row.querySelector('.po-vat-exempt-no');
        return Boolean(supplierYes && supplierYes.checked && exemptNo && exemptNo.checked);
    }

    function getVatWarningMessage(row) {
        const supplierYes = row.querySelector('.po-vat-supplier-yes');
        const exemptYes = row.querySelector('.po-vat-exempt-yes');
        if (supplierYes && !supplierYes.checked) {
            return 'VAT withholding not applicable — supplier is non-VAT registered';
        }
        if (exemptYes && exemptYes.checked) {
            return 'VAT withholding not applicable — transaction is VAT-exempt';
        }
        return '';
    }

    function updateVatRowDisplay(row) {
        const warningEl = row.querySelector('.po-vat-warning');
        const amountEl = row.querySelector('.po-tax-amount-readonly');
        const applicable = isVatApplicable(row);
        const warning = getVatWarningMessage(row);
        if (warningEl) {
            if (!applicable && warning) {
                warningEl.textContent = warning;
                warningEl.hidden = false;
            } else {
                warningEl.textContent = '';
                warningEl.hidden = true;
            }
        }
        if (amountEl) {
            if (applicable) {
                amountEl.hidden = false;
                amountEl.textContent = formatMoney(roundMoney(currentGrossAmount * 0.05));
            } else {
                amountEl.hidden = true;
            }
        }
    }

    function createTaxRow(type, data = {}) {
        if (!taxRowsBody) {
            return null;
        }
        taxRowCounter += 1;
        const tr = document.createElement('tr');
        tr.className = 'po-tax-row';
        tr.dataset.rowId = `po-tax-row-${taxRowCounter}`;
        tr.dataset.taxKind = type;
        tr.dataset.rateOverride = data.rate_override ? '1' : '0';

        if (type === 'ewt') {
            const txn = resolveEwtTypeByLabel(data.transaction_type || data.transaction_type_label || 'purchase_of_services');
            const rate = Number(data.rate != null ? data.rate : txn.rate);
            const pct = roundMoney(rate * 100);
            const amount = roundMoney(currentGrossAmount * rate);
            tr.innerHTML = `
                <td>
                    <div class="po-tax-ewt-fields">
                        <span class="po-tax-type-label">Expanded Withholding Tax (EWT)</span>
                        <label class="po-tax-mini-label">Transaction type</label>
                        <select class="po-tax-transaction-select">${buildEwtTransactionOptions(txn.key)}</select>
                        <input type="hidden" class="po-tax-type-value" value="ewt">
                    </div>
                </td>
                <td>
                    <label class="po-tax-mini-label">Rate</label>
                    <div class="po-tax-rate-input-wrap">
                        <input type="number" class="po-tax-rate-pct-input" min="0" max="100" step="0.01" value="${pct}">
                        <span class="po-tax-rate-suffix">%</span>
                    </div>
                </td>
                <td>
                    <label class="po-tax-mini-label">Amount deducted</label>
                    <span class="po-tax-amount-readonly">${formatMoney(amount)}</span>
                </td>
                <td style="text-align:center;">
                    <button type="button" class="po-tax-remove-btn" title="Remove row"><i class="fas fa-trash-can" aria-hidden="true"></i></button>
                </td>`;
        } else if (type === 'vat') {
            const supplierVat = data.supplier_vat_registered !== 0 && data.supplier_vat_registered !== false;
            const vatExempt = data.transaction_vat_exempt === 1 || data.transaction_vat_exempt === true;
            const supplierYes = data.supplier_vat_registered == null ? true : supplierVat;
            const exemptYes = vatExempt;
            tr.innerHTML = `
                <td>
                    <div class="po-tax-vat-fields">
                        <span class="po-tax-type-label">VAT Withholding</span>
                        <div class="po-vat-condition">
                            <span class="po-tax-mini-label">Supplier is VAT-registered</span>
                            <div class="po-vat-radio-group">
                                <label><input type="radio" class="po-vat-supplier-yes" name="poVatSupplier${taxRowCounter}" value="yes"${supplierYes ? ' checked' : ''}> Yes</label>
                                <label><input type="radio" class="po-vat-supplier-no" name="poVatSupplier${taxRowCounter}" value="no"${!supplierYes ? ' checked' : ''}> No</label>
                            </div>
                        </div>
                        <div class="po-vat-condition">
                            <span class="po-tax-mini-label">Transaction is VAT-exempt</span>
                            <div class="po-vat-radio-group">
                                <label><input type="radio" class="po-vat-exempt-yes" name="poVatExempt${taxRowCounter}" value="yes"${exemptYes ? ' checked' : ''}> Yes</label>
                                <label><input type="radio" class="po-vat-exempt-no" name="poVatExempt${taxRowCounter}" value="no"${!exemptYes ? ' checked' : ''}> No</label>
                            </div>
                        </div>
                        <span class="vat-warning po-vat-warning" hidden></span>
                        <input type="hidden" class="po-tax-type-value" value="vat">
                    </div>
                </td>
                <td><span class="po-tax-rate-fixed">5%</span></td>
                <td><span class="po-tax-amount-readonly">${formatMoney(roundMoney(currentGrossAmount * 0.05))}</span></td>
                <td style="text-align:center;">
                    <button type="button" class="po-tax-remove-btn" title="Remove row"><i class="fas fa-trash-can" aria-hidden="true"></i></button>
                </td>`;
        } else {
            const label = String(data.label || '');
            const amount = Number(data.amount_deducted || 0);
            tr.innerHTML = `
                <td>
                    <label class="po-tax-mini-label">Label</label>
                    <input type="text" class="po-tax-label-input" placeholder="Deduction label" value="${escapeHtml(label)}">
                    <input type="hidden" class="po-tax-type-value" value="other">
                </td>
                <td><span class="po-tax-rate-fixed">Manual</span></td>
                <td>
                    <input type="number" class="po-tax-amount-input" min="0" step="0.01" value="${amount > 0 ? amount : ''}" placeholder="0.00">
                </td>
                <td style="text-align:center;">
                    <button type="button" class="po-tax-remove-btn" title="Remove row"><i class="fas fa-trash-can" aria-hidden="true"></i></button>
                </td>`;
        }

        taxRowsBody.appendChild(tr);
        bindTaxRowEvents(tr);
        if (type === 'vat') {
            updateVatRowDisplay(tr);
        }
        updateTaxTypeButtons();
        recalculateTaxBreakdown();
        return tr;
    }

    function bindTaxRowEvents(row) {
        if (!row) {
            return;
        }
        const txnSelect = row.querySelector('.po-tax-transaction-select');
        const ratePctInput = row.querySelector('.po-tax-rate-pct-input');
        const amountInput = row.querySelector('.po-tax-amount-input');
        const removeBtn = row.querySelector('.po-tax-remove-btn');
        const vatRadios = row.querySelectorAll(
            '.po-vat-supplier-yes, .po-vat-supplier-no, .po-vat-exempt-yes, .po-vat-exempt-no'
        );

        if (txnSelect) {
            txnSelect.addEventListener('change', () => {
                const match = EWT_TRANSACTION_TYPES.find((item) => item.key === txnSelect.value);
                if (match && ratePctInput) {
                    ratePctInput.value = String(roundMoney(match.rate * 100));
                    row.dataset.rateOverride = '0';
                }
                recalculateTaxBreakdown();
            });
        }
        if (ratePctInput) {
            ratePctInput.addEventListener('input', () => {
                syncEwtRateOverrideState(row);
                recalculateTaxBreakdown();
            });
        }
        if (amountInput) {
            amountInput.addEventListener('input', recalculateTaxBreakdown);
        }
        vatRadios.forEach((radio) => {
            radio.addEventListener('change', () => {
                updateVatRowDisplay(row);
                recalculateTaxBreakdown();
            });
        });
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                row.remove();
                updateTaxTypeButtons();
                recalculateTaxBreakdown();
            });
        }
    }

    function removeTaxRowByType(type) {
        const row = taxRowsBody ? taxRowsBody.querySelector(`tr[data-tax-kind="${type}"]`) : null;
        if (row) {
            row.remove();
        }
        updateTaxTypeButtons();
        recalculateTaxBreakdown();
    }

    function updateTaxTypeButtons() {
        const hasEwt = rowHasType('ewt');
        const hasVat = rowHasType('vat');
        if (addEwtBtn) {
            addEwtBtn.classList.toggle('active', hasEwt);
            addEwtBtn.innerHTML = hasEwt
                ? '<i class="fas fa-check" aria-hidden="true"></i> EWT'
                : '<i class="fas fa-plus" aria-hidden="true"></i> EWT';
        }
        if (addVatBtn) {
            addVatBtn.classList.toggle('active', hasVat);
            addVatBtn.innerHTML = hasVat
                ? '<i class="fas fa-check" aria-hidden="true"></i> VAT Withholding'
                : '<i class="fas fa-plus" aria-hidden="true"></i> VAT Withholding';
        }
    }

    function toggleTaxRow(type, defaults = {}) {
        if (rowHasType(type)) {
            removeTaxRowByType(type);
            return;
        }
        if (type === 'ewt' || type === 'vat') {
            createTaxRow(type, defaults);
        }
    }

    function rowHasType(type) {
        if (!taxRowsBody) {
            return false;
        }
        return Boolean(taxRowsBody.querySelector(`tr[data-tax-kind="${type}"]`));
    }

    function collectTaxRows() {
        if (!taxRowsBody) {
            return [];
        }
        const rows = [];
        taxRowsBody.querySelectorAll('tr.po-tax-row').forEach((row) => {
            const kind = row.dataset.taxKind || 'other';
            const typeInput = row.querySelector('.po-tax-type-value');
            const taxType = typeInput ? typeInput.value : kind;

            if (kind === 'ewt') {
                const txnSelect = row.querySelector('.po-tax-transaction-select');
                const txn = EWT_TRANSACTION_TYPES.find((item) => item.key === (txnSelect ? txnSelect.value : ''));
                const rate = getEwtRateFromRow(row);
                const amount = roundMoney(currentGrossAmount * rate);
                if (amount <= 0) {
                    return;
                }
                rows.push({
                    tax_type: taxType,
                    transaction_type: txn ? txn.label : '',
                    rate,
                    rate_override: row.dataset.rateOverride === '1',
                    amount_deducted: amount,
                    label: null,
                });
                return;
            }

            if (kind === 'vat') {
                if (!isVatApplicable(row)) {
                    return;
                }
                const rate = 0.05;
                const amount = roundMoney(currentGrossAmount * rate);
                rows.push({
                    tax_type: taxType,
                    rate,
                    amount_deducted: amount,
                    supplier_vat_registered: 1,
                    transaction_vat_exempt: 0,
                    label: null,
                });
                return;
            }

            const labelInput = row.querySelector('.po-tax-label-input');
            const amountInput = row.querySelector('.po-tax-amount-input');
            const label = labelInput ? labelInput.value.trim() : '';
            const amount = roundMoney(Number(amountInput ? amountInput.value : 0));
            if (amount <= 0) {
                return;
            }
            rows.push({
                tax_type: taxType,
                rate:
                    currentGrossAmount > 0
                        ? Math.round((amount / currentGrossAmount) * 10000) / 10000
                        : 0,
                amount_deducted: amount,
                label,
            });
        });
        return rows;
    }

    function recalculateTaxBreakdown() {
        if (!taxRowsBody) {
            return 0;
        }

        taxRowsBody.querySelectorAll('tr.po-tax-row').forEach((row) => {
            const kind = row.dataset.taxKind || '';
            if (kind === 'ewt') {
                const rate = getEwtRateFromRow(row);
                const amountEl = row.querySelector('.po-tax-amount-readonly');
                if (amountEl) {
                    amountEl.textContent = formatMoney(roundMoney(currentGrossAmount * rate));
                }
            } else if (kind === 'vat') {
                updateVatRowDisplay(row);
            }
        });

        const rows = collectTaxRows();
        let deductionTotal = 0;
        if (taxBreakdownDeductions) {
            taxBreakdownDeductions.innerHTML = rows
                .map((row) => {
                    deductionTotal += row.amount_deducted;
                    let label = 'Other';
                    if (normalizeTaxTypeKey(row.tax_type) === 'ewt') {
                        label = `EWT (${formatRateLabel(row.rate)})`;
                    } else if (normalizeTaxTypeKey(row.tax_type) === 'vat') {
                        label = 'VAT Withholding (5%)';
                    } else if (row.label) {
                        label = row.label;
                    }
                    return `<div class="breakdown-row breakdown-row--deduction">
                        <span>Less: ${escapeHtml(label)}</span>
                        <strong>– ${formatMoney(row.amount_deducted)}</strong>
                    </div>`;
                })
                .join('');
        }

        const net = roundMoney(currentGrossAmount - deductionTotal);
        if (taxNetEl) {
            taxNetEl.textContent = formatMoney(net);
        }
        return net;
    }

    function clearTaxRows() {
        if (taxRowsBody) {
            taxRowsBody.innerHTML = '';
        }
        updateTaxTypeButtons();
    }

    function populateTaxRowsFromSaved(taxes) {
        clearTaxRows();
        const list = Array.isArray(taxes) ? taxes : [];
        if (!list.length) {
            recalculateTaxBreakdown();
            return;
        }
        list.forEach((item) => {
            const kind = normalizeTaxTypeKey(item.tax_type);
            createTaxRow(kind, {
                transaction_type: item.transaction_type,
                rate: item.rate,
                rate_override: item.rate_override,
                amount_deducted: item.amount_deducted,
                label: item.label,
                supplier_vat_registered: item.supplier_vat_registered,
                transaction_vat_exempt: item.transaction_vat_exempt,
            });
        });
        updateTaxTypeButtons();
    }

    async function loadTaxRecord() {
        if (!isComptroller || !taxSection || currentPoId <= 0) {
            return;
        }
        try {
            const params = new URLSearchParams({
                action: 'fetch_tax',
                purchase_order_id: String(currentPoId),
            });
            const res = await fetch(`${api}?${params.toString()}`, { credentials: 'same-origin' });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                return;
            }
            if (Number(data.gross_amount) > 0) {
                currentGrossAmount = Number(data.gross_amount);
                updateTaxGrossDisplay(currentGrossAmount);
            }
            populateTaxRowsFromSaved(data.taxes || []);
            if (taxNotesEl) {
                taxNotesEl.value = String(data.notes || '');
            }
            if (data.net_payable != null && taxNetEl) {
                taxNetEl.textContent = formatMoney(data.net_payable);
            }
            currentTaxStatus = String(data.tax_status || 'draft').toLowerCase() === 'finalized' ? 'finalized' : 'draft';
            lastTaxFinalizedAt = data.tax_finalized_at || null;
            applyTaxWorkflowUi({
                taxComputed: Boolean(data.tax_computed),
                finalizedAt: lastTaxFinalizedAt,
            });
            recalculateTaxBreakdown();
        } catch {
            /* ignore — comptroller can still enter fresh data */
        }
    }

    function collectTaxesForSave() {
        return collectTaxRows();
    }

    function buildTaxSaveBody(action, taxes) {
        const netPayable = recalculateTaxBreakdown();
        const notes = taxNotesEl ? taxNotesEl.value.trim() : '';
        const body = new FormData();
        body.append('action', action);
        body.append('purchase_order_id', String(currentPoId));
        body.append('taxes', JSON.stringify(taxes));
        body.append('notes', notes);
        body.append('net_payable', String(netPayable));
        return { body, netPayable, notes };
    }

    function showDraftSavedHint(savedAt) {
        if (!taxDraftSavedHint) {
            return;
        }
        const label = formatSavedAtLabel(savedAt);
        taxDraftSavedHint.textContent = label ? `Saved at ${label}` : 'Saved';
        taxDraftSavedHint.hidden = false;
    }

    async function saveTaxDraft() {
        if (!isComptroller || currentPoId <= 0 || currentTaxStatus === 'finalized' || taxDraftSaving) {
            return;
        }
        const taxes = collectTaxesForSave();
        taxDraftSaving = true;
        applyTaxWorkflowUi({ taxComputed: true });

        const { body } = buildTaxSaveBody('save_tax_draft', taxes);

        try {
            const res = await fetch(api, {
                method: 'POST',
                credentials: 'same-origin',
                body,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Could not save tax draft.');
            }

            if (data.net_payable != null && taxNetEl) {
                taxNetEl.textContent = formatMoney(data.net_payable);
            }
            populateTaxRowsFromSaved(data.taxes || taxes);
            if (taxNotesEl && data.notes != null) {
                taxNotesEl.value = String(data.notes);
            }
            currentTaxStatus = 'draft';
            showDraftSavedHint(data.saved_at || new Date().toISOString());
            applyTaxWorkflowUi({ taxComputed: true });
        } catch (err) {
            console.error('Failed to save tax draft:', err);
            showToast(err instanceof Error ? err.message : 'Could not save tax draft.');
        } finally {
            taxDraftSaving = false;
            applyTaxWorkflowUi({ taxComputed: true });
        }
    }

    async function finalizeTaxRecord() {
        if (!isComptroller || currentPoId <= 0 || currentTaxStatus === 'finalized' || taxFinalizeSaving) {
            return;
        }
        const taxes = collectTaxesForSave();

        const ok = await showConfirmModal(
            'Finalizing will lock this computation and notify the requester that payment is ready. Continue?'
        );
        if (!ok) {
            return;
        }

        taxFinalizeSaving = true;
        applyTaxWorkflowUi({ taxComputed: true });

        const { body } = buildTaxSaveBody('finalize_tax', taxes);

        try {
            const res = await fetch(api, {
                method: 'POST',
                credentials: 'same-origin',
                body,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Could not finalize tax computation.');
            }

            showToast(data.message || 'Tax computation finalized.', 'success');
            if (data.net_payable != null && taxNetEl) {
                taxNetEl.textContent = formatMoney(data.net_payable);
            }
            populateTaxRowsFromSaved(data.taxes || taxes);
            if (taxNotesEl && data.notes != null) {
                taxNotesEl.value = String(data.notes);
            }
            if (taxDraftSavedHint) {
                taxDraftSavedHint.hidden = true;
            }
            currentTaxStatus = 'finalized';
            lastTaxFinalizedAt = data.tax_finalized_at || new Date().toISOString();
            applyTaxWorkflowUi({
                taxComputed: true,
                finalizedAt: lastTaxFinalizedAt,
            });
        } catch (err) {
            console.error('Failed to finalize tax computation:', err);
            showToast(err instanceof Error ? err.message : 'Could not finalize tax computation.');
        } finally {
            taxFinalizeSaving = false;
            applyTaxWorkflowUi({ taxComputed: true, finalizedAt: lastTaxFinalizedAt });
        }
    }

    async function reopenTaxRecord() {
        if (!isComptroller || currentPoId <= 0 || currentTaxStatus !== 'finalized' || taxReopenSaving) {
            return;
        }

        const ok = await showConfirmModal('Reopen this tax computation for editing?');
        if (!ok) {
            return;
        }

        taxReopenSaving = true;
        applyTaxWorkflowUi({ taxComputed: true, finalizedAt: lastTaxFinalizedAt });

        try {
            const body = new FormData();
            body.append('action', 'reopen_tax');
            body.append('purchase_order_id', String(currentPoId));

            const res = await fetch(api, {
                method: 'POST',
                credentials: 'same-origin',
                body,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Could not reopen tax computation.');
            }

            showToast(data.message || 'Tax computation reopened.', 'success');
            currentTaxStatus = 'draft';
            lastTaxFinalizedAt = null;
            applyTaxWorkflowUi({ taxComputed: Boolean(data.taxes && data.taxes.length) });
        } catch (err) {
            console.error('Failed to reopen tax computation:', err);
            showToast(err instanceof Error ? err.message : 'Could not reopen tax computation.');
        } finally {
            taxReopenSaving = false;
            applyTaxWorkflowUi({ taxComputed: true });
        }
    }

    function initComptrollerTaxUi() {
        if (!isComptroller || !taxSection) {
            return;
        }

        addEwtBtn = document.getElementById('poTaxAddEwtBtn');
        addVatBtn = document.getElementById('poTaxAddVatBtn');
        addOtherBtn = document.getElementById('poTaxAddOtherBtn');
        addDeductionBtn = document.getElementById('poTaxAddDeductionBtn');

        if (addEwtBtn) {
            addEwtBtn.addEventListener('click', () => {
                toggleTaxRow('ewt', { transaction_type: 'purchase_of_services', rate: 0.02 });
            });
        }
        if (addVatBtn) {
            addVatBtn.addEventListener('click', () => {
                toggleTaxRow('vat', {
                    supplier_vat_registered: 1,
                    transaction_vat_exempt: 0,
                });
            });
        }
        if (addOtherBtn) {
            addOtherBtn.addEventListener('click', () => createTaxRow('other'));
        }
        if (addDeductionBtn) {
            addDeductionBtn.addEventListener('click', () => createTaxRow('other'));
        }
        if (taxDraftBtn) {
            taxDraftBtn.addEventListener('click', () => {
                void saveTaxDraft();
            });
        }
        if (taxFinalizeBtn) {
            taxFinalizeBtn.addEventListener('click', () => {
                void finalizeTaxRecord();
            });
        }
        if (taxReopenBtn) {
            taxReopenBtn.addEventListener('click', () => {
                void reopenTaxRecord();
            });
        }

        applyTaxWorkflowUi({ taxComputed: false });
        updateTaxTypeButtons();
        updateTaxGrossDisplay(currentGrossAmount);
        recalculateTaxBreakdown();
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

        initComptrollerTaxUi();

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
