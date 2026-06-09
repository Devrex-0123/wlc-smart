/**
 * Canvass form — same layout patterns as requisition_form.js (items + supplier matrix).
 */
(function () {
    const card = document.getElementById('canvassCard');
    if (!card) return;

    const gsdReadonly = card.dataset.gsdReadonly === '1';
    const canvasserRegister = card.dataset.canvasserRegister === '1';
    const CANVASSER_REQUESTS_API = '../../app/api/canvasser_requests.php';

    const requestId = parseInt(card.dataset.requestId || '0', 10);
    const api = card.dataset.api || '../../app/api/canvass_detail.php';
    const deanApi = card.dataset.deanApi || '../../app/api/dean_requisition.php';

    const cvItemName = document.getElementById('cvItemName');
    const cvItemBrand = document.getElementById('cvItemBrand');
    const cvItemModel = document.getElementById('cvItemModel');
    const cvItemSpecs = document.getElementById('cvItemSpecs');
    const cvItemNameSuggestions = document.getElementById('cvItemNameSuggestions');
    const cvItemSuggestList = document.getElementById('cvItemSuggestList');
    const cvAddItemBtn = document.getElementById('cvAddItemBtn');
    const cvItemChips = document.getElementById('cvItemChips');
    const cvPreferredSection = document.getElementById('cvPreferredSection');
    const cvPreferredTable = document.getElementById('cvPreferredTable');
    const cvPreferredCards = document.getElementById('cvPreferredCards');
    const cvOpenAddPreferredBtn = document.getElementById('cvOpenAddPreferredBtn');
    const cvSupplierDropdown = document.getElementById('cvSupplierDropdown');
    const cvSupplierPickerLabel = document.querySelector('#cvCanvasSection .supplier-picker > label[for="cvSupplierDropdownBtn"]');
    const cvSupplierDropdownBtn = document.getElementById('cvSupplierDropdownBtn');
    const cvSupplierDropdownList = document.getElementById('cvSupplierDropdownList');
    const cvSupplierSelectedText = document.getElementById('cvSupplierSelectedText');
    const cvSupplierDropdownPreview = document.getElementById('cvSupplierDropdownPreview');
    const cvAddSupplierBtn = document.getElementById('cvAddSupplierBtn');
    const cvRegisterSupplierBtn = document.getElementById('cvRegisterSupplierBtn');
    const cvCanvassedCards = document.getElementById('cvCanvassedCards');
    const cvCanvassedSupplierLabel = document.querySelector('.canvassed-supplier-label');
    const cvCanvassedSupplierWrap = document.getElementById('cvCanvassedSupplierWrap');
    const cvSaveBtn = document.getElementById('cvSaveBtn');
    const cvSaveDraftBtn = document.getElementById('cvSaveDraftBtn');
    const cvCompleteCanvassBtn = document.getElementById('cvCompleteCanvassBtn');
    const cvFormToast = document.getElementById('cvFormToast');
    const cvConfirmModal = document.getElementById('cvConfirmModal');
    const cvConfirmMessage = document.getElementById('cvConfirmMessage');
    const cvConfirmOkBtn = document.getElementById('cvConfirmOkBtn');
    const cvConfirmCancelBtn = document.getElementById('cvConfirmCancelBtn');
    const cvCanvassItemsHintWrap = document.getElementById('cvCanvassItemsHintWrap');
    const cvCanvassHintDismiss = document.getElementById('cvCanvassHintDismiss');
    const cvCanvassHintShow = document.getElementById('cvCanvassHintShow');
    const cvSuggestedSupplierNotice = document.getElementById('cvSuggestedSupplierNotice');
    const cvSuggestedSupplierNoticeText = document.getElementById('cvSuggestedSupplierNoticeText');
    const cvSuggestedSupplierNoticeDismiss = document.getElementById('cvSuggestedSupplierNoticeDismiss');

    const CV_CANVASS_HINT_DISMISSED_KEY = 'cwirms_cv_canvass_items_hint_dismissed';
    let cvSuggestedNoticeDismissed = false;

    function syncCanvassHintChrome() {
        const dismissed = sessionStorage.getItem(CV_CANVASS_HINT_DISMISSED_KEY) === '1';
        if (cvCanvassItemsHintWrap) {
            cvCanvassItemsHintWrap.hidden = dismissed;
        }
        if (cvCanvassHintShow) {
            cvCanvassHintShow.hidden = !dismissed;
        }
    }

    function formatItemHeader(item, index) {
        const label = String(item.name || `ITEM ${index + 1}`).trim() || `ITEM ${index + 1}`;
        return `ITEM ${index + 1} - ${escapeHtml(label)}`;
    }

    function preferredPhotoKey(supplierId, itemIndex) {
        return `${Number(supplierId)}-${Number(itemIndex)}`;
    }

    function getPreferredDraftPrice(supplierId, itemIndex) {
        const key = String(supplierId);
        const drafts = state.preferredPriceDrafts[key] || {};
        const value = drafts[itemIndex];
        return value === null || value === undefined ? '' : String(value);
    }

    function getPreferredPhotoUrl(supplierId, itemIndex) {
        const sid = Number(supplierId || 0);
        const key = preferredPhotoKey(sid, itemIndex);
        const local = state.preferredQuotePhotos[key] || {};
        if (local.preview_url) {
            return String(local.preview_url);
        }
        if (local.url) {
            return String(local.url);
        }
        const pref = (state.preferredSuppliers || []).find((s) => Number(s.supplier_id) === sid);
        if (!pref || !pref.quote_photos) {
            return '';
        }
        const fromApi = pref.quote_photos[itemIndex] ?? pref.quote_photos[String(itemIndex)];
        return fromApi ? String(fromApi) : '';
    }

    function getRequestProcurementQtyMeta() {
        const lines = Array.isArray(state.requestedLines) ? state.requestedLines : [];
        if (lines.length === 0) {
            return { quantity: 1, unit_type: 'unit' };
        }
        const setLines = lines.filter((row) => String(row.unit_type || '').toLowerCase() === 'set');
        if (setLines.length > 0) {
            const best = setLines.reduce((acc, row) =>
                Math.max(1, Number(row.quantity) || 1) > Math.max(1, Number(acc.quantity) || 1) ? row : acc
            );
            return {
                quantity: Math.max(1, Number(best.quantity) || 1),
                unit_type: 'set',
            };
        }
        if (lines.length === 1) {
            return {
                quantity: Math.max(1, Number(lines[0].quantity) || 1),
                unit_type: String(lines[0].unit_type || 'unit'),
            };
        }
        return { quantity: 1, unit_type: 'unit' };
    }

    function pluralizeUnitType(unitType, qty) {
        const unit = String(unitType || 'unit');
        if (qty === 1) {
            return unit;
        }
        if (unit === 'set') {
            return 'sets';
        }
        if (unit.endsWith('s')) {
            return unit;
        }
        return `${unit}s`;
    }

    function formatRequisitionQtyHint(meta) {
        const reqQty = Math.max(1, Number(meta.quantity) || 1);
        const unitLabel = pluralizeUnitType(meta.unit_type, reqQty);
        if (reqQty <= 1) {
            return '';
        }
        return `<span class="cv-requisition-qty-hint">Requisition qty: ${reqQty} ${escapeHtml(unitLabel)}</span>`;
    }

    function formatPricingOverviewQtyLabel(line) {
        const reqQty = Math.max(1, Number(line.requisition_qty ?? line.quantity ?? 1));
        const perSet = Math.max(1, Number(line.qty_per_set ?? 1));
        const unitLabel = pluralizeUnitType(line.unit_type, reqQty);
        if (reqQty > 1 && perSet === 1) {
            const setWord = String(line.unit_type || 'unit') === 'set' ? 'sets' : unitLabel;
            return `${reqQty} ${unitLabel} (1 per set × ${reqQty} ${setWord})`;
        }
        return `${reqQty} ${unitLabel}`;
    }

    function setPreferredDraftPrice(supplierId, itemIndex, value) {
        const key = String(supplierId);
        if (!state.preferredPriceDrafts[key]) {
            state.preferredPriceDrafts[key] = {};
        }
        state.preferredPriceDrafts[key][itemIndex] = value;
        state.hasUnsavedChanges = true;
        scheduleRequesterAutosave();
    }

    function getPreferredSupplierItemIndices(supplierId) {
        const sid = String(supplierId);
        const stored = state.preferredSupplierItems[sid];
        if (Array.isArray(stored)) {
            return [...stored].sort((a, b) => a - b);
        }
        return [];
    }

    function setPreferredSupplierItemIndices(supplierId, indices) {
        state.preferredSupplierItems[String(supplierId)] = [...indices].sort((a, b) => a - b);
    }

    function addPreferredSupplierItem(supplierId, itemIndex) {
        const idx = Number(itemIndex);
        const sid = String(supplierId);
        if (Number.isNaN(idx) || idx < 0) {
            return false;
        }
        const current = getPreferredSupplierItemIndices(sid);
        if (current.includes(idx)) {
            return false;
        }
        setPreferredSupplierItemIndices(sid, [...current, idx]);
        state.hasUnsavedChanges = true;
        scheduleRequesterAutosave();
        return true;
    }

    function removePreferredSupplierItem(supplierId, itemIndex) {
        const idx = Number(itemIndex);
        const sid = String(supplierId);
        const current = getPreferredSupplierItemIndices(sid).filter((i) => i !== idx);
        setPreferredSupplierItemIndices(sid, current);
        if (state.preferredPriceDrafts[sid]) {
            delete state.preferredPriceDrafts[sid][idx];
            delete state.preferredPriceDrafts[sid][String(idx)];
        }
        const photoKey = preferredPhotoKey(sid, idx);
        const existing = state.preferredQuotePhotos[photoKey] || {};
        if (existing.preview_url && String(existing.preview_url).startsWith('blob:')) {
            URL.revokeObjectURL(existing.preview_url);
        }
        delete state.preferredQuotePhotos[photoKey];
        state.hasUnsavedChanges = true;
        scheduleRequesterAutosave();
    }

    function scheduleRequesterAutosave() {
        if (canvasserRegister || !requestId || state.items.length === 0) {
            return;
        }
        if (state.autoSaveTimer) {
            clearTimeout(state.autoSaveTimer);
        }
        state.autoSaveTimer = setTimeout(async () => {
            state.autoSaveTimer = null;
            try {
                const result = await persistCanvassToServer('draft');
                if (!result.ok) {
                    console.warn('Requester autosave failed:', result.message);
                }
            } catch (error) {
                console.warn('Requester autosave error:', error);
            }
        }, 1200);
    }

    function clearRequesterAutosaveTimer() {
        if (state.autoSaveTimer) {
            clearTimeout(state.autoSaveTimer);
            state.autoSaveTimer = null;
        }
    }

    function flushRequesterAutosave() {
        if (canvasserRegister || !requestId || state.items.length === 0) {
            return;
        }
        if (!window.navigator.sendBeacon) {
            return;
        }
        const itemsPayload = buildItemsPayloadForSave();
        const body = new URLSearchParams();
        body.set('action', 'save');
        body.set('request_id', String(requestId));
        body.set('items', JSON.stringify(itemsPayload));
        body.set('suppliers', JSON.stringify(buildSuppliersPayloadForSave()));
        body.set('preferred_suppliers', JSON.stringify(state.preferredSuppliers || []));
        body.set('preferred_quotes', JSON.stringify(buildPreferredQuotesPayload()));
        const blob = new Blob([body.toString()], { type: 'application/x-www-form-urlencoded' });
        window.navigator.sendBeacon(api, blob);
    }

    function syncPreferredSupplierPrices(supplierId) {
        const sidNum = Number(supplierId || 0);
        if (!sidNum) {
            showToast('Invalid preferred supplier.', 'error');
            return;
        }
        const preferred = state.preferredSuppliers.find((s) => Number(s.supplier_id) === sidNum);
        if (!preferred) {
            showToast('Preferred supplier not found.', 'error');
            return;
        }
        const drafts = state.preferredPriceDrafts[String(sidNum)] || {};
        let hasValue = false;
        const target = state.selectedSuppliers.find((s) => Number(s.supplier_id) === sidNum);
        if (!target) {
            state.selectedSuppliers.push({
                supplier_id: preferred.supplier_id,
                supplier_name: preferred.supplier_name,
                supplier_image: preferred.supplier_image,
                prices: {},
                benefits: '',
                discounts: [],
            });
        }
        const supplierRow = state.selectedSuppliers.find((s) => Number(s.supplier_id) === sidNum);
        getPreferredSupplierItemIndices(sidNum).forEach((itemIndex) => {
            const draftVal = drafts[itemIndex];
            if (draftVal !== undefined && draftVal !== '') {
                hasValue = true;
                supplierRow.prices[itemIndex] = draftVal;
            }
        });
        if (!hasValue) {
            showToast('Enter at least one price before syncing.', 'error');
            return;
        }
        showToast('Preferred supplier prices added to the canvass matrix.');
        renderPreferredTable();
        renderSupplierTable();
    }

    function openCvQuotePhotoLightbox(src) {
        const lightbox = document.getElementById('cvQuotePhotoLightbox');
        const img = document.getElementById('cvQuotePhotoLightboxImg');
        if (!lightbox || !img || !src) {
            return;
        }
        img.src = src;
        lightbox.classList.remove('hidden');
        lightbox.setAttribute('aria-hidden', 'false');
    }

    function closeCvQuotePhotoLightbox() {
        const lightbox = document.getElementById('cvQuotePhotoLightbox');
        const img = document.getElementById('cvQuotePhotoLightboxImg');
        if (!lightbox || !img) {
            return;
        }
        lightbox.classList.add('hidden');
        img.src = '';
        lightbox.setAttribute('aria-hidden', 'true');
    }

    function getGsdSuggestSelectionContext() {
        const gsdDecision = String((cachedCanvassApproval && cachedCanvassApproval.gsd_status) || '')
            .trim()
            .toLowerCase();
        const canChangeSuggested =
            canSelectSuggestedSupplierInTable && gsdDecision !== 'accept' && gsdDecision !== 'reject';
        const showGsdSuggestUi = gsdReadonly && canChangeSuggested;
        return { gsdDecision, canChangeSuggested, showGsdSuggestUi };
    }

    function normalizeSuggestedEntry(raw) {
        if (raw === null || raw === undefined || raw === '') {
            return null;
        }
        if (typeof raw === 'number') {
            const supplierId = Number(raw);
            return supplierId > 0 ? { supplierId, source: null } : null;
        }
        if (typeof raw === 'object') {
            const supplierId = Number(raw.supplierId || 0);
            if (!supplierId) {
                return null;
            }
            const source =
                raw.source === 'preferred' || raw.source === 'canvassed' ? raw.source : null;
            return { supplierId, source };
        }
        return null;
    }

    function inferSuggestedSelectionSource(supplierId, matrixSource) {
        const sid = Number(supplierId || 0);
        if (!sid) {
            return null;
        }
        const inPreferred = (state.preferredSuppliers || []).some((s) => Number(s.supplier_id) === sid);
        const inCanvassed = (state.selectedSuppliers || []).some((s) => Number(s.supplier_id) === sid);
        if (inPreferred && !inCanvassed) {
            return 'preferred';
        }
        if (inCanvassed && !inPreferred) {
            return 'canvassed';
        }
        return null;
    }

    function isSuggestedForMatrix(itemIndex, supplierId, matrixSource) {
        const entry = normalizeSuggestedEntry(state.suggestedByItem[itemIndex]);
        if (!entry || entry.supplierId !== Number(supplierId)) {
            return false;
        }
        const source = entry.source || inferSuggestedSelectionSource(entry.supplierId, matrixSource);
        if (!source) {
            return false;
        }
        return source === matrixSource;
    }

    function setSuggestedSelection(itemIndex, supplierId, matrixSource) {
        state.suggestedByItem[itemIndex] = {
            supplierId: Number(supplierId),
            source: matrixSource,
        };
    }

    function handleSuggestedSupplierRadioChange(radio) {
        if (!radio) {
            return;
        }
        const itemIndex = parseInt(radio.getAttribute('data-item-index') || '-1', 10);
        const supplierId = parseInt(radio.value || '0', 10);
        if (Number.isNaN(itemIndex) || itemIndex < 0) {
            return;
        }
        if (Number.isNaN(supplierId) || supplierId <= 0) {
            return;
        }
        const matrixSource =
            radio.getAttribute('data-selection-source') === 'preferred' ? 'preferred' : 'canvassed';
        setSuggestedSelection(itemIndex, supplierId, matrixSource);
        renderSupplierTable();
        renderPreferredTable();
    }

    function bindSuggestedSupplierSelectionHandlers(root) {
        if (!root || root.dataset.cvSuggestedSelectionBound) {
            return;
        }
        root.dataset.cvSuggestedSelectionBound = '1';
        root.addEventListener('change', (e) => {
            const radio = e.target.closest('.cv-suggested-item-radio');
            if (!radio) {
                return;
            }
            handleSuggestedSupplierRadioChange(radio);
        });
    }

    function emitCanvassPricingUpdate() {
        window.dispatchEvent(new CustomEvent('cwirms-canvass-pricing-update'));
    }

    function getItemQuantityMeta(itemIndex) {
        const baseline = getRequestProcurementQtyMeta();
        const item = state.items[itemIndex];
        if (!item) {
            return { quantity: baseline.quantity, unit_type: baseline.unit_type, qty_per_set: 1, requisition_qty: baseline.quantity };
        }
        const lineId = item.requisition_line_id != null ? Number(item.requisition_line_id) : 0;
        if (lineId > 0) {
            const row = (state.requestedLines || []).find(
                (r) => Number(r.requisition_line_id) === lineId
            );
            if (row) {
                const rlQty = Math.max(1, Number(row.quantity) || 1);
                const rlUnit = String(row.unit_type || 'unit');
                if (rlUnit === 'set' || rlQty > 1) {
                    return {
                        quantity: rlQty,
                        unit_type: rlUnit,
                        qty_per_set: 1,
                        requisition_qty: rlQty,
                    };
                }
            }
        }
        return {
            quantity: baseline.quantity,
            unit_type: baseline.unit_type,
            qty_per_set: 1,
            requisition_qty: baseline.quantity,
        };
    }

    function parseUnitPrice(raw) {
        if (raw === null || raw === undefined || String(raw).trim() === '') {
            return null;
        }
        const num = Number(raw);
        if (!Number.isFinite(num) || num < 0) {
            return null;
        }
        return Math.round(num * 100) / 100;
    }

    function getPreferredUnitPrice(supplierId, itemIndex) {
        const draft = getPreferredDraftPrice(supplierId, itemIndex);
        const fromDraft = parseUnitPrice(draft);
        if (fromDraft !== null) {
            return fromDraft;
        }
        const pref = (state.preferredSuppliers || []).find((s) => Number(s.supplier_id) === Number(supplierId));
        if (!pref || !pref.quoted_prices) {
            return null;
        }
        const raw =
            pref.quoted_prices[itemIndex] ??
            pref.quoted_prices[String(itemIndex)];
        return parseUnitPrice(raw);
    }

    function getCanvassedUnitPrice(supplierId, itemIndex) {
        const supplier = (state.selectedSuppliers || []).find(
            (s) => Number(s.supplier_id) === Number(supplierId)
        );
        if (!supplier || !supplier.prices) {
            return null;
        }
        const raw = supplier.prices[itemIndex] ?? supplier.prices[String(itemIndex)];
        return parseUnitPrice(raw);
    }

    function parseCanvassDiscountPercent(raw) {
        if (raw === null || raw === undefined || String(raw).trim() === '') {
            return 0;
        }
        const num = Number(raw);
        if (!Number.isFinite(num) || num <= 0) {
            return 0;
        }
        return Math.min(100, Math.round(num * 100) / 100);
    }

    function ensureSupplierDiscountsArray(supplier) {
        if (!supplier) {
            return;
        }
        if (!Array.isArray(supplier.discounts)) {
            supplier.discounts = [];
        }
    }

    function getCanvassDiscountPercents(supplier) {
        if (!supplier || !Array.isArray(supplier.discounts)) {
            return [];
        }
        return supplier.discounts
            .map((entry) => parseCanvassDiscountPercent(entry?.discount_percent))
            .filter((pct) => pct > 0);
    }

    function getEffectiveCompoundedDiscountPercent(percents) {
        if (!Array.isArray(percents) || percents.length === 0) {
            return 0;
        }
        let factor = 1;
        percents.forEach((pct) => {
            factor *= 1 - pct / 100;
        });
        return Math.round((1 - factor) * 10000) / 100;
    }

    function resolveCanvassedSupplierDetails(supplier) {
        const sid = Number(supplier?.supplier_id || 0);
        const catalog = sid
            ? (state.availableSuppliers || []).find((x) => Number(x.supplier_id) === sid)
            : null;
        return {
            contact_person: catalog?.contact_person || supplier?.contact_person || '',
            tin: catalog?.tin ?? supplier?.tin ?? null,
            address: catalog?.address ?? supplier?.address,
            city: catalog?.city ?? supplier?.city,
            country: catalog?.country ?? supplier?.country,
            postal_code: catalog?.postal_code ?? supplier?.postal_code,
        };
    }

    function hasAnyCanvassedMatrixDiscounts() {
        return (state.selectedSuppliers || []).some((supplier) => getCanvassDiscountPercents(supplier).length > 0);
    }

    function formatCompoundedDiscountLabel(percents) {
        const effective = getEffectiveCompoundedDiscountPercent(percents);
        if (effective <= 0) {
            return null;
        }
        const label = Number.isInteger(effective) ? String(effective) : String(effective).replace(/\.?0+$/, '');
        return `${label}%`;
    }

    function getCanvassedLineDiscountLabel(supplierId, selectionSource) {
        if (selectionSource !== 'canvassed') {
            return null;
        }
        const supplier = (state.selectedSuppliers || []).find((s) => Number(s.supplier_id) === Number(supplierId));
        return formatCompoundedDiscountLabel(getCanvassDiscountPercents(supplier));
    }

    function getCanvassDiscountsForSave(discounts) {
        return (Array.isArray(discounts) ? discounts : [])
            .map((entry) => ({
                label: String(entry?.label || '').trim() || null,
                discount_percent: parseCanvassDiscountPercent(entry?.discount_percent),
            }))
            .filter((entry) => entry.discount_percent > 0)
            .map((entry) => ({
                label: entry.label,
                discount_percent: entry.discount_percent,
            }));
    }

    function applyCanvassLineDiscount(lineTotal, supplierId, selectionSource) {
        if (lineTotal === null || selectionSource !== 'canvassed') {
            return lineTotal;
        }
        const supplier = (state.selectedSuppliers || []).find(
            (s) => Number(s.supplier_id) === Number(supplierId)
        );
        const percents = getCanvassDiscountPercents(supplier);
        if (percents.length === 0) {
            return lineTotal;
        }
        let result = lineTotal;
        percents.forEach((pct) => {
            result *= 1 - pct / 100;
        });
        return Math.round(result * 100) / 100;
    }

    function buildCanvassedSupplierHeaderBadge(supplier) {
        const percents = getCanvassDiscountPercents(supplier);
        const effective = getEffectiveCompoundedDiscountPercent(percents);
        const benefits = String(supplier.benefits || '').trim();
        if (effective > 0) {
            const label = Number.isInteger(effective) ? String(effective) : String(effective).replace(/\.?0+$/, '');
            const badgeText =
                percents.length > 1 ? `${label}% off (${percents.length} discounts)` : `${label}% discount`;
            return `<span class="cv-canvass-discount-badge">${escapeHtml(badgeText)}</span>`;
        }
        if (benefits) {
            return '<span class="cv-canvass-benefits-badge">Has benefits</span>';
        }
        return '';
    }

    function buildCanvassedDiscountRowHtml(row, supplierIndex, discountIndex, readonly) {
        const roAttr = readonly ? ' readonly' : '';
        const disAttr = readonly ? ' disabled' : '';
        const label = String(row?.label || '');
        const pct =
            row?.discount_percent != null && row.discount_percent !== '' ? String(row.discount_percent) : '';
        return `<div class="cv-canvass-discount-item" data-cv-discount-index="${discountIndex}">
            <input type="text" class="cv-canvass-discount-label-input" placeholder="e.g. Bulk discount" maxlength="100" value="${escapeHtml(label)}" data-cv-supplier-index="${supplierIndex}" data-cv-discount-index="${discountIndex}"${roAttr}${disAttr}>
            <div class="cv-canvass-discount-pct-wrap">
                <input type="number" class="cv-canvass-discount-pct-input" min="0" max="100" step="0.01" placeholder="0" value="${escapeHtml(pct)}" data-cv-supplier-index="${supplierIndex}" data-cv-discount-index="${discountIndex}"${roAttr}${disAttr}>
                <span class="cv-canvass-discount-pct-suffix" aria-hidden="true">%</span>
            </div>
            ${
                readonly
                    ? ''
                    : `<button type="button" class="cv-canvass-discount-remove" data-cv-supplier-index="${supplierIndex}" data-cv-discount-index="${discountIndex}" title="Remove discount" aria-label="Remove discount">×</button>`
            }
        </div>`;
    }

    function buildCanvassedOptionalNotesSection(supplier, supplierIndex, readonly) {
        ensureSupplierDiscountsArray(supplier);
        const benefits = String(supplier.benefits || '');
        const roAttr = readonly ? ' readonly' : '';
        const disAttr = readonly ? ' disabled' : '';
        const discountRows = (supplier.discounts || [])
            .map((row, discountIndex) => buildCanvassedDiscountRowHtml(row, supplierIndex, discountIndex, readonly))
            .join('');
        return `<div class="cv-canvass-optional-notes">
            <div class="cv-canvass-optional-notes-divider">Optional supplier notes</div>
            <label class="cv-canvass-notes-field">
                <span class="cv-canvass-notes-label">Benefits <span class="cv-field-optional">(optional)</span></span>
                <textarea class="cv-canvass-benefits-input" rows="2" placeholder="e.g. Free delivery, 1-year warranty, freebies included..." data-cv-supplier-index="${supplierIndex}"${roAttr}${disAttr}>${escapeHtml(benefits)}</textarea>
            </label>
            <div class="cv-canvass-notes-field cv-canvass-discount-list">
                <span class="cv-canvass-notes-label">Discounts <span class="cv-field-optional">(optional)</span></span>
                <div class="cv-canvass-discount-rows" data-cv-supplier-index="${supplierIndex}">${discountRows}</div>
                ${
                    readonly
                        ? ''
                        : `<button type="button" class="cv-canvass-add-discount-btn" data-cv-supplier-index="${supplierIndex}">+ Add discount</button>`
                }
                <p class="cv-canvass-discount-hint">Compounded in pricing overview: total × (1 − d₁%) × (1 − d₂%) …</p>
            </div>
        </div>`;
    }

    function refreshCanvassedSupplierBadge(supplierIndex) {
        const supplier = state.selectedSuppliers[supplierIndex];
        if (!supplier || !cvCanvassedCards) {
            return;
        }
        const card = cvCanvassedCards.querySelector(`[data-cv-supplier-card-index="${supplierIndex}"]`);
        if (!card) {
            return;
        }
        const badgeEl = card.querySelector('.cv-canvass-header-badge');
        if (badgeEl) {
            badgeEl.innerHTML = buildCanvassedSupplierHeaderBadge(supplier);
        }
    }

    function getSupplierNameById(supplierId) {
        const sid = Number(supplierId || 0);
        if (!sid) {
            return null;
        }
        const fromPref = (state.preferredSuppliers || []).find((s) => Number(s.supplier_id) === sid);
        if (fromPref && fromPref.supplier_name) {
            return String(fromPref.supplier_name);
        }
        const fromCanvas = (state.selectedSuppliers || []).find((s) => Number(s.supplier_id) === sid);
        if (fromCanvas && fromCanvas.supplier_name) {
            return String(fromCanvas.supplier_name);
        }
        const fromCatalog = (state.availableSuppliers || []).find((s) => Number(s.supplier_id) === sid);
        return fromCatalog && fromCatalog.supplier_name ? String(fromCatalog.supplier_name) : null;
    }

    function buildCanvassPricingSnapshot() {
        const lines = [];
        let selectedCount = 0;
        let grandTotal = 0;
        const showDiscountColumn = hasAnyCanvassedMatrixDiscounts();

        state.items.forEach((item, itemIndex) => {
            const entry = normalizeSuggestedEntry(state.suggestedByItem[itemIndex]);
            const qtyMeta = getItemQuantityMeta(itemIndex);
            const quantity = qtyMeta.quantity;
            const qtyPerSet = qtyMeta.qty_per_set;
            const requisitionQty = qtyMeta.requisition_qty;
            let supplierId = null;
            let supplierName = null;
            let selectionSource = null;
            let unitPrice = null;
            let lineTotal = null;

            if (entry && entry.supplierId > 0) {
                supplierId = entry.supplierId;
                supplierName = getSupplierNameById(supplierId);
                selectionSource =
                    entry.source === 'preferred' || entry.source === 'canvassed'
                        ? entry.source
                        : inferSuggestedSelectionSource(supplierId, 'canvassed');
                selectedCount += 1;

                if (selectionSource === 'preferred') {
                    unitPrice = getPreferredUnitPrice(supplierId, itemIndex);
                } else {
                    unitPrice = getCanvassedUnitPrice(supplierId, itemIndex);
                    if (!selectionSource) {
                        selectionSource = 'canvassed';
                    }
                }

                if (unitPrice !== null) {
                    lineTotal = Math.round(unitPrice * quantity * 100) / 100;
                    lineTotal = applyCanvassLineDiscount(lineTotal, supplierId, selectionSource);
                    grandTotal += lineTotal;
                }
            }

            const discountLabel =
                showDiscountColumn && supplierId
                    ? getCanvassedLineDiscountLabel(supplierId, selectionSource)
                    : null;

            lines.push({
                item_index: itemIndex,
                canvass_detail_id: Number(item.canvass_detail_id || 0),
                item_name: String(item.name || `Item ${itemIndex + 1}`),
                quantity,
                qty_per_set: qtyPerSet,
                requisition_qty: requisitionQty,
                unit_type: qtyMeta.unit_type,
                supplier_id: supplierId,
                supplier_name: supplierName,
                selection_source: selectionSource,
                unit_price: unitPrice,
                line_total: lineTotal,
                discount_label: discountLabel,
            });
        });

        return {
            lines,
            item_count: state.items.length,
            selected_count: selectedCount,
            grand_total: Math.round(grandTotal * 100) / 100,
            currency: 'PHP',
            show_discount_column: showDiscountColumn,
        };
    }

    function renderPreferredTable() {
        if (!cvPreferredCards) {
            return;
        }
        const editable = Boolean(window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.editable);
        const { showGsdSuggestUi, canChangeSuggested } = getGsdSuggestSelectionContext();
        const suggested = unionSuggestedSupplierIds();
        const rows = Array.isArray(state.preferredSuppliers) ? state.preferredSuppliers : [];
        const hint = document.getElementById('cvPreferredHint');
        const sectionHead = document.getElementById('cvPreferredSectionHead');
        const picker = cvPreferredSection ? cvPreferredSection.querySelector('.cv-preferred-picker') : null;
        const matrixLabel = cvPreferredSection ? cvPreferredSection.querySelector('.preferred-supplier-label') : null;
        if (picker) {
            picker.hidden = !editable;
        }
        if (sectionHead) {
            sectionHead.hidden = !editable;
        }
        if (hint) {
            hint.hidden = !editable;
            hint.textContent = editable
                ? 'Search a supplier and add quotes per item. New supplier entries are tagged for verifier awareness.'
                : 'Preferred suppliers indicated by the requester.';
        }
        if (matrixLabel) {
            matrixLabel.hidden = false;
        }
        if (rows.length === 0) {
            cvPreferredCards.innerHTML = `<div class="empty-state">${editable ? 'No preferred suppliers yet.' : 'No preferred suppliers added by the requester.'}</div>`;
            return;
        }
        cvPreferredCards.innerHTML = rows
            .map((s) => {
                const sid = Number(s.supplier_id || 0);
                const isSuggested = suggested.has(sid);
                const isNew = Number(s.is_preferred || 0) === 1;
                const locationLine = formatSupplierLocation(s);
                const avatarSrc = escapeHtml(getSupplierImageUrl(s.supplier_image));
                const linkedIndices = getPreferredSupplierItemIndices(sid);
                const availableItems = state.items
                    .map((item, index) => ({ item, index }))
                    .filter(({ index }) => !linkedIndices.includes(index));
                const addItemOptions = availableItems
                    .map(
                        ({ item, index }) =>
                            `<option value="${index}">${escapeHtml(item.name || `Item ${index + 1}`)}</option>`
                    )
                    .join('');
                const noCanvassItems = state.items.length === 0;
                const addItemBar = editable
                    ? noCanvassItems
                        ? '<div class="cv-pref-add-item-bar"><span class="empty-state">Add canvass items first.</span></div>'
                        : `<div class="cv-pref-add-item-bar">
                        <select class="cv-pref-add-item-select" data-pref-supplier-id="${sid}" aria-label="Select canvass item to quote"${availableItems.length === 0 ? ' disabled' : ''}>
                            <option value="">Select item…</option>
                            ${addItemOptions}
                        </select>
                        <button type="button" class="cv-pref-add-item-btn" data-pref-supplier-id="${sid}"${availableItems.length === 0 ? ' disabled' : ''}>+ Add item</button>
                    </div>`
                    : '';
                const itemRows = linkedIndices
                    .map((index) => {
                        const item = state.items[index];
                        if (!item) {
                            return '';
                        }
                        const val = getPreferredDraftPrice(sid, index);
                        const previewRaw = getPreferredPhotoUrl(sid, index);
                        const preview = previewRaw ? resolvePublicUploadUrl(previewRaw) : '';
                        let photoCell = '<span class="cv-pref-photo-empty">—</span>';
                        if (preview && !editable) {
                            photoCell = `<button type="button" class="cv-pref-photo-view-btn" data-photo-url="${escapeHtml(preview)}" title="Click to view quotation photo">
                                <img src="${escapeHtml(preview)}" alt="" class="cv-pref-photo-thumb">
                            </button>`;
                        } else if (editable) {
                            const uploadedText = preview
                                ? `<span class="cv-pref-photo-status">Photo added</span><button type="button" class="cv-pref-photo-remove" data-pref-supplier-id="${sid}" data-cv-item-index="${index}" title="Remove photo">Remove</button>`
                                : '';
                            photoCell = `<label class="cv-pref-photo-slot" title="Upload quotation photo">
                                    <input type="file" class="cv-pref-photo-input" accept="image/*" data-pref-supplier-id="${sid}" data-cv-item-index="${index}">
                                    ${preview ? `<img src="${escapeHtml(preview)}" alt="" class="cv-pref-photo-thumb">` : '<span class="cv-pref-photo-placeholder"><i class="fas fa-image"></i></span>'}
                                </label>${uploadedText}`;
                        }
                        const isSelectedForItem = isSuggestedForMatrix(index, sid, 'preferred');
                        const pricePresent = val !== null && String(val).trim() !== '';
                        const radioName = `cvSuggestedPreferredItem${index}`;
                        const canPickThisCell = showGsdSuggestUi && pricePresent;
                        const radioHtml = showGsdSuggestUi
                            ? `<label class="cv-suggested-radio-wrap" title="Accept requester preferred quote for this item">
                                <input type="radio"
                                    class="cv-suggested-item-radio cv-pref-suggested-item-radio"
                                    name="${radioName}"
                                    value="${sid}"
                                    data-item-index="${index}"
                                    data-canvass-detail-id="${Number(item.canvass_detail_id || 0)}"
                                    data-selection-source="preferred"
                                    ${isSelectedForItem ? 'checked' : ''}
                                    ${canPickThisCell ? '' : 'disabled'}>
                               </label>`
                            : '';
                        const selectedBadge =
                            gsdReadonly && isSelectedForItem ? '<span class="cv-gsd-suggested-badge">Suggested</span>' : '';
                        const clearBtn =
                            showGsdSuggestUi && isSelectedForItem
                                ? `<button type="button" class="cv-suggested-clear-btn" data-item-index="${index}" data-canvass-detail-id="${Number(item.canvass_detail_id || 0)}" title="Clear suggested supplier">Clear</button>`
                                : '';
                        const qtyMeta = getItemQuantityMeta(index);
                        const reqQty = Math.max(1, Number(qtyMeta.quantity) || 1);
                        const unitLabel = pluralizeUnitType(qtyMeta.unit_type, reqQty);
                        const removeItemBtn = editable
                            ? `<button type="button" class="cv-pref-remove-item-btn" data-pref-supplier-id="${sid}" data-cv-item-index="${index}" title="Remove item from this supplier" aria-label="Remove item">×</button>`
                            : '';
                        return `<tr class="${isSelectedForItem ? 'cv-gsd-suggested-row' : ''}">
                            <td>
                                <div class="cv-canvass-item-name-cell">
                                    ${escapeHtml(item.name || `Item ${index + 1}`)}
                                </div>
                            </td>
                            <td class="cv-pref-req-qty-cell">Req. qty: ${reqQty} ${escapeHtml(unitLabel)}</td>
                            <td>
                                <div class="cv-pref-price-wrap">
                                    ${radioHtml}
                                    <span class="cv-pref-peso">PHP</span>
                                    <input type="number" min="0" step="0.01" class="cv-preferred-price-input" data-pref-supplier-id="${sid}" data-cv-item-index="${index}" value="${escapeHtml(String(val))}" placeholder="0.00"${editable ? '' : ' readonly'}>
                                    ${selectedBadge}
                                    ${clearBtn}
                                </div>
                            </td>
                            <td>${photoCell}</td>
                            <td class="cv-pref-item-action-cell">${removeItemBtn}</td>
                        </tr>`;
                    })
                    .join('');
                const emptyItemsRow =
                    linkedIndices.length === 0
                        ? `<tr><td colspan="5" class="empty-state">${editable ? 'No items added yet. Select an item above.' : 'No quoted items for this supplier.'}</td></tr>`
                        : '';
                return `<article class="cv-pref-card">
                    <div class="cv-pref-card-head">
                        <div class="cv-pref-card-id">
                            <img src="${avatarSrc}" alt="" class="cv-pref-avatar cv-pref-avatar-img" onerror="${supplierAvatarOnError}">
                            <div>
                                <div class="cv-pref-name">${escapeHtml(s.supplier_name || '')}${isNew ? '<span class="cv-pref-new-badge">New</span>' : ''}${isSuggested ? '<span class="supplier-suggest-badge">Suggested</span>' : ''}</div>
                                <div class="cv-pref-contact">${escapeHtml(s.contact_person || 'No contact person')}</div>
                                <div class="cv-pref-tin"><span class="cv-pref-tin-label">TIN</span>${escapeHtml(formatSupplierTinDisplay(s.tin))}</div>
                                ${locationLine ? `<div class="cv-pref-location"><i class="fas fa-location-dot" aria-hidden="true"></i> ${escapeHtml(locationLine)}</div>` : ''}
                            </div>
                        </div>
                        <div class="cv-pref-card-actions">
                            ${editable ? `<button type="button" class="cv-pref-edit-btn" data-pref-id="${sid}" title="Edit supplier details">Edit</button>` : ''}
                            ${editable ? `<button type="button" class="cv-pref-remove-btn" data-pref-id="${sid}" title="Remove preferred supplier">×</button>` : ''}
                        </div>
                    </div>
                    <div class="cv-pref-card-body">
                        ${addItemBar}
                        <table class="cv-pref-items-table" aria-label="Supplier quotes">
                            <thead><tr><th>Item name</th><th>Requisition qty</th><th>Quoted price</th><th>Quotation photo</th><th></th></tr></thead>
                            <tbody>${itemRows}${emptyItemsRow}</tbody>
                        </table>
                    </div>
                </article>`;
            })
            .join('');
        emitCanvassPricingUpdate();
    }

    function isPreferredSupplierAdded(supplierId) {
        const sid = Number(supplierId || 0);
        if (!sid) {
            return false;
        }
        return (state.preferredSuppliers || []).some((s) => Number(s.supplier_id) === sid);
    }

    function updateCvPrefSupplierInfoPanel(supplierId) {
        const panel = document.getElementById('cvPrefSupplierInfoPanel');
        const bodyEl = document.getElementById('cvPrefSupplierInfoPanelBody');
        if (!panel || !bodyEl) {
            return;
        }
        const id = supplierId != null && supplierId !== '' ? Number(supplierId) : 0;
        if (!id || Number.isNaN(id)) {
            state.preferredSearchSelection = null;
            panel.hidden = true;
            bodyEl.innerHTML = '';
            return;
        }
        const supplier = (state.availableSuppliers || []).find((x) => Number(x.supplier_id) === id);
        if (!supplier) {
            state.preferredSearchSelection = null;
            panel.hidden = true;
            bodyEl.innerHTML = '';
            return;
        }
        state.preferredSearchSelection = id;
        panel.hidden = false;
        const rows = [];
        rows.push(`<div class="cv-pref-name">${escapeHtml(supplier.supplier_name || '')}</div>`);
        if (supplier.contact_person) {
            rows.push(`<div class="cv-pref-contact">${escapeHtml(supplier.contact_person)}</div>`);
        }
        const locationLine = formatSupplierLocation(supplier);
        if (locationLine) {
            rows.push(
                `<div class="cv-pref-location"><i class="fas fa-location-dot" aria-hidden="true"></i> ${escapeHtml(locationLine)}</div>`
            );
        }
        rows.push(
            `<div class="cv-pref-tin"><span class="cv-pref-tin-label">TIN</span>${escapeHtml(formatSupplierTinDisplay(supplier.tin))}</div>`
        );
        bodyEl.innerHTML = rows.join('');
    }

    function resetPreferredSupplierSearch() {
        const input = document.getElementById('cvPrefSupplierSearch');
        const list = document.getElementById('cvPrefSupplierSearchList');
        state.preferredSearchFocused = false;
        updateCvPrefSupplierInfoPanel(null);
        if (input) {
            input.value = '';
            input.blur();
        }
        if (list) {
            list.innerHTML = '';
        }
    }

    function renderPreferredPicker() {
        const input = document.getElementById('cvPrefSupplierSearch');
        const list = document.getElementById('cvPrefSupplierSearchList');
        if (!input || !list) return;
        const qRaw = String(input.value || '').trim();
        const q = qRaw.toLowerCase();
        const focused = Boolean(state.preferredSearchFocused);
        const addedIds = new Set(
            (state.preferredSuppliers || [])
                .map((s) => Number(s.supplier_id || 0))
                .filter((id) => id > 0)
        );
        const suppliers = (Array.isArray(state.availableSuppliers) ? state.availableSuppliers : []).filter(
            (s) => !addedIds.has(Number(s.supplier_id || 0))
        );
        if (!focused) {
            list.innerHTML = '';
            return;
        }
        const suggested = unionSuggestedSupplierIds();
        const orderedBase = [...suppliers].sort((a, b) => {
            const sa = suggested.has(Number(a.supplier_id)) ? 0 : 1;
            const sb = suggested.has(Number(b.supplier_id)) ? 0 : 1;
            if (sa !== sb) return sa - sb;
            return String(a.supplier_name || '').localeCompare(String(b.supplier_name || ''), undefined, {
                sensitivity: 'base',
            });
        });
        const results = qRaw.length >= 2
            ? orderedBase.filter((s) => {
                  const name = String(s.supplier_name || '').toLowerCase();
                  const contact = String(s.contact_person || '').toLowerCase();
                  return name.includes(q) || contact.includes(q);
              })
            : orderedBase.slice(0, 12);
        const hasExact = results.some((s) => String(s.supplier_name || '').trim().toLowerCase() === q);
        const existingHtml = results
            .slice(0, 20)
            .map(
                (s) => `<button type="button" class="pref-search-option${suggested.has(Number(s.supplier_id || 0)) ? ' supplier-option-suggested' : ''}" data-supplier-id="${escapeHtml(String(s.supplier_id))}">
                    <img src="${escapeHtml(getSupplierImageUrl(s.supplier_image))}" alt="" class="supplier-option-avatar" onerror="${supplierAvatarOnError}">
                    <span class="supplier-option-name">${escapeHtml(s.supplier_name || '')}${suggested.has(Number(s.supplier_id || 0)) ? '<span class="supplier-suggest-badge">Suggested</span>' : ''}</span>
                    <span class="supplier-option-contact">${escapeHtml(s.contact_person || '—')}</span>
                </button>`
            )
            .join('');
        const addNewHtml = qRaw.length >= 2
            ? `<button type="button" class="pref-search-option cv-pref-add-new-option" data-add-new-name="${escapeHtml(qRaw)}">
                <span class="cv-pref-add-new-icon" aria-hidden="true"><i class="fas fa-circle-plus"></i></span>
                <span class="supplier-option-name">Add "${escapeHtml(qRaw)}" as new supplier</span>
            </button>`
            : '';
        if (results.length === 0 && qRaw.length >= 2 && !hasExact) {
            list.innerHTML = addNewHtml;
            return;
        }
        list.innerHTML = `${existingHtml}${addNewHtml}`;
    }

    async function linkPreferredSupplier(supplierId) {
        const sid = Number(supplierId || 0);
        if (!sid) {
            showToast('Invalid supplier.', 'error');
            return;
        }
        if (isPreferredSupplierAdded(sid)) {
            showToast('This supplier is already added.', 'error');
            resetPreferredSupplierSearch();
            return;
        }
        try {
            const prefApi = (window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.api) || api;
            const body = new URLSearchParams();
            body.set('action', 'link_preferred');
            body.set('request_id', String(requestId));
            body.set('supplier_id', String(sid));
            const res = await fetch(prefApi, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(), credentials: 'include' });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Failed to add preferred.', 'error');
                if (data.already_added) {
                    resetPreferredSupplierSearch();
                    await loadPreferredSuppliers();
                }
                return;
            }
            showToast(data.message || 'Preferred supplier added.');
            resetPreferredSupplierSearch();
            await loadPreferredSuppliers();
        } catch {
            showToast('Network error.', 'error');
        }
    }

    async function addPreferredSupplierByName(name) {
        const cleaned = String(name || '').trim();
        if (!cleaned) {
            showToast('Supplier name is required.', 'error');
            return;
        }
        const nameTaken = (state.preferredSuppliers || []).some(
            (s) => String(s.supplier_name || '').trim().toLowerCase() === cleaned.toLowerCase()
        );
        if (nameTaken) {
            showToast('This supplier is already added.', 'error');
            resetPreferredSupplierSearch();
            return;
        }
        const prefApi = (window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.api) || api;
        const body = new URLSearchParams();
        body.set('action', 'add_preferred');
        body.set('request_id', String(requestId));
        body.set('supplier_name', cleaned);
        body.set('contact_person', '');
        body.set('phone_number', '');
        body.set('email', '');
        body.set('shop_url', '');
        try {
            const res = await fetch(prefApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'include',
            });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not add supplier.', 'error');
                return;
            }
            showToast(data.message || 'Preferred supplier added.');
            await loadPreferredSuppliers();
            const created = state.preferredSuppliers.find(
                (s) => String(s.supplier_name || '').trim().toLowerCase() === cleaned.toLowerCase()
            );
            if (created) {
                created.is_preferred = 1;
            }
            resetPreferredSupplierSearch();
        } catch {
            showToast('Network error.', 'error');
        }
    }

    async function promptAddNewSupplierWithOptionalInfo(name) {
        const cleaned = String(name || '').trim();
        if (!cleaned) {
            showToast('Supplier name is required.', 'error');
            return;
        }
        const wantsDetails = await showConfirmModal(
            `Do you want to add supplier info for "${cleaned}"?\n\nYes: Open details form\nNo: Add quickly with name only`
        );
        if (wantsDetails) {
            resetPreferredSupplierSearch();
            openPrefSupModal('add', {
                supplier_name: cleaned,
                contact_person: '',
                phone_number: '',
                email: '',
                shop_url: '',
                address: '',
                city: '',
                country: '',
                postal_code: '',
            });
            return;
        }
        await addPreferredSupplierByName(cleaned);
    }

    function addPreferredToMatrix(supplierId) {
        const id = Number(supplierId || 0);
        if (!id) return;
        if (state.selectedSuppliers.some((s) => Number(s.supplier_id) === id)) {
            showToast('That supplier is already in the matrix.', 'error');
            return;
        }
        const full = state.availableSuppliers.find((x) => Number(x.supplier_id) === id);
        if (!full) { showToast('Supplier not found in catalog.', 'error'); return; }
        const prices = {};
        state.items.forEach((_, i) => { prices[i] = ''; });
        state.selectedSuppliers.push({
            supplier_id: full.supplier_id,
            supplier_name: full.supplier_name,
            supplier_image: full.supplier_image,
            prices,
            benefits: '',
            discounts: [],
        });
        renderSupplierTable();
        scheduleRequesterAutosave();
    }

    async function loadPreferredSuppliers() {
        if (!cvPreferredCards) {
            return;
        }
        try {
            const prefApi = (window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.api) || api;
            const res = await fetch(`${prefApi}?action=get_preferred&request_id=${encodeURIComponent(String(requestId))}`, { credentials: 'include' });
            const data = await res.json();
            if (!data || !data.success) {
                showToast(data && data.message ? data.message : 'Unable to load preferred suppliers.', 'error');
                state.preferredSuppliers = [];
            } else if (Array.isArray(data.preferred_suppliers)) {
                state.preferredSuppliers = data.preferred_suppliers.map((s) => ({
                    supplier_id: s.supplier_id,
                    supplier_name: s.supplier_name,
                    contact_person: s.contact_person || '',
                    phone_number: s.phone_number || '',
                    email: s.email || '',
                    shop_url: s.shop_url || '',
                    address: s.address || '',
                    city: s.city || '',
                    country: s.country || '',
                    postal_code: s.postal_code || '',
                    tin: s.tin || '',
                    supplier_image: s.supplier_image || '',
                    quoted_prices: s.quoted_prices || {},
                    quote_photos: s.quote_photos || {},
                    quoted_item_indices: Array.isArray(s.quoted_item_indices) ? s.quoted_item_indices : [],
                    is_preferred: Number(s.is_preferred || 0),
                }));
                hydratePreferredSupplierItemsFromApi();
                hydratePreferredPriceDraftsFromApi();
                hydratePreferredPhotoDraftsFromApi();
            } else {
                state.preferredSuppliers = [];
                state.preferredPriceDrafts = {};
                state.preferredSupplierItems = {};
                state.preferredQuotePhotos = {};
            }
        } catch {
            showToast('Unable to load preferred suppliers.', 'error');
            state.preferredSuppliers = [];
            state.preferredPriceDrafts = {};
            state.preferredSupplierItems = {};
            state.preferredQuotePhotos = {};
        }
        renderPreferredTable();
        renderPreferredPicker();
    }

    async function uploadPendingPreferredPhotos() {
        const entries = Object.entries(state.preferredQuotePhotos || {});
        for (const [key, payload] of entries) {
            if (!payload || !payload.file) {
                continue;
            }
            const [sidStr, itemStr] = key.split('-');
            const sid = Number(sidStr || 0);
            const itemIndex = Number(itemStr || 0);
            if (!sid || Number.isNaN(itemIndex)) {
                continue;
            }
            const form = new FormData();
            form.append('action', 'upload_quote_photo');
            form.append('request_id', String(requestId));
            form.append('supplier_id', String(sid));
            form.append('item_index', String(itemIndex));
            form.append('quote_photo', payload.file);
            const res = await fetch(api, {
                method: 'POST',
                body: form,
                credentials: 'include',
            });
            const data = await res.json();
            if (!data.success || !data.photo_url) {
                throw new Error(data.message || 'Photo upload failed.');
            }
            state.preferredQuotePhotos[key] = {
                file: null,
                preview_url: data.photo_url,
                url: data.photo_url,
            };
        }
    }

    async function removePreferredPhoto(supplierId, itemIndex) {
        const key = preferredPhotoKey(supplierId, itemIndex);
        const existing = state.preferredQuotePhotos[key] || {};
        if (existing.preview_url && String(existing.preview_url).startsWith('blob:')) {
            URL.revokeObjectURL(existing.preview_url);
        }
        delete state.preferredQuotePhotos[key];
        try {
            const body = new URLSearchParams();
            body.set('action', 'remove_quote_photo');
            body.set('request_id', String(requestId));
            body.set('supplier_id', String(supplierId));
            body.set('item_index', String(itemIndex));
            await fetch(api, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'include',
            });
        } catch {
            /* best effort */
        }
        state.hasUnsavedChanges = true;
        renderPreferredTable();
    }

    function openPrefSupModal(mode, existing) {
        const modal = document.getElementById('cvPrefSupModal');
        if (!modal) return;
        document.getElementById('cvPrefSupModalTitle').textContent = mode === 'edit' ? 'Edit preferred supplier' : 'Add preferred supplier';
        document.getElementById('cvPrefSupModalSupplierId').value = existing ? String(existing.supplier_id || '') : '';
        document.getElementById('cvPrefSupName').value = existing ? (existing.supplier_name || '') : '';
        document.getElementById('cvPrefSupContact').value = existing ? (existing.contact_person || '') : '';
        const tinInput = document.getElementById('cvPrefSupTin');
        if (tinInput) {
            tinInput.value = existing ? (existing.tin || '') : '';
        }
        document.getElementById('cvPrefSupPhone').value = existing ? (existing.phone_number || '') : '';
        document.getElementById('cvPrefSupEmail').value = existing ? (existing.email || '') : '';
        document.getElementById('cvPrefSupUrl').value = existing ? (existing.shop_url || '') : '';
        document.getElementById('cvPrefSupAddress').value = existing ? (existing.address || '') : '';
        document.getElementById('cvPrefSupCity').value = existing ? (existing.city || '') : '';
        document.getElementById('cvPrefSupCountry').value = existing ? (existing.country || '') : '';
        document.getElementById('cvPrefSupPostal').value = existing ? (existing.postal_code || '') : '';
        modal.style.display = 'flex';
    }

    function closePrefSupModal() {
        const modal = document.getElementById('cvPrefSupModal');
        if (!modal) return;
        modal.style.display = 'none';
    }

    async function savePrefSupModal() {
        const supplierId = document.getElementById('cvPrefSupModalSupplierId').value || '';
        const name = (document.getElementById('cvPrefSupName').value || '').trim();
        if (!name) { showToast('Supplier name is required.', 'error'); return; }
        const body = new URLSearchParams();
        body.set('action', supplierId ? 'update_preferred' : 'add_preferred');
        body.set('request_id', String(requestId));
        if (supplierId) body.set('supplier_id', supplierId);
        body.set('supplier_name', name);
        body.set('contact_person', (document.getElementById('cvPrefSupContact').value || '').trim());
        body.set('tin', (document.getElementById('cvPrefSupTin')?.value || '').trim());
        body.set('phone_number', (document.getElementById('cvPrefSupPhone').value || '').trim());
        body.set('email', (document.getElementById('cvPrefSupEmail').value || '').trim());
        body.set('shop_url', (document.getElementById('cvPrefSupUrl').value || '').trim());
        body.set('address', (document.getElementById('cvPrefSupAddress').value || '').trim());
        body.set('city', (document.getElementById('cvPrefSupCity').value || '').trim());
        body.set('country', (document.getElementById('cvPrefSupCountry').value || '').trim());
        body.set('postal_code', (document.getElementById('cvPrefSupPostal').value || '').trim());
        try {
            const prefApi = (window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.api) || api;
            const res = await fetch(prefApi, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(), credentials: 'include' });
            const data = await res.json();
            if (!data.success) { showToast(data.message || 'Failed to save.', 'error'); return; }
            closePrefSupModal();
            showToast(data.message || 'Saved.');
            resetPreferredSupplierSearch();
            await loadPreferredSuppliers();
        } catch {
            showToast('Network error.', 'error');
        }
    }

    async function removePrefSup(supplierId) {
        const ok = await showConfirmModal('Remove this preferred supplier?');
        if (!ok) return;
        const body = new URLSearchParams();
        body.set('action', 'remove_preferred');
        body.set('request_id', String(requestId));
        body.set('supplier_id', String(supplierId));
        try {
            const prefApi = (window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.api) || api;
            const res = await fetch(prefApi, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(), credentials: 'include' });
            const data = await res.json();
            if (!data.success) { showToast(data.message || 'Failed.', 'error'); return; }
            showToast(data.message || 'Removed.');
            await loadPreferredSuppliers();
        } catch {
            showToast('Network error.', 'error');
        }
    }

    syncCanvassHintChrome();

    if (cvCanvassHintDismiss && cvCanvassItemsHintWrap) {
        cvCanvassHintDismiss.addEventListener('click', () => {
            sessionStorage.setItem(CV_CANVASS_HINT_DISMISSED_KEY, '1');
            syncCanvassHintChrome();
        });
    }
    if (cvCanvassHintShow) {
        cvCanvassHintShow.addEventListener('click', () => {
            sessionStorage.removeItem(CV_CANVASS_HINT_DISMISSED_KEY);
            syncCanvassHintChrome();
        });
    }

    const state = {
        items: [],
        selectedSuppliers: [],
        availableSuppliers: [],
        preferredSuppliers: [],
        catalogItems: [],
        lastSuggestItems: [],
        suggestTimer: null,
        selectedSupplierId: null,
        preferredSearchSelection: null,
        preferredSearchFocused: false,
        preferredPriceDrafts: {},
        preferredSupplierItems: {},
        preferredSearchTimer: null,
        preferredQuotePhotos: {},
        canvassedSupplierCount: 0,
        suggestedByItem: {},
        requestedLines: [],
        autoSaveTimer: null,
        hasUnsavedChanges: false,
    };
    const gsdSuggestedHiddenInput = document.getElementById('gsdSuggestedSupplierId');
    const canSelectSuggestedSupplierInTable = !!gsdSuggestedHiddenInput;

    let cachedCanvassApproval = null;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function resolvePublicUploadUrl(path) {
        if (!path) {
            return '';
        }
        const raw = String(path).trim();
        if (!raw) {
            return '';
        }
        if (/^(https?:|blob:|data:)/i.test(raw)) {
            return raw;
        }
        let normalized = raw.replace(/\\/g, '/');
        normalized = normalized.replace(/^app\/api\/public\//i, '');
        normalized = normalized.replace(/^public\//i, '');
        if (normalized.startsWith('../')) {
            return normalized;
        }
        return `../${normalized.replace(/^\/+/, '')}`;
    }

    const supplierImagePlaceholder =
        'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" rx="20" fill="%23dcf5e0"/><text x="50%" y="55%" text-anchor="middle" font-size="16" fill="%231f4f28" font-family="Arial">S</text></svg>';

    function getSupplierImageUrl(supplierImage) {
        if (!supplierImage || !String(supplierImage).trim()) {
            return supplierImagePlaceholder;
        }
        return resolvePublicUploadUrl(supplierImage) || supplierImagePlaceholder;
    }

    function formatSupplierLocation(s) {
        if (!s) {
            return '';
        }
        const parts = [
            s.address,
            s.city,
            s.country,
            s.postal_code,
        ]
            .map((v) => (v == null ? '' : String(v).trim()))
            .filter(Boolean);
        return parts.join(', ');
    }

    function formatSupplierTinDisplay(tin) {
        const value = tin == null ? '' : String(tin).trim();
        return value !== '' ? value : 'N/A';
    }

    function formatTinInputValue(raw) {
        const digits = String(raw || '').replace(/\D/g, '').slice(0, 12);
        const parts = [];
        for (let i = 0; i < digits.length; i += 3) {
            parts.push(digits.slice(i, i + 3));
        }
        return parts.join('-');
    }

    const supplierAvatarOnError =
        "this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22><rect width=%2240%22 height=%2240%22 rx=%2220%22 fill=%22%23dcf5e0%22/><text x=%2250%25%22 y=%2255%25%22 text-anchor=%22middle%22 font-size=%2216%22 fill=%22%231f4f28%22 font-family=%22Arial%22>S</text></svg>'";

    function fillCvItemDatalist(catalog) {
        if (!cvItemNameSuggestions) return;
        const names = (Array.isArray(catalog) ? catalog : [])
            .map((x) => (x && x.item_name ? String(x.item_name).trim() : ''))
            .filter(Boolean);
        const unique = Array.from(new Set(names));
        cvItemNameSuggestions.innerHTML = unique
            .map((v) => `<option value="${escapeHtml(v)}"></option>`)
            .join('');
    }

    function hideCvSuggestList() {
        if (cvItemSuggestList) {
            cvItemSuggestList.hidden = true;
            cvItemSuggestList.innerHTML = '';
        }
        state.lastSuggestItems = [];
    }

    function applyCatalogMatchExactName() {
        const name = (cvItemName && cvItemName.value.trim()) || '';
        if (!name || !state.catalogItems.length) return;
        const match = state.catalogItems.find(
            (x) => String(x.item_name || '').toLowerCase() === name.toLowerCase()
        );
        if (match && cvItemBrand && cvItemModel) {
            if (!(cvItemBrand.value || '').trim() && match.brand) {
                cvItemBrand.value = String(match.brand);
            }
            if (!(cvItemModel.value || '').trim() && match.model) {
                cvItemModel.value = String(match.model);
            }
        }
    }

    async function fetchCvItemSuggestions(term) {
        const q = String(term || '').trim();
        if (!cvItemSuggestList || !deanApi || q.length < 1) {
            hideCvSuggestList();
            return;
        }
        try {
            const res = await fetch(
                `${deanApi}?action=item_suggestions&term=${encodeURIComponent(q)}`,
                { credentials: 'include' }
            );
            const data = await res.json();
            if (!data.success) {
                hideCvSuggestList();
                return;
            }
            const list = Array.isArray(data.items) ? data.items : [];
            state.lastSuggestItems = list;
            if (list.length === 0) {
                hideCvSuggestList();
                return;
            }
            cvItemSuggestList.innerHTML = list
                .map((it, idx) => {
                    const sub = [it.brand, it.model].filter(Boolean).join(' · ');
                    return `<li role="presentation"><button type="button" class="cv-item-suggest-btn" role="option" data-suggest-index="${idx}"><span class="cv-item-suggest-title">${escapeHtml(
                        String(it.item_name || '')
                    )}</span>${
                        sub
                            ? `<span class="cv-item-suggest-sub">${escapeHtml(sub)}</span>`
                            : ''
                    }</button></li>`;
                })
                .join('');
            cvItemSuggestList.hidden = false;
        } catch {
            hideCvSuggestList();
        }
    }

    function scheduleCvItemSuggest() {
        if (state.suggestTimer) {
            clearTimeout(state.suggestTimer);
        }
        state.suggestTimer = setTimeout(() => {
            state.suggestTimer = null;
            const term = cvItemName ? cvItemName.value.trim() : '';
            fetchCvItemSuggestions(term);
        }, 200);
    }

    function normalizePrices(prices) {
        const out = {};
        if (!prices || typeof prices !== 'object') return out;
        Object.keys(prices).forEach((k) => {
            const idx = Number(k);
            if (!Number.isNaN(idx)) {
                out[idx] = prices[k];
            }
        });
        return out;
    }

    function hydratePreferredSupplierItemsFromApi() {
        const items = {};
        (state.preferredSuppliers || []).forEach((s) => {
            const sid = String(s.supplier_id || '');
            if (!sid) {
                return;
            }
            let indices = [];
            if (Array.isArray(s.quoted_item_indices) && s.quoted_item_indices.length > 0) {
                indices = s.quoted_item_indices
                    .map((n) => Number(n))
                    .filter((n) => !Number.isNaN(n) && n >= 0);
            } else {
                const priceKeys =
                    s.quoted_prices && typeof s.quoted_prices === 'object' ? Object.keys(s.quoted_prices) : [];
                const photoKeys =
                    s.quote_photos && typeof s.quote_photos === 'object' ? Object.keys(s.quote_photos) : [];
                const merged = new Set(
                    [...priceKeys, ...photoKeys]
                        .map((k) => Number(k))
                        .filter((n) => !Number.isNaN(n) && n >= 0)
                );
                indices = [...merged];
            }
            items[sid] = indices.sort((a, b) => a - b);
        });
        state.preferredSupplierItems = items;
    }

    function hydratePreferredPriceDraftsFromApi() {
        const drafts = {};
        (state.preferredSuppliers || []).forEach((s) => {
            const sid = String(s.supplier_id || '');
            if (!sid) {
                return;
            }
            const raw = s.quoted_prices;
            if (raw && typeof raw === 'object' && Object.keys(raw).length > 0) {
                drafts[sid] = normalizePrices(raw);
            }
        });
        state.preferredPriceDrafts = drafts;
    }

    function hydratePreferredPhotoDraftsFromApi() {
        const photos = {};
        (state.preferredSuppliers || []).forEach((s) => {
            const sid = Number(s.supplier_id || 0);
            if (!sid) {
                return;
            }
            const raw = s.quote_photos;
            if (!raw || typeof raw !== 'object') {
                return;
            }
            Object.keys(raw).forEach((idxStr) => {
                const itemIndex = Number(idxStr);
                const path = raw[idxStr];
                if (Number.isNaN(itemIndex) || !path) {
                    return;
                }
                const key = preferredPhotoKey(sid, itemIndex);
                photos[key] = {
                    file: null,
                    preview_url: String(path),
                    url: String(path),
                };
            });
        });
        state.preferredQuotePhotos = photos;
    }

    function buildItemsPayloadForSave() {
        return state.items.map((it) => ({
            item_name: it.name,
            brand: it.brand || '',
            model: it.model || '',
            specification: it.specification,
            requisition_line_id: it.requisition_line_id,
        }));
    }

    function buildSuppliersPayloadForSave() {
        return (state.selectedSuppliers || [])
            .map((s) => {
                const benefits = String(s.benefits || '').trim();
                return {
                    supplier_id: s.supplier_id,
                    prices: s.prices || {},
                    photos: s.photos || {},
                    benefits: benefits !== '' ? benefits : null,
                    discounts: getCanvassDiscountsForSave(s.discounts),
                };
            })
            .filter((s) => Number(s.supplier_id || 0) > 0);
    }

    function buildPreferredQuotesPayload() {
        return (state.preferredSuppliers || [])
            .map((pref) => {
                const sid = Number(pref.supplier_id || 0);
                if (!sid) {
                    return null;
                }
                const drafts = state.preferredPriceDrafts[String(sid)] || {};
                const itemIndices = getPreferredSupplierItemIndices(sid);
                const prices = {};
                const photos = {};
                itemIndices.forEach((idx) => {
                    const val = drafts[idx];
                    if (val !== null && val !== undefined && String(val).trim() !== '') {
                        prices[idx] = String(val).trim();
                    }
                    const url = getPreferredPhotoUrl(sid, idx);
                    if (url && !String(url).startsWith('blob:')) {
                        photos[idx] = url;
                    }
                });
                return { supplier_id: sid, prices, photos, item_indices: itemIndices };
            })
            .filter(Boolean);
    }

    function supplierRowHasQuotedPrice(supplier) {
        if (!supplier || !supplier.prices || typeof supplier.prices !== 'object') {
            return false;
        }
        return Object.values(supplier.prices).some((raw) => {
            if (raw === null || raw === '') return false;
            const n = Number(raw);
            return Number.isFinite(n) && n >= 0;
        });
    }

    function approvalStepIsAccept(value) {
        return String(value || '').trim().toLowerCase() === 'accept';
    }

    function approvalStepIsReject(value) {
        return String(value || '').trim().toLowerCase() === 'reject';
    }

    function approvalDetailForStep(raw) {
        if (approvalStepIsAccept(raw)) return 'Verified';
        if (approvalStepIsReject(raw)) return 'Rejected';
        return '';
    }

    /**
     * Bottom strip: canvas → GSD → Comptroller → President (matches requisition_form circle styles).
     * Static subtitles (Canvassed by, etc.) live in HTML; detail lines show status or canvasser name.
     */
    function syncCanvasserToolbar() {
        if (!canvasserRegister) {
            return;
        }
        const draft = cvSaveDraftBtn;
        const complete = cvCompleteCanvassBtn;
        const undo = document.getElementById('cvCanvasserUndoBtn');
        const hint = document.querySelector('.cv-canvasser-save-hint');
        const appr = cachedCanvassApproval;
        const c = String((appr && appr.canvas_status) || '').trim().toLowerCase();
        const finalized = c === 'accept' || c === 'reject';
        if (draft) {
            draft.disabled = finalized;
            draft.hidden = finalized;
        }
        if (complete) {
            complete.disabled = finalized;
            complete.hidden = finalized;
        }
        if (undo) {
            undo.hidden = !finalized;
            undo.disabled = false;
        }
        if (hint) {
            hint.hidden = finalized;
        }
    }

    function applyCanvassApproval(appr) {
        cachedCanvassApproval = appr && typeof appr === 'object' ? appr : null;
        if (canvasserRegister) {
            syncCanvasserToolbar();
        }
        const strip = document.getElementById('cvApprovalStrip');
        if (!strip) return;
        const roles = strip.querySelectorAll('.approval-role');
        const details = [
            document.getElementById('cvApprCanvasserDetail'),
            document.getElementById('cvApprGsdDetail'),
            document.getElementById('cvApprCompDetail'),
            document.getElementById('cvApprPresDetail'),
        ];
        const statuses = appr && typeof appr === 'object'
            ? [appr.canvas_status, appr.gsd_status, appr.comp_status, appr.pres_status]
            : [null, null, null, null];

        roles.forEach((role, i) => {
            const circle = role.querySelector('.circle-icon');
            if (!circle) return;
            const raw = statuses[i];
            let step = 'inactive';
            if (approvalStepIsAccept(raw)) step = 'active';
            else if (approvalStepIsReject(raw)) step = 'rejected';
            circle.classList.remove('active', 'inactive', 'rejected');
            circle.classList.add(step);
            const icon = circle.querySelector('i');
            if (icon) {
                icon.className = step === 'rejected' ? 'fas fa-xmark' : 'fas fa-check';
            }
        });

        if (details[0]) {
            const cRaw = statuses[0];
            let t = approvalDetailForStep(cRaw);
            if (approvalStepIsAccept(cRaw) && appr && appr.canvassed_by) {
                const name = String(appr.canvassed_by).trim();
                if (name) t = name;
            }
            details[0].textContent = t;
            if (t && t !== 'Rejected' && t !== 'Verified') {
                details[0].setAttribute('title', t);
            } else {
                details[0].removeAttribute('title');
            }
        }
        for (let j = 1; j < 4; j += 1) {
            if (details[j]) {
                details[j].textContent = approvalDetailForStep(statuses[j]);
                details[j].removeAttribute('title');
            }
        }
        updateSuggestedSupplierNotice();
        renderSupplierTable();
        if (gsdReadonly && typeof window.__imrmsGsdAssigneeSyncApproval === 'function') {
            window.__imrmsGsdAssigneeSyncApproval(appr);
        }
    }

    function updateSuggestedSupplierNotice() {
        if (!cvSuggestedSupplierNotice || !cvSuggestedSupplierNoticeText) {
            return;
        }
        const reviewerCfg = window.IMRMS_CANVASS_REVIEWER || null;
        let role = reviewerCfg && reviewerCfg.role ? String(reviewerCfg.role).toLowerCase() : '';
        if (!role) {
            try {
                const fromParam = new URLSearchParams(window.location.search).get('from') || '';
                const fromLc = String(fromParam).toLowerCase();
                if (fromLc === 'comptroller' || fromLc === 'president') {
                    role = fromLc;
                }
            } catch {
                /* no-op */
            }
        }
        if (role !== 'comptroller' && role !== 'president') {
            cvSuggestedSupplierNotice.hidden = true;
            cvSuggestedSupplierNoticeText.textContent = '';
            return;
        }
        const selectedCount = Object.keys(state.suggestedByItem || {}).length;
        const totalItems = Array.isArray(state.items) ? state.items.length : 0;
        if (selectedCount <= 0 || totalItems <= 0) {
            cvSuggestedSupplierNotice.hidden = true;
            cvSuggestedSupplierNoticeText.textContent = '';
            return;
        }
        if (cvSuggestedNoticeDismissed) {
            cvSuggestedSupplierNotice.hidden = true;
            return;
        }
        cvSuggestedSupplierNoticeText.textContent = selectedCount >= totalItems
            ? `Information: GSD already selected suggested suppliers for all ${totalItems} items.`
            : `Information: GSD selected suggested suppliers for ${selectedCount} out of ${totalItems} items.`;
        cvSuggestedSupplierNotice.hidden = false;
    }

    function showToast(message, type) {
        if (!cvFormToast) return;
        cvFormToast.textContent = message;
        cvFormToast.className = `toast ${type || 'success'}`;
        cvFormToast.style.display = 'block';
        clearTimeout(showToast._t);
        showToast._t = setTimeout(() => {
            cvFormToast.style.display = 'none';
        }, 3000);
    }

    function showConfirmModal(message) {
        return new Promise((resolve) => {
            if (!cvConfirmModal || !cvConfirmMessage) {
                resolve(false);
                return;
            }
            cvConfirmMessage.textContent = message;
            cvConfirmModal.style.display = 'flex';

            const cleanup = (v) => {
                cvConfirmModal.style.display = 'none';
                cvConfirmOkBtn.removeEventListener('click', onOk);
                cvConfirmCancelBtn.removeEventListener('click', onCancel);
                cvConfirmModal.removeEventListener('click', onBackdrop);
                document.removeEventListener('keydown', onEsc);
                resolve(v);
            };
            const onOk = () => cleanup(true);
            const onCancel = () => cleanup(false);
            const onBackdrop = (e) => {
                if (e.target.classList.contains('confirm-modal-backdrop')) {
                    cleanup(false);
                }
            };
            const onEsc = (e) => {
                if (e.key === 'Escape') cleanup(false);
            };

            cvConfirmOkBtn.addEventListener('click', onOk);
            cvConfirmCancelBtn.addEventListener('click', onCancel);
            cvConfirmModal.addEventListener('click', onBackdrop);
            document.addEventListener('keydown', onEsc);
        });
    }

    function unionSuggestedSupplierIds() {
        const set = new Set();
        state.items.forEach((it) => {
            (it.suggested_supplier_ids || []).forEach((id) => {
                const n = Number(id);
                if (!Number.isNaN(n) && n > 0) {
                    set.add(n);
                }
            });
        });
        return set;
    }

    function updateCvSupplierContactPanel() {
        const panel = document.getElementById('cvSupplierContactPanel');
        if (!panel || !canvasserRegister) {
            if (panel) {
                panel.hidden = true;
            }
            return;
        }
        const bodyEl = document.getElementById('cvSupplierContactPanelBody');
        if (!bodyEl) {
            return;
        }
        const id = state.selectedSupplierId ? Number(state.selectedSupplierId) : 0;
        if (!id || Number.isNaN(id)) {
            panel.hidden = true;
            return;
        }
        const s = state.availableSuppliers.find((x) => Number(x.supplier_id) === id);
        if (!s) {
            panel.hidden = true;
            return;
        }
        panel.hidden = false;
        const lines = [];
        if (s.contact_person) {
            lines.push(`<div><span class="sc-label">Contact</span>${escapeHtml(s.contact_person)}</div>`);
        }
        if (s.phone_number) {
            const tel = String(s.phone_number).replace(/\s+/g, '');
            lines.push(
                `<div><span class="sc-label">Phone</span><a href="tel:${escapeHtml(tel)}">${escapeHtml(s.phone_number)}</a></div>`
            );
        }
        if (s.email) {
            lines.push(`<div><span class="sc-label">Email</span><a href="mailto:${escapeHtml(s.email)}">${escapeHtml(s.email)}</a></div>`);
        }
        const addr = [s.address, s.city, s.country, s.postal_code].filter(Boolean).join(', ');
        if (addr) {
            lines.push(`<div><span class="sc-label">Address</span>${escapeHtml(addr)}</div>`);
        }
        if (lines.length === 0) {
            bodyEl.innerHTML = '<p class="sc-muted">No contact details on file for this supplier.</p>';
        } else {
            bodyEl.innerHTML = lines.join('');
        }
    }

    function setSupplierPickerHighlight(supplierId) {
        const id = supplierId != null && supplierId !== '' ? Number(supplierId) : 0;
        if (!id || Number.isNaN(id)) {
            state.selectedSupplierId = null;
            if (cvSupplierDropdownPreview) {
                cvSupplierDropdownPreview.hidden = true;
                cvSupplierDropdownPreview.removeAttribute('src');
            }
            if (cvSupplierSelectedText) cvSupplierSelectedText.textContent = 'Select Supplier';
            updateCvSupplierContactPanel();
            return;
        }
        const supplier = state.availableSuppliers.find((x) => Number(x.supplier_id) === id);
        state.selectedSupplierId = String(id);
        if (supplier && cvSupplierSelectedText && cvSupplierDropdownPreview) {
            cvSupplierSelectedText.textContent = supplier.supplier_name || 'Supplier';
            cvSupplierDropdownPreview.src = getSupplierImageUrl(supplier.supplier_image);
            cvSupplierDropdownPreview.alt = supplier.supplier_name || '';
            cvSupplierDropdownPreview.hidden = false;
        }
        updateCvSupplierContactPanel();
    }

    function insertSupplierIntoCvCatalog(supplierRow) {
        if (!supplierRow || supplierRow.supplier_id == null) {
            return;
        }
        const id = Number(supplierRow.supplier_id);
        if (!state.availableSuppliers.some((x) => Number(x.supplier_id) === id)) {
            state.availableSuppliers.push(supplierRow);
            state.availableSuppliers.sort((a, b) =>
                String(a.supplier_name || '').localeCompare(String(b.supplier_name || ''), undefined, {
                    sensitivity: 'base',
                })
            );
        }
        renderSupplierDropdown();
    }

    function openCanvasserSupplierModal() {
        if (!canvasserRegister) {
            return;
        }
        const modal = document.getElementById('canvasserNewSupplierModal');
        if (!modal) {
            return;
        }
        const form = document.getElementById('canvasserNewSupplierForm');
        if (form) {
            form.reset();
        }
        modal.style.display = 'flex';
    }

    function closeCanvasserSupplierModal() {
        const modal = document.getElementById('canvasserNewSupplierModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    async function submitCvCanvasserNewSupplier() {
        if (!canvasserRegister) {
            return;
        }
        if (!requestId) {
            showToast('Missing request.', 'error');
            return;
        }
        const nameEl = document.getElementById('canvasserNewSupplierName');
        const name = nameEl ? nameEl.value.trim() : '';
        if (!name) {
            showToast('Supplier name is required.', 'error');
            return;
        }
        const body = new URLSearchParams();
        body.set('action', 'create_supplier');
        body.set('request_id', String(requestId));
        body.set('supplier_name', name);
        body.set('contact_person', (document.getElementById('canvasserNewSupplierContact') || {}).value?.trim() || '');
        body.set('phone_number', (document.getElementById('canvasserNewSupplierPhone') || {}).value?.trim() || '');
        body.set('email', (document.getElementById('canvasserNewSupplierEmail') || {}).value?.trim() || '');
        body.set('address', (document.getElementById('canvasserNewSupplierAddress') || {}).value?.trim() || '');
        body.set('city', (document.getElementById('canvasserNewSupplierCity') || {}).value?.trim() || '');
        body.set('country', (document.getElementById('canvasserNewSupplierCountry') || {}).value?.trim() || '');
        body.set('postal_code', (document.getElementById('canvasserNewSupplierPostal') || {}).value?.trim() || '');
        body.set('tin', (document.getElementById('canvasserNewSupplierTin') || {}).value?.trim() || '');
        try {
            const res = await fetch(CANVASSER_REQUESTS_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'include',
            });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not save supplier.', 'error');
                return;
            }
            if (data.supplier) {
                insertSupplierIntoCvCatalog(data.supplier);
                setSupplierPickerHighlight(data.supplier.supplier_id);
            }
            closeCanvasserSupplierModal();
            showToast(data.message || 'Supplier registered.');
        } catch {
            showToast('Network error.', 'error');
        }
    }

    function renderSupplierDropdown() {
        if (!cvSupplierDropdownList) return;
        if (!state.availableSuppliers.length) {
            const regBtn = canvasserRegister
                ? '<button type="button" class="supplier-catalog-empty-add" id="cvOpenNewSupplierFromEmpty">Register a new supplier</button>'
                : '';
            const tail = canvasserRegister
                ? ` ${regBtn}`
                : ' Add suppliers in Supplier Management.';
            cvSupplierDropdownList.innerHTML = `<div class="supplier-option-empty">No suppliers in the directory yet.${tail}</div>`;
            if (canvasserRegister && cvSupplierDropdown) {
                const emptyBtn = document.getElementById('cvOpenNewSupplierFromEmpty');
                if (emptyBtn) {
                    emptyBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        cvSupplierDropdown.classList.remove('open');
                        openCanvasserSupplierModal();
                    });
                }
            }
            return;
        }
        const suggested = unionSuggestedSupplierIds();
        const ordered = [...state.availableSuppliers].sort((a, b) => {
            const sa = suggested.has(Number(a.supplier_id)) ? 0 : 1;
            const sb = suggested.has(Number(b.supplier_id)) ? 0 : 1;
            if (sa !== sb) {
                return sa - sb;
            }
            return String(a.supplier_name || '').localeCompare(String(b.supplier_name || ''), undefined, {
                sensitivity: 'base',
            });
        });
        cvSupplierDropdownList.innerHTML = ordered
            .map((supplier) => {
                const sid = Number(supplier.supplier_id);
                const isSugg = suggested.has(sid);
                return `
            <button type="button" class="supplier-option${isSugg ? ' supplier-option-suggested' : ''}" data-supplier-id="${supplier.supplier_id}">
                <img src="${escapeHtml(getSupplierImageUrl(supplier.supplier_image))}" alt="" class="supplier-option-avatar" onerror="${supplierAvatarOnError}">
                <span class="supplier-option-name">${escapeHtml(supplier.supplier_name)}${
                    isSugg
                        ? '<span class="supplier-suggest-badge">Suggested</span>'
                        : ''
                }</span>
            </button>
        `;
            })
            .join('');
    }

    function renderCvItems() {
        if (!cvItemChips) return;
        if (state.items.length === 0) {
            cvItemChips.innerHTML = `<p class="item-chips-empty">${
                canvasserRegister
                    ? 'No canvass lines yet. The requester must add them before you can enter supplier prices.'
                    : 'No canvass items yet.'
            }</p>`;
            renderPreferredTable();
            return;
        }
        cvItemChips.innerHTML = state.items
            .map(
                (item, index) => {
                    const bm = [item.brand, item.model].filter((x) => x && String(x).trim());
                    const meta =
                        bm.length > 0
                            ? `<div class="item-chip-meta">${escapeHtml(bm.join(' · '))}</div>`
                            : '';
                    const removeBtn =
                        gsdReadonly || canvasserRegister
                            ? ''
                            : `<button type="button" class="remove-item-btn" data-cv-item-index="${index}" title="Remove item">
                    <i class="fas fa-times"></i>
                </button>`;
                    return `
            <div class="item-chip">
                <div class="item-chip-number">Item ${index + 1}</div>
                <div class="item-chip-name">${escapeHtml(item.name)}</div>
                ${formatRequisitionQtyHint(getItemQuantityMeta(index))}
                ${meta}
                <div class="item-chip-spec">${escapeHtml(item.specification || '—')}</div>
                ${removeBtn}
            </div>
        `;
                }
            )
            .join('');
        renderPreferredTable();
    }

    function canvassItemColumnTitles() {
        if (state.items.length === 0) {
            return ['ITEM 1'];
        }
        return state.items.map((item, index) => {
            const label = (item.name || '').trim() || `Item ${index + 1}`;
            return `ITEM ${index + 1} - ${label}`;
        });
    }

    function onSupplierTableChange() {
        state.hasUnsavedChanges = true;
    }

    function syncCanvassedSupplierMatrixVisibility() {
        const isRequesterView = Boolean(window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.isRequester);
        if (cvSupplierPickerLabel) {
            cvSupplierPickerLabel.hidden = isRequesterView;
        }
        const hasCanvassedData = state.selectedSuppliers.length > 0;
        if (cvCanvassedSupplierLabel) {
            cvCanvassedSupplierLabel.hidden = !hasCanvassedData;
        }
        if (cvCanvassedSupplierWrap) {
            cvCanvassedSupplierWrap.hidden = !hasCanvassedData;
        }
    }

    function renderSupplierTable() {
        if (!cvCanvassedCards) {
            return;
        }
        syncCanvassedSupplierMatrixVisibility();
        const canvasStatus = String((cachedCanvassApproval && cachedCanvassApproval.canvas_status) || '').trim().toLowerCase();
        const canvasserLocked = canvasserRegister && (canvasStatus === 'accept' || canvasStatus === 'reject');
        const isRequesterView = Boolean(window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.isRequester);
        const hideStructureActions = gsdReadonly || canvasserLocked || isRequesterView;
        const { showGsdSuggestUi, canChangeSuggested } = getGsdSuggestSelectionContext();

        if (state.selectedSuppliers.length === 0) {
            cvCanvassedCards.innerHTML = `<div class="empty-state">${
                canvasserRegister
                    ? 'No canvassed suppliers yet. Add suppliers and enter quoted prices here.'
                    : 'No canvassed supplier quotes yet. This section is filled in by the assigned canvasser.'
            }</div>`;
            return;
        }

        if (state.items.length === 0) {
            cvCanvassedCards.innerHTML = `<div class="empty-state">${
                canvasserRegister
                    ? 'Waiting for the requester to add canvass lines before you can quote prices.'
                    : 'Add canvass items first.'
            }</div>`;
            return;
        }

        cvCanvassedCards.innerHTML = state.selectedSuppliers
            .map((supplier, supplierIndex) => {
                const sidNum = Number(supplier.supplier_id);
                const avatarSrc = escapeHtml(getSupplierImageUrl(supplier.supplier_image));
                const roAttr = gsdReadonly || isRequesterView ? ' readonly' : '';
                const notesReadonly = hideStructureActions || isRequesterView;
                const headerBadge = buildCanvassedSupplierHeaderBadge(supplier);
                const supplierDetails = resolveCanvassedSupplierDetails(supplier);
                const locationLine = formatSupplierLocation(supplierDetails);
                const namePart = canvasserRegister
                    ? `<button type="button" class="supplier-table-contact-name-btn cv-canvas-contact-btn" data-cv-supplier-id="${escapeHtml(
                          String(supplier.supplier_id)
                      )}" aria-label="${escapeHtml(`View contact for ${supplier.supplier_name || 'supplier'}`)}">${escapeHtml(
                          supplier.supplier_name || ''
                      )}</button>`
                    : escapeHtml(supplier.supplier_name || '');
                const itemRows = state.items
                    .map((it, itemIndex) => {
                        const value = supplier.prices[itemIndex] ?? '';
                        const isSelectedForItem = isSuggestedForMatrix(itemIndex, sidNum, 'canvassed');
                        const pricePresent = value !== null && String(value).trim() !== '';
                        const radioName = `cvSuggestedCanvassedItem${itemIndex}`;
                        const canPickThisCell = showGsdSuggestUi && pricePresent;
                        const radioHtml = showGsdSuggestUi
                            ? `<label class="cv-suggested-radio-wrap" title="Suggested supplier for this item">
                                <input type="radio"
                                    class="cv-suggested-item-radio"
                                    name="${radioName}"
                                    value="${sidNum}"
                                    data-item-index="${itemIndex}"
                                    data-canvass-detail-id="${Number(it.canvass_detail_id || 0)}"
                                    data-selection-source="canvassed"
                                    ${isSelectedForItem ? 'checked' : ''}
                                    ${canPickThisCell ? '' : 'disabled'}>
                               </label>`
                            : '';
                        const selectedBadge =
                            gsdReadonly && isSelectedForItem ? '<span class="cv-gsd-suggested-badge">Suggested</span>' : '';
                        const clearBtn =
                            showGsdSuggestUi && isSelectedForItem
                                ? `<button type="button" class="cv-suggested-clear-btn" data-item-index="${itemIndex}" data-canvass-detail-id="${Number(it.canvass_detail_id || 0)}" title="Clear suggested supplier">Clear</button>`
                                : '';
                        return `<tr class="${isSelectedForItem ? 'cv-gsd-suggested-row' : ''}">
                            <td>${itemIndex + 1}</td>
                            <td>
                                <div class="cv-canvass-item-name-cell">
                                    ${escapeHtml(it.name || `Item ${itemIndex + 1}`)}
                                    ${formatRequisitionQtyHint(getItemQuantityMeta(itemIndex))}
                                </div>
                            </td>
                            <td>
                                <div class="cv-pref-price-wrap cv-canvas-price-wrap">
                                    ${radioHtml}
                                    <span class="cv-pref-peso">PHP</span>
                                    <input type="number" min="0" step="0.01" class="supplier-price-input" data-cv-supplier-index="${supplierIndex}" data-cv-item-index="${itemIndex}" value="${escapeHtml(String(value))}" placeholder="0.00"${roAttr}>
                                    ${selectedBadge}
                                    ${clearBtn}
                                </div>
                            </td>
                        </tr>`;
                    })
                    .join('');
                return `<article class="cv-pref-card cv-canvas-card" data-cv-supplier-card-index="${supplierIndex}">
                    <div class="cv-pref-card-head">
                        <div class="cv-pref-card-id">
                            <img src="${avatarSrc}" alt="" class="cv-pref-avatar cv-pref-avatar-img" onerror="${supplierAvatarOnError}">
                            <div>
                                <div class="cv-pref-name cv-canvass-head-name-row">${namePart}<span class="cv-canvass-header-badge">${headerBadge}</span></div>
                                <div class="cv-pref-contact">${escapeHtml(supplierDetails.contact_person || 'No contact person')}</div>
                                <div class="cv-pref-tin"><span class="cv-pref-tin-label">TIN</span>${escapeHtml(formatSupplierTinDisplay(supplierDetails.tin))}</div>
                                ${locationLine ? `<div class="cv-pref-location"><i class="fas fa-location-dot" aria-hidden="true"></i> ${escapeHtml(locationLine)}</div>` : ''}
                            </div>
                        </div>
                        ${
                            hideStructureActions
                                ? ''
                                : `<div class="cv-pref-card-actions">
                            <button type="button" class="cv-pref-remove-btn cv-canvas-remove-btn" data-cv-supplier-index="${supplierIndex}" title="Remove supplier">×</button>
                        </div>`
                        }
                    </div>
                    <div class="cv-pref-card-body">
                        <table class="cv-pref-items-table" aria-label="Canvassed supplier quotes">
                            <thead><tr><th>#</th><th>Item name</th><th>Quoted price</th></tr></thead>
                            <tbody>${itemRows || '<tr><td colspan="3" class="empty-state">Add canvass items first.</td></tr>'}</tbody>
                        </table>
                        ${buildCanvassedOptionalNotesSection(supplier, supplierIndex, notesReadonly)}
                    </div>
                </article>`;
            })
            .join('');
        emitCanvassPricingUpdate();
    }

    function removeItemAt(index) {
        state.items.splice(index, 1);
        state.selectedSuppliers.forEach((supplier) => {
            const newPrices = {};
            state.items.forEach((_, newIdx) => {
                const oldIdx = newIdx >= index ? newIdx + 1 : newIdx;
                if (supplier.prices[oldIdx] !== undefined) {
                    newPrices[newIdx] = supplier.prices[oldIdx];
                }
            });
            supplier.prices = newPrices;
        });
    }

    function removeSupplierAt(index) {
        state.selectedSuppliers.splice(index, 1);
    }

    function renderRequestedRequisitionItems(lines) {
        const body = document.getElementById('cvRequestedItemsTableBody');
        if (!body) return;
        const arr = Array.isArray(lines) ? lines : [];
        if (arr.length === 0) {
            body.innerHTML =
                '<tr class="requested-items-empty"><td colspan="4">No lines were recorded on the requisition.</td></tr>';
            return;
        }
        body.innerHTML = arr
            .map((row, i) => {
                const name = escapeHtml(row.item_name || '—');
                const brand = String(row.item_brand || '').trim();
                const cat = String(row.item_category || '').trim();
                const metaParts = [brand, cat].filter(Boolean);
                const metaRow = metaParts.length
                    ? `<div class="cv-req-line-meta">${escapeHtml(metaParts.join(' · '))}</div>`
                    : '';
                const qty = row.quantity != null ? row.quantity : 1;
                const unit = escapeHtml(String(row.unit_type || 'unit'));
                return `<tr>
                <td class="requested-items-table-num">${i + 1}</td>
                <td class="requested-items-table-item">${name}${metaRow}</td>
                <td class="requested-items-table-qty">${escapeHtml(String(qty))}</td>
                <td class="requested-items-table-unit">${unit}</td>
            </tr>`;
            })
            .join('');
    }

    function applyGsdReadonlyUi() {
        if (!gsdReadonly) {
            return;
        }
        [cvAddItemBtn, cvSupplierDropdownBtn, cvAddSupplierBtn].forEach((el) => {
            if (el) {
                el.disabled = true;
                el.hidden = true;
            }
        });
        if (cvItemName) {
            cvItemName.disabled = true;
        }
        if (cvItemBrand) {
            cvItemBrand.disabled = true;
        }
        if (cvItemModel) {
            cvItemModel.disabled = true;
        }
        if (cvItemSpecs) {
            cvItemSpecs.disabled = true;
        }
        if (cvCanvassItemsHintWrap) {
            cvCanvassItemsHintWrap.hidden = true;
        }
        if (cvCanvassHintShow) {
            cvCanvassHintShow.hidden = true;
        }
    }

    async function loadForm() {
        try {
            const res = await fetch(`${api}?action=get&request_id=${encodeURIComponent(String(requestId))}`, {
                credentials: 'include',
            });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not load.', 'error');
                return;
            }

            const h = data.header || {};
            const deptEl = document.getElementById('cvOfficeDisplay');
            const facEl = document.getElementById('cvFacilityDisplay');
            const dateEl = document.getElementById('cvRequestDate');
            const purposeEl = document.getElementById('cvPurpose');
            if (deptEl) deptEl.value = h.office_name || '—';
            if (facEl) facEl.value = h.facility_label || '—';
            if (dateEl) dateEl.value = h.request_date || '—';
            if (purposeEl) purposeEl.value = h.purpose || '';

            renderRequestedRequisitionItems(data.requisition_requested_items);
            state.requestedLines = Array.isArray(data.requisition_requested_items)
                ? data.requisition_requested_items.map((row) => ({
                      requisition_line_id: Number(row.requisition_line_id || 0),
                      quantity: Math.max(1, Number(row.quantity) || 1),
                      unit_type: String(row.unit_type || 'unit'),
                  }))
                : [];

            state.catalogItems = Array.isArray(data.item_catalog) ? data.item_catalog : [];
            fillCvItemDatalist(state.catalogItems);

            state.availableSuppliers = Array.isArray(data.supplier_catalog) ? data.supplier_catalog : [];

            state.items = (Array.isArray(data.items) ? data.items : []).map((it) => ({
                name: String(it.item_name || ''),
                brand: String(it.brand || ''),
                model: String(it.model || ''),
                specification: String(it.specification || ''),
                canvass_detail_id: Number(it.canvass_detail_id || 0),
                requisition_line_id: it.requisition_line_id != null ? it.requisition_line_id : null,
                suggested_supplier_ids: Array.isArray(it.suggested_supplier_ids)
                    ? it.suggested_supplier_ids
                          .map((x) => Number(x))
                          .filter((n) => !Number.isNaN(n) && n > 0)
                    : [],
                selected_supplier_id: Number(it.selected_supplier_id || 0),
                selected_supplier_source: it.selected_supplier_source || null,
            }));

            state.suggestedByItem = {};
            state.items.forEach((it, idx) => {
                const sid = Number(it.selected_supplier_id || 0);
                if (sid > 0) {
                    const src =
                        it.selected_supplier_source === 'preferred' ||
                        it.selected_supplier_source === 'canvassed'
                            ? it.selected_supplier_source
                            : null;
                    state.suggestedByItem[idx] = { supplierId: sid, source: src };
                }
            });

            state.selectedSuppliers = (Array.isArray(data.suppliers) ? data.suppliers : []).map((s) => ({
                supplier_id: s.supplier_id,
                supplier_name: s.supplier_name,
                supplier_image: s.supplier_image,
                prices: normalizePrices(s.prices),
                photos: normalizePrices(s.photos),
                benefits: s.benefits != null ? String(s.benefits) : '',
                discounts: Array.isArray(s.discounts)
                    ? s.discounts.map((d) => ({
                          label: d.label != null ? String(d.label) : '',
                          discount_percent:
                              d.discount_percent != null && d.discount_percent !== ''
                                  ? String(d.discount_percent)
                                  : '',
                      }))
                    : [],
            }));
            state.preferredSuppliers = Array.isArray(data.preferred_suppliers) ? (data.preferred_suppliers.map((s) => ({
                supplier_id: s.supplier_id,
                supplier_name: s.supplier_name,
                contact_person: s.contact_person || '',
                phone_number: s.phone_number || '',
                email: s.email || '',
                shop_url: s.shop_url || '',
                supplier_image: s.supplier_image || '',
            }))) : [];

            setSupplierPickerHighlight(null);
            renderCvItems();
            renderSupplierTable();
            renderSupplierDropdown();
            await loadPreferredSuppliers();
            state.canvassedSupplierCount = state.selectedSuppliers.length;
            applyCanvassApproval(data.approval);
            applyGsdReadonlyUi();
            renderPreferredTable();
            if (typeof window.__imrmsCvReviewerAfterLoad === 'function') {
                try {
                    window.__imrmsCvReviewerAfterLoad();
                } catch {
                    /* no-op */
                }
            }
        } catch {
            showToast('Network error.', 'error');
        }
    }

    async function persistCanvassToServer(submissionMode = 'draft') {
        if (state.items.length === 0) {
            return {
                ok: false,
                message: canvasserRegister
                    ? 'The requester has not added canvass lines yet. Nothing to quote.'
                    : 'Add at least one canvass item.',
            };
        }

        await uploadPendingPreferredPhotos();

        const body = new URLSearchParams();
        body.set('action', 'save');
        body.set('request_id', String(requestId));
        body.set('items', JSON.stringify(buildItemsPayloadForSave()));
        body.set('suppliers', JSON.stringify(buildSuppliersPayloadForSave()));
        body.set('preferred_suppliers', JSON.stringify(state.preferredSuppliers || []));
        if (!canvasserRegister) {
            body.set('preferred_quotes', JSON.stringify(buildPreferredQuotesPayload()));
        }
        body.set('submission_mode', submissionMode);

        const res = await fetch(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'include',
        });
        let data;
        try {
            data = await res.json();
        } catch {
            return { ok: false, message: 'Invalid response from server.' };
        }
        if (!data.success) {
            return {
                ok: false,
                message: data.message || 'Save failed.',
                code: data.code || null,
            };
        }
        return { ok: true, message: data.message || 'Saved.' };
    }

    async function postCanvasApproval(canvasStatus) {
        const body = new URLSearchParams();
        body.set('action', 'set_canvas_approval');
        body.set('request_id', String(requestId));
        body.set('canvas_status', canvasStatus);
        const res = await fetch(CANVASSER_REQUESTS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'include',
        });
        return res.json();
    }

    function applyCanvassOptimisticFromApi(data) {
        if (!data || typeof data !== 'object') {
            return;
        }
        const base =
            cachedCanvassApproval && typeof cachedCanvassApproval === 'object'
                ? { ...cachedCanvassApproval }
                : {};
        if (data.canvas_status != null) {
            base.canvas_status = data.canvas_status;
        }
        if (data.canvassed_by !== undefined) {
            base.canvassed_by = data.canvassed_by;
        }
        applyCanvassApproval(base);
    }

    function setCanvasserActionBusy(busy) {
        if (!canvasserRegister) {
            return;
        }
        const undo = document.getElementById('cvCanvasserUndoBtn');
        if (busy) {
            if (cvSaveDraftBtn) {
                cvSaveDraftBtn.disabled = true;
            }
            if (cvCompleteCanvassBtn) {
                cvCompleteCanvassBtn.disabled = true;
            }
            if (undo) {
                undo.disabled = true;
            }
        } else {
            syncCanvasserToolbar();
        }
    }

    async function saveDraft() {
        if (!canvasserRegister) {
            return;
        }
        setCanvasserActionBusy(true);
        try {
            const result = await persistCanvassToServer('draft');
            if (!result.ok) {
                showToast(result.message || 'Save failed.', 'error');
                setCanvasserActionBusy(false);
                return;
            }
            showToast('✓ Draft saved successfully.');
            state.hasUnsavedChanges = false;
            setTimeout(() => {
                window.history.back();
            }, 800);
        } catch {
            showToast('Network error.', 'error');
            setCanvasserActionBusy(false);
        }
    }

    async function completeCanvassing() {
        if (!canvasserRegister) {
            return;
        }
        if (!requestId) {
            showToast('Missing request.', 'error');
            return;
        }
        const noQuoteSupplier = state.selectedSuppliers.find((s) => !supplierRowHasQuotedPrice(s));
        if (noQuoteSupplier) {
            showToast('Each supplier must have at least one quoted price. Remove supplier rows with no quote before completing.', 'error');
            return;
        }
        const ok = await showConfirmModal(
            'Save your supplier quotes and mark canvassing as complete? The canvass step will be approved and the request status will be set to Ongoing.'
        );
        if (!ok) {
            return;
        }
        setCanvasserActionBusy(true);
        try {
            // First save the canvass as submitted
            let persistResult = await persistCanvassToServer('submitted');
            if (!persistResult.ok) {
                if (persistResult.code === 'canvas_finalized') {
                    persistResult = { ok: true };
                } else {
                    showToast(persistResult.message || 'Save failed.', 'error');
                    return;
                }
            }
            
            // Then mark canvass as accepted through approval API
            const data = await postCanvasApproval('accept');
            if (!data.success) {
                showToast(
                    data.message ||
                        'Could not record canvass completion. If your quotes were saved, try Complete again.',
                    'error'
                );
                return;
            }
            applyCanvassOptimisticFromApi(data);
            showToast(data.message || 'Canvassing complete.');
            await loadForm();
        } catch {
            showToast('Network error.', 'error');
        } finally {
            setCanvasserActionBusy(false);
        }
    }

    async function undoCanvasserCompletion() {
        if (!canvasserRegister || !requestId) {
            return;
        }
        const ok = await showConfirmModal(
            'Undo canvass completion? You can edit supplier quotes again and the request will return to Pending until you complete canvassing.'
        );
        if (!ok) {
            return;
        }
        setCanvasserActionBusy(true);
        try {
            const data = await postCanvasApproval('pending');
            if (!data.success) {
                showToast(data.message || 'Undo failed.', 'error');
                return;
            }
            applyCanvassOptimisticFromApi(data);
            showToast(data.message || 'Canvass step reopened.');
            await loadForm();
        } catch {
            showToast('Network error.', 'error');
        } finally {
            setCanvasserActionBusy(false);
        }
    }

    async function saveForm() {
        if (canvasserRegister) {
            return;
        }
        if (state.items.length === 0) {
            showToast('Add at least one canvass item.', 'error');
            return;
        }
        const ok = await showConfirmModal(
            'Submit your canvass form? After submitting, verifiers can view your preferred suppliers and quotes.'
        );
        if (!ok) return;

        if (state.autoSaveTimer) {
            clearRequesterAutosaveTimer();
        }
        if (cvSaveBtn) cvSaveBtn.disabled = true;
        try {
            const result = await persistCanvassToServer('submitted');
            if (!result.ok) {
                showToast(result.message || 'Submit failed.', 'error');
                return;
            }
            showToast('✓ Canvass form submitted');
            state.hasUnsavedChanges = false;
            await loadForm();
        } catch {
            showToast('Network error.', 'error');
        } finally {
            if (cvSaveBtn) cvSaveBtn.disabled = false;
        }
    }

    if (cvAddItemBtn && !canvasserRegister) {
        cvAddItemBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const name = (cvItemName && cvItemName.value.trim()) || '';
            const brand = (cvItemBrand && cvItemBrand.value.trim()) || '';
            const model = (cvItemModel && cvItemModel.value.trim()) || '';
            const spec = (cvItemSpecs && cvItemSpecs.value.trim()) || '';
            if (!name) {
                showToast('Enter an item name.', 'error');
                return;
            }
            hideCvSuggestList();
            state.items.push({
                name,
                brand,
                model,
                specification: spec,
                requisition_line_id: null,
                suggested_supplier_ids: [],
            });
            state.hasUnsavedChanges = true;
            const newIdx = state.items.length - 1;
            state.selectedSuppliers.forEach((s) => {
                if (s.prices[newIdx] === undefined) {
                    s.prices[newIdx] = '';
                }
            });
            if (cvItemName) cvItemName.value = '';
            if (cvItemBrand) cvItemBrand.value = '';
            if (cvItemModel) cvItemModel.value = '';
            if (cvItemSpecs) cvItemSpecs.value = '';
            renderCvItems();
            renderSupplierTable();
            scheduleRequesterAutosave();
            (async () => {
                try {
                    const qs = new URLSearchParams({
                        action: 'suggest_suppliers',
                        request_id: String(requestId),
                        item_name: name,
                        requisition_line_id: '',
                    });
                    const res = await fetch(`${api}?${qs.toString()}`, { credentials: 'include' });
                    const payload = await res.json();
                    if (
                        payload.success &&
                        Array.isArray(payload.supplier_ids) &&
                        state.items[newIdx]
                    ) {
                        state.items[newIdx].suggested_supplier_ids = payload.supplier_ids
                            .map((x) => Number(x))
                            .filter((n) => !Number.isNaN(n) && n > 0);
                        renderSupplierDropdown();
                    }
                } catch {
                    /* ignore */
                }
            })();
        });
    }

    if (cvItemName && !canvasserRegister) {
        cvItemName.addEventListener('input', () => {
            scheduleCvItemSuggest();
        });
        cvItemName.addEventListener('change', () => {
            applyCatalogMatchExactName();
        });
        cvItemName.addEventListener('focus', () => {
            scheduleCvItemSuggest();
        });
        cvItemName.addEventListener('blur', () => {
            setTimeout(() => hideCvSuggestList(), 180);
        });
    }

    if (cvItemSuggestList && !canvasserRegister) {
        cvItemSuggestList.addEventListener('mousedown', (e) => {
            e.preventDefault();
        });
        cvItemSuggestList.addEventListener('click', (e) => {
            const btn = e.target.closest('.cv-item-suggest-btn');
            if (!btn) return;
            const idx = parseInt(btn.getAttribute('data-suggest-index') || '-1', 10);
            const row = state.lastSuggestItems[idx];
            if (!row) return;
            if (cvItemName) cvItemName.value = String(row.item_name || '');
            if (cvItemBrand) cvItemBrand.value = row.brand != null ? String(row.brand) : '';
            if (cvItemModel) cvItemModel.value = row.model != null ? String(row.model) : '';
            hideCvSuggestList();
        });
    }

    if (cvItemChips) {
        cvItemChips.addEventListener('click', (e) => {
            if (gsdReadonly || canvasserRegister) {
                return;
            }
            const btn = e.target.closest('.remove-item-btn');
            if (!btn) return;
            const idx = parseInt(btn.getAttribute('data-cv-item-index'), 10);
            if (Number.isNaN(idx)) return;
            removeItemAt(idx);
            renderCvItems();
            renderSupplierTable();
            renderSupplierDropdown();
            scheduleRequesterAutosave();
        });
    }

    if (cvSupplierDropdownBtn && cvSupplierDropdown) {
        cvSupplierDropdownBtn.addEventListener('click', () => {
            cvSupplierDropdown.classList.toggle('open');
        });
    }

    if (cvSupplierDropdownList && cvSupplierDropdown) {
        cvSupplierDropdownList.addEventListener('click', (e) => {
            const opt = e.target.closest('.supplier-option');
            if (!opt) return;
            const sid = opt.getAttribute('data-supplier-id');
            setSupplierPickerHighlight(sid);
            cvSupplierDropdown.classList.remove('open');
        });
    }

    document.addEventListener('click', (e) => {
        if (cvSupplierDropdown && !cvSupplierDropdown.contains(e.target)) {
            cvSupplierDropdown.classList.remove('open');
        }
    });

    if (cvAddSupplierBtn) {
        cvAddSupplierBtn.addEventListener('click', () => {
            const id = state.selectedSupplierId ? Number(state.selectedSupplierId) : 0;
            if (!id || Number.isNaN(id)) {
                showToast('Select a supplier first.', 'error');
                return;
            }
            if (state.selectedSuppliers.some((s) => Number(s.supplier_id) === id)) {
                showToast('That supplier is already in the matrix.', 'error');
                return;
            }
            const full = state.availableSuppliers.find((x) => Number(x.supplier_id) === id);
            if (!full) {
                showToast('Supplier not found in catalog.', 'error');
                return;
            }
            const prices = {};
            state.items.forEach((_, i) => {
                prices[i] = '';
            });
            state.selectedSuppliers.push({
                supplier_id: full.supplier_id,
                supplier_name: full.supplier_name,
                supplier_image: full.supplier_image,
                prices,
                benefits: '',
                discounts: [],
            });
            renderSupplierTable();
            scheduleRequesterAutosave();
        });
    }

    if (cvCanvassedCards) {
        cvCanvassedCards.addEventListener('change', () => {
            onSupplierTableChange();
        });
        cvCanvassedCards.addEventListener('click', (e) => {
            const contactBtn = e.target.closest('.cv-canvas-contact-btn');
            if (contactBtn) {
                const sid = contactBtn.getAttribute('data-cv-supplier-id');
                if (sid) {
                    setSupplierPickerHighlight(sid);
                }
                return;
            }
            const btn = e.target.closest('.cv-canvas-remove-btn');
            if (!btn) {
                return;
            }
            if (gsdReadonly) {
                return;
            }
            const idx = parseInt(btn.getAttribute('data-cv-supplier-index'), 10);
            if (Number.isNaN(idx)) {
                return;
            }
            removeSupplierAt(idx);
            renderSupplierTable();
            scheduleRequesterAutosave();
        });

        cvCanvassedCards.addEventListener('input', (e) => {
            const priceInp = e.target.closest('.supplier-price-input');
            if (priceInp) {
                const si = parseInt(priceInp.getAttribute('data-cv-supplier-index'), 10);
                const ii = parseInt(priceInp.getAttribute('data-cv-item-index'), 10);
                if (Number.isNaN(si) || Number.isNaN(ii)) {
                    return;
                }
                const sup = state.selectedSuppliers[si];
                if (!sup) {
                    return;
                }
                sup.prices[ii] = priceInp.value;
                if (!canvasserRegister) {
                    scheduleRequesterAutosave();
                } else {
                    state.hasUnsavedChanges = true;
                }
                return;
            }

            const benefitsInp = e.target.closest('.cv-canvass-benefits-input');
            if (benefitsInp) {
                const si = parseInt(benefitsInp.getAttribute('data-cv-supplier-index'), 10);
                if (Number.isNaN(si)) {
                    return;
                }
                const sup = state.selectedSuppliers[si];
                if (!sup) {
                    return;
                }
                sup.benefits = benefitsInp.value;
                refreshCanvassedSupplierBadge(si);
                if (!canvasserRegister) {
                    scheduleRequesterAutosave();
                } else {
                    state.hasUnsavedChanges = true;
                }
                return;
            }

            const discountLabelInp = e.target.closest('.cv-canvass-discount-label-input');
            if (discountLabelInp) {
                const si = parseInt(discountLabelInp.getAttribute('data-cv-supplier-index'), 10);
                const di = parseInt(discountLabelInp.getAttribute('data-cv-discount-index'), 10);
                if (Number.isNaN(si) || Number.isNaN(di)) {
                    return;
                }
                const sup = state.selectedSuppliers[si];
                if (!sup || !Array.isArray(sup.discounts) || !sup.discounts[di]) {
                    return;
                }
                sup.discounts[di].label = discountLabelInp.value;
                refreshCanvassedSupplierBadge(si);
                if (!canvasserRegister) {
                    scheduleRequesterAutosave();
                } else {
                    state.hasUnsavedChanges = true;
                }
                return;
            }

            const discountPctInp = e.target.closest('.cv-canvass-discount-pct-input');
            if (discountPctInp) {
                const si = parseInt(discountPctInp.getAttribute('data-cv-supplier-index'), 10);
                const di = parseInt(discountPctInp.getAttribute('data-cv-discount-index'), 10);
                if (Number.isNaN(si) || Number.isNaN(di)) {
                    return;
                }
                const sup = state.selectedSuppliers[si];
                if (!sup || !Array.isArray(sup.discounts) || !sup.discounts[di]) {
                    return;
                }
                sup.discounts[di].discount_percent = discountPctInp.value.trim();
                refreshCanvassedSupplierBadge(si);
                emitCanvassPricingUpdate();
                if (!canvasserRegister) {
                    scheduleRequesterAutosave();
                } else {
                    state.hasUnsavedChanges = true;
                }
            }
        });

        bindSuggestedSupplierSelectionHandlers(cvCanvassedCards);

        cvCanvassedCards.addEventListener('click', (e) => {
            const addDiscountBtn = e.target.closest('.cv-canvass-add-discount-btn');
            if (addDiscountBtn) {
                const si = parseInt(addDiscountBtn.getAttribute('data-cv-supplier-index'), 10);
                if (Number.isNaN(si)) {
                    return;
                }
                const sup = state.selectedSuppliers[si];
                if (!sup) {
                    return;
                }
                ensureSupplierDiscountsArray(sup);
                sup.discounts.push({ label: '', discount_percent: '' });
                renderSupplierTable();
                if (!canvasserRegister) {
                    scheduleRequesterAutosave();
                } else {
                    state.hasUnsavedChanges = true;
                }
                return;
            }

            const removeDiscountBtn = e.target.closest('.cv-canvass-discount-remove');
            if (removeDiscountBtn) {
                const si = parseInt(removeDiscountBtn.getAttribute('data-cv-supplier-index'), 10);
                const di = parseInt(removeDiscountBtn.getAttribute('data-cv-discount-index'), 10);
                if (Number.isNaN(si) || Number.isNaN(di)) {
                    return;
                }
                const sup = state.selectedSuppliers[si];
                if (!sup || !Array.isArray(sup.discounts)) {
                    return;
                }
                sup.discounts.splice(di, 1);
                renderSupplierTable();
                refreshCanvassedSupplierBadge(si);
                emitCanvassPricingUpdate();
                if (!canvasserRegister) {
                    scheduleRequesterAutosave();
                } else {
                    state.hasUnsavedChanges = true;
                }
                return;
            }

            const clearBtn = e.target.closest('.cv-suggested-clear-btn');
            if (!clearBtn || !gsdReadonly) {
                return;
            }
            const itemIndex = parseInt(clearBtn.getAttribute('data-item-index') || '-1', 10);
            if (Number.isNaN(itemIndex) || itemIndex < 0) {
                return;
            }
            delete state.suggestedByItem[itemIndex];
            renderSupplierTable();
            renderPreferredTable();
            if (typeof window.__imrmsClearSuggestedSupplierItem === 'function') {
                window.__imrmsClearSuggestedSupplierItem(
                    parseInt(clearBtn.getAttribute('data-canvass-detail-id') || '0', 10)
                );
            }
        });
    }

    if (cvPreferredCards) {
        bindSuggestedSupplierSelectionHandlers(cvPreferredCards);
    }

    if (cvSaveBtn) {
        cvSaveBtn.addEventListener('click', () => void saveForm());
    }
    if (cvSaveDraftBtn) {
    cvSaveDraftBtn.addEventListener('click', () => {
        if (canvasserRegister) {
            void saveDraft();
        } else {
            // Requester "Save draft" — same as saveForm() but skip the confirm dialog
            if (state.items.length === 0) {
                showToast('Add at least one canvass item.', 'error');
                return;
            }
            clearRequesterAutosaveTimer();
            cvSaveDraftBtn.disabled = true;
            persistCanvassToServer('draft')
                .then((result) => {
                    if (!result.ok) {
                        showToast(result.message || 'Save failed.', 'error');
                        return;
                    }
                    showToast('✓ Changes saved as draft');
                    state.hasUnsavedChanges = false;
                })
                .catch(() => showToast('Network error.', 'error'))
                .finally(() => { cvSaveDraftBtn.disabled = false; });
        }
    });
}
    if (cvCompleteCanvassBtn) {
        cvCompleteCanvassBtn.addEventListener('click', () => void completeCanvassing());
    }
    const cvCanvasserUndoBtn = document.getElementById('cvCanvasserUndoBtn');
    if (cvCanvasserUndoBtn) {
        cvCanvasserUndoBtn.addEventListener('click', () => void undoCanvasserCompletion());
    }

    if (canvasserRegister) {
        const cvClearSupplierContactBtn = document.getElementById('cvClearSupplierContactBtn');
        if (cvClearSupplierContactBtn && !cvClearSupplierContactBtn.dataset.cvBound) {
            cvClearSupplierContactBtn.dataset.cvBound = '1';
            cvClearSupplierContactBtn.addEventListener('click', () => {
                setSupplierPickerHighlight(null);
                if (cvSupplierDropdown) {
                    cvSupplierDropdown.classList.remove('open');
                }
            });
        }
        const cvSupplierModal = document.getElementById('canvasserNewSupplierModal');
        if (cvSupplierModal && cvSupplierModal.dataset.cvSupplierUiBound !== '1') {
            cvSupplierModal.dataset.cvSupplierUiBound = '1';
            if (cvRegisterSupplierBtn) {
                cvRegisterSupplierBtn.addEventListener('click', () => openCanvasserSupplierModal());
            }
            const submitNewSup = document.getElementById('canvasserNewSupplierSubmit');
            if (submitNewSup) {
                submitNewSup.addEventListener('click', () => void submitCvCanvasserNewSupplier());
            }
            const newSupForm = document.getElementById('canvasserNewSupplierForm');
            if (newSupForm) {
                newSupForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    void submitCvCanvasserNewSupplier();
                });
            }
            cvSupplierModal.querySelectorAll('[data-close-canvasser-supplier-modal]').forEach((el) => {
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    closeCanvasserSupplierModal();
                });
            });
            const newSupTinInput = document.getElementById('canvasserNewSupplierTin');
            if (newSupTinInput) {
                newSupTinInput.addEventListener('input', () => {
                    const formatted = formatTinInputValue(newSupTinInput.value);
                    if (newSupTinInput.value !== formatted) {
                        newSupTinInput.value = formatted;
                    }
                });
            }
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && cvSupplierModal.style.display === 'flex') {
                    closeCanvasserSupplierModal();
                }
            });
        }
    }

    if (cvSupplierDropdownPreview) {
        cvSupplierDropdownPreview.addEventListener('error', () => {
            cvSupplierDropdownPreview.onerror = null;
            cvSupplierDropdownPreview.src = getSupplierImageUrl('');
        });
    }

    if (cvSuggestedSupplierNoticeDismiss && cvSuggestedSupplierNotice) {
        cvSuggestedSupplierNoticeDismiss.addEventListener('click', () => {
            cvSuggestedNoticeDismissed = true;
            cvSuggestedSupplierNotice.hidden = true;
        });
    }

    window.addEventListener('beforeunload', () => {
        if (state.autoSaveTimer) {
            clearRequesterAutosaveTimer();
            flushRequesterAutosave();
        }
    });

    // Preferred supplier UI bindings and requester view adjustments
    (function bindPreferredSupplierUi() {
        const prefCfg = window.CWIRMS_PREF_SUP || null;
        const isRequester = Boolean(prefCfg && prefCfg.isRequester);
        const editable = Boolean(prefCfg && prefCfg.editable);
        if (isRequester) {
            if (cvSupplierDropdown) cvSupplierDropdown.style.display = 'none';
            if (cvAddSupplierBtn) cvAddSupplierBtn.style.display = 'none';
            if (cvRegisterSupplierBtn) cvRegisterSupplierBtn.style.display = 'none';
        }

        const quoteLightbox = document.getElementById('cvQuotePhotoLightbox');
        const quoteLightboxClose = document.getElementById('cvQuotePhotoLightboxClose');
        if (quoteLightbox) {
            quoteLightbox.addEventListener('click', (e) => {
                if (e.target === quoteLightbox) {
                    closeCvQuotePhotoLightbox();
                }
            });
        }
        if (quoteLightboxClose) {
            quoteLightboxClose.addEventListener('click', () => closeCvQuotePhotoLightbox());
        }
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') {
                return;
            }
            const lb = document.getElementById('cvQuotePhotoLightbox');
            if (lb && !lb.classList.contains('hidden')) {
                closeCvQuotePhotoLightbox();
            }
        });

        if (cvPreferredCards) {
            cvPreferredCards.addEventListener('click', (e) => {
                const viewPhotoBtn = e.target.closest('.cv-pref-photo-view-btn');
                if (viewPhotoBtn) {
                    const src = viewPhotoBtn.getAttribute('data-photo-url') || '';
                    if (src) {
                        openCvQuotePhotoLightbox(src);
                    }
                    return;
                }
                const clearBtn = e.target.closest('.cv-suggested-clear-btn');
                if (clearBtn && gsdReadonly) {
                    e.preventDefault();
                    const itemIndex = parseInt(clearBtn.getAttribute('data-item-index') || '-1', 10);
                    if (!Number.isNaN(itemIndex) && itemIndex >= 0) {
                        delete state.suggestedByItem[itemIndex];
                        renderSupplierTable();
                        renderPreferredTable();
                        if (typeof window.__imrmsClearSuggestedSupplierItem === 'function') {
                            window.__imrmsClearSuggestedSupplierItem(
                                parseInt(clearBtn.getAttribute('data-canvass-detail-id') || '0', 10)
                            );
                        }
                    }
                }
            });
        }

        if (!editable) {
            return;
        }
        if (cvOpenAddPreferredBtn) {
            cvOpenAddPreferredBtn.addEventListener('click', () => openPrefSupModal('add', null));
        }
        const saveBtn = document.getElementById('cvPrefSupModalSave');
        const cancelBtn = document.getElementById('cvPrefSupModalCancel');
        const backdrop = document.getElementById('cvPrefSupModalBackdrop');
        const prefTinInput = document.getElementById('cvPrefSupTin');
        if (prefTinInput) {
            prefTinInput.addEventListener('input', () => {
                const formatted = formatTinInputValue(prefTinInput.value);
                if (prefTinInput.value !== formatted) {
                    prefTinInput.value = formatted;
                }
            });
        }
        if (saveBtn) saveBtn.addEventListener('click', () => void savePrefSupModal());
        if (cancelBtn) cancelBtn.addEventListener('click', () => closePrefSupModal());
        if (backdrop) backdrop.addEventListener('click', () => closePrefSupModal());
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('cvPrefSupModal') && document.getElementById('cvPrefSupModal').style.display === 'flex') {
                closePrefSupModal();
            }
        });
        if (cvPreferredSection) {
            cvPreferredSection.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.cv-pref-edit-btn');
                const removeBtn = e.target.closest('.cv-pref-remove-btn');
                if (editBtn) {
                    const sid = editBtn.getAttribute('data-pref-id');
                    const existing = state.preferredSuppliers.find((s) => String(s.supplier_id) === String(sid));
                    openPrefSupModal('edit', existing || null);
                }
                if (removeBtn) {
                    const sid = removeBtn.getAttribute('data-pref-id');
                    if (sid) void removePrefSup(sid);
                }
                const addMat = e.target.closest('.cv-pref-addtomatrix-btn');
                if (addMat) {
                    const pid = addMat.getAttribute('data-pref-id');
                    if (pid) addPreferredToMatrix(pid);
                }
                const syncBtn = e.target.closest('.cv-pref-sync-btn');
                if (syncBtn) {
                    const sid = syncBtn.getAttribute('data-pref-id');
                    if (sid) syncPreferredSupplierPrices(sid);
                }
            });

            if (cvPreferredCards) {
                cvPreferredCards.addEventListener('input', (e) => {
                    const input = e.target.closest('.cv-preferred-price-input');
                    if (!input) return;
                    const sid = input.getAttribute('data-pref-supplier-id');
                    const itemIndex = Number(input.getAttribute('data-cv-item-index') || '0');
                    if (!sid || Number.isNaN(itemIndex)) return;
                    setPreferredDraftPrice(sid, itemIndex, input.value);
                });
                cvPreferredCards.addEventListener('change', (e) => {
                    const fileInput = e.target.closest('.cv-pref-photo-input');
                    if (!fileInput) return;
                    const sid = Number(fileInput.getAttribute('data-pref-supplier-id') || '0');
                    const itemIndex = Number(fileInput.getAttribute('data-cv-item-index') || '0');
                    const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                    if (!sid || Number.isNaN(itemIndex) || !file) return;
                    const key = preferredPhotoKey(sid, itemIndex);
                    const existing = state.preferredQuotePhotos[key] || {};
                    if (existing.preview_url && String(existing.preview_url).startsWith('blob:')) {
                        URL.revokeObjectURL(existing.preview_url);
                    }
                    const previewUrl = URL.createObjectURL(file);
                    state.preferredQuotePhotos[key] = {
                        file,
                        preview_url: previewUrl,
                        url: existing.url || '',
                    };
                    state.hasUnsavedChanges = true;
                    renderPreferredTable();
                    scheduleRequesterAutosave();
                });
                cvPreferredCards.addEventListener('click', (e) => {
                    const addItemBtn = e.target.closest('.cv-pref-add-item-btn');
                    if (addItemBtn) {
                        const sid = Number(addItemBtn.getAttribute('data-pref-supplier-id') || '0');
                        const card = addItemBtn.closest('.cv-pref-card');
                        const select = card ? card.querySelector('.cv-pref-add-item-select') : null;
                        const itemIndex = select ? Number(select.value) : NaN;
                        if (!sid || Number.isNaN(itemIndex) || itemIndex < 0) {
                            showToast('Select an item to add.', 'error');
                            return;
                        }
                        if (addPreferredSupplierItem(sid, itemIndex)) {
                            renderPreferredTable();
                        }
                        return;
                    }
                    const removeItemBtn = e.target.closest('.cv-pref-remove-item-btn');
                    if (removeItemBtn) {
                        const sid = Number(removeItemBtn.getAttribute('data-pref-supplier-id') || '0');
                        const itemIndex = Number(removeItemBtn.getAttribute('data-cv-item-index') || '0');
                        if (!sid || Number.isNaN(itemIndex)) {
                            return;
                        }
                        removePreferredSupplierItem(sid, itemIndex);
                        renderPreferredTable();
                        return;
                    }
                    const removePhotoBtn = e.target.closest('.cv-pref-photo-remove');
                    if (!removePhotoBtn) return;
                    const sid = Number(removePhotoBtn.getAttribute('data-pref-supplier-id') || '0');
                    const itemIndex = Number(removePhotoBtn.getAttribute('data-cv-item-index') || '0');
                    if (!sid || Number.isNaN(itemIndex)) return;
                    void removePreferredPhoto(sid, itemIndex);
                });
            }

            const prefSearch = document.getElementById('cvPrefSupplierSearch');
            const prefList = document.getElementById('cvPrefSupplierSearchList');
            if (prefSearch) {
                prefSearch.addEventListener('input', () => {
                    state.preferredSearchFocused = true;
                    if (state.preferredSearchTimer) {
                        clearTimeout(state.preferredSearchTimer);
                    }
                    state.preferredSearchTimer = setTimeout(() => {
                        renderPreferredPicker();
                    }, 220);
                });
                prefSearch.addEventListener('focus', () => {
                    state.preferredSearchFocused = true;
                    renderPreferredPicker();
                });
                prefSearch.addEventListener('blur', () => {
                    // Use a longer delay to ensure click events on list items complete
                    setTimeout(() => {
                        state.preferredSearchFocused = false;
                        renderPreferredPicker();
                        updateCvPrefSupplierInfoPanel(null);
                    }, 250);
                });
            }
            if (prefList) {
                // Prevent input blur when clicking on list
                prefList.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    const opt = e.target.closest('.pref-search-option');
                    if (!opt) {
                        return;
                    }
                    const sid = opt.getAttribute('data-supplier-id');
                    if (sid) {
                        updateCvPrefSupplierInfoPanel(sid);
                    }
                });
                prefList.addEventListener('click', (e) => {
                    const opt = e.target.closest('.pref-search-option');
                    if (!opt) return;
                    e.preventDefault();
                    const sid = opt.getAttribute('data-supplier-id');
                    const addName = opt.getAttribute('data-add-new-name');
                    if (sid) {
                        if (isPreferredSupplierAdded(sid)) {
                            showToast('This supplier is already added.', 'error');
                            resetPreferredSupplierSearch();
                            return;
                        }
                        void linkPreferredSupplier(sid);
                        return;
                    }
                    if (addName) {
                        const cleaned = String(addName).trim();
                        const catalogMatch = (state.availableSuppliers || []).find(
                            (s) => String(s.supplier_name || '').trim().toLowerCase() === cleaned.toLowerCase()
                        );
                        if (catalogMatch && isPreferredSupplierAdded(catalogMatch.supplier_id)) {
                            showToast('This supplier is already added.', 'error');
                            resetPreferredSupplierSearch();
                            return;
                        }
                        void promptAddNewSupplierWithOptionalInfo(addName);
                    }
                });
            } else {
                console.warn('cvPrefSupplierSearchList element not found - preferred supplier search will not work');
            }
        }
    })();

    // Close button handler with unsaved changes confirmation
    const closeBtn = document.querySelector('.requisition-close-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', async (event) => {
            if (state.hasUnsavedChanges) {
                event.preventDefault();
                const confirmed = await showConfirmModal('Do you want to save the changes as draft?\n\nYes: Save as draft\nNo: Discard changes');
                if (confirmed) {
                    // For requesters, use saveForm; for canvassers/verifiers, use saveDraft
                    if (!canvasserRegister && cvSaveBtn) {
                        // Requester mode - save without the extra confirmation dialog
                        if (state.items.length === 0) {
                            showToast('Add at least one canvass item.', 'error');
                            return;
                        }
                        if (state.autoSaveTimer) {
                            clearRequesterAutosaveTimer();
                        }
                        if (cvSaveBtn) cvSaveBtn.disabled = true;
                        try {
                            const result = await persistCanvassToServer('draft');
                            if (!result.ok) {
                                showToast(result.message || 'Save failed.', 'error');
                                if (cvSaveBtn) cvSaveBtn.disabled = false;
                                return;
                            }
                            showToast('✓ Changes saved as draft');
                            state.hasUnsavedChanges = false;
                            setTimeout(() => {
                                window.history.back();
                            }, 600);
                        } catch {
                            showToast('Network error.', 'error');
                            if (cvSaveBtn) cvSaveBtn.disabled = false;
                        }
                    } else if (canvasserRegister && cvSaveDraftBtn) {
                        // Canvasser mode - use existing saveDraft function
                        await saveDraft();
                        setTimeout(() => {
                            window.history.back();
                        }, 600);
                    }
                } else {
                    // User clicked "No" - just go back without saving
                    event.preventDefault();
                    window.history.back();
                }
            }
        });
    }

    applyGsdReadonlyUi();
    loadForm();

    window.CWIRMSCanvassForm = {
        buildPricingSnapshot: buildCanvassPricingSnapshot,
        formatPricingOverviewQtyLabel,
    };

    window.IMRMS_DEAN_CANVASS_SYNC = {
        async refreshApprovalStrip() {
            try {
                const res = await fetch(
                    `${api}?action=get&request_id=${encodeURIComponent(String(requestId))}`,
                    { credentials: 'include' }
                );
                const data = await res.json();
                if (data.success && data.approval) {
                    applyCanvassApproval(data.approval);
                }
            } catch {
                /* ignore */
            }
        },
    };
})();
