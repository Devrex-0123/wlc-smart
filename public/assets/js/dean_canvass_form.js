/**
 * Canvass form — same layout patterns as requisition_form.js (items + supplier matrix).
 */
(function () {
    const card = document.getElementById('canvassCard');
    if (!card) return;

    const gsdReadonly = card.dataset.gsdReadonly === '1';
    const gsdReviewView = card.dataset.gsdReview === '1';
    const gsdOutcomeReadonlyView = card.dataset.gsdOutcomeReadonly === '1';
    const canvasserRegister = card.dataset.canvasserRegister === '1';
    const requesterEditView = card.dataset.requesterEdit === '1';
    const CANVASSER_REQUESTS_API = card.dataset.canvasserApi || '../../app/api/canvasser_requests.php';
    const GSD_REQUESTS_API = card.dataset.gsdApi || '../../app/api/gsd/requests.php';

    const requestId = parseInt(card.dataset.requestId || '0', 10);
    const api = card.dataset.api || '../../app/api/canvass_detail.php';
    const deanApi = card.dataset.deanApi || '../../app/api/dean_requisition.php';

    // Edit window countdown — only active when the server sends a positive seconds value.
    (function initEditWindowCountdown() {
        const seconds = parseInt(card.dataset.editWindowSeconds || '0', 10);
        if (seconds <= 0) return;
        const countdownEl = document.getElementById('cvEditCountdown');
        if (!countdownEl) return;
        function fmt(s) {
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = s % 60;
            return [h, m, sec].map(function (n) { return String(n).padStart(2, '0'); }).join(':');
        }
        let remaining = seconds;
        countdownEl.textContent = fmt(remaining);
        const timer = setInterval(function () {
            remaining--;
            if (remaining <= 0) {
                clearInterval(timer);
                window.location.reload();
            } else {
                countdownEl.textContent = fmt(remaining);
            }
        }, 1000);
    }());

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
    const cvCanvasSection = document.getElementById('cvCanvasSection'); // null for the requester now
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

    function getCanvassedSupplierItemIndices(supplierId) {
        const sid = String(supplierId);
        const stored = state.canvassedSupplierItems[sid];
        if (Array.isArray(stored)) {
            return [...stored].sort((a, b) => a - b);
        }
        return [];
    }

    function setCanvassedSupplierItemIndices(supplierId, indices) {
        state.canvassedSupplierItems[String(supplierId)] = [...indices].sort((a, b) => a - b);
    }

    function addCanvassedSupplierItem(supplierId, itemIndex) {
        const idx = Number(itemIndex);
        const sid = String(supplierId);
        if (Number.isNaN(idx) || idx < 0 || idx >= state.items.length || !state.items[idx]) {
            showToast('Select a valid canvass item.', 'error');
            return false;
        }
        const current = getCanvassedSupplierItemIndices(sid);
        if (current.includes(idx)) {
            return false;
        }
        setCanvassedSupplierItemIndices(supplierId, [...current, idx]);
        const supplier = state.selectedSuppliers.find((s) => String(s.supplier_id) === sid);
        if (supplier) {
            if (!supplier.prices || typeof supplier.prices !== 'object') {
                supplier.prices = {};
            }
            if (supplier.prices[idx] === undefined) {
                supplier.prices[idx] = '';
            }
        }
        state.hasUnsavedChanges = true;
        scheduleRequesterAutosave();
        return true;
    }

    function removeCanvassedSupplierItem(supplierId, itemIndex) {
        const idx = Number(itemIndex);
        const sid = String(supplierId);
        setCanvassedSupplierItemIndices(
            supplierId,
            getCanvassedSupplierItemIndices(sid).filter((i) => i !== idx)
        );
        const supplier = state.selectedSuppliers.find((s) => String(s.supplier_id) === sid);
        if (supplier && supplier.prices) {
            delete supplier.prices[idx];
            delete supplier.prices[String(idx)];
        }
        state.hasUnsavedChanges = true;
        scheduleRequesterAutosave();
    }

    function hydrateCanvassedSupplierItemsFromSavedPrices() {
        const items = {};
        (state.selectedSuppliers || []).forEach((supplier) => {
            const sid = String(supplier.supplier_id);
            const indices = [];
            const rawPrices = supplier.prices && typeof supplier.prices === 'object' ? supplier.prices : {};
            Object.keys(rawPrices).forEach((k) => {
                const idx = Number(k);
                if (!Number.isNaN(idx) && idx >= 0 && idx < state.items.length && state.items[idx]) {
                    indices.push(idx);
                }
            });
            const tracked = getCanvassedSupplierItemIndices(supplier.supplier_id);
            tracked.forEach((idx) => {
                if (!indices.includes(idx)) {
                    indices.push(idx);
                }
            });
            items[sid] = [...new Set(indices)].sort((a, b) => a - b);
        });
        state.canvassedSupplierItems = items;
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

    function markFormSaved() {
        state.hasUnsavedChanges = false;
        clearRequesterAutosaveTimer();
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
        const showGsdSuggestUi = gsdReadonly && canChangeSuggested && !gsdReviewView;
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

    async function handleGsdAwardRadioChange(radio) {
        if (!radio || !gsdReviewView) {
            return;
        }
        const lineId = parseInt(
            radio.getAttribute('data-requisition-line-id') || radio.dataset.canvassDetailId || '0',
            10
        );
        const supplierId = parseInt(radio.value || '0', 10);
        const selectionSource =
            radio.getAttribute('data-selection-source') === 'preferred' ? 'preferred' : 'canvassed';
        if (lineId <= 0 || supplierId <= 0) {
            return;
        }

        const { canChangeSuggested } = getGsdSuggestSelectionContext();
        if (!canChangeSuggested) {
            return;
        }

        try {
            const body = new URLSearchParams();
            body.set('action', 'save_suggested_supplier_item');
            body.set('request_id', String(requestId));
            body.set('requisition_line_id', String(lineId));
            body.set('suggested_supplier_id', String(supplierId));
            body.set('selection_source', selectionSource);
            const res = await fetch(GSD_REQUESTS_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'include',
            });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not save quote selection.', 'error');
                return;
            }

            const line = (state.gsdLines || []).find((r) => Number(r.requisition_line_id) === lineId);
            if (line) {
                line.award = {
                    supplier_id: supplierId,
                    supplier_name: data.suggested_supplier_name || '',
                    selection_source: selectionSource,
                };
            }
            emitCanvassPricingUpdate();
            updateGsdVerifyButtonState();
        } catch {
            showToast('Network error saving quote selection.', 'error');
        }
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
            if (
                gsdReviewView
                && (radio.dataset.requisitionLineId || radio.dataset.canvassDetailId)
            ) {
                handleGsdAwardRadioChange(radio);
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
        if ((gsdReviewView || gsdOutcomeReadonlyView) && Array.isArray(state.gsdLines) && state.gsdLines.length > 0) {
            return buildGsdPricingSnapshotFromLines(state.gsdLines);
        }

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
                        return `<div class="cv-pref-item-row${isSelectedForItem ? ' cv-gsd-suggested-row' : ''}">
                            <div class="cv-pref-item-name-block">
                                <span class="cv-pref-item-name">${escapeHtml(item.name || `Item ${index + 1}`)}</span>
                                <span class="cv-pref-item-qty">Req. qty: ${reqQty} ${escapeHtml(unitLabel)}</span>
                            </div>
                            <div class="cv-pref-price-wrap">
                                ${radioHtml}
                                <span class="cv-pref-peso">PHP</span>
                                <input type="number" min="0" step="0.01" class="cv-preferred-price-input" data-pref-supplier-id="${sid}" data-cv-item-index="${index}" value="${escapeHtml(String(val))}" placeholder="0.00"${editable ? '' : ' readonly'}>
                                ${selectedBadge}
                                ${clearBtn}
                            </div>
                            <div class="cv-pref-item-actions">
                                ${photoCell}${removeItemBtn}
                            </div>
                        </div>`;
                    })
                    .join('');
                const emptyItemsRow =
                    linkedIndices.length === 0
                        ? `<div class="cv-pref-items-empty">${editable ? 'No items added yet. Select an item above.' : 'No quoted items for this supplier.'}</div>`
                        : '';
                return `<article class="cv-pref-card" data-supplier-id="${sid}">
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
                        <div class="cv-pref-items">${itemRows}${emptyItemsRow}</div>
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
        state.selectedSuppliers.push({
            supplier_id: full.supplier_id,
            supplier_name: full.supplier_name,
            supplier_image: full.supplier_image,
            prices: {},
            benefits: '',
            discounts: [],
        });
        setCanvassedSupplierItemIndices(full.supplier_id, []);
        renderSupplierTable();
        scheduleRequesterAutosave();
    }

    async function loadPreferredSuppliers() {
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
        if (cvPreferredCards) {
            renderPreferredTable();
            renderPreferredPicker();
        }
        hydrateCanvassedSupplierItemsFromSavedPrices();
        
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
        canvasserLines: [],
        gsdLines: [],
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
        canvassedSupplierItems: {},
        preferredSearchTimer: null,
        preferredQuotePhotos: {},
        canvassedSupplierCount: 0,
        suggestedByItem: {},
        requestedLines: [],
        autoSaveTimer: null,
        hasUnsavedChanges: false,
        isHydrating: false,
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
                const sid = Number(s.supplier_id || 0);
                const benefits = String(s.benefits || '').trim();
                const linkedIndices = getCanvassedSupplierItemIndices(sid);
                const prices = {};
                linkedIndices.forEach((idx) => {
                    const raw = s.prices && (s.prices[idx] ?? s.prices[String(idx)]);
                    if (raw !== undefined && raw !== null) {
                        prices[idx] = raw;
                    }
                });
                return {
                    supplier_id: s.supplier_id,
                    prices,
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
        const linkedIndices = getCanvassedSupplierItemIndices(supplier.supplier_id);
        if (linkedIndices.length === 0) {
            return false;
        }
        return linkedIndices.some((idx) => {
            const raw = supplier.prices[idx] ?? supplier.prices[String(idx)];
            if (raw === null || raw === '') {
                return false;
            }
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

    function approvalRoleKeyFromLabel(label) {
        const lc = String(label || '').toLowerCase();
        if (lc.includes('canvasser')) return 'canvas';
        if (lc.includes('g.s.d') || lc.includes('gsd')) return 'gsd';
        if (lc.includes('comptroller')) return 'comp';
        if (lc.includes('president')) return 'pres';
        return '';
    }

    function applyCanvassApproval(appr) {
        cachedCanvassApproval = appr && typeof appr === 'object' ? appr : null;
        if (canvasserRegister) {
            syncCanvasserToolbar();
        }
        const strip = document.getElementById('cvApprovalStrip');
        if (!strip) return;
        const roles = strip.querySelectorAll('.approval-role');
        const statusByKey = appr && typeof appr === 'object'
            ? {
                canvas: appr.canvas_status,
                gsd: appr.gsd_status,
                comp: appr.comp_status,
                pres: appr.pres_status,
            }
            : { canvas: null, gsd: null, comp: null, pres: null };
        const detailByKey = {
            canvas: document.getElementById('cvApprCanvasserDetail'),
            gsd: document.getElementById('cvApprGsdDetail'),
            comp: document.getElementById('cvApprCompDetail'),
            pres: document.getElementById('cvApprPresDetail'),
        };

        roles.forEach((role) => {
            const nameEl = role.querySelector('.approval-name');
            const key = approvalRoleKeyFromLabel(nameEl ? nameEl.textContent : '');
            if (!key) return;
            const circle = role.querySelector('.circle-icon');
            if (!circle) return;
            const raw = statusByKey[key];
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

        Object.keys(detailByKey).forEach((key) => {
            const detailEl = detailByKey[key];
            if (!detailEl) return;
            const raw = statusByKey[key];
            let t = approvalDetailForStep(raw);
            if (key === 'canvas' && approvalStepIsAccept(raw) && appr && appr.canvassed_by) {
                const name = String(appr.canvassed_by).trim();
                if (name) t = name;
            }
            detailEl.textContent = t;
            if (key === 'canvas' && t && t !== 'Rejected' && t !== 'Verified') {
                detailEl.setAttribute('title', t);
            } else {
                detailEl.removeAttribute('title');
            }
        });
        updateSuggestedSupplierNotice();

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
        if (state.isHydrating) {
            return;
        }
        state.hasUnsavedChanges = true;
    }

    function syncCanvassedSupplierMatrixVisibility() {
    const isRequesterView = Boolean(window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.isRequester);
    if (cvSupplierPickerLabel) {
        cvSupplierPickerLabel.hidden = isRequesterView;
    }
    const hasCanvassedData = state.selectedSuppliers && state.selectedSuppliers.length > 0;
    
    if (cvCanvassedSupplierLabel) {
        cvCanvassedSupplierLabel.hidden = !hasCanvassedData;
    }
    if (cvCanvassedSupplierWrap) {
        if (!hasCanvassedData) {
            cvCanvassedSupplierWrap.setAttribute('hidden', 'hidden');
            cvCanvassedSupplierWrap.style.display = 'none';
        } else {
            cvCanvassedSupplierWrap.removeAttribute('hidden');
            cvCanvassedSupplierWrap.style.display = '';
        }
    }
}

    function renderSupplierTable() {
        if (!cvCanvassedCards) {
        return;
    }

    const hasData = state.selectedSuppliers && state.selectedSuppliers.length > 0;

    // Heading + picker are canvasser-only in the PHP now. Everyone else only sees
    // anything here once the canvasser has actually added a supplier.
    if (cvCanvasSection) {
        cvCanvasSection.hidden = !canvasserRegister && !hasData;
    }

    if (!hasData) {
        if (cvCanvassedSupplierWrap) cvCanvassedSupplierWrap.hidden = true;
        if (cvCanvassedSupplierLabel) cvCanvassedSupplierLabel.hidden = true;
        cvCanvassedCards.innerHTML = `<div class="empty-state">${
            canvasserRegister
                ? 'No canvassed suppliers yet. Add suppliers and enter quoted prices here.'
                : 'No canvassed supplier quotes yet. This section is filled in by the assigned canvasser.'
        }</div>`;
        return;
    }

    if (cvCanvassedSupplierWrap) cvCanvassedSupplierWrap.hidden = false;
    if (cvCanvassedSupplierLabel) cvCanvassedSupplierLabel.hidden = false;
    
    // If there IS data, show the container
    if (cvCanvassedSupplierWrap) {
        cvCanvassedSupplierWrap.removeAttribute('hidden');
        cvCanvassedSupplierWrap.style.display = '';
        cvCanvassedSupplierWrap.style.visibility = '';
        cvCanvassedSupplierWrap.style.height = '';
        cvCanvassedSupplierWrap.style.overflow = '';
        cvCanvassedSupplierWrap.style.padding = '';
        cvCanvassedSupplierWrap.style.margin = '';
        cvCanvassedSupplierWrap.style.border = '';
    }
    if (cvCanvassedSupplierLabel) {
        cvCanvassedSupplierLabel.removeAttribute('hidden');
    }
    
    
    const canvasStatus = String((cachedCanvassApproval && cachedCanvassApproval.canvas_status) || '').trim().toLowerCase();
    const canvasserLocked = canvasserRegister && (canvasStatus === 'accept' || canvasStatus === 'reject');
    const isRequesterView = Boolean(window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.isRequester);
    const hideStructureActions = gsdReadonly || canvasserLocked || isRequesterView;
    const { showGsdSuggestUi, canChangeSuggested } = getGsdSuggestSelectionContext();

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
            const linkedIndices = getCanvassedSupplierItemIndices(sidNum);
            const availableItems = state.items
                .map((item, index) => ({ item, index }))
                .filter(({ index }) => !linkedIndices.includes(index));
            const addItemOptions = availableItems
                .map(
                    ({ item, index }) =>
                        `<option value="${index}">${escapeHtml(item.name || `Item ${index + 1}`)}</option>`
                )
                .join('');
            const canAddItems = canvasserRegister && !hideStructureActions && !isRequesterView;
            const addItemBar = canAddItems
                ? state.items.length === 0
                    ? '<div class="cv-pref-add-item-bar"><span class="empty-state">Add canvass items first.</span></div>'
                    : `<div class="cv-pref-add-item-bar">
                    <select class="cv-pref-add-item-select cv-canvas-add-item-select" data-cv-supplier-id="${sidNum}" aria-label="Select item to quote"${availableItems.length === 0 ? ' disabled' : ''}>
                        <option value="">Select item…</option>
                        ${addItemOptions}
                    </select>
                    <button type="button" class="cv-pref-add-item-btn cv-canvas-add-item-btn" data-cv-supplier-id="${sidNum}"${availableItems.length === 0 ? ' disabled' : ''}>+ Add item</button>
                </div>`
                : '';
            const itemRows = linkedIndices
                .map((itemIndex) => {
                    const it = state.items[itemIndex];
                    if (!it) {
                        return '';
                    }
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
                    const removeItemBtn =
                        canAddItems
                            ? `<button type="button" class="cv-pref-remove-item-btn cv-canvas-remove-item-btn" data-cv-supplier-id="${sidNum}" data-cv-item-index="${itemIndex}" title="Remove item" aria-label="Remove item">×</button>`
                            : '';
                    return `<tr class="${isSelectedForItem ? 'cv-gsd-suggested-row' : ''}">
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
                        <td class="cv-pref-item-action-cell">${removeItemBtn}</td>
                    </tr>`;
                })
                .join('');
            const emptyItemsRow =
                linkedIndices.length === 0
                    ? `<tr><td colspan="3" class="empty-state">${canAddItems ? 'No items added yet. Select an item above.' : 'No quoted items for this supplier.'}</td></tr>`
                    : '';
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
                        <button type="button" class="cv-pref-remove-btn cv-canvas-remove-btn" data-cv-supplier-index="${supplierIndex}" title="Remove supplier" aria-label="Remove supplier"><i class="fas fa-times" aria-hidden="true"></i></button>
                    </div>`
                    }
                </div>
                <div class="cv-pref-card-body">
                    ${addItemBar}
                    <table class="cv-pref-items-table" aria-label="Canvassed supplier quotes">
                        <thead><tr><th>Item name</th><th>Quoted price</th><th></th></tr></thead>
                        <tbody>${itemRows}${emptyItemsRow}</tbody>
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

    // ── Canvasser new-style view (reads from requisition_line + requisition_line_quotes) ──

    function fmtPhp(val) {
        const n = parseFloat(val);
        if (Number.isNaN(n)) return '—';
        return 'PHP ' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderCanvasserLineItems(lines) {
        const body = document.getElementById('cvRequestedItemsTableBody');
        if (!body) return;
        if (!Array.isArray(lines) || lines.length === 0) {
            body.innerHTML =
                '<tr class="requested-items-empty"><td colspan="5">No items recorded on this requisition.</td></tr>';
            return;
        }

        let html = '';
        let lastGroup = null;
        let rowNum = 0;

        lines.forEach((row) => {
            const group = String(row.group_label || '').trim();
            if (group !== lastGroup) {
                if (group) {
                    html += `<tr class="cv-canvasser-group-header-row">
                        <td colspan="5" class="cv-canvasser-group-header">
                            <i class="fas fa-layer-group" aria-hidden="true"></i> ${escapeHtml(group)}
                        </td>
                    </tr>`;
                }
                lastGroup = group;
            }

            rowNum++;
            const name  = escapeHtml(row.item_name || '—');
            const brand = String(row.brand || '').trim();
            const model = String(row.model || '').trim();
            const spec  = String(row.specification || '').trim();
            const metaParts = [brand, model, spec].filter(Boolean);
            const metaRow = metaParts.length
                ? `<div class="cv-req-line-meta">${escapeHtml(metaParts.join(' · '))}</div>`
                : '';
            const qty   = row.quantity != null ? row.quantity : 1;
            const unit  = escapeHtml(String(row.unit_type || 'unit'));
            const lid   = Number(row.requisition_line_id || 0);
            const groupClass = group ? ' cv-canvasser-item-grouped' : '';

            // Existing canvassed quote chips
            const cQuotes = Array.isArray(row.canvassed_quotes) ? row.canvassed_quotes : [];
            const pQuotes = Array.isArray(row.preferred_quotes)  ? row.preferred_quotes  : [];
            let quotesHtml = '';
            if (pQuotes.length) {
                quotesHtml += pQuotes.map((q) => `
                    <span class="cv-line-quote-chip cv-line-quote-chip--preferred" title="Preferred quote from requester">
                        <span class="cv-line-quote-chip-label">${escapeHtml(q.supplier_name)}</span>
                        <span class="cv-line-quote-chip-price">${fmtPhp(q.quoted_unit_price)}</span>
                        <span class="cv-line-quote-chip-badge">preferred</span>
                    </span>`).join('');
            }
            if (cQuotes.length) {
                quotesHtml += cQuotes.map((q) => `
                    <span class="cv-line-quote-chip cv-line-quote-chip--canvassed" data-supplier-id="${Number(q.supplier_id)}" data-line-id="${lid}" title="${escapeHtml(q.benefits || '')}">
                        <span class="cv-line-quote-chip-label">${escapeHtml(q.supplier_name)}</span>
                        <span class="cv-line-quote-chip-price">${fmtPhp(q.quoted_unit_price)}</span>
                        <button type="button" class="cv-line-quote-chip-del" data-line-id="${lid}" data-supplier-id="${Number(q.supplier_id)}" aria-label="Remove quote" title="Remove quote">×</button>
                    </span>`).join('');
            }
            if (!quotesHtml) {
                quotesHtml = '<span class="cv-line-quote-empty">No quotes yet</span>';
            }

            const addBtn = `<button type="button" class="cv-add-quote-btn" data-line-id="${lid}" data-line-name="${escapeHtml(row.item_name || '')}" aria-label="Add quote for ${escapeHtml(row.item_name || '')}">
                <i class="fas fa-plus" aria-hidden="true"></i> Quote
            </button>`;

            html += `<tr class="cv-canvasser-item-row${groupClass}">
                <td class="requested-items-table-num">${rowNum}</td>
                <td class="requested-items-table-item">${name}${metaRow}</td>
                <td class="requested-items-table-qty">${escapeHtml(String(qty))}</td>
                <td class="requested-items-table-unit">${unit}</td>
                <td class="cv-canvasser-item-quotes-cell">
                    <div class="cv-canvasser-item-quotes">${quotesHtml}</div>
                    ${addBtn}
                </td>
            </tr>`;
        });

        body.innerHTML = html;

        // Attach delete listeners
        body.querySelectorAll('.cv-line-quote-chip-del').forEach((btn) => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const lineId    = parseInt(btn.dataset.lineId, 10);
                const supplierId = parseInt(btn.dataset.supplierId, 10);
                btn.disabled = true;
                const data = await deleteCanvasserLineQuote(lineId, supplierId);
                if (!data.success) {
                    showToast(data.message || 'Could not remove quote.', 'error');
                    btn.disabled = false;
                    return;
                }
                await refreshCanvasserView();
            });
        });

        // Attach Add Quote listeners
        body.querySelectorAll('.cv-add-quote-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const lineId   = parseInt(btn.dataset.lineId, 10);
                const lineName = btn.dataset.lineName || '';
                openCanvasserQuoteModal(lineId, lineName);
            });
        });
    }

    async function loadCanvasserView() {
        try {
            const res = await fetch(
                `${CANVASSER_REQUESTS_API}?action=get_canvass_view&request_id=${encodeURIComponent(String(requestId))}`,
                { credentials: 'include' }
            );
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not load.', 'error');
                return;
            }

            // Header fields
            const h = data.header || {};
            const deptEl    = document.getElementById('cvOfficeDisplay');
            const facEl     = document.getElementById('cvFacilityDisplay');
            const dateEl    = document.getElementById('cvRequestDate');
            const purposeEl = document.getElementById('cvPurpose');
            if (deptEl)    deptEl.value    = h.office_name    || '—';
            if (facEl)     facEl.value     = h.facility_label || '—';
            if (dateEl)    dateEl.value    = h.request_date   || '—';
            if (purposeEl) purposeEl.value = h.purpose        || '';

            state.canvasserLines    = data.lines     || [];
            state.availableSuppliers = data.suppliers || [];

            renderCanvasserLineItems(state.canvasserLines);
            buildCanvasserQuoteModalSupplierList(state.availableSuppliers);
            applyCanvassApproval(data.approval);
            syncCanvasserToolbar();
        } catch {
            showToast('Network error loading canvass view.', 'error');
        }
    }

    async function refreshCanvasserView() {
        try {
            const res = await fetch(
                `${CANVASSER_REQUESTS_API}?action=get_canvass_view&request_id=${encodeURIComponent(String(requestId))}`,
                { credentials: 'include' }
            );
            const data = await res.json();
            if (!data.success) return;
            state.canvasserLines    = data.lines     || [];
            state.availableSuppliers = data.suppliers || [];
            renderCanvasserLineItems(state.canvasserLines);
            buildCanvasserQuoteModalSupplierList(state.availableSuppliers);
        } catch { /* silent */ }
    }

    // ── GSD review view (requisition_line + quotes + awards) ─────────────────

    function buildGsdPricingSnapshotFromLines(lines) {
        let selectedCount = 0;
        let grandTotal = 0;
        let hasDiscount = false;
        const currency = 'PHP';
        const out = (lines || []).map((row, index) => {
            const qty = Math.max(1, Number(row.quantity) || 1);
            const unit = String(row.unit_type || 'unit');
            const award = row.award || null;
            const supplierId = award ? Number(award.supplier_id) : null;
            const selectionSource = award
                ? (award.selection_source === 'preferred' ? 'preferred' : 'canvassed')
                : null;
            let supplierName = award ? String(award.supplier_name || '') : null;
            let unitPrice = null;
            let lineTotal = null;
            let discountLabel = null;
            let discountPercent = null;

            if (supplierId && selectionSource) {
                selectedCount += 1;
                const quotes = selectionSource === 'preferred'
                    ? (row.preferred_quotes || [])
                    : (row.canvassed_quotes || []);
                const match = quotes.find((q) => Number(q.supplier_id) === supplierId);
                if (match && match.quoted_unit_price != null && !Number.isNaN(Number(match.quoted_unit_price))) {
                    unitPrice = Math.round(Number(match.quoted_unit_price) * 100) / 100;
                    const discountPct = parseFloat(match.discount_percent) || 0;
                    if (discountPct > 0) {
                        discountPercent = discountPct;
                        discountLabel = `${discountPct}%`;
                        hasDiscount = true;
                    }
                    lineTotal = Math.round(unitPrice * qty * (1 - discountPct / 100) * 100) / 100;
                    grandTotal += lineTotal;
                    if (!supplierName && match.supplier_name) {
                        supplierName = String(match.supplier_name);
                    }
                }
            }

            return {
                item_index: index,
                canvass_detail_id: Number(row.requisition_line_id || 0),
                requisition_line_id: Number(row.requisition_line_id || 0),
                item_name: String(row.item_name || `Item ${index + 1}`),
                quantity: qty,
                qty_per_set: 1,
                requisition_qty: qty,
                unit_type: unit,
                supplier_id: supplierId,
                supplier_name: supplierName,
                selection_source: selectionSource,
                unit_price: unitPrice,
                line_total: lineTotal,
                discount_percent: discountPercent,
                discount_label: discountLabel,
            };
        });

        return {
            lines: out,
            item_count: out.length,
            selected_count: selectedCount,
            grand_total: Math.round(grandTotal * 100) / 100,
            currency,
            show_discount_column: hasDiscount,
        };
    }

    function buildGsdQuoteOptionHtml(lineId, quote, quoteType, selectedAward, canSelect) {
        const sid = Number(quote.supplier_id || 0);
        const isSelected = selectedAward
            && Number(selectedAward.supplier_id) === sid
            && (selectedAward.selection_source || 'canvassed') === quoteType;
        const checked = isSelected ? ' checked' : '';
        const disabled = canSelect ? '' : ' disabled';
        const badge = quoteType === 'preferred' ? 'preferred' : 'canvassed';
        const price = fmtPhp(quote.quoted_unit_price);
        if (canSelect) {
            return `<label class="cv-gsd-quote-option cv-line-quote-chip cv-line-quote-chip--${badge}">
                <input type="radio" class="cv-suggested-item-radio" name="gsd_award_${lineId}"
                    value="${sid}" data-requisition-line-id="${lineId}" data-canvass-detail-id="${lineId}"
                    data-selection-source="${quoteType}"${checked}${disabled}>
                <span class="cv-line-quote-chip-label">${escapeHtml(quote.supplier_name || '')}</span>
                <span class="cv-line-quote-chip-price">${price}</span>
                <span class="cv-line-quote-chip-badge">${badge}</span>
            </label>`;
        }
        return `<span class="cv-line-quote-chip cv-line-quote-chip--${badge}${isSelected ? ' cv-line-quote-chip--selected' : ''}" title="${escapeHtml(quote.benefits || '')}">
            <span class="cv-line-quote-chip-label">${escapeHtml(quote.supplier_name || '')}</span>
            <span class="cv-line-quote-chip-price">${price}</span>
            <span class="cv-line-quote-chip-badge">${badge}</span>
        </span>`;
    }

    function renderGsdLineItems(lines) {
        const body = document.getElementById('cvRequestedItemsTableBody');
        if (!body) return;

        if (!Array.isArray(lines) || lines.length === 0) {
            body.innerHTML =
                '<tr class="requested-items-empty"><td colspan="4">No line items found.</td></tr>';
            return;
        }

        let html = '';
        let lastGroup = null;
        let rowNum = 0;

        lines.forEach((row) => {
            const group = String(row.group_label || '').trim();
            if (group !== lastGroup) {
                if (group) {
                    html += `<tr class="cv-canvasser-group-header-row">
                        <td colspan="4" class="cv-canvasser-group-header">
                            <i class="fas fa-layer-group" aria-hidden="true"></i> ${escapeHtml(group)}
                        </td>
                    </tr>`;
                }
                lastGroup = group;
            }
            rowNum++;
            const name = escapeHtml(row.item_name || '—');
            const brand = String(row.brand || '').trim();
            const model = String(row.model || '').trim();
            const spec = String(row.specification || '').trim();
            const metaParts = [brand, model, spec].filter(Boolean);
            const metaRow = metaParts.length
                ? `<div class="cv-req-line-meta">${escapeHtml(metaParts.join(' · '))}</div>`
                : '';
            const qty = row.quantity != null ? row.quantity : 1;
            const unit = escapeHtml(String(row.unit_type || 'unit'));
            html += `<tr class="cv-gsd-item-row${group ? ' cv-canvasser-item-grouped' : ''}">
                <td class="requested-items-table-num">${rowNum}</td>
                <td class="requested-items-table-item">${name}${metaRow}</td>
                <td class="requested-items-table-qty">${escapeHtml(String(qty))}</td>
                <td class="requested-items-table-unit">${unit}</td>
            </tr>`;
        });

        body.innerHTML = html;
    }

    async function loadGsdOutcomeReadonlyView() {
        let data = window.CWIRMS_GSD_OUTCOME || null;
        if (!data || typeof data !== 'object') {
            try {
                const res = await fetch(
                    `${api}?action=get_canvass_outcome_view&request_id=${encodeURIComponent(String(requestId))}`,
                    { credentials: 'include' }
                );
                if (!res.ok) {
                    const errText = await res.text();
                    console.error('[GSD outcome HTTP error]', res.status, errText);
                    showToast(`Could not load GSD canvass outcome (${res.status}).`, 'error');
                    return;
                }
                const raw = await res.text();
                try {
                    data = JSON.parse(raw);
                } catch (parseErr) {
                    console.error('[GSD outcome JSON parse error]', parseErr, raw.slice(0, 500));
                    showToast('Could not read GSD canvass outcome response.', 'error');
                    return;
                }
            } catch (err) {
                console.error('[GSD outcome fetch error]', err);
                showToast('Network error loading GSD canvass outcome.', 'error');
                return;
            }
        }
        if (!data.success) {
            showToast(data.message || 'Could not load GSD canvass outcome.', 'error');
            return;
        }

        try {
            const h = data.header || {};
            const deptEl = document.getElementById('cvOfficeDisplay');
            const facEl = document.getElementById('cvFacilityDisplay');
            const dateEl = document.getElementById('cvRequestDate');
            const purposeEl = document.getElementById('cvPurpose');
            if (deptEl) deptEl.value = h.office_name || '—';
            if (facEl) facEl.value = h.facility_label || '—';
            if (dateEl) dateEl.value = h.request_date || '—';
            if (purposeEl) purposeEl.value = h.purpose || '';

            state.gsdLines = data.lines || [];
            state.availableSuppliers = data.suppliers || state.availableSuppliers;
            renderGsdLineItems(state.gsdLines);
            gsdPopulatePreferredState(state.gsdLines);
            renderGsdSectionB(state.gsdLines, { readonly: true });
            renderGsdSectionC(state.gsdLines, { readonly: true });
            syncGsdOutcomeSectionVisibility(state.gsdLines);
            applyCanvassApproval(data.approval);

            const sectionBDisplayEl = document.getElementById('cvGsdSectionBCanvasserDisplay');
            const sectionBNameEl = document.getElementById('cvGsdSectionBCanvasserName');
            if (sectionBDisplayEl && sectionBNameEl) {
                let canvasserName = '';
                for (const line of state.gsdLines) {
                    for (const q of (line.canvassed_quotes || [])) {
                        if (q.canvasser_name) { canvasserName = q.canvasser_name; break; }
                    }
                    if (canvasserName) break;
                }
                if (canvasserName) {
                    sectionBNameEl.textContent = canvasserName;
                    sectionBDisplayEl.hidden = false;
                }
            }
            try {
                emitCanvassPricingUpdate();
            } catch (pricingErr) {
                console.error('[GSD outcome pricing sync error]', pricingErr);
            }
        } catch (err) {
            console.error('[GSD outcome render error]', err);
            showToast('Loaded GSD outcome data but could not render all sections.', 'error');
        }
    }

    async function loadGsdReviewView() {
        const tbody = document.getElementById('cvRequestedItemsTableBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:1rem;color:#64748b;">Loading items…</td></tr>';
        }
        try {
            const res = await fetch(
                `${GSD_REQUESTS_API}?action=get_gsd_review_view&request_id=${encodeURIComponent(String(requestId))}`,
                { credentials: 'include' }
            );
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not load GSD review.', 'error');
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

            state.gsdLines = data.lines || [];
            state.availableSuppliers = data.suppliers || state.availableSuppliers;
            gsdRestoreCanvasserName(state.gsdLines);
            renderGsdLineItems(state.gsdLines);
            buildGsdCanvassQuoteModalSupplierList(state.availableSuppliers);
            initGsdCanvassQuoteModalListeners();
            bindGsdOfficerNameInputOnce();
            gsdPopulatePreferredState(state.gsdLines);
            renderGsdSectionB(state.gsdLines);
            renderGsdSectionC(state.gsdLines);
            // Auto-select cheapest if no awards yet
            await gsdAutoSelectCheapest(state.gsdLines);
            emitCanvassPricingUpdate();
            // Wire Add Supplier button
            const addSupBtn = document.getElementById('cvGsdAddSupBtn');
            if (addSupBtn && !addSupBtn.dataset.gsdBound) {
                addSupBtn.dataset.gsdBound = '1';
                addSupBtn.addEventListener('click', () => {
                    initGsdAddSupModalListeners();
                    openGsdAddSupModal();
                });
            }
            // Wire Save Draft button
            const saveDraftBtn = document.getElementById('btn-save-draft');
            if (saveDraftBtn && !saveDraftBtn.dataset.gsdBound) {
                saveDraftBtn.dataset.gsdBound = '1';
                saveDraftBtn.addEventListener('click', saveGSDDraft);
            }
            applyCanvassApproval(data.approval);
            updateGsdVerifyButtonState();
        } catch (err) {
            console.error('[GSD review load error]', err);
            showToast('Network error loading GSD review.', 'error');
        }
    }

    async function refreshGsdReviewView() {
        try {
            const res = await fetch(
                `${GSD_REQUESTS_API}?action=get_gsd_review_view&request_id=${encodeURIComponent(String(requestId))}`,
                { credentials: 'include' }
            );
            const data = await res.json();
            if (!data.success) return;
            state.gsdLines = data.lines || [];
            gsdRestoreCanvasserName(state.gsdLines);
            if (data.suppliers) {
                state.availableSuppliers = data.suppliers;
                buildGsdCanvassQuoteModalSupplierList(state.availableSuppliers);
            }
            renderGsdLineItems(state.gsdLines);
            gsdPopulatePreferredState(state.gsdLines);
            renderGsdSectionB(state.gsdLines);
            renderGsdSectionC(state.gsdLines);
            emitCanvassPricingUpdate();
        } catch { /* silent */ }
    }

    window.__cwirmsRefreshGsdReviewView = refreshGsdReviewView;

    function gsdAllLinesAwarded() {
        const lines = state.gsdLines || [];
        const linesWithQuotes = lines.filter(
            (l) => (l.preferred_quotes || []).length > 0 || (l.canvassed_quotes || []).length > 0
        );
        return linesWithQuotes.length > 0 && linesWithQuotes.every((l) => l.award != null);
    }

    window.__cwirmsGsdAllAwardsReady = gsdAllLinesAwarded;

    async function prepareGsdVerify() {
        const flushed = await gsdFlushPendingCanvassQuotes();
        if (!flushed.ok) {
            return flushed;
        }
        if (!gsdAllLinesAwarded()) {
            return {
                ok: false,
                message: 'Select a supplier quote for every line item before verifying.',
            };
        }
        return { ok: true };
    }

    window.__cwirmsPrepareGsdVerify = prepareGsdVerify;

    function updateGsdVerifyButtonState() {
        const approveBtn = document.getElementById('comptrollerApproveBtn');
        const hintEl = document.getElementById('gsdVerifyHint');
        if (!approveBtn || !gsdReviewView) return;
        const gsdDone = String((cachedCanvassApproval && cachedCanvassApproval.gsd_status) || '').trim().toLowerCase();
        if (gsdDone === 'accept' || gsdDone === 'reject') return;
        const allSet = gsdAllLinesAwarded();
        approveBtn.disabled = !allSet;
        if (hintEl) {
            if (allSet) {
                hintEl.textContent = 'All quotes selected. You can now verify this request.';
                hintEl.className = 'gsd-verify-hint gsd-verify-hint-ready';
            } else {
                const lines = state.gsdLines || [];
                const missing = lines.filter(
                    (l) => ((l.preferred_quotes || []).length > 0 || (l.canvassed_quotes || []).length > 0) && !l.award
                ).length;
                hintEl.textContent = `Select a supplier quote for ${missing} remaining line item${missing !== 1 ? 's' : ''} before verifying.`;
                hintEl.className = 'gsd-verify-hint gsd-verify-hint-pending';
            }
        }
    }

    window.__cwirmsUpdateGsdVerifyButtonState = updateGsdVerifyButtonState;

    let _gsdOfficerNameInputBound = false;
    function bindGsdOfficerNameInputOnce() {
        if (_gsdOfficerNameInputBound) return;
        const nameInput = document.getElementById('cvGsdOfficerNameInput');
        if (!nameInput) return;
        _gsdOfficerNameInputBound = true;
        nameInput.addEventListener('input', () => {
            const val = nameInput.value.trim();
            updateGsdVerifyButtonState();
            const detailEl = document.getElementById('cvApprGsdDetail');
            if (detailEl) detailEl.textContent = val ? val : '';
            const labelEl = document.getElementById('cvGsdCanvassedByLabel');
            const nameEl = document.getElementById('cvGsdCanvassedByName');
            if (nameEl) nameEl.textContent = val;
            if (labelEl) labelEl.hidden = !val;
        });
    }

    // ── GSD Sections A / B / C rendering ─────────────────────────────────────

    function gsdRestoreCanvasserName(lines) {
        const input = document.getElementById('cvGsdCanvasserInput');
        if (!input || input.value.trim()) return;
        for (const line of (lines || [])) {
            for (const q of (line.canvassed_quotes || [])) {
                if (q.canvasser_name) { input.value = q.canvasser_name; return; }
            }
        }
    }

    function gsdGetInitials(name) {
        return (String(name || '?')).split(/\s+/).map(w => w[0] || '').join('').toUpperCase().slice(0, 2) || '??';
    }

    const GSD_AV_COLORS = ['#3b82f6','#8b5cf6','#f59e0b','#ef4444','#10b981','#0ea5e9','#f97316','#ec4899'];
    function gsdAvatarColor(name) {
        let h = 0;
        for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) & 0x7fffffff;
        return GSD_AV_COLORS[h % GSD_AV_COLORS.length];
    }

    function gsdFmtPeso(v) {
        const n = parseFloat(v);
        if (Number.isNaN(n)) return '₱0.00';
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function gsdGroupBySupplier(lines, quoteType) {
        const map = new Map();
        (lines || []).forEach((line) => {
            const quotes = quoteType === 'preferred' ? (line.preferred_quotes || []) : (line.canvassed_quotes || []);
            quotes.forEach((q) => {
                const sid = Number(q.supplier_id);
                if (!map.has(sid)) {
                    map.set(sid, {
                        supplier_id: sid,
                        supplier_name: String(q.supplier_name || ''),
                        supplier_image: String(q.supplier_image || ''),
                        benefits: q.benefits || '',
                        discount_percent: q.discount_percent || null,
                        items: [],
                    });
                }
                const unit = String(line.unit_type || 'unit');
                const unitShort = unit === 'piece' ? 'pcs' : (unit === 'ream' ? 'reams' : unit);
                map.get(sid).items.push({
                    requisition_line_id: Number(line.requisition_line_id),
                    item_name: String(line.item_name || ''),
                    quantity: Number(line.quantity || 1),
                    unit_type: unit,
                    unit_short: unitShort,
                    quoted_unit_price: q.quoted_unit_price,
                    benefits: q.benefits || '',
                    discount_percent: q.discount_percent || null,
                    quote_photo: q.quote_photo || null,
                });
            });
        });
        return map;
    }

    function gsdPopulatePreferredState(gsdLines) {
        // Build state.items and state.requestedLines from gsdLines so renderPreferredTable works
        state.requestedLines = (gsdLines || []).map((line) => ({
            requisition_line_id: Number(line.requisition_line_id),
            quantity: Number(line.quantity || 1),
            unit_type: String(line.unit_type || 'unit'),
        }));
        state.items = (gsdLines || []).map((line) => ({
            name: String(line.item_name || ''),
            brand: String(line.brand || ''),
            model: String(line.model || ''),
            specification: String(line.specification || ''),
            canvass_detail_id: Number(line.canvass_detail_id || 0),
            requisition_line_id: Number(line.requisition_line_id),
            suggested_supplier_ids: [],
            selected_supplier_id: 0,
            selected_supplier_source: null,
        }));
        // Build preferredSuppliers from per-line preferred_quotes, keyed by item index
        const catalogById = new Map(
            (state.availableSuppliers || []).map((s) => [Number(s.supplier_id), s])
        );
        const prefSupMap = new Map();
        (gsdLines || []).forEach((line, lineIdx) => {
            (line.preferred_quotes || []).forEach((q) => {
                const sid = String(q.supplier_id);
                const cat = catalogById.get(Number(q.supplier_id)) || {};
                if (!prefSupMap.has(sid)) {
                    prefSupMap.set(sid, {
                        supplier_id: q.supplier_id,
                        supplier_name: q.supplier_name || cat.supplier_name || '',
                        supplier_image: q.supplier_image || cat.supplier_image || '',
                        contact_person: cat.contact_person || '',
                        phone_number: cat.phone_number || '',
                        email: cat.email || '',
                        shop_url: cat.shop_url || '',
                        address: cat.address || '',
                        city: cat.city || '',
                        country: cat.country || '',
                        postal_code: cat.postal_code || '',
                        tin: cat.tin || '',
                        quoted_prices: {}, quote_photos: {},
                        quoted_item_indices: [], is_preferred: 0,
                    });
                }
                const sup = prefSupMap.get(sid);
                sup.quoted_prices[lineIdx] = String(q.quoted_unit_price ?? '');
                if (q.quote_photo) {
                    sup.quote_photos[lineIdx] = String(q.quote_photo);
                }
                if (!sup.quoted_item_indices.includes(lineIdx)) sup.quoted_item_indices.push(lineIdx);
            });
        });
        state.preferredSuppliers = [...prefSupMap.values()];
        state.preferredPriceDrafts = {};
        state.preferredSupplierItems = {};
        state.preferredQuotePhotos = {};
        hydratePreferredSupplierItemsFromApi();
        hydratePreferredPriceDraftsFromApi();
        hydratePreferredPhotoDraftsFromApi();
        renderPreferredTable();
    }

    function gsdHasSectionAData(lines) {
        return (lines || []).some((line) => (line.preferred_quotes || []).length > 0);
    }

    function gsdHasSectionBData(lines) {
        return (lines || []).some((line) =>
            (line.canvassed_quotes || []).some((q) => {
                const price = q.quoted_unit_price;
                return price != null && price !== '' && !Number.isNaN(Number(price)) && Number(price) >= 0;
            })
        );
    }

    function syncGsdOutcomeSectionVisibility(lines) {
        if (!gsdReviewView && !gsdOutcomeReadonlyView) {
            return;
        }
        const hasA = gsdHasSectionAData(lines);
        const hasB = gsdHasSectionBData(lines);
        const hasAny = hasA || hasB;
        const sectionB = document.getElementById('cvGsdSectionB');
        const sectionC = document.getElementById('cvGsdSectionC');
        const abstractSection = document.getElementById('cvGsdAbstractTotalSection');

        // Section B: always visible in GSD edit mode so they can add quotes.
        // In readonly mode show it whenever any supplier data exists (A or B), so the
        // "No G.S.D. canvassed quotes were recorded." message is visible even when
        // only preferred (Section A) quotes are present.
        if (sectionB) {
            sectionB.hidden = gsdReviewView ? false : !hasAny;
        }
        // Section C and pricing abstract show whenever ANY supplier exists in A or B.
        [sectionC, abstractSection].forEach((el) => {
            if (el) el.hidden = !hasAny;
        });
        const pricingSection = document.getElementById('cvPricingOverviewSection');
        if (pricingSection) {
            pricingSection.hidden = !hasAny;
        }
    }

    function renderGsdSectionA(lines) {
        const container = document.getElementById('cvGsdPrefCards');
        if (!container) return;
        const map = gsdGroupBySupplier(lines, 'preferred');
        if (map.size === 0) {
            container.innerHTML = '<p class="gsd-cv-empty-note">No preferred quotes submitted by the requester yet.</p>';
            return;
        }
        let html = '';
        map.forEach((sup) => {
            const initials = gsdGetInitials(sup.supplier_name);
            const color = gsdAvatarColor(sup.supplier_name);
            let total = 0;
            let itemsHtml = '';
            sup.items.forEach((item) => {
                const price = parseFloat(item.quoted_unit_price) || 0;
                total += price * item.quantity;
                const photoUrl = item.quote_photo ? resolvePublicUploadUrl(String(item.quote_photo)) : '';
                const photoHtml = photoUrl
                    ? `<button type="button" class="cv-pref-photo-view-btn gsd-cv-quote-photo-btn" data-photo-url="${escapeHtml(photoUrl)}" title="View quotation photo"><img src="${escapeHtml(photoUrl)}" alt="quote photo" class="cv-pref-photo-thumb"></button>`
                    : '';
                itemsHtml += `<div class="gsd-cv-item-row">
                    <span class="gsd-cv-item-name">${escapeHtml(item.item_name)}</span>
                    ${photoHtml}
                    <span class="gsd-cv-item-qty">\xd7${item.quantity} ${escapeHtml(item.unit_short)}</span>
                    <span class="gsd-cv-item-price">${gsdFmtPeso(price)}</span>
                </div>`;
            });
            html += `<div class="gsd-cv-pref-card">
                <div class="gsd-cv-pref-card-hd">
                    <div class="gsd-cv-sup-avatar" style="background:${color}">${escapeHtml(initials)}</div>
                    <span class="gsd-cv-pref-card-name">${escapeHtml(sup.supplier_name)}</span>
                    <span class="gsd-cv-pref-badge">preferred</span>
                </div>
                <div class="gsd-cv-pref-card-bd">
                    ${itemsHtml}
                    <div class="gsd-cv-total-row">
                        <span class="gsd-cv-total-label">Total</span>
                        <span>${gsdFmtPeso(total)}</span>
                    </div>
                </div>
            </div>`;
        });
        container.innerHTML = html;
    }

    function renderGsdSectionB(lines, options) {
        const readonly = Boolean(options && options.readonly);
        const container = document.getElementById('cvGsdCanvCards');
        if (!container) return;
        const map = gsdGroupBySupplier(lines, 'canvassed');
        if (map.size === 0) {
            container.innerHTML = readonly
                ? '<p class="gsd-cv-empty-note">No G.S.D. canvassed quotes were recorded.</p>'
                : '<p class="gsd-cv-empty-note">No canvassed quotes yet. Click <strong>+ Add Supplier</strong> to add one.</p>';
            syncGsdOutcomeSectionVisibility(lines);
            return;
        }
        if (readonly) {
            let html = '';
            map.forEach((sup) => {
                const supplierId = sup.supplier_id;
                const initials = gsdGetInitials(sup.supplier_name);
                const color = gsdAvatarColor(sup.supplier_name);
                const imgSrc = sup.supplier_image ? escapeHtml(resolvePublicUploadUrl(sup.supplier_image)) : '';
                const avatarHtml = `<div class="gsd-cv-sup-avatar" style="background:${color}">${imgSrc ? `<img src="${imgSrc}" class="gsd-sup-av-img" onerror="this.style.display='none'" alt="">` : ''}${escapeHtml(initials)}</div>`;
                const fullSup = (state.availableSuppliers || []).find((s) => Number(s.supplier_id) === supplierId) || {};
                const benefits = sup.items?.[0]?.benefits || '';
                const discVal = sup.discount_percent ? String(sup.discount_percent) : '';
                let itemsHtml = '';
                (lines || []).forEach((line) => {
                    const q = (line.canvassed_quotes || []).find((quote) => Number(quote.supplier_id) === supplierId);
                    if (!q || q.quoted_unit_price == null) {
                        return;
                    }
                    const price = parseFloat(q.quoted_unit_price) || 0;
                    itemsHtml += `<div class="gsd-cv-canv-item-row gsd-cv-canv-item-row--readonly">
                        <div class="gsd-cv-canv-item-info">
                            <span class="gsd-cv-canv-item-name">${escapeHtml(line.item_name || '')}</span>
                            <span class="gsd-cv-canv-item-qty">Qty: ${Number(line.quantity || 1)} ${escapeHtml(line.unit_type || 'unit')}</span>
                        </div>
                        <span class="gsd-cv-canv-item-price-readonly">${gsdFmtPeso(price)}</span>
                    </div>`;
                });
                html += `<div class="gsd-cv-canv-card gsd-cv-canv-card--readonly">
                    <div class="gsd-cv-canv-card-hd">
                        ${avatarHtml}
                        <div class="gsd-cv-sup-info">
                            <span class="gsd-cv-pref-card-name">${escapeHtml(sup.supplier_name)}</span>
                            ${fullSup.contact_person ? `<div class="gsd-cv-sup-meta"><i class="fas fa-user" aria-hidden="true"></i> ${escapeHtml(fullSup.contact_person)}</div>` : ''}
                        </div>
                    </div>
                    <div class="gsd-cv-canv-card-bd">
                        ${itemsHtml}
                        ${benefits ? `<div class="gsd-cv-benefits-readonly"><span class="gsd-cv-benefits-lbl">Benefits</span> ${escapeHtml(benefits)}</div>` : ''}
                        ${discVal ? `<div class="gsd-cv-disc-readonly"><span class="gsd-cv-benefits-lbl">Discount</span> ${escapeHtml(discVal)}%</div>` : ''}
                    </div>
                </div>`;
            });
            container.innerHTML = html;
            syncGsdOutcomeSectionVisibility(lines);
            return;
        }
        container.innerHTML = '';
        map.forEach((sup) => {
            container.appendChild(gsdBuildCanvCard(sup, lines));
        });
        syncGsdOutcomeSectionVisibility(lines);
    }

    // Build a single item price-row element for a supplier card
    function gsdBuildItemRowEl(line, supplierId) {
        const q = (line.canvassed_quotes || []).find((q) => Number(q.supplier_id) === supplierId);
        const price = q && q.quoted_unit_price != null ? parseFloat(q.quoted_unit_price) : '';
        const displayPrice = price !== '' && !Number.isNaN(price) ? price.toFixed(2) : '';
        const row = document.createElement('div');
        row.className = 'gsd-cv-canv-item-row';
        row.dataset.lineId = String(line.requisition_line_id);
        row.innerHTML = `
            <button type="button" class="gsd-cv-item-rm-btn" data-line-id="${line.requisition_line_id}" title="Remove item">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <div class="gsd-cv-canv-item-info">
                <span class="gsd-cv-canv-item-name">${escapeHtml(line.item_name || '')}</span>
                <span class="gsd-cv-canv-item-qty">Qty: ${Number(line.quantity || 1)} ${escapeHtml(line.unit_type || 'unit')}</span>
            </div>
            <div class="gsd-cv-price-wrap">
                <span class="gsd-cv-price-lbl">PHP</span>
                <input type="number" class="gsd-cv-price-inp"
                    data-line-id="${line.requisition_line_id}"
                    data-supplier-id="${supplierId}"
                    value="${escapeHtml(displayPrice)}"
                    min="0" step="0.01" placeholder="0">
            </div>`;
        return row;
    }

    // Refresh "Add item" select to show only lines not yet added to the card
    function gsdRefreshAddItemSelect(card, lines, supplierId) {
        const sel = card.querySelector('.gsd-cv-add-item-sel');
        if (!sel) return;
        const shownIds = new Set(
            [...card.querySelectorAll('.gsd-cv-canv-item-row')].map((r) => parseInt(r.dataset.lineId, 10))
        );
        const remaining = (lines || []).filter((l) => !shownIds.has(Number(l.requisition_line_id)));
        const wrap = sel.closest('.gsd-cv-add-item-row');
        if (remaining.length === 0) {
            if (wrap) wrap.style.display = 'none';
        } else {
            if (wrap) wrap.style.display = '';
            sel.innerHTML = `<option value="">+ Add item…</option>` +
                remaining.map((l) => `<option value="${l.requisition_line_id}">${escapeHtml(l.item_name || '')} — Qty ${Number(l.quantity || 1)} ${escapeHtml(l.unit_type || '')}</option>`).join('');
        }
    }

    // Wire price-save and per-row remove for a single item row
    function gsdWireItemRow(rowEl, card, lines, supplierId) {
        const priceInp = rowEl.querySelector('.gsd-cv-price-inp');
        if (priceInp) {
            let _t = null;
            priceInp.addEventListener('change', () => {
                clearTimeout(_t);
                _t = setTimeout(() => {
                    const lineId = parseInt(priceInp.dataset.lineId, 10);
                    const price = priceInp.value.trim();
                    if (price === '' || isNaN(parseFloat(price)) || parseFloat(price) < 0) return;
                    const benInput = card.querySelector('.gsd-cv-benefits-inp');
                    const ben = benInput ? benInput.value.trim() : '';
                    const discRow = card.querySelector('.gsd-cv-disc-row');
                    const discInpEl = card.querySelector('.gsd-cv-disc-inp');
                    const disc = (discRow && discRow.style.display !== 'none' && discInpEl) ? discInpEl.value.trim() : '';
                    const parsedDiscount = disc !== '' ? parseFloat(disc) : null;
                    // Local state update only — no API call until Save Draft is clicked
                    const line = (state.gsdLines || []).find((l) => Number(l.requisition_line_id) === lineId);
                    if (line) {
                        const qList = line.canvassed_quotes || (line.canvassed_quotes = []);
                        const existing = qList.find((q) => Number(q.supplier_id) === supplierId);
                        if (existing) {
                            existing.quoted_unit_price = parseFloat(price);
                            existing.discount_percent = parsedDiscount;
                        } else {
                            const allSup = (state.availableSuppliers || []).find((s) => Number(s.supplier_id) === supplierId);
                            qList.push({
                                supplier_id: supplierId,
                                supplier_name: allSup ? allSup.supplier_name : '',
                                quoted_unit_price: parseFloat(price),
                                benefits: ben,
                                quote_type: 'canvassed',
                                discount_percent: parsedDiscount,
                            });
                        }
                    }
                    renderGsdSectionC(state.gsdLines);
                    emitCanvassPricingUpdate();
                    markUnsaved();
                }, 300);
            });
        }
        const rmBtn = rowEl.querySelector('.gsd-cv-item-rm-btn');
        if (rmBtn) {
            rmBtn.addEventListener('click', async () => {
                const lineId = parseInt(rmBtn.dataset.lineId, 10);
                const body = new URLSearchParams();
                body.set('action', 'remove_canvass_line');
                body.set('request_id', String(requestId));
                body.set('supplier_id', String(supplierId));
                body.set('requisition_line_id', String(lineId));
                try {
                    const res = await fetch(GSD_REQUESTS_API, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                        credentials: 'include',
                    });
                    const data = await res.json();
                    if (!data.success) { showToast(data.message || 'Could not remove item.', 'error'); return; }
                    const line = (state.gsdLines || []).find((l) => Number(l.requisition_line_id) === lineId);
                    if (line) {
                        line.canvassed_quotes = (line.canvassed_quotes || []).filter((q) => Number(q.supplier_id) !== supplierId);
                        if (line.award && Number(line.award.supplier_id) === supplierId) line.award = null;
                    }
                    rowEl.remove();
                    gsdRefreshAddItemSelect(card, lines, supplierId);
                    renderGsdSectionC(state.gsdLines);
                } catch { showToast('Network error.', 'error'); }
            });
        }
    }

    function gsdBuildCanvCard(sup, lines) {
        const supplierId = sup.supplier_id;
        const initials = gsdGetInitials(sup.supplier_name);
        const color = gsdAvatarColor(sup.supplier_name);
        const imgSrc = sup.supplier_image ? escapeHtml(resolvePublicUploadUrl(sup.supplier_image)) : '';
        const avatarHtml = `<div class="gsd-cv-sup-avatar" style="background:${color}">${imgSrc ? `<img src="${imgSrc}" class="gsd-sup-av-img" onerror="this.style.display='none'" alt="">` : ''}${escapeHtml(initials)}</div>`;
        const card = document.createElement('div');
        card.className = 'gsd-cv-canv-card';
        card.dataset.supplierId = String(supplierId);

        const fullSup = (state.availableSuppliers || []).find((s) => Number(s.supplier_id) === supplierId) || {};
        const contactPerson = fullSup.contact_person || '';
        const tin = fullSup.tin || '';
        const address = fullSup.address || '';

        const benefitsVal = escapeHtml(sup.items?.[0]?.benefits || '');
        const discVal = sup.discount_percent ? String(sup.discount_percent) : '';

        card.innerHTML = `
            <div class="gsd-cv-canv-card-hd">
                ${avatarHtml}
                <div class="gsd-cv-sup-info">
                    <span class="gsd-cv-pref-card-name">${escapeHtml(sup.supplier_name)}</span>
                    ${contactPerson ? `<div class="gsd-cv-sup-meta"><i class="fas fa-user" aria-hidden="true"></i> ${escapeHtml(contactPerson)}</div>` : ''}
                    ${tin ? `<div class="gsd-cv-sup-meta"><span class="gsd-cv-sup-meta-lbl">TIN</span> ${escapeHtml(tin)}</div>` : ''}
                    ${address ? `<div class="gsd-cv-sup-meta"><i class="fas fa-location-dot" aria-hidden="true"></i> ${escapeHtml(address)}</div>` : ''}
                </div>
                <button type="button" class="gsd-cv-remove-btn" data-supplier-id="${supplierId}">
                    <i class="fas fa-trash-alt" aria-hidden="true"></i> Remove
                </button>
            </div>
            <div class="gsd-cv-canv-card-bd">
                <div class="gsd-cv-item-rows"></div>
                <div class="gsd-cv-add-item-row">
                    <select class="gsd-cv-add-item-sel"><option value="">+ Add item…</option></select>
                </div>
                <div class="gsd-cv-benefits-row">
                    <span class="gsd-cv-benefits-lbl">Benefits</span>
                    <input type="text" class="gsd-cv-benefits-inp" data-supplier-id="${supplierId}"
                        value="${benefitsVal}" placeholder="e.g. Includes VAT, free delivery…">
                </div>
                <div class="gsd-cv-disc-row" style="${discVal ? '' : 'display:none'}">
                    <span class="gsd-cv-benefits-lbl">Discount %</span>
                    <input type="number" class="gsd-cv-disc-inp" min="0" max="100" step="0.01"
                        placeholder="0" value="${escapeHtml(discVal)}">
                </div>
                <button type="button" class="gsd-cv-add-disc-btn">${discVal ? '− Remove discount' : '+ Add discount'}</button>
            </div>`;

        // Populate rows only for lines that already have a quote for this supplier
        const rowsContainer = card.querySelector('.gsd-cv-item-rows');
        const activeLines = (lines || []).filter((line) =>
            (line.canvassed_quotes || []).some((q) => Number(q.supplier_id) === supplierId)
        );
        activeLines.forEach((line) => {
            const rowEl = gsdBuildItemRowEl(line, supplierId);
            rowsContainer.appendChild(rowEl);
            gsdWireItemRow(rowEl, card, lines, supplierId);
        });

        // Populate the "add item" dropdown with remaining lines
        gsdRefreshAddItemSelect(card, lines, supplierId);

        // Wire "add item" select
        const addItemSel = card.querySelector('.gsd-cv-add-item-sel');
        if (addItemSel) {
            addItemSel.addEventListener('change', () => {
                const lineId = parseInt(addItemSel.value, 10);
                if (!lineId) return;
                const line = (lines || []).find((l) => Number(l.requisition_line_id) === lineId);
                if (!line) return;
                const rowEl = gsdBuildItemRowEl(line, supplierId);
                rowsContainer.appendChild(rowEl);
                gsdWireItemRow(rowEl, card, lines, supplierId);
                gsdRefreshAddItemSelect(card, lines, supplierId);
                addItemSel.value = '';
            });
        }

        // Wire remove-supplier button
        card.querySelector('.gsd-cv-remove-btn').addEventListener('click', async () => {
            await gsdRemoveSupplier(supplierId, card);
        });

        // Wire discount toggle
        const discBtn = card.querySelector('.gsd-cv-add-disc-btn');
        const discRow = card.querySelector('.gsd-cv-disc-row');
        const discInp = card.querySelector('.gsd-cv-disc-inp');
        if (discBtn && discRow && discInp) {
            discBtn.addEventListener('click', () => {
                const isOpen = discRow.style.display !== 'none';
                if (isOpen) {
                    discRow.style.display = 'none';
                    discInp.value = '';
                    discBtn.textContent = '+ Add discount';
                    // Clear discount in local state for all lines on this card
                    card.querySelectorAll('.gsd-cv-price-inp').forEach((inp) => {
                        const lineId = parseInt(inp.dataset.lineId, 10);
                        const line = (state.gsdLines || []).find((l) => Number(l.requisition_line_id) === lineId);
                        if (!line) return;
                        const existing = (line.canvassed_quotes || []).find((q) => Number(q.supplier_id) === supplierId);
                        if (existing) existing.discount_percent = null;
                    });
                    renderGsdSectionC(state.gsdLines);
                    emitCanvassPricingUpdate();
                    markUnsaved();
                } else {
                    discRow.style.display = 'flex';
                    discBtn.textContent = '− Remove discount';
                    discInp.focus();
                }
            });
            let _discTimer = null;
            discInp.addEventListener('change', () => {
                clearTimeout(_discTimer);
                _discTimer = setTimeout(() => {
                    const discountPercent = discInp.value.trim();
                    const parsedDiscount = discountPercent !== '' ? parseFloat(discountPercent) : null;
                    // Update discount in local state for all lines that already have a price for this supplier
                    card.querySelectorAll('.gsd-cv-price-inp').forEach((inp) => {
                        const lineId = parseInt(inp.dataset.lineId, 10);
                        const price = inp.value.trim();
                        if (price === '' || isNaN(parseFloat(price)) || parseFloat(price) < 0) return;
                        const line = (state.gsdLines || []).find((l) => Number(l.requisition_line_id) === lineId);
                        if (!line) return;
                        const existing = (line.canvassed_quotes || []).find((q) => Number(q.supplier_id) === supplierId);
                        if (existing) existing.discount_percent = parsedDiscount;
                    });
                    renderGsdSectionC(state.gsdLines);
                    emitCanvassPricingUpdate();
                    markUnsaved();
                }, 400);
            });
        }

        // Benefits change — mark unsaved only (benefits are read from DOM on save)
        const benInputEl = card.querySelector('.gsd-cv-benefits-inp');
        if (benInputEl) {
            benInputEl.addEventListener('change', () => markUnsaved());
        }

        return card;
    }

    async function gsdPostCanvassQuote(lineId, supplierId, price, benefits, canvasserName, discountPercent) {
        const body = new URLSearchParams();
        body.set('action', 'add_canvass_quote');
        body.set('request_id', String(requestId));
        body.set('requisition_line_id', String(lineId));
        body.set('supplier_id', String(supplierId));
        body.set('unit_price', price);
        body.set('benefits', benefits || '');
        body.set('canvasser_name', canvasserName || '');
        body.set('discount_percent', discountPercent != null ? String(discountPercent) : '');
        try {
            const res = await fetch(GSD_REQUESTS_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'include',
            });
            const data = await res.json();
            if (!data.success) {
                return { ok: false, message: data.message || 'Could not save price.' };
            }
            const line = (state.gsdLines || []).find((l) => Number(l.requisition_line_id) === lineId);
            if (line) {
                const qList = line.canvassed_quotes || (line.canvassed_quotes = []);
                const existing = qList.find((q) => Number(q.supplier_id) === supplierId);
                if (existing) {
                    existing.quoted_unit_price = parseFloat(price);
                    existing.discount_percent = discountPercent != null && discountPercent !== '' ? parseFloat(discountPercent) : null;
                } else {
                    const allSup = (state.availableSuppliers || []).find((s) => Number(s.supplier_id) === supplierId);
                    qList.push({
                        supplier_id: supplierId,
                        supplier_name: allSup ? allSup.supplier_name : '',
                        quoted_unit_price: parseFloat(price),
                        benefits,
                        quote_type: 'canvassed',
                        discount_percent: discountPercent != null && discountPercent !== '' ? parseFloat(discountPercent) : null,
                    });
                }
            }
            return { ok: true };
        } catch {
            return { ok: false, message: 'Network error saving price.' };
        }
    }

    async function gsdSaveCanvPrice(lineId, supplierId, price, benefits, canvasserName, discountPercent) {
        const result = await gsdPostCanvassQuote(lineId, supplierId, price, benefits, canvasserName, discountPercent);
        if (!result.ok) {
            showToast(result.message || 'Could not save price.', 'error');
            return;
        }
        renderGsdSectionC(state.gsdLines);
        emitCanvassPricingUpdate();
    }

    async function gsdFlushPendingCanvassQuotes() {
        const canvasserInput = document.getElementById('cvGsdCanvasserInput');
        const canvasserName = canvasserInput ? canvasserInput.value.trim() : '';
        const saves = [];
        document.querySelectorAll('#cvGsdCanvCards .gsd-cv-canv-card').forEach((card) => {
            const supplierId = parseInt(card.dataset.supplierId, 10);
            const benInput = card.querySelector('.gsd-cv-benefits-inp');
            const ben = benInput ? benInput.value.trim() : '';
            const discRow = card.querySelector('.gsd-cv-disc-row');
            const discInp = card.querySelector('.gsd-cv-disc-inp');
            const disc = (discRow && discRow.style.display !== 'none' && discInp) ? discInp.value.trim() : '';
            card.querySelectorAll('.gsd-cv-price-inp').forEach((inp) => {
                const price = inp.value.trim();
                if (price !== '' && !isNaN(parseFloat(price)) && parseFloat(price) >= 0) {
                    saves.push(gsdPostCanvassQuote(
                        parseInt(inp.dataset.lineId, 10),
                        supplierId,
                        price,
                        ben,
                        canvasserName,
                        disc
                    ));
                }
            });
        });
        if (saves.length === 0) {
            return { ok: true };
        }
        const results = await Promise.all(saves);
        const failed = results.find((r) => !r.ok);
        if (failed) {
            return { ok: false, message: failed.message || 'Save your canvass quotes before selecting a supplier.' };
        }
        markSaved();
        return { ok: true };
    }

    function markUnsaved() {
        const btn = document.getElementById('btn-save-draft');
        const banner = document.getElementById('gsd-unsaved-banner');
        if (btn && !btn.classList.contains('gsd-unsaved')) {
            btn.classList.add('gsd-unsaved');
            btn.innerHTML = '<span class="gsd-unsaved-dot" aria-hidden="true"></span> ⚠ Unsaved changes — Save draft';
        }
        if (banner) banner.style.display = '';
    }

    function markSaved() {
        const btn = document.getElementById('btn-save-draft');
        const banner = document.getElementById('gsd-unsaved-banner');
        if (btn) {
            btn.classList.remove('gsd-unsaved');
            btn.innerHTML = '<i class="fas fa-floppy-disk" aria-hidden="true"></i> Save draft';
        }
        if (banner) banner.style.display = 'none';
    }

    async function saveGSDDraft() {
        const btn = document.getElementById('btn-save-draft');
        const hint = document.getElementById('gsd-save-hint');
        const canvasserInput = document.getElementById('cvGsdCanvasserInput');
        const canvasserName = canvasserInput ? canvasserInput.value.trim() : '';

        if (!canvasserName) {
            if (hint) { hint.textContent = 'Please enter the canvasser name before saving.'; hint.className = 'gsd-save-hint error'; }
            if (canvasserInput) canvasserInput.focus();
            return;
        }

        const saves = [];
        document.querySelectorAll('#cvGsdCanvCards .gsd-cv-canv-card').forEach((card) => {
            const supplierId = parseInt(card.dataset.supplierId, 10);
            const benInput = card.querySelector('.gsd-cv-benefits-inp');
            const ben = benInput ? benInput.value.trim() : '';
            const discRow = card.querySelector('.gsd-cv-disc-row');
            const discInp = card.querySelector('.gsd-cv-disc-inp');
            const disc = (discRow && discRow.style.display !== 'none' && discInp) ? discInp.value.trim() : '';
            card.querySelectorAll('.gsd-cv-price-inp').forEach((inp) => {
                const price = inp.value.trim();
                if (price !== '' && !isNaN(parseFloat(price)) && parseFloat(price) >= 0) {
                    saves.push(gsdPostCanvassQuote(parseInt(inp.dataset.lineId, 10), supplierId, price, ben, canvasserName, disc));
                }
            });
        });

        if (saves.length === 0) {
            if (hint) { hint.textContent = 'Add at least one price before saving.'; hint.className = 'gsd-save-hint error'; }
            return;
        }

        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Saving…'; }
        if (hint) { hint.textContent = 'Saving…'; hint.className = 'gsd-save-hint'; }

        const results = await Promise.all(saves);
        const failed = results.filter((r) => !r.ok).length;

        if (btn) btn.disabled = false;
        if (failed === 0) {
            if (btn) { btn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i> Saved'; btn.classList.add('saved'); }
            if (hint) { hint.textContent = 'Draft saved successfully. You can continue editing.'; hint.className = 'gsd-save-hint success'; }
            setTimeout(() => {
                markSaved();
                if (btn) btn.classList.remove('saved');
                if (hint) { hint.textContent = 'Saves your supplier quotes and canvasser name without approving.'; hint.className = 'gsd-save-hint'; }
            }, 3000);
        } else {
            if (btn) btn.innerHTML = '<i class="fas fa-floppy-disk" aria-hidden="true"></i> Save draft';
            if (hint) { hint.textContent = `${failed} item(s) could not be saved. Please try again.`; hint.className = 'gsd-save-hint error'; }
        }
    }

    async function gsdSaveSupplierDiscount(card, discountPercent) {
        const supplierId = parseInt(card.dataset.supplierId, 10);
        const canvasserInput = document.getElementById('cvGsdCanvasserInput');
        const canvasserName = canvasserInput ? canvasserInput.value.trim() : '';
        const benInput = card.querySelector('.gsd-cv-benefits-inp');
        const ben = benInput ? benInput.value.trim() : '';
        const priceInputs = card.querySelectorAll('.gsd-cv-price-inp');
        const saves = [];
        priceInputs.forEach((inp) => {
            const price = inp.value.trim();
            if (price !== '' && !isNaN(parseFloat(price)) && parseFloat(price) >= 0) {
                saves.push(gsdPostCanvassQuote(parseInt(inp.dataset.lineId, 10), supplierId, price, ben, canvasserName, discountPercent));
            }
        });
        if (saves.length > 0) {
            const results = await Promise.all(saves);
            if (results.every((r) => r.ok)) {
                renderGsdSectionC(state.gsdLines);
            }
        }
    }

    async function gsdRemoveSupplier(supplierId, cardEl) {
        const body = new URLSearchParams();
        body.set('action', 'remove_canvass_supplier');
        body.set('request_id', String(requestId));
        body.set('supplier_id', String(supplierId));
        try {
            const res = await fetch(GSD_REQUESTS_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'include',
            });
            const data = await res.json();
            if (!data.success) { showToast(data.message || 'Could not remove supplier.', 'error'); return; }
            // Update state
            (state.gsdLines || []).forEach((line) => {
                line.canvassed_quotes = (line.canvassed_quotes || []).filter((q) => Number(q.supplier_id) !== supplierId);
                if (line.award && Number(line.award.supplier_id) === supplierId) line.award = null;
            });
            cardEl.remove();
            const container = document.getElementById('cvGsdCanvCards');
            if (container && !container.querySelector('.gsd-cv-canv-card')) {
                container.innerHTML = '<p class="gsd-cv-empty-note">No canvassed quotes yet. Click <strong>+ Add Supplier</strong> to add one.</p>';
            }
            renderGsdSectionC(state.gsdLines);
            emitCanvassPricingUpdate();
            updateGsdVerifyButtonState();
            showToast('Supplier removed.');
        } catch {
            showToast('Network error removing supplier.', 'error');
        }
    }

    function renderGsdSectionC(lines, options) {
        const readonly = Boolean(options && options.readonly);
        const container = document.getElementById('cvGsdSelGrid');
        if (!container) return;

        // Aggregate all suppliers (preferred + canvassed)
        const suppMap = new Map();
        (lines || []).forEach((line) => {
            const qty = Number(line.quantity || 1);
            const addToMap = (q, source) => {
                const sid = Number(q.supplier_id);
                if (!suppMap.has(sid)) {
                    suppMap.set(sid, {
                        supplier_id: sid,
                        name: String(q.supplier_name || ''),
                        supplier_image: String(q.supplier_image || ''),
                        source,
                        items: [],
                        raw_total: 0,
                        discount_pct: null,
                    });
                }
                const price = parseFloat(q.quoted_unit_price) || 0;
                suppMap.get(sid).items.push({ name: String(line.item_name || ''), qty, price });
                suppMap.get(sid).raw_total += price * qty;
                if (source === 'canvassed' && q.discount_percent && !suppMap.get(sid).discount_pct) {
                    suppMap.get(sid).discount_pct = parseFloat(q.discount_percent);
                }
            };
            (line.preferred_quotes || []).forEach((q) => addToMap(q, 'preferred'));
            (line.canvassed_quotes || []).forEach((q) => addToMap(q, 'canvassed'));
        });

        if (suppMap.size === 0) {
            container.innerHTML = '<p class="gsd-cv-empty-note">Add supplier quotes in Sections A and B above to see selection cards here.</p>';
            syncGsdOutcomeSectionVisibility(lines);
            return;
        }

        // Compute effective totals
        suppMap.forEach((sup) => {
            const disc = sup.discount_pct && sup.discount_pct > 0 ? sup.discount_pct / 100 : 0;
            sup.effective_total = Math.round(sup.raw_total * (1 - disc) * 100) / 100;
        });

        // Sort cheapest first
        const sorted = [...suppMap.values()].sort((a, b) => a.effective_total - b.effective_total);

        // Determine current selection (consensus across all awarded lines)
        const awardedIds = new Set();
        (lines || []).forEach((line) => { if (line.award) awardedIds.add(Number(line.award.supplier_id)); });
        let selectedSupplierId = awardedIds.size === 1 ? [...awardedIds][0] : null;

        let html = '';
        sorted.forEach((sup, idx) => {
            const rank = idx + 1;
            const isSelected = sup.supplier_id === selectedSupplierId;
            const initials = gsdGetInitials(sup.name);
            const color = gsdAvatarColor(sup.name);
            const selImgSrc = sup.supplier_image ? escapeHtml(resolvePublicUploadUrl(sup.supplier_image)) : '';
            const selAvatarHtml = `<div class="gsd-cv-sel-avatar" style="background:${color}">${selImgSrc ? `<img src="${selImgSrc}" class="gsd-sup-av-img" onerror="this.style.display='none'" alt="">` : ''}${escapeHtml(initials)}</div>`;
            const disc = sup.discount_pct && sup.discount_pct > 0 ? sup.discount_pct : null;
            const rankHtml = rank === 1
                ? `<span class="gsd-cv-rank-badge rank1">🏆 #1 Cheapest</span>`
                : `<span class="gsd-cv-rank-badge">#${rank}</span>`;
            const itemRowsHtml = sup.items.map((it) =>
                `<div class="gsd-cv-sel-item-row">
                    <span class="gsd-cv-sel-item-nm">${escapeHtml(it.name)}</span>
                    <span class="gsd-cv-sel-item-pr">${gsdFmtPeso(it.price)}</span>
                </div>`
            ).join('');
            const discHtml = disc ? `<div class="gsd-cv-sel-disc-note">☑ ${disc}% discount applied</div>` : '';
            const trophyHtml = rank === 1 ? ' 🏆' : '';

            html += `<div class="gsd-cv-sel-card${isSelected ? ' gsd-selected' : ''}" data-supplier-id="${sup.supplier_id}" data-source="${sup.source}">
                <div class="gsd-cv-sel-card-hd">
                    <input type="radio" class="gsd-cv-sel-radio" name="gsd_sel_sup" value="${sup.supplier_id}" ${isSelected ? 'checked' : ''}${readonly ? ' disabled' : ''}>
                    ${selAvatarHtml}
                    <span class="gsd-cv-sel-name">${escapeHtml(sup.name)}</span>
                </div>
                <div class="gsd-cv-sel-badges">
                    <span class="gsd-cv-src-badge ${sup.source}">${sup.source === 'preferred' ? 'Preferred' : 'Canvassed'}</span>
                    ${rankHtml}
                </div>
                ${itemRowsHtml}
                ${discHtml}
                <div class="gsd-cv-sel-total">
                    <span class="gsd-cv-sel-total-lbl">TOTAL</span>
                    <span class="gsd-cv-sel-total-amt">${gsdFmtPeso(sup.effective_total)}${trophyHtml}</span>
                </div>
            </div>`;
        });
        container.innerHTML = html;

        if (!readonly) {
            // Wire card clicks
            container.querySelectorAll('.gsd-cv-sel-card').forEach((cardEl) => {
                cardEl.addEventListener('click', async () => {
                    const sid = parseInt(cardEl.dataset.supplierId, 10);
                    const src = cardEl.dataset.source;
                    const saved = await gsdSelectSupplierAllLines(sid, src);
                    if (!saved) {
                        return;
                    }
                    renderGsdSectionC(state.gsdLines);
                });
            });
        }
        syncGsdOutcomeSectionVisibility(lines);
    }

    async function gsdSelectSupplierAllLines(supplierId, source) {
        if (source === 'canvassed') {
            const flushed = await gsdFlushPendingCanvassQuotes();
            if (!flushed.ok) {
                showToast(flushed.message || 'Save your canvass quotes before selecting a supplier.', 'error');
                return false;
            }
        }

        const lines = state.gsdLines || [];
        const relevant = lines.filter((line) => {
            const quotes = source === 'preferred' ? (line.preferred_quotes || []) : (line.canvassed_quotes || []);
            return quotes.some((q) => Number(q.supplier_id) === supplierId);
        });
        if (relevant.length === 0) {
            showToast('No line items match this supplier selection.', 'error');
            return false;
        }

        let savedCount = 0;
        let lastError = '';
        for (const line of relevant) {
            const body = new URLSearchParams();
            body.set('action', 'save_suggested_supplier_item');
            body.set('request_id', String(requestId));
            body.set('requisition_line_id', String(line.requisition_line_id));
            body.set('suggested_supplier_id', String(supplierId));
            body.set('selection_source', source);
            try {
                const res = await fetch(GSD_REQUESTS_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                    credentials: 'include',
                });
                const data = await res.json();
                if (data.success) {
                    line.award = {
                        supplier_id: supplierId,
                        supplier_name: data.suggested_supplier_name || '',
                        selection_source: source,
                    };
                    savedCount += 1;
                } else {
                    lastError = data.message || 'Could not save supplier selection.';
                }
            } catch {
                lastError = 'Network error saving supplier selection.';
            }
        }

        if (savedCount !== relevant.length) {
            showToast(lastError || 'Could not save supplier selection for every line item.', 'error');
            emitCanvassPricingUpdate();
            updateGsdVerifyButtonState();
            return false;
        }

        emitCanvassPricingUpdate();
        updateGsdVerifyButtonState();
        return true;
    }

    async function gsdAutoSelectCheapest(lines) {
        const hasAward = (lines || []).some((l) => l.award != null);
        if (hasAward) return;
        // Build supplier totals same as renderGsdSectionC
        const suppMap = new Map();
        (lines || []).forEach((line) => {
            const qty = Number(line.quantity || 1);
            const addToMap = (q, source) => {
                const sid = Number(q.supplier_id);
                if (!suppMap.has(sid)) suppMap.set(sid, { supplier_id: sid, source, raw_total: 0, discount_pct: null });
                suppMap.get(sid).raw_total += (parseFloat(q.quoted_unit_price) || 0) * qty;
                if (source === 'canvassed' && q.discount_percent && !suppMap.get(sid).discount_pct) {
                    suppMap.get(sid).discount_pct = parseFloat(q.discount_percent);
                }
            };
            (line.preferred_quotes || []).forEach((q) => addToMap(q, 'preferred'));
            (line.canvassed_quotes || []).forEach((q) => addToMap(q, 'canvassed'));
        });
        if (suppMap.size === 0) return;
        suppMap.forEach((sup) => {
            const disc = sup.discount_pct && sup.discount_pct > 0 ? sup.discount_pct / 100 : 0;
            sup.effective_total = sup.raw_total * (1 - disc);
        });
        const sorted = [...suppMap.values()].sort((a, b) => a.effective_total - b.effective_total);
        if (sorted.length > 0) {
            const saved = await gsdSelectSupplierAllLines(sorted[0].supplier_id, sorted[0].source);
            if (saved) {
                renderGsdSectionC(lines);
            }
        }
    }

    // ── GSD Section B: Add Supplier modal ────────────────────────────────────

    // ── GSD Add Supplier modal (redesigned) ──────────────────────────────────

    let _gsdAddSupSelectedSup = null; // { supplier_id, supplier_name, tin, address }
    let _gsdAddSupRegOpen = false;

    function _gsdSupInitials(name) {
        const w = String(name || '?').trim().split(/\s+/);
        return ((w[0][0] || '') + (w[1] ? w[1][0] : '')).toUpperCase() || '?';
    }

    function _gsdSupRenderResults(query) {
        const container = document.getElementById('gsdAddSupResultsList');
        if (!container) return;
        const q = (query || '').trim().toLowerCase();
        const all = state.availableSuppliers || [];
        const filtered = q ? all.filter((s) => (s.supplier_name || '').toLowerCase().includes(q)) : all;

        if (filtered.length === 0) {
            container.innerHTML = `<div class="gsd-sup-empty-state">
                <i class="fas fa-search-slash" aria-hidden="true"></i>
                <span class="gsd-sup-empty-text">No supplier found for "<strong>${escapeHtml(query)}</strong>"</span>
                <button type="button" class="gsd-sup-empty-reg-btn" data-prefill="${escapeHtml(query)}">
                    Register "${escapeHtml(query)}" as new supplier
                </button>
            </div>`;
            container.querySelector('.gsd-sup-empty-reg-btn')?.addEventListener('click', (e) => {
                _gsdSupOpenRegisterForm(e.currentTarget.dataset.prefill || '');
            });
            return;
        }

        const rowsHtml = filtered.map((s) => {
            const sid = Number(s.supplier_id);
            const name = escapeHtml(s.supplier_name || '');
            const tin = s.tin ? escapeHtml(String(s.tin)) : '';
            const addr = s.address ? escapeHtml(String(s.address)) : '';
            const meta = [tin ? `TIN: ${tin}` : '', addr].filter(Boolean).join(' · ');
            const initials = escapeHtml(_gsdSupInitials(s.supplier_name || ''));
            const rowImgSrc = s.supplier_image ? escapeHtml(resolvePublicUploadUrl(s.supplier_image)) : '';
            const isSelected = _gsdAddSupSelectedSup && _gsdAddSupSelectedSup.supplier_id === sid;
            return `<div class="gsd-sup-row${isSelected ? ' gsd-sup-selected' : ''}" data-supplier-id="${sid}"
                         data-supplier-name="${name}" data-tin="${tin}" data-address="${addr}">
                <div class="gsd-sup-avatar-sq">${rowImgSrc ? `<img src="${rowImgSrc}" class="gsd-sup-av-img" onerror="this.style.display='none'" alt="">` : ''}${initials}</div>
                <div class="gsd-sup-row-info">
                    <div class="gsd-sup-row-name">${name}</div>
                    ${meta ? `<div class="gsd-sup-row-meta">${meta}</div>` : ''}
                </div>
                <i class="fas fa-circle-check gsd-sup-row-check" aria-hidden="true"></i>
            </div>`;
        }).join('');

        const hintRow = `<div class="gsd-sup-register-hint-row">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
            Not here?&nbsp;
            <button type="button" class="gsd-sup-register-hint-btn">Register as new supplier</button>
        </div>`;

        container.innerHTML = rowsHtml + hintRow;

        container.querySelectorAll('.gsd-sup-row').forEach((row) => {
            row.addEventListener('click', () => {
                const sid = parseInt(row.dataset.supplierId, 10);
                const name = row.dataset.supplierName || '';
                _gsdAddSupSelectedSup = { supplier_id: sid, supplier_name: name, tin: row.dataset.tin || '', address: row.dataset.address || '' };
                _gsdSupCloseRegisterForm();
                _gsdSupRenderResults(document.getElementById('gsdAddSupSearch')?.value || '');
                _gsdSupSetHint(`${name} selected`);
                _gsdSupCheckSaveState();
            });
        });

        container.querySelector('.gsd-sup-register-hint-btn')?.addEventListener('click', () => {
            _gsdSupOpenRegisterForm(document.getElementById('gsdAddSupSearch')?.value || '');
        });
    }

    function _gsdSupOpenRegisterForm(prefillName) {
        _gsdAddSupRegOpen = true;
        _gsdAddSupSelectedSup = null;
        const panel = document.getElementById('gsdAddSupRegPanel');
        const nameInp = document.getElementById('gsdAddSupRegName');
        if (panel) panel.style.display = '';
        if (nameInp) { nameInp.value = prefillName || ''; nameInp.focus(); }
        _gsdSupSetHint('Fill in supplier details');
        _gsdSupCheckSaveState();
    }

    function _gsdSupCloseRegisterForm() {
        _gsdAddSupRegOpen = false;
        const panel = document.getElementById('gsdAddSupRegPanel');
        if (panel) panel.style.display = 'none';
        ['gsdAddSupRegName','gsdAddSupRegContact','gsdAddSupRegPhone','gsdAddSupRegTin','gsdAddSupRegAddress']
            .forEach((id) => { const el = document.getElementById(id); if (el) el.value = ''; });
        _gsdSupCheckSaveState();
    }

    function _gsdSupSetHint(text) {
        const el = document.getElementById('gsdAddSupHintText');
        if (el) el.textContent = text;
    }

    function _gsdSupCheckSaveState() {
        const btn = document.getElementById('gsdAddSupModalConfirm');
        if (!btn) return;
        const regName = (document.getElementById('gsdAddSupRegName')?.value || '').trim();
        const ready = _gsdAddSupSelectedSup || (_gsdAddSupRegOpen && regName.length > 0);
        btn.disabled = !ready;
    }

    function _gsdSupAddCardToSection(supplierId, supplierName) {
        const container = document.getElementById('cvGsdCanvCards');
        if (!container) return;
        if (container.querySelector(`[data-supplier-id="${supplierId}"]`)) {
            showToast('This supplier is already added.', 'error');
            return false;
        }
        container.querySelectorAll('.gsd-cv-empty-note').forEach((e) => e.remove());
        const found = (state.availableSuppliers || []).find((s) => Number(s.supplier_id) === supplierId);
        const sup = { supplier_id: supplierId, supplier_name: supplierName, supplier_image: found?.supplier_image || '', benefits: '', discount_percent: null, items: [] };
        container.appendChild(gsdBuildCanvCard(sup, state.gsdLines));
        return true;
    }

    function _gsdSupResetModal() {
        _gsdAddSupSelectedSup = null;
        _gsdSupCloseRegisterForm();
        const searchInp = document.getElementById('gsdAddSupSearch');
        if (searchInp) searchInp.value = '';
        _gsdSupRenderResults('');
        _gsdSupSetHint('Search to find a supplier');
        _gsdSupCheckSaveState();
        const modal = document.getElementById('gsdAddSupModal');
        if (modal) modal.style.display = 'none';
    }

    // kept for backward-compat (called externally by the Add Supplier button wiring)
    function gsdBuildAddSupList() { _gsdSupRenderResults(''); }
    function gsdFilterAddSupList(q) { _gsdSupRenderResults(q); }

    let _gsdAddSupModalBound = false;
    function initGsdAddSupModalListeners() {
        if (_gsdAddSupModalBound) return;
        const modal = document.getElementById('gsdAddSupModal');
        if (!modal) return;
        _gsdAddSupModalBound = true;

        document.getElementById('gsdAddSupModalClose')?.addEventListener('click', () => _gsdSupResetModal());
        document.getElementById('gsdAddSupModalCancel')?.addEventListener('click', () => _gsdSupResetModal());
        document.getElementById('gsdAddSupModalBackdrop')?.addEventListener('click', () => _gsdSupResetModal());

        document.getElementById('gsdAddSupRegClose')?.addEventListener('click', () => {
            _gsdSupCloseRegisterForm();
            _gsdSupSetHint('Search to find a supplier');
        });

        document.getElementById('gsdAddSupRegName')?.addEventListener('input', () => _gsdSupCheckSaveState());

        const searchInp = document.getElementById('gsdAddSupSearch');
        if (searchInp) {
            searchInp.addEventListener('input', () => {
                _gsdAddSupSelectedSup = null;
                _gsdSupCloseRegisterForm();
                _gsdSupSetHint('Search to find a supplier');
                _gsdSupRenderResults(searchInp.value);
                _gsdSupCheckSaveState();
            });
        }

        document.getElementById('gsdAddSupModalConfirm')?.addEventListener('click', async () => {
            const btn = document.getElementById('gsdAddSupModalConfirm');
            if (btn) btn.disabled = true;

            if (_gsdAddSupSelectedSup) {
                const ok = _gsdSupAddCardToSection(_gsdAddSupSelectedSup.supplier_id, _gsdAddSupSelectedSup.supplier_name);
                if (ok) { showToast('Supplier added. Enter prices for each item.'); _gsdSupResetModal(); }
                else if (btn) btn.disabled = false;
                return;
            }

            if (_gsdAddSupRegOpen) {
                const name = (document.getElementById('gsdAddSupRegName')?.value || '').trim();
                if (!name) { showToast('Supplier name is required.', 'error'); if (btn) btn.disabled = false; return; }
                const body = new URLSearchParams();
                body.set('action', 'register_supplier');
                body.set('supplier_name', name);
                body.set('contact_person', document.getElementById('gsdAddSupRegContact')?.value || '');
                body.set('phone_number', document.getElementById('gsdAddSupRegPhone')?.value || '');
                body.set('tin', document.getElementById('gsdAddSupRegTin')?.value || '');
                body.set('address', document.getElementById('gsdAddSupRegAddress')?.value || '');
                try {
                    const res = await fetch(GSD_REQUESTS_API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(), credentials: 'include' });
                    const data = await res.json();
                    if (!data.success) { showToast(data.message || 'Could not register supplier.', 'error'); if (btn) btn.disabled = false; return; }
                    // Add to local suppliers array so it appears in future searches
                    state.availableSuppliers = state.availableSuppliers || [];
                    state.availableSuppliers.push({ supplier_id: data.supplier_id, supplier_name: data.supplier_name, tin: data.tin || '', address: data.address || '', contact_person: data.contact_person || '', phone_number: data.phone_number || '', supplier_image: null });
                    buildGsdCanvassQuoteModalSupplierList(state.availableSuppliers);
                    const ok = _gsdSupAddCardToSection(data.supplier_id, data.supplier_name);
                    if (ok) { showToast(`"${data.supplier_name}" registered and added.`); _gsdSupResetModal(); }
                    else if (btn) btn.disabled = false;
                } catch { showToast('Network error.', 'error'); if (btn) btn.disabled = false; }
                return;
            }

            if (btn) btn.disabled = false;
        });
    }

    function openGsdAddSupModal() {
        const modal = document.getElementById('gsdAddSupModal');
        if (!modal) return;
        _gsdAddSupSelectedSup = null;
        _gsdAddSupRegOpen = false;
        const searchInp = document.getElementById('gsdAddSupSearch');
        if (searchInp) searchInp.value = '';
        _gsdSupCloseRegisterForm();
        _gsdSupSetHint('Search to find a supplier');
        _gsdSupCheckSaveState();
        _gsdSupRenderResults('');
        modal.style.display = 'flex';
    }

    // ── GSD Canvass Quote Modal ──

    function buildGsdCanvassQuoteModalSupplierList(suppliers) {
        const list = document.getElementById('gsdCqSupplierList');
        if (!list) return;
        list.innerHTML = (suppliers || []).map((s) => {
            return `<div class="supplier-dropdown-item" data-supplier-id="${Number(s.supplier_id)}" data-supplier-name="${escapeHtml(s.supplier_name || '')}" role="option" tabindex="0">
                <span>${escapeHtml(s.supplier_name || '')}</span>
            </div>`;
        }).join('') || '<div class="supplier-dropdown-empty">No suppliers found.</div>';
        list.style.display = 'none';

        list.querySelectorAll('.supplier-dropdown-item').forEach((item) => {
            item.addEventListener('click', () => {
                document.getElementById('gsdCqSupplierId').value = item.dataset.supplierId;
                const nameSearch = document.getElementById('gsdCqSupplierNameSearch');
                if (nameSearch) nameSearch.value = item.dataset.supplierName || '';
                document.getElementById('gsdCqSupplierText').textContent = item.dataset.supplierName || '';
                list.style.display = 'none';
            });
        });
    }

    function openGsdCanvassQuoteModal(lineId, lineName) {
        const modal = document.getElementById('gsdCqModal');
        if (!modal) return;
        document.getElementById('gsdCqLineName').textContent = lineName;
        document.getElementById('gsdCqLineId').value = String(lineId);
        const sid = document.getElementById('gsdCqSupplierId');
        const stxt = document.getElementById('gsdCqSupplierText');
        const price = document.getElementById('gsdCqPrice');
        const ben = document.getElementById('gsdCqBenefits');
        const disc = document.getElementById('gsdCqDiscount');
        const cname = document.getElementById('gsdCqCanvasserName');
        if (sid) sid.value = '';
        if (stxt) stxt.textContent = 'Select supplier…';
        if (price) price.value = '';
        if (ben) ben.value = '';
        if (disc) disc.value = '';
        if (cname) cname.value = '';
        const nameSearch = document.getElementById('gsdCqSupplierNameSearch');
        if (nameSearch) nameSearch.value = '';
        const list = document.getElementById('gsdCqSupplierList');
        if (list) list.style.display = 'none';
        modal.style.display = 'flex';
        modal.removeAttribute('aria-hidden');
    }

    function closeGsdCanvassQuoteModal() {
        const modal = document.getElementById('gsdCqModal');
        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    let _gsdCqModalListenersInit = false;
    function initGsdCanvassQuoteModalListeners() {
        const modal = document.getElementById('gsdCqModal');
        if (!modal || _gsdCqModalListenersInit) return;
        _gsdCqModalListenersInit = true;

        const closeBtn = document.getElementById('gsdCqModalClose');
        const cancelBtn = document.getElementById('gsdCqModalCancel');
        const backdrop = document.getElementById('gsdCqModalBackdrop');
        const saveBtn = document.getElementById('gsdCqModalSave');
        const supBtn = document.getElementById('gsdCqSupplierBtn');
        const supList = document.getElementById('gsdCqSupplierList');

        if (closeBtn) closeBtn.addEventListener('click', closeGsdCanvassQuoteModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeGsdCanvassQuoteModal);
        if (backdrop) backdrop.addEventListener('click', closeGsdCanvassQuoteModal);

        // Text-input supplier search (supBtn is hidden; search handled via text input)
        const supSearchInput = document.getElementById('gsdCqSupplierNameSearch');
        const supDropWrap = document.getElementById('gsdCqSupplierDropdown');
        if (supSearchInput && supList) {
            const filterGsdSupplierList = (q) => {
                const lq = q.trim().toLowerCase();
                const items = supList.querySelectorAll('.supplier-dropdown-item');
                items.forEach((item) => {
                    const name = (item.dataset.supplierName || '').toLowerCase();
                    item.style.display = (!lq || name.includes(lq)) ? '' : 'none';
                });
                supList.style.display = items.length > 0 ? 'block' : 'none';
            };
            supSearchInput.addEventListener('focus', () => filterGsdSupplierList(supSearchInput.value));
            supSearchInput.addEventListener('input', () => {
                const sid = document.getElementById('gsdCqSupplierId');
                if (sid) sid.value = '';
                filterGsdSupplierList(supSearchInput.value);
            });
        }
        document.addEventListener('click', (e) => {
            if (!supList) return;
            const wrap = supDropWrap || document.getElementById('gsdCqSupplierDropdown');
            if (wrap && wrap.contains(e.target)) return;
            supList.style.display = 'none';
        });

        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                const lineId = parseInt(document.getElementById('gsdCqLineId').value, 10);
                const supplierId = parseInt((document.getElementById('gsdCqSupplierId') || {}).value || '0', 10);
                const priceRaw = ((document.getElementById('gsdCqPrice') || {}).value || '').trim();
                const benefits = ((document.getElementById('gsdCqBenefits') || {}).value || '').trim();
                const discountRaw = ((document.getElementById('gsdCqDiscount') || {}).value || '').trim();
                const canvasserName = ((document.getElementById('gsdCqCanvasserName') || {}).value || '').trim();

                if (!supplierId) {
                    const typed = document.getElementById('gsdCqSupplierNameSearch');
                    if (typed && typed.value.trim()) {
                        showToast('Select a supplier from the list.', 'error');
                    } else {
                        showToast('Please select a supplier.', 'error');
                    }
                    return;
                }
                if (!canvasserName) { showToast('Canvassed By Name is required.', 'error'); return; }
                if (priceRaw === '' || isNaN(parseFloat(priceRaw)) || parseFloat(priceRaw) < 0) {
                    showToast('Enter a valid unit price (≥ 0).', 'error');
                    return;
                }

                saveBtn.disabled = true;
                try {
                    const body = new URLSearchParams();
                    body.set('action', 'add_canvass_quote');
                    body.set('request_id', String(requestId));
                    body.set('requisition_line_id', String(lineId));
                    body.set('supplier_id', String(supplierId));
                    body.set('unit_price', priceRaw);
                    body.set('benefits', benefits);
                    body.set('discount_percent', discountRaw);
                    body.set('canvasser_name', canvasserName);

                    const res = await fetch(GSD_REQUESTS_API, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                        credentials: 'include',
                    });
                    const data = await res.json();
                    if (!data.success) {
                        showToast(data.message || 'Could not save quote.', 'error');
                        return;
                    }
                    showToast(data.message || 'Canvass quote saved.');
                    closeGsdCanvassQuoteModal();
                    await refreshGsdReviewView();
                } catch {
                    showToast('Network error.', 'error');
                } finally {
                    saveBtn.disabled = false;
                }
            });
        }
    }

    // ── Quote Modal ──

    function buildCanvasserQuoteModalSupplierList(suppliers) {
        const list = document.getElementById('cvQuoteModalSupplierList');
        if (!list) return;
        list.innerHTML = (suppliers || []).map((s) => {
            const img = s.supplier_image
                ? `<img src="${escapeHtml(s.supplier_image)}" alt="" class="supplier-dropdown-img" width="24" height="24" decoding="async">`
                : `<span class="supplier-dropdown-img supplier-dropdown-img-placeholder" aria-hidden="true"><i class="fas fa-store"></i></span>`;
            return `<div class="supplier-dropdown-item" data-supplier-id="${Number(s.supplier_id)}" data-supplier-name="${escapeHtml(s.supplier_name)}" role="option" tabindex="0">
                ${img}<span>${escapeHtml(s.supplier_name)}</span>
            </div>`;
        }).join('') || '<div class="supplier-dropdown-empty">No suppliers found.</div>';

        list.querySelectorAll('.supplier-dropdown-item').forEach((item) => {
            item.addEventListener('click', () => {
                const sid  = parseInt(item.dataset.supplierId, 10);
                const name = item.dataset.supplierName || '';
                document.getElementById('cvQuoteModalSupplierId').value = String(sid);
                document.getElementById('cvQuoteModalSupplierText').textContent = name;
                list.style.display = '';
            });
        });
    }

    function openCanvasserQuoteModal(lineId, lineName) {
        const modal = document.getElementById('cvCanvasserQuoteModal');
        if (!modal) return;

        document.getElementById('cvQuoteModalLineName').textContent = lineName;
        document.getElementById('cvQuoteModalLineId').value = String(lineId);

        // Reset quote form
        const sidEl = document.getElementById('cvQuoteModalSupplierId');
        const txtEl = document.getElementById('cvQuoteModalSupplierText');
        const priceEl = document.getElementById('cvQuoteModalPrice');
        const benEl = document.getElementById('cvQuoteModalBenefits');
        if (sidEl)  sidEl.value = '';
        if (txtEl)  txtEl.textContent = 'Select supplier…';
        if (priceEl) priceEl.value = '';
        if (benEl)  benEl.value = '';

        // Show existing quotes
        const line = (state.canvasserLines || []).find((l) => l.requisition_line_id === lineId);
        renderQuoteModalExisting(line ? line.canvassed_quotes : []);

        const list = document.getElementById('cvQuoteModalSupplierList');
        if (list) list.style.display = '';

        modal.style.display = 'flex';
        modal.removeAttribute('aria-hidden');
    }

    function renderQuoteModalExisting(quotes) {
        const wrap = document.getElementById('cvQuoteModalExistingQuotes');
        if (!wrap) return;
        if (!quotes || quotes.length === 0) {
            wrap.innerHTML = '<p class="cv-quote-modal-no-quotes">No canvassed quotes for this item yet.</p>';
            return;
        }
        wrap.innerHTML = quotes.map((q) => `
            <div class="cv-quote-modal-existing-chip">
                <span class="cv-quote-modal-existing-name">${escapeHtml(q.supplier_name)}</span>
                <span class="cv-quote-modal-existing-price">${fmtPhp(q.quoted_unit_price)}</span>
                ${q.benefits ? `<span class="cv-quote-modal-existing-note">${escapeHtml(q.benefits)}</span>` : ''}
                <button type="button" class="cv-quote-modal-existing-del" data-supplier-id="${Number(q.supplier_id)}" aria-label="Remove">×</button>
            </div>`).join('');

        wrap.querySelectorAll('.cv-quote-modal-existing-del').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const lineId = parseInt(document.getElementById('cvQuoteModalLineId').value, 10);
                const supplierId = parseInt(btn.dataset.supplierId, 10);
                btn.disabled = true;
                const data = await deleteCanvasserLineQuote(lineId, supplierId);
                if (!data.success) {
                    showToast(data.message || 'Could not remove.', 'error');
                    btn.disabled = false;
                    return;
                }
                showToast('Quote removed.', 'success');
                await refreshCanvasserView();
                const line = (state.canvasserLines || []).find((l) => l.requisition_line_id === lineId);
                renderQuoteModalExisting(line ? line.canvassed_quotes : []);
            });
        });
    }

    function closeCanvasserQuoteModal() {
        const modal = document.getElementById('cvCanvasserQuoteModal');
        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    async function saveCanvasserLineQuote(lineId, supplierId, price, benefits) {
        const body = new URLSearchParams();
        body.set('action', 'save_line_quote');
        body.set('request_id', String(requestId));
        body.set('requisition_line_id', String(lineId));
        body.set('supplier_id', String(supplierId));
        body.set('quoted_unit_price', String(price));
        body.set('benefits', benefits);

        const res = await fetch(CANVASSER_REQUESTS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'include',
        });
        return res.json();
    }

    async function deleteCanvasserLineQuote(lineId, supplierId) {
        const body = new URLSearchParams();
        body.set('action', 'delete_line_quote');
        body.set('request_id', String(requestId));
        body.set('requisition_line_id', String(lineId));
        body.set('supplier_id', String(supplierId));

        const res = await fetch(CANVASSER_REQUESTS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'include',
        });
        return res.json();
    }

    function initCanvasserQuoteModalListeners() {
        const modal     = document.getElementById('cvCanvasserQuoteModal');
        const backdrop  = document.getElementById('cvQuoteModalBackdrop');
        const closeBtn  = document.getElementById('cvQuoteModalClose');
        const cancelBtn = document.getElementById('cvQuoteModalCancel');
        const saveBtn   = document.getElementById('cvQuoteModalSave');
        const supBtn    = document.getElementById('cvQuoteModalSupplierBtn');
        const supList   = document.getElementById('cvQuoteModalSupplierList');

        if (closeBtn)  closeBtn.addEventListener('click',  closeCanvasserQuoteModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeCanvasserQuoteModal);
        if (backdrop)  backdrop.addEventListener('click',  closeCanvasserQuoteModal);

        if (supBtn && supList) {
            supBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                supList.style.display = supList.style.display === 'block' ? '' : 'block';
            });
            document.addEventListener('click', (e) => {
                if (!supBtn.contains(e.target) && !supList.contains(e.target)) {
                    supList.style.display = '';
                }
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                const lineId     = parseInt(document.getElementById('cvQuoteModalLineId').value, 10);
                const supplierId = parseInt((document.getElementById('cvQuoteModalSupplierId') || {}).value || '0', 10);
                const priceRaw   = ((document.getElementById('cvQuoteModalPrice') || {}).value || '').trim();
                const benefits   = ((document.getElementById('cvQuoteModalBenefits') || {}).value || '').trim();

                if (!supplierId) {
                    showToast('Please select a supplier.', 'error');
                    return;
                }
                if (priceRaw === '' || isNaN(parseFloat(priceRaw)) || parseFloat(priceRaw) < 0) {
                    showToast('Enter a valid unit price (≥ 0).', 'error');
                    return;
                }

                saveBtn.disabled = true;
                try {
                    const data = await saveCanvasserLineQuote(lineId, supplierId, priceRaw, benefits);
                    if (!data.success) {
                        showToast(data.message || 'Could not save quote.', 'error');
                        return;
                    }
                    showToast('✓ ' + (data.message || 'Quote saved.'));
                    await refreshCanvasserView();
                    const line = (state.canvasserLines || []).find((l) => l.requisition_line_id === lineId);
                    renderQuoteModalExisting(line ? line.canvassed_quotes : []);
                    // Reset form
                    document.getElementById('cvQuoteModalSupplierId').value = '';
                    document.getElementById('cvQuoteModalSupplierText').textContent = 'Select supplier…';
                    document.getElementById('cvQuoteModalPrice').value = '';
                    document.getElementById('cvQuoteModalBenefits').value = '';
                } catch {
                    showToast('Network error.', 'error');
                } finally {
                    saveBtn.disabled = false;
                }
            });
        }
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

    // ── Requester per-line preferred-quote view ──────────────────────────────────

    const state_req = { lines: [], suppliers: [] };

    async function loadRequesterLineView() {
        const tbody = document.getElementById('cvRequestedItemsTableBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:1rem;color:#64748b;">Loading items…</td></tr>';
        }
        try {
            const res = await fetch(
                `${api}?action=get_requester_line_view&request_id=${encodeURIComponent(String(requestId))}`,
                { credentials: 'include' }
            );
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not load items.', 'error');
                return;
            }
            state_req.lines     = data.lines || [];
            state_req.suppliers = data.suppliers || [];
            window._cvAvailableSuppliers = state_req.suppliers.map(function (s) {
                return { id: s.supplier_id, name: s.supplier_name || '', contact: s.contact_person || '', tin: s.tin || '', address: s.address || '', image: s.supplier_image || '' };
            });

            const h = data.header || {};
            const deptEl    = document.getElementById('cvOfficeDisplay');
            const facEl     = document.getElementById('cvFacilityDisplay');
            const dateEl    = document.getElementById('cvRequestDate');
            const purposeEl = document.getElementById('cvPurpose');
            if (deptEl)    deptEl.value    = h.office_name    || '—';
            if (facEl)     facEl.value     = h.facility_label || '—';
            if (dateEl)    dateEl.value    = h.request_date   || '';
            if (purposeEl) purposeEl.value = h.purpose        || '';

            renderRequesterLineItems(state_req.lines);
            window.CANVASS_ITEMS = state_req.lines.map(function (r) {
                return { id: r.requisition_line_id, name: r.item_name || '(unnamed)', qty: r.quantity || 1 };
            });
            if (window.CWIRMS_REQUESTER_SUPPLIER_QUOTES) {
                window.CWIRMS_REQUESTER_SUPPLIER_QUOTES.hydrateFromLines(state_req.lines);
            }
        } catch (err) {
            showToast('Network error loading items.', 'error');
        }
    }

    async function refreshRequesterLineView() {
        try {
            const res = await fetch(
                `${api}?action=get_requester_line_view&request_id=${encodeURIComponent(String(requestId))}`,
                { credentials: 'include' }
            );
            const data = await res.json();
            if (data.success) {
                state_req.lines     = data.lines     || [];
                state_req.suppliers = data.suppliers || state_req.suppliers;
                renderRequesterLineItems(state_req.lines);
                if (window.CWIRMS_REQUESTER_SUPPLIER_QUOTES) {
                    window.CWIRMS_REQUESTER_SUPPLIER_QUOTES.hydrateFromLines(state_req.lines);
                }
            }
        } catch { /* silent */ }
    }

    function renderRequesterLineItems(lines) {
        const tbody = document.getElementById('cvRequestedItemsTableBody');
        if (!tbody) return;
        if (!lines || lines.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:1.5rem;color:#64748b;">No items found on this requisition.</td></tr>';
            return;
        }

        let html    = '';
        let rowNum  = 0;
        let lastGrp = null;

        for (const row of lines) {
            const grp  = (row.group_label || '').trim();
            const name = row.item_name || '(unnamed)';

            if (grp && grp !== lastGrp) {
                lastGrp = grp;
                html += `<tr class="cv-line-group-header-row">
                    <td colspan="4" class="cv-line-group-header">
                        <i class="fas fa-layer-group" aria-hidden="true"></i> ${escapeHtml(grp)}
                    </td></tr>`;
            } else if (!grp && lastGrp !== '') {
                lastGrp = '';
            }

            rowNum++;
            const subParts = [];
            if (row.brand)         subParts.push(`<span class="ri-meta-chip">Brand: ${escapeHtml(row.brand)}</span>`);
            if (row.model)         subParts.push(`<span class="ri-meta-chip">Model: ${escapeHtml(row.model)}</span>`);
            if (row.specification) subParts.push(`<span class="ri-meta-chip ri-spec-chip">${escapeHtml(row.specification)}</span>`);
            const subHtml = subParts.length
                ? `<div class="ri-meta-chips">${subParts.join('')}</div>` : '';

            html += `<tr class="requested-item-row${grp ? ' in-group' : ''}">
                <td class="ri-num">${rowNum}</td>
                <td class="ri-name-cell">
                    <span class="ri-item-name">${escapeHtml(name)}</span>
                    ${subHtml}
                </td>
                <td>${escapeHtml(String(row.quantity || 1))}</td>
                <td>${escapeHtml(row.unit_type || 'unit')}</td>
            </tr>`;
        }

        tbody.innerHTML = html;
    }

    // ── Preferred-quote modal ─────────────────────────────────────────────────

    let _prefModalCurrentLineId = 0;

    function openRequesterPrefQuoteModal(lineId, lineName) {
        const modal = document.getElementById('cvRequesterPrefQuoteModal');
        if (!modal) return;
        _prefModalCurrentLineId = lineId;

        const nameEl = document.getElementById('cvPrefQuoteModalLineName');
        if (nameEl) nameEl.textContent = lineName;

        const lineIdEl = document.getElementById('cvPrefQuoteModalLineId');
        if (lineIdEl) lineIdEl.value = String(lineId);

        // Reset form
        const suppIdEl   = document.getElementById('cvPrefQuoteModalSupplierId');
        const suppTextEl = document.getElementById('cvPrefQuoteModalSupplierText');
        const priceEl    = document.getElementById('cvPrefQuoteModalPrice');
        const benefitsEl = document.getElementById('cvPrefQuoteModalBenefits');
        if (suppIdEl)   suppIdEl.value   = '';
        if (suppTextEl) suppTextEl.textContent = 'Select supplier…';
        if (priceEl)    priceEl.value    = '';
        if (benefitsEl) benefitsEl.value = '';

        // Populate supplier dropdown and ensure list starts hidden
        buildRequesterPrefQuoteSupplierList();
        const supList = document.getElementById('cvPrefQuoteModalSupplierList');
        if (supList) supList.style.display = '';

        // Show existing preferred quotes for this line
        const line = state_req.lines.find(l => l.requisition_line_id === lineId);
        renderPrefQuoteModalExisting(line ? (line.preferred_quotes || []) : []);

        modal.style.display = 'flex';
    }

    function closeRequesterPrefQuoteModal() {
        const modal = document.getElementById('cvRequesterPrefQuoteModal');
        if (modal) modal.style.display = 'none';
        _prefModalCurrentLineId = 0;
    }

    function buildRequesterPrefQuoteSupplierList() {
        const list = document.getElementById('cvPrefQuoteModalSupplierList');
        if (!list) return;
        const suppliers = state_req.suppliers || [];
        if (suppliers.length === 0) {
            list.innerHTML = '<div class="supplier-dropdown-empty">No suppliers found.</div>';
            return;
        }
        list.innerHTML = suppliers.map(s => {
            const imgSrc = escapeHtml(getSupplierImageUrl(s.supplier_image));
            const img = s.supplier_image
                ? `<img src="${imgSrc}" alt="" class="supplier-dropdown-img" width="24" height="24" decoding="async" onerror="this.style.display='none'">`
                : `<span class="supplier-dropdown-img supplier-dropdown-img-placeholder" aria-hidden="true"><i class="fas fa-store"></i></span>`;
            return `<button type="button" class="supplier-dropdown-item" data-supplier-id="${s.supplier_id}" data-supplier-name="${escapeHtml(s.supplier_name)}">
                ${img}<span>${escapeHtml(s.supplier_name)}</span></button>`;
        }).join('');

        list.querySelectorAll('.supplier-dropdown-item').forEach(btn => {
            btn.addEventListener('click', () => {
                const suppIdEl   = document.getElementById('cvPrefQuoteModalSupplierId');
                const suppTextEl = document.getElementById('cvPrefQuoteModalSupplierText');
                if (suppIdEl)   suppIdEl.value          = btn.dataset.supplierId;
                if (suppTextEl) suppTextEl.textContent   = btn.dataset.supplierName;
                list.style.display = '';
            });
        });
    }

    function renderPrefQuoteModalExisting(quotes) {
        const wrap = document.getElementById('cvPrefQuoteModalExistingWrap');
        const cont = document.getElementById('cvPrefQuoteModalExistingQuotes');
        if (!cont) return;
        if (!quotes || quotes.length === 0) {
            if (wrap) wrap.style.display = 'none';
            return;
        }
        if (wrap) wrap.style.display = '';
        cont.innerHTML = quotes.map(q => {
            const price = parseFloat(q.quoted_unit_price || 0);
            return `<div class="cv-quote-modal-existing-row">
                <span class="cv-quote-modal-existing-supplier">${escapeHtml(q.supplier_name)}</span>
                <span class="cv-quote-modal-existing-price">${fmtPhp(price)}</span>
                ${q.benefits ? `<span class="cv-quote-modal-existing-note">${escapeHtml(q.benefits)}</span>` : ''}
                <button type="button" class="cv-quote-modal-existing-del" data-supplier-id="${q.supplier_id}" aria-label="Remove">
                    <i class="fas fa-trash-alt" aria-hidden="true"></i>
                </button>
            </div>`;
        }).join('');

        cont.querySelectorAll('.cv-quote-modal-existing-del').forEach(btn => {
            btn.addEventListener('click', async () => {
                const supplierId = parseInt(btn.dataset.supplierId || '0', 10);
                await deleteRequesterPrefQuote(_prefModalCurrentLineId, supplierId);
                closeRequesterPrefQuoteModal();
            });
        });
    }

    async function saveRequesterPrefQuote() {
        const lineId     = _prefModalCurrentLineId;
        const suppIdEl   = document.getElementById('cvPrefQuoteModalSupplierId');
        const priceEl    = document.getElementById('cvPrefQuoteModalPrice');
        const benefitsEl = document.getElementById('cvPrefQuoteModalBenefits');
        const saveBtn    = document.getElementById('cvPrefQuoteModalSave');

        const supplierId = parseInt((suppIdEl && suppIdEl.value) || '0', 10);
        const price      = (priceEl && priceEl.value.trim()) || '';
        const benefits   = (benefitsEl && benefitsEl.value.trim()) || '';

        if (!lineId || !supplierId) {
            showToast('Please select a supplier.', 'error');
            return;
        }
        if (price === '' || isNaN(parseFloat(price)) || parseFloat(price) < 0) {
            showToast('Please enter a valid unit price.', 'error');
            return;
        }

        if (saveBtn) saveBtn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('request_id',          String(requestId));
            fd.append('requisition_line_id', String(lineId));
            fd.append('supplier_id',         String(supplierId));
            fd.append('quoted_unit_price',   price);
            fd.append('benefits',            benefits);

            const res  = await fetch(`${api}?action=save_preferred_quote`, {
                method: 'POST', credentials: 'include', body: fd,
            });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not save quote.', 'error');
                return;
            }
            showToast('Preferred quote saved.');
            closeRequesterPrefQuoteModal();
            await refreshRequesterLineView();
        } catch {
            showToast('Network error saving quote.', 'error');
        } finally {
            if (saveBtn) saveBtn.disabled = false;
        }
    }

    async function deleteRequesterPrefQuote(lineId, supplierId) {
        if (!lineId || !supplierId) return;
        const fd = new FormData();
        fd.append('request_id',          String(requestId));
        fd.append('requisition_line_id', String(lineId));
        fd.append('supplier_id',         String(supplierId));
        try {
            const res  = await fetch(`${api}?action=delete_preferred_quote`, {
                method: 'POST', credentials: 'include', body: fd,
            });
            const data = await res.json();
            if (!data.success) {
                showToast(data.message || 'Could not remove quote.', 'error');
                return;
            }
            showToast('Preferred quote removed.');
            await refreshRequesterLineView();
        } catch {
            showToast('Network error removing quote.', 'error');
        }
    }

    function initRequesterPrefQuoteModalListeners() {
        const backdrop = document.getElementById('cvPrefQuoteModalBackdrop');
        if (backdrop) backdrop.addEventListener('click', closeRequesterPrefQuoteModal);
        const closeBtn = document.getElementById('cvPrefQuoteModalClose');
        if (closeBtn) closeBtn.addEventListener('click', closeRequesterPrefQuoteModal);
        const cancelBtn = document.getElementById('cvPrefQuoteModalCancel');
        if (cancelBtn) cancelBtn.addEventListener('click', closeRequesterPrefQuoteModal);
    
        const saveBtn = document.getElementById('cvPrefQuoteModalSave');
        if (saveBtn) saveBtn.addEventListener('click', saveRequesterPrefQuote);
    
        const dropdownBtn = document.getElementById('cvPrefQuoteModalSupplierBtn');
        const dropdownList = document.getElementById('cvPrefQuoteModalSupplierList');
        if (dropdownBtn && dropdownList) {
            dropdownBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownList.style.display = dropdownList.style.display === 'block' ? '' : 'block';
            });

            document.addEventListener('click', (e) => {
                const modal = document.getElementById('cvRequesterPrefQuoteModal');
                if (!modal || modal.style.display === 'none') return;
                if (dropdownBtn.contains(e.target) || dropdownList.contains(e.target)) return;
                dropdownList.style.display = '';
            });
        }
    
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modal = document.getElementById('cvRequesterPrefQuoteModal');
                if (modal && modal.style.display !== 'none') {
                    closeRequesterPrefQuoteModal();
                }
            }
        });
    }

    // ── End requester preferred-quote view ────────────────────────────────────

    async function loadForm() {
    state.isHydrating = true;
    try {
        // Canvassers use the new flat-table endpoint (reads from requisition_line / requisition_line_quotes)
        if (canvasserRegister) {
            await loadCanvasserView();
            return;
        }
        if (gsdReviewView) {
            await loadGsdReviewView();
            return;
        }
        if (gsdOutcomeReadonlyView) {
            await loadGsdOutcomeReadonlyView();
            return;
        }
        // Requesters (owners) use per-line preferred-quote view
        if (requesterEditView) {
            await loadRequesterLineView();
            return;
        }

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
        window._cvAvailableSuppliers = state.availableSuppliers.map(function (s) {
            return { id: s.supplier_id, name: s.supplier_name || '', contact: s.contact_person || '', tin: s.tin || '', address: formatSupplierLocation(s) || '' };
        });

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
        window._cvItemsSnapshot = state.items.map((it, i) => ({ index: i, name: it.name, requisition_line_id: it.requisition_line_id }));

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
        await loadPreferredSuppliers();
        hydrateCanvassedSupplierItemsFromSavedPrices();
        
        // Apply approval BEFORE rendering supplier table
        applyCanvassApproval(data.approval);
        
        // Now render the supplier table
        renderSupplierTable();
        renderSupplierDropdown();
        state.canvassedSupplierCount = state.selectedSuppliers.length;
        applyGsdReadonlyUi();
        if (typeof window.__imrmsCvReviewerAfterLoad === 'function') {
            try {
                window.__imrmsCvReviewerAfterLoad();
            } catch {
                /* no-op */
            }
        }
    } catch {
        showToast('Network error.', 'error');
    } finally {
        state.isHydrating = false;
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
        if (!canvasserRegister && !requesterEditView) {
            return;
        }
        // Quotes are now saved per-line via the + Quote modal. "Save draft" just navigates back.
        showToast('Preferred quotes are saved automatically. Returning to status page…');
        setTimeout(() => {
            window.location.href = 'dean_requisition_status_progress.php?rid=' + requestId;
        }, 1200);
    }

    async function completeCanvassing() {
        if (!canvasserRegister) {
            return;
        }
        if (!requestId) {
            showToast('Missing request.', 'error');
            return;
        }

        // Validate every line has at least one canvassed quote
        const lines = state.canvasserLines || [];
        if (lines.length === 0) {
            showToast('No items found on this requisition.', 'error');
            return;
        }
        const unquoted = lines.filter((l) => !l.canvassed_quotes || l.canvassed_quotes.length === 0);
        if (unquoted.length > 0) {
            const names = unquoted.slice(0, 3).map((l) => l.item_name || 'item').join(', ');
            showToast(
                `Add at least one supplier quote for every item before completing. Missing: ${names}${unquoted.length > 3 ? ` and ${unquoted.length - 3} more` : ''}.`,
                'error'
            );
            return;
        }

        const ok = await showConfirmModal(
            'Mark canvassing as complete? All your supplier quotes are already saved. The canvass step will be approved and the request status will be set to Ongoing.'
        );
        if (!ok) {
            return;
        }
        setCanvasserActionBusy(true);
        try {
            // Quotes are saved per-line — just record the canvass approval
            const data = await postCanvasApproval('accept');
            if (!data.success) {
                showToast(
                    data.message || 'Could not record canvass completion. Try again.',
                    'error'
                );
                return;
            }
            applyCanvassOptimisticFromApi(data);
            showToast(data.message || 'Canvassing complete.');
            await loadCanvasserView();
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
            await loadCanvasserView();
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
        if (requesterEditView) {
            if (state.autoSaveTimer) {
                clearRequesterAutosaveTimer();
            }
            if (cvSaveBtn) cvSaveBtn.disabled = true;
            try {
                const flush = window.CWIRMS_REQUESTER_SUPPLIER_QUOTES;
                if (flush) {
                    const result = await flush.flushAllQuotes();
                    if (!result.ok) {
                        showToast(result.message || 'Could not save supplier quotes.', 'error');
                        return;
                    }
                }
                showToast('Supplier quotes saved.');
                markFormSaved();
                setTimeout(() => {
                    window.location.href = 'dean_requisition_status_progress.php?rid=' + requestId;
                }, 1200);
            } catch {
                showToast('Network error saving quotes.', 'error');
            } finally {
                if (cvSaveBtn) cvSaveBtn.disabled = false;
            }
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
            markFormSaved();
            await loadForm();
            markFormSaved();
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
            state.selectedSuppliers.push({
                supplier_id: full.supplier_id,
                supplier_name: full.supplier_name,
                supplier_image: full.supplier_image,
                prices: {},
                benefits: '',
                discounts: [],
            });
            setCanvassedSupplierItemIndices(full.supplier_id, []);
            setSupplierPickerHighlight(null);
            if (cvSupplierDropdown) {
                cvSupplierDropdown.classList.remove('open');
            }
            renderSupplierTable();
            scheduleRequesterAutosave();
        });
    }

    if (cvCanvassedCards) {
        cvCanvassedCards.addEventListener('change', (e) => {
            if (e.target.closest('.cv-canvas-add-item-select')) {
                return;
            }
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
            const addItemBtn = e.target.closest('.cv-canvas-add-item-btn');
            if (addItemBtn) {
                const sid = Number(addItemBtn.getAttribute('data-cv-supplier-id') || '0');
                const card = addItemBtn.closest('.cv-pref-card');
                const select = card ? card.querySelector('.cv-canvas-add-item-select') : null;
                const itemIndex = select ? Number(select.value) : NaN;
                if (!sid || Number.isNaN(itemIndex) || itemIndex < 0) {
                    showToast('Select an item to add.', 'error');
                    return;
                }
                if (addCanvassedSupplierItem(sid, itemIndex)) {
                    renderSupplierTable();
                }
                return;
            }
            const removeItemBtn = e.target.closest('.cv-canvas-remove-item-btn');
            if (removeItemBtn) {
                const sid = Number(removeItemBtn.getAttribute('data-cv-supplier-id') || '0');
                const itemIndex = Number(removeItemBtn.getAttribute('data-cv-item-index') || '0');
                if (!sid || Number.isNaN(itemIndex)) {
                    return;
                }
                removeCanvassedSupplierItem(sid, itemIndex);
                renderSupplierTable();
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
        } else if (requesterEditView && window.CWIRMS_REQUESTER_SUPPLIER_QUOTES) {
            clearRequesterAutosaveTimer();
            cvSaveDraftBtn.disabled = true;
            window.CWIRMS_REQUESTER_SUPPLIER_QUOTES.flushAllQuotes()
                .then((result) => {
                    if (!result.ok) {
                        showToast(result.message || 'Save failed.', 'error');
                        return;
                    }
                    showToast('Supplier quotes saved.');
                    markFormSaved();
                })
                .catch(() => showToast('Network error.', 'error'))
                .finally(() => { cvSaveDraftBtn.disabled = false; });
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
                    markFormSaved();
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
        // GSD preferred-quote photo thumbnails (section A) — delegated on the card so it works
        // even after renderGsdSectionA re-renders the container.
        card.addEventListener('click', (e) => {
            const btn = e.target.closest('.gsd-cv-quote-photo-btn');
            if (!btn) return;
            const src = btn.getAttribute('data-photo-url') || '';
            if (src) openCvQuotePhotoLightbox(src);
        });
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
                            markFormSaved();
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
    if (canvasserRegister) {
        initCanvasserQuoteModalListeners();
    }
    if (requesterEditView) {
        initRequesterPrefQuoteModalListeners();
    }

    window.CWIRMSCanvassForm = {
        buildPricingSnapshot: buildCanvassPricingSnapshot,
        formatPricingOverviewQtyLabel,
        gsdHasSectionBData,
        getGsdLines: () => state.gsdLines || [],
    };

    loadForm();

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
        applyApproval(approval) {
            applyCanvassApproval(approval);
        },
    };
})();

// ── Quote-modal wiring (requester view: "+ Add Quote" → supplier card) ────────
(function () {
    var _qmLine = null;

    // Open modal on "+ Add Quote" button click
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-open-quote-modal');
        if (!btn) return;
        _qmLine = {
            lineId: btn.dataset.lineId,
            itemName: btn.dataset.itemName,
            itemQty: btn.dataset.itemQty
        };

        // Populate supplier dropdown from currently rendered preferred-supplier cards
        var sel = document.getElementById('qm-supplier-select');
        if (!sel) return;
        sel.innerHTML = '<option value="">Select supplier…<\/option>';
        document.querySelectorAll('article.cv-pref-card[data-supplier-id]').forEach(function (card) {
            var nameEl = card.querySelector('.cv-pref-name');
            var name = nameEl ? nameEl.textContent.trim() : 'Supplier';
            var opt = document.createElement('option');
            opt.value = card.dataset.supplierId;
            opt.textContent = name;
            sel.appendChild(opt);
        });

        var nameEl  = document.getElementById('qm-item-name');
        var qtyEl   = document.getElementById('qm-item-qty');
        var priceEl = document.getElementById('qm-price-input');
        if (nameEl)  nameEl.textContent = _qmLine.itemName;
        if (qtyEl)   qtyEl.textContent  = _qmLine.itemQty + ' unit';
        if (priceEl) priceEl.value      = '';

        var modal = document.getElementById('quote-modal');
        if (modal) modal.style.display = 'flex';
    });

    // Close handlers
    var closeBtn  = document.getElementById('quote-modal-close');
    var cancelBtn = document.getElementById('qm-cancel');
    var modal     = document.getElementById('quote-modal');
    if (closeBtn)  closeBtn.addEventListener('click', _qmClose);
    if (cancelBtn) cancelBtn.addEventListener('click', _qmClose);
    if (modal)     modal.addEventListener('click', function (e) { if (e.target === this) _qmClose(); });

    function _qmClose() {
        var m = document.getElementById('quote-modal');
        if (m) m.style.display = 'none';
        _qmLine = null;
    }

    // Save
    var saveBtn = document.getElementById('qm-save');
    if (saveBtn) saveBtn.addEventListener('click', function () {
        var supplierId = (document.getElementById('qm-supplier-select') || {}).value;
        var price      = (document.getElementById('qm-price-input')     || {}).value;
        if (!supplierId) { alert('Please select a supplier.'); return; }
        if (!price || parseFloat(price) <= 0) { alert('Please enter a valid price.'); return; }

        var card = document.querySelector('article.cv-pref-card[data-supplier-id="' + supplierId + '"]');
        if (!card) { alert('Supplier card not found on this page.'); return; }

        // Resolve canvass item index via the snapshot exposed by loadForm()
        var targetIndex = null;
        var snap = window._cvItemsSnapshot || [];
        var lineId = Number(_qmLine && _qmLine.lineId);
        if (lineId) {
            var hit = snap.find(function (it) { return Number(it.requisition_line_id) === lineId; });
            if (hit) targetIndex = hit.index;
        }
        // Fallback: scan any add-item select option text for a name match
        if (targetIndex === null && _qmLine) {
            document.querySelectorAll('.cv-pref-add-item-select').forEach(function (s) {
                if (targetIndex !== null) return;
                Array.from(s.options).forEach(function (o) {
                    if (o.value !== '' && o.textContent.trim() === _qmLine.itemName) {
                        targetIndex = parseInt(o.value, 10);
                    }
                });
            });
        }
        if (targetIndex === null) {
            alert('Could not match "' + (_qmLine ? _qmLine.itemName : '') + '" to a canvass item.\nMake sure canvass items are set up first.');
            return;
        }

        var existing = card.querySelector('.cv-preferred-price-input[data-cv-item-index="' + targetIndex + '"]');
        if (existing) {
            // Item already in card — just update price
            existing.value = parseFloat(price).toFixed(2);
            existing.dispatchEvent(new Event('input', { bubbles: true }));
            _qmClose();
        } else {
            // Add item to card first, then set price after re-render
            var addSel = card.querySelector('.cv-pref-add-item-select');
            var addBtn = card.querySelector('.cv-pref-add-item-btn');
            if (!addSel || !addBtn) { alert('Cannot add item — the supplier card may have all items already linked.'); return; }
            addSel.value = String(targetIndex);
            addBtn.click();
            setTimeout(function () {
                var inp = card.querySelector('.cv-preferred-price-input[data-cv-item-index="' + targetIndex + '"]');
                if (inp) {
                    inp.value = parseFloat(price).toFixed(2);
                    inp.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }, 150);
            _qmClose();
        }
    });

})();

// ── Supplier Quotes section (requester view) ─────────────────────────────────
(function () {
    var activeSuppliers  = [];
    var reqItems         = [];
    var _selectedSupplier = null;
    var _saveTimers      = {};
    var card             = document.getElementById('canvassCard');
    var _canvassApi      = (card && card.dataset.api) || '../../app/api/canvass_detail.php';
    var _requestId       = card ? parseInt(card.dataset.requestId || '0', 10) : 0;

    function openPhotoLightbox(src) {
        var lb  = document.getElementById('cvQuotePhotoLightbox');
        var img = document.getElementById('cvQuotePhotoLightboxImg');
        if (!lb || !img || !src) return;
        img.src = src;
        lb.classList.remove('hidden');
        lb.setAttribute('aria-hidden', 'false');
    }

    function showSupToast(message, type) {
        var el = document.getElementById('cvFormToast');
        if (!el) return;
        el.textContent = message;
        el.className = 'toast ' + (type === 'error' ? 'error' : 'success');
        el.style.display = 'block';
        setTimeout(function () { el.style.display = 'none'; }, 4200);
    }

    function hydrateFromLines(lines) {
        var bySup = {};
        (lines || []).forEach(function (line) {
            var lineId = line.requisition_line_id;
            (line.preferred_quotes || []).forEach(function (q) {
                var sid = String(q.supplier_id);
                if (!bySup[sid]) {
                    bySup[sid] = {
                        id:      sid,
                        name:    q.supplier_name || '',
                        contact: '',
                        tin:     '',
                        address: '',
                        image:   q.supplier_image || '',
                        items:   [],
                    };
                }
                bySup[sid].items.push({
                    itemId: lineId,
                    price:  q.quoted_unit_price != null ? String(q.quoted_unit_price) : '',
                    photo:  q.quote_photo ? resolveImgPath(String(q.quote_photo)) : null,
                    saved:  true,
                });
            });
        });
        activeSuppliers = Object.keys(bySup).map(function (k) { return bySup[k]; });
        renderSection();
    }

    async function saveQuoteToServer(supplierId, lineId, price) {
        if (!_requestId || !supplierId || !lineId) {
            return { success: false, message: 'Missing save fields.' };
        }
        var priceStr = String(price || '').trim();
        if (priceStr === '' || isNaN(parseFloat(priceStr)) || parseFloat(priceStr) < 0) {
            return { success: false, message: 'Unit price must be a valid non-negative number.' };
        }
        var fd = new FormData();
        fd.append('request_id', String(_requestId));
        fd.append('requisition_line_id', String(lineId));
        fd.append('supplier_id', String(supplierId));
        fd.append('quoted_unit_price', priceStr);
        fd.append('benefits', '');
        var res = await fetch(_canvassApi + '?action=save_preferred_quote', {
            method: 'POST',
            credentials: 'include',
            body: fd,
        });
        return res.json();
    }

    async function deleteQuoteFromServer(supplierId, lineId) {
        if (!_requestId || !supplierId || !lineId) {
            return { success: false };
        }
        var fd = new FormData();
        fd.append('request_id', String(_requestId));
        fd.append('requisition_line_id', String(lineId));
        fd.append('supplier_id', String(supplierId));
        var res = await fetch(_canvassApi + '?action=delete_preferred_quote', {
            method: 'POST',
            credentials: 'include',
            body: fd,
        });
        return res.json();
    }

    function scheduleQuoteSave(supplierId, lineId, price) {
        var key = supplierId + ':' + lineId;
        if (_saveTimers[key]) {
            clearTimeout(_saveTimers[key]);
        }
        _saveTimers[key] = setTimeout(function () {
            _saveTimers[key] = null;
            var priceStr = String(price || '').trim();
            if (priceStr === '') return;
            saveQuoteToServer(supplierId, lineId, priceStr).then(function (data) {
                if (!data.success) {
                    showSupToast(data.message || 'Could not save quote.', 'error');
                    return;
                }
                var sup = activeSuppliers.find(function (s) { return s.id === String(supplierId); });
                if (sup) {
                    var it = sup.items.find(function (i) { return i.itemId === lineId; });
                    if (it) it.saved = true;
                }
            }).catch(function () {
                showSupToast('Network error saving quote.', 'error');
            });
        }, 900);
    }

    async function flushAllQuotes() {
        // Validate: every item that was added must have a price > 0.
        var missingPrices = [];
        activeSuppliers.forEach(function (sup) {
            sup.items.forEach(function (it) {
                var priceNum = parseFloat(String(it.price || '').trim());
                if (isNaN(priceNum) || priceNum <= 0) {
                    var req = (window.CANVASS_ITEMS || []).find(function (r) { return r.id === it.itemId; });
                    missingPrices.push({ suppId: sup.id, itemId: it.itemId, label: (req ? req.name : 'Item') + ' — ' + sup.name });
                }
            });
        });
        if (missingPrices.length) {
            // Highlight the offending inputs
            missingPrices.forEach(function (m) {
                var inp = document.querySelector(
                    '.supplier-price-input[data-supplier-id="' + m.suppId + '"][data-item-id="' + m.itemId + '"]'
                );
                if (inp) {
                    inp.classList.add('is-invalid');
                    inp.addEventListener('input', function onFix() {
                        inp.classList.remove('is-invalid');
                        inp.removeEventListener('input', onFix);
                    }, { once: true });
                }
            });
            return {
                ok: false,
                message: 'Please enter a quote price (greater than 0) for: ' +
                    missingPrices.map(function (m) { return m.label; }).join(', ') + '.',
            };
        }

        var tasks = [];
        activeSuppliers.forEach(function (sup) {
            sup.items.forEach(function (it) {
                var priceStr = String(it.price || '').trim();
                if (priceStr !== '') {
                    tasks.push(saveQuoteToServer(sup.id, it.itemId, priceStr));
                }
            });
        });
        if (!tasks.length) {
            return { ok: true, message: 'Nothing to save.' };
        }
        var results = await Promise.all(tasks);
        var failed = results.find(function (r) { return !r || !r.success; });
        if (failed) {
            return { ok: false, message: (failed && failed.message) || 'Could not save one or more quotes.' };
        }
        activeSuppliers.forEach(function (sup) {
            sup.items.forEach(function (it) {
                if (String(it.price || '').trim() !== '') {
                    it.saved = true;
                }
            });
        });
        return { ok: true };
    }

    window.CWIRMS_REQUESTER_SUPPLIER_QUOTES = {
        hydrateFromLines: hydrateFromLines,
        flushAllQuotes: flushAllQuotes,
    };

    // Mirror resolvePublicUploadUrl from main IIFE (not in scope here)
    function resolveImgPath(path) {
        if (!path) return '';
        var raw = String(path).trim();
        if (!raw) return '';
        if (/^(https?:|blob:|data:)/i.test(raw)) return raw;
        var n = raw.replace(/\\/g, '/');
        n = n.replace(/^app\/api\/public\//i, '');
        n = n.replace(/^public\//i, '');
        if (n.startsWith('../')) return n;
        return '../' + n.replace(/^\/+/, '');
    }

    function getSupplierImg(imageField) {
        return imageField ? resolveImgPath(imageField) : '';
    }

    function getInitials(name) {
        var words = (name || '').trim().split(/\s+/);
        return ((words[0] || '')[0] + ((words[1] || '')[0] || '')).toUpperCase();
    }

    function escHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderSelectionPreview(s) {
        var panel = document.getElementById('supplier-combobox-selection');
        var body  = document.getElementById('supplier-combobox-selection-body');
        if (!panel || !body || !s) {
            if (panel) panel.hidden = true;
            return;
        }
        var imgSrc = getSupplierImg(s.image);
        var avatarHtml = imgSrc
            ? '<img src="' + escHtml(imgSrc) + '" class="sup-combo-logo" alt=""' +
              ' onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'inline-flex\'">' +
              '<span class="sup-combo-initials" style="display:none">' + escHtml(getInitials(s.name)) + '</span>'
            : '<span class="sup-combo-initials">' + escHtml(getInitials(s.name)) + '</span>';
        var meta = [s.contact, s.tin ? ('TIN ' + s.tin) : '', s.address].filter(Boolean).join(' · ');
        body.innerHTML =
            avatarHtml +
            '<div><div class="supplier-combobox-selection-name">' + escHtml(s.name || '') + '</div>' +
            (meta ? '<div class="supplier-combobox-selection-meta">' + escHtml(meta) + '</div>' : '') +
            '</div>';
        panel.hidden = false;
    }

    function hideSelectionPreview() {
        var panel = document.getElementById('supplier-combobox-selection');
        if (panel) panel.hidden = true;
    }

    function normalizeSupplierEntry(raw) {
        if (!raw) return null;
        var id = raw.id != null ? raw.id : raw.supplier_id;
        if (id == null || id === '') return null;
        return {
            id:      String(id),
            name:    raw.name || raw.supplier_name || '',
            contact: raw.contact || raw.contact_person || '',
            tin:     raw.tin || '',
            address: raw.address || '',
            image:   raw.image || raw.supplier_image || '',
        };
    }

    function pickAndAddSupplier(raw) {
        var s = normalizeSupplierEntry(raw);
        if (!s) {
            showSupToast('Invalid supplier selection.', 'error');
            return false;
        }
        if (activeSuppliers.some(function (x) { return x.id === s.id; })) {
            showSupToast('"' + s.name + '" is already in your quotation.', 'error');
            return false;
        }
        activeSuppliers.push({
            id:      s.id,
            name:    s.name,
            contact: s.contact,
            tin:     s.tin,
            address: s.address,
            image:   s.image,
            items:   [],
        });
        renderSection();
        showSupToast('"' + s.name + '" added to quotation.');
        return true;
    }

    // ── Supplier modal — GSD-style ────────────────────────────────────────────
    var _cvSupRegPanelOpen = false;

    function _cvSupSetHint(text) {
        var el = document.getElementById('supplier-modal-hint-text');
        if (el) el.textContent = text;
    }

    function _cvSupCheckSaveState() {
        var btn = document.getElementById('supplier-modal-save');
        if (!btn) return;
        var nameVal = ((document.getElementById('sup-reg-name') || {}).value || '').trim();
        btn.disabled = !(_selectedSupplier || (_cvSupRegPanelOpen && nameVal.length > 0));
    }

    function _cvSupCloseRegPanel() {
        _cvSupRegPanelOpen = false;
        var panel = document.getElementById('cv-sup-reg-panel');
        if (panel) panel.style.display = 'none';
        ['sup-reg-name','sup-reg-contact','sup-reg-phone','sup-reg-email','sup-reg-address','sup-reg-tin'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        _cvSupCheckSaveState();
    }

    function _cvSupOpenRegPanel(prefill) {
        _cvSupRegPanelOpen = true;
        _selectedSupplier = null;
        var panel = document.getElementById('cv-sup-reg-panel');
        if (panel) panel.style.display = '';
        var nameInp = document.getElementById('sup-reg-name');
        if (nameInp) { nameInp.value = prefill || ''; setTimeout(function () { nameInp.focus(); }, 40); }
        _cvSupSetHint('Fill in supplier details');
        _cvSupCheckSaveState();
    }

    function _cvSupRenderResults(query) {
        var sups    = window._cvAvailableSuppliers || [];
        var usedIds = activeSuppliers.map(function (s) { return String(s.id); });
        var q       = (query || '').trim().toLowerCase();
        var container = document.getElementById('supplier-combobox-list');
        if (!container) return;

        var filtered = sups.filter(function (s) {
            var sid = String(s.id != null ? s.id : s.supplier_id || '');
            if (!sid || usedIds.includes(sid)) return false;
            if (!q) return true;
            return (s.name || s.supplier_name || '').toLowerCase().includes(q);
        });

        container.innerHTML = '';

        if (!filtered.length) {
            container.innerHTML = '<div class="gsd-sup-empty-state">' +
                '<i class="fas fa-search-slash" aria-hidden="true"></i>' +
                '<span class="gsd-sup-empty-text">No supplier found' + (q ? ' for &ldquo;' + escHtml(q) + '&rdquo;' : '') + '</span>' +
                '<button type="button" class="gsd-sup-empty-reg-btn">' +
                (q ? '+ Register &ldquo;' + escHtml(q) + '&rdquo;' : '+ Register new supplier') +
                '</button></div>';
            container.querySelector('.gsd-sup-empty-reg-btn').addEventListener('click', function () {
                _cvSupOpenRegPanel(q);
            });
            return;
        }

        filtered.forEach(function (raw) {
            var s = normalizeSupplierEntry(raw);
            if (!s) return;
            var imgSrc = getSupplierImg(s.image);
            var initials = escHtml(getInitials(s.name));
            var avatarHtml = imgSrc
                ? '<div class="gsd-sup-avatar-sq"><img src="' + escHtml(imgSrc) + '" class="gsd-sup-av-img" alt=""' +
                  ' onerror="this.style.display=\'none\';this.parentElement.textContent=\'' + initials + '\'"></div>'
                : '<div class="gsd-sup-avatar-sq">' + initials + '</div>';
            var meta = [s.tin ? 'TIN: ' + s.tin : '', s.address].filter(Boolean).join(' · ');
            var isSelected = _selectedSupplier && String(_selectedSupplier.id) === String(s.id);
            var row = document.createElement('div');
            row.className = 'gsd-sup-row' + (isSelected ? ' gsd-sup-selected' : '');
            row.innerHTML = avatarHtml +
                '<div class="gsd-sup-row-info">' +
                '<div class="gsd-sup-row-name">' + escHtml(s.name) + '</div>' +
                (meta ? '<div class="gsd-sup-row-meta">' + escHtml(meta) + '</div>' : '') +
                '</div>' +
                '<i class="fas fa-circle-check gsd-sup-row-check" aria-hidden="true"></i>';
            row.addEventListener('click', function () {
                _selectedSupplier = s;
                _cvSupCloseRegPanel();
                _cvSupRenderResults(document.getElementById('supplier-combobox-input') ? document.getElementById('supplier-combobox-input').value : '');
                _cvSupSetHint('"' + s.name + '" selected');
                _cvSupCheckSaveState();
            });
            container.appendChild(row);
        });

        var hintRow = document.createElement('div');
        hintRow.className = 'gsd-sup-register-hint-row';
        hintRow.innerHTML = '<i class="fas fa-circle-info" aria-hidden="true"></i> Not here?&nbsp;' +
            '<button type="button" class="gsd-sup-register-hint-btn">Register as new supplier</button>';
        hintRow.querySelector('.gsd-sup-register-hint-btn').addEventListener('click', function () {
            var inp = document.getElementById('supplier-combobox-input');
            _cvSupOpenRegPanel(inp ? inp.value.trim() : '');
        });
        container.appendChild(hintRow);
    }

    // ── Modal ─────────────────────────────────────────────────────────────────
    function openModal() {
        _selectedSupplier = null;
        _cvSupCloseRegPanel();
        var inp = document.getElementById('supplier-combobox-input');
        if (inp) inp.value = '';
        _cvSupSetHint('Search to find a supplier');
        _cvSupCheckSaveState();
        _cvSupRenderResults('');
        var ov = document.getElementById('supplier-modal-overlay');
        if (ov) ov.style.display = 'flex';
        if (inp) setTimeout(function () { inp.focus(); }, 60);
    }

    function closeModal() {
        var ov = document.getElementById('supplier-modal-overlay');
        if (ov) ov.style.display = 'none';
        _cvSupCloseRegPanel();
    }

    function addSupplier() {
        if (_selectedSupplier) {
            pickAndAddSupplier(_selectedSupplier);
            closeModal();
            return;
        }
        if (!_cvSupRegPanelOpen) return;
        var name = ((document.getElementById('sup-reg-name') || {}).value || '').trim();
        if (!name) { showSupToast('Supplier name is required.', 'error'); return; }
        var emailVal = ((document.getElementById('sup-reg-email') || {}).value || '').trim();
        if (emailVal && !isValidEmail(emailVal)) { showSupToast('Invalid email format.', 'error'); return; }
        if (!_requestId) { showSupToast('Missing request reference. Reload and try again.', 'error'); return; }
        var btn = document.getElementById('supplier-modal-save');
        if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
        var body = new URLSearchParams();
        body.set('action', 'add_preferred');
        body.set('request_id', String(_requestId));
        body.set('supplier_name', name);
        body.set('contact_person', ((document.getElementById('sup-reg-contact') || {}).value || ''));
        body.set('phone_number',   ((document.getElementById('sup-reg-phone')   || {}).value || ''));
        body.set('email',          emailVal);
        body.set('address',        ((document.getElementById('sup-reg-address') || {}).value || ''));
        body.set('tin',            ((document.getElementById('sup-reg-tin')     || {}).value || ''));
        body.set('shop_url', ''); body.set('city', ''); body.set('country', ''); body.set('postal_code', '');
        fetch(_canvassApi, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(), credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) { showSupToast(data.message || 'Could not register supplier.', 'error'); return; }
            var newSup = data.supplier || {};
            var entry = normalizeSupplierEntry({ supplier_id: newSup.supplier_id, supplier_name: newSup.supplier_name || name, contact_person: newSup.contact_person || '', tin: newSup.tin || '', address: newSup.address || '', supplier_image: newSup.supplier_image || '' });
            if (entry && entry.id) {
                var existing = window._cvAvailableSuppliers || [];
                if (!existing.find(function (s) { return String(s.id != null ? s.id : s.supplier_id || '') === String(entry.id); })) { existing.push(entry); window._cvAvailableSuppliers = existing; }
                pickAndAddSupplier(entry);
                closeModal();
                showSupToast('"' + entry.name + '" registered and added.');
            } else { showSupToast('Supplier registered but could not be selected. Search for it manually.', 'error'); closeModal(); }
        })
        .catch(function () { showSupToast('Network error. Please try again.', 'error'); })
        .finally(function () { if (btn) { btn.disabled = false; btn.textContent = 'Add supplier'; } });
    }

    function removeSupplier(suppId) {
        var sup = activeSuppliers.find(function (s) { return s.id === suppId; });
        if (sup && sup.items.length) {
            Promise.all(sup.items.map(function (it) {
                if (it.saved || String(it.price || '').trim() !== '') {
                    return deleteQuoteFromServer(suppId, it.itemId);
                }
                return Promise.resolve({ success: true });
            })).catch(function () {
                showSupToast('Could not remove all saved quotes.', 'error');
            });
        }
        activeSuppliers = activeSuppliers.filter(function (s) { return s.id !== suppId; });
        renderSection();
    }

    function addItemToSupplier(suppId) {
        var sup = activeSuppliers.find(function (s) { return s.id === suppId; });
        var sel = document.getElementById('sup-item-sel-' + suppId);
        if (!sel || !sel.value || !sup) return;
        var itemId = parseInt(sel.value, 10);
        if (sup.items.find(function (i) { return i.itemId === itemId; })) return;
        sup.items.push({ itemId: itemId, price: '', photo: null, saved: false });
        sel.value = '';
        renderSection();
        // Immediately persist a placeholder row so the association survives a page
        // reload even before the user enters a price.
        saveQuoteToServer(suppId, itemId, '0').then(function (data) {
            if (!data || !data.success) return;
            var s = activeSuppliers.find(function (a) { return a.id === suppId; });
            var it = s ? s.items.find(function (i) { return i.itemId === itemId; }) : null;
            if (it) {
                it.saved = true;
            } else {
                // Item was removed while the placeholder was in-flight — delete the row.
                deleteQuoteFromServer(suppId, itemId).catch(function () {});
            }
        }).catch(function () {});
    }

    function removeItemFromSupplier(suppId, itemId) {
        var sup = activeSuppliers.find(function (s) { return s.id === suppId; });
        if (!sup) return;
        var it = sup.items.find(function (i) { return i.itemId === itemId; });
        if (it && (it.saved || String(it.price || '').trim() !== '')) {
            deleteQuoteFromServer(suppId, itemId).catch(function () {
                showSupToast('Could not remove saved quote.', 'error');
            });
        }
        sup.items = sup.items.filter(function (i) { return i.itemId !== itemId; });
        renderSection();
    }

    // ── Render ────────────────────────────────────────────────────────────────
    function renderSection() {
        reqItems = window.CANVASS_ITEMS || [];
        var emptyEl   = document.getElementById('supplier-empty-state');
        var sectionEl = document.getElementById('supplier-grid-section');
        if (!emptyEl || !sectionEl) return;

        if (!activeSuppliers.length) {
            emptyEl.style.display   = 'block';
            sectionEl.style.display = 'none';
            return;
        }
        emptyEl.style.display   = 'none';
        sectionEl.style.display = 'block';

        var grid = document.getElementById('supplier-card-grid');
        if (!grid) return;
        grid.innerHTML = '';

        activeSuppliers.forEach(function (sup) {
            var usedIds   = sup.items.map(function (i) { return i.itemId; });
            var available = reqItems.filter(function (r) { return !usedIds.includes(r.id); });

            // Avatar: logo image when available, initials fallback
            // Wrap both elements — onerror hides img and reveals initials div via display toggle
            // (avoids the broken \" escaping that terminates HTML attribute values early)
            var imgSrc = getSupplierImg(sup.image);
            var avatarHtml = imgSrc
                ? '<div class="sup-avatar-wrap">' +
                  '<img src="' + escHtml(imgSrc) + '" class="supplier-avatar-img" alt=""' +
                  ' onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">' +
                  '<div class="supplier-avatar" style="display:none">' + escHtml(getInitials(sup.name)) + '</div>' +
                  '</div>'
                : '<div class="supplier-avatar">' + escHtml(getInitials(sup.name)) + '</div>';

            var itemRowsHtml = sup.items.map(function (si, idx) {
                var req = reqItems.find(function (r) { return r.id === si.itemId; });
                if (!req) return '';
                var thumbHtml = si.photo
                    ? '<div class="item-photo-preview">' +
                      '<button type="button" class="btn-item-photo-view" data-photo-url="' + escHtml(si.photo) + '" title="Click to view full size">' +
                      '<img src="' + escHtml(si.photo) + '" class="item-photo-thumb" alt="quote photo"></button>' +
                      '<button type="button" class="btn-item-photo-remove" data-sup-id="' + escHtml(sup.id) + '" data-item-id="' + si.itemId + '" title="Remove photo" aria-label="Remove photo">' +
                      '<i class="fas fa-trash" aria-hidden="true"></i></button></div>'
                    : '';
                return (idx > 0 ? '<div class="supplier-item-divider"></div>' : '') +
                    '<div class="supplier-item-row" data-item-id="' + si.itemId + '">' +
                    '<div class="supplier-item-row-name">' + escHtml(req.name) + '</div>' +
                    '<div class="supplier-item-row-qty">Req. qty: ' + escHtml(String(req.qty)) + ' unit</div>' +
                    '<div class="supplier-price-row">' +
                    '<span class="php-prefix-label">PHP</span>' +
                    '<input class="supplier-price-input" type="number" placeholder="0.00" min="0" step="0.01"' +
                    ' value="' + escHtml(si.price) + '"' +
                    ' data-supplier-id="' + escHtml(sup.id) + '" data-item-id="' + si.itemId + '">' +
                    '<label class="btn-item-photo" title="Upload quote image">' +
                    '<i class="fas fa-camera" aria-hidden="true"></i>' +
                    '<input type="file" accept="image/*" class="item-photo-input" data-sup-id="' + escHtml(sup.id) + '" data-item-id="' + si.itemId + '" style="display:none">' +
                    '</label>' +
                    '<button type="button" class="btn-item-remove" data-sup-id="' + escHtml(sup.id) + '" data-item-id="' + si.itemId + '" aria-label="Remove item">' +
                    '<i class="fas fa-times" aria-hidden="true"></i></button>' +
                    '</div>' +
                    thumbHtml +
                    '</div>';
            }).join('');

            var optHtml = available.map(function (i) {
                return '<option value="' + i.id + '">' + escHtml(i.name) + '</option>';
            }).join('');

            var card = document.createElement('div');
            card.className = 'supplier-card';
            card.dataset.supplierId = sup.id;
            card.innerHTML =
                '<div class="supplier-card-head">' +
                '<div class="supplier-card-head-left">' +
                avatarHtml +
                '<div>' +
                '<div class="supplier-card-name">' + escHtml(sup.name) + '</div>' +
                (sup.contact ? '<div class="supplier-card-sub">' + escHtml(sup.contact) + '</div>' : '') +
                (sup.tin     ? '<div class="supplier-card-sub">TIN ' + escHtml(sup.tin) + '</div>' : '') +
                (sup.address ? '<div class="supplier-card-sub">' + escHtml(sup.address) + '</div>' : '') +
                '</div></div>' +
                '<div class="supplier-card-head-actions">' +
                '<button class="btn-sup-remove" data-sup-id="' + escHtml(sup.id) + '">Remove</button>' +
                '</div></div>' +
                '<div class="supplier-card-body">' +
                '<div class="supplier-item-add-row">' +
                '<select id="sup-item-sel-' + escHtml(sup.id) + '"' + (!available.length ? ' disabled' : '') + '>' +
                '<option value="">Select item to quote…</option>' + optHtml +
                '</select>' +
                '<button class="btn-sup-additem" data-sup-id="' + escHtml(sup.id) + '"' + (!available.length ? ' disabled' : '') + '>+ Add item</button>' +
                '</div>' +
                (itemRowsHtml || '<div class="supplier-card-empty">Select an item above to add a quoted price</div>') +
                '</div>';
            grid.appendChild(card);
        });

        // Wire: price inputs
        grid.querySelectorAll('.supplier-price-input').forEach(function (inp) {
            inp.addEventListener('input', function () {
                var sup = activeSuppliers.find(function (s) { return s.id === inp.dataset.supplierId; });
                if (sup) {
                    var itemId = parseInt(inp.dataset.itemId, 10);
                    var it = sup.items.find(function (i) { return i.itemId === itemId; });
                    if (it) {
                        it.price = inp.value;
                        it.saved = false;
                        scheduleQuoteSave(sup.id, itemId, inp.value);
                    }
                }
            });
            inp.addEventListener('blur', function () {
                var priceStr = String(inp.value || '').trim();
                if (priceStr === '') return;
                var key = inp.dataset.supplierId + ':' + inp.dataset.itemId;
                if (_saveTimers[key]) {
                    clearTimeout(_saveTimers[key]);
                    _saveTimers[key] = null;
                }
                saveQuoteToServer(inp.dataset.supplierId, parseInt(inp.dataset.itemId, 10), priceStr)
                    .then(function (data) {
                        if (!data.success) {
                            showSupToast(data.message || 'Could not save quote.', 'error');
                            return;
                        }
                        var sup = activeSuppliers.find(function (s) { return s.id === inp.dataset.supplierId; });
                        if (sup) {
                            var it = sup.items.find(function (i) { return i.itemId === parseInt(inp.dataset.itemId, 10); });
                            if (it) it.saved = true;
                        }
                    })
                    .catch(function () {
                        showSupToast('Network error saving quote.', 'error');
                    });
            });
        });

        // Wire: photo file inputs
        grid.querySelectorAll('.item-photo-input').forEach(function (inp) {
            inp.addEventListener('change', function () {
                var file = inp.files && inp.files[0];
                if (!file) return;
                var supId  = inp.dataset.supId;
                var itemId = parseInt(inp.dataset.itemId, 10);
                var sup    = activeSuppliers.find(function (s) { return s.id === supId; });
                if (!sup) return;
                var it = sup.items.find(function (i) { return i.itemId === itemId; });
                if (!it) return;
                // Show data-URL preview immediately while upload is in flight
                var reader = new FileReader();
                reader.onload = function (e) { it.photo = e.target.result; renderSection(); };
                reader.readAsDataURL(file);
                // Upload to server and replace preview with persistent URL
                var fd = new FormData();
                fd.append('request_id', String(_requestId));
                fd.append('requisition_line_id', String(itemId));
                fd.append('supplier_id', String(supId));
                fd.append('quote_photo', file);
                fetch(_canvassApi + '?action=upload_requester_quote_photo', {
                    method: 'POST',
                    credentials: 'include',
                    body: fd,
                }).then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success && data.photo_url) {
                        it.photo = resolveImgPath(data.photo_url);
                        renderSection();
                    } else {
                        showSupToast(data.message || 'Photo upload failed.', 'error');
                        it.photo = null;
                        renderSection();
                    }
                }).catch(function () {
                    showSupToast('Network error uploading photo.', 'error');
                    it.photo = null;
                    renderSection();
                });
            });
        });

        // Wire: photo remove buttons
        grid.querySelectorAll('.btn-item-photo-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var supId  = btn.dataset.supId;
                var itemId = parseInt(btn.dataset.itemId, 10);
                var sup    = activeSuppliers.find(function (s) { return s.id === supId; });
                if (!sup) return;
                var it = sup.items.find(function (i) { return i.itemId === itemId; });
                if (it) {
                    it.photo = null;
                    renderSection();
                }
                var fd = new FormData();
                fd.append('request_id', String(_requestId));
                fd.append('requisition_line_id', String(itemId));
                fd.append('supplier_id', String(supId));
                fetch(_canvassApi + '?action=remove_requester_quote_photo', {
                    method: 'POST',
                    credentials: 'include',
                    body: fd,
                }).catch(function () {
                    showSupToast('Could not remove photo from server.', 'error');
                });
            });
        });

        // Wire: photo view buttons (lightbox)
        grid.querySelectorAll('.btn-item-photo-view').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var src = btn.dataset.photoUrl;
                if (src) openPhotoLightbox(src);
            });
        });

        // Wire: remove-item buttons
        grid.querySelectorAll('.btn-item-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                removeItemFromSupplier(btn.dataset.supId, parseInt(btn.dataset.itemId, 10));
            });
        });

        // Wire: remove-supplier buttons
        grid.querySelectorAll('.btn-sup-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                removeSupplier(btn.dataset.supId);
            });
        });

        // Wire: add-item buttons
        grid.querySelectorAll('.btn-sup-additem').forEach(function (btn) {
            btn.addEventListener('click', function () {
                addItemToSupplier(btn.dataset.supId);
            });
        });
    }

    // ── Register Supplier modal ───────────────────────────────────────────────
    function isValidEmail(value) {
        var email = String(value || '').trim();
        if (!email) return true;
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }


    // ── Init ──────────────────────────────────────────────────────────────────
    function init() {
        var btnEmpty  = document.getElementById('btn-add-supplier-empty');
        var btnFooter = document.getElementById('btn-add-supplier-footer');
        var btnClose  = document.getElementById('supplier-modal-close');
        var btnCancel = document.getElementById('supplier-modal-cancel');
        var btnSave   = document.getElementById('supplier-modal-save');
        var overlay   = document.getElementById('supplier-modal-overlay');
        var comboInp  = document.getElementById('supplier-combobox-input');
        if (!btnEmpty) return; // not on requester view

        btnEmpty.addEventListener('click', openModal);
        if (btnFooter) btnFooter.addEventListener('click', openModal);
        if (btnClose)  btnClose.addEventListener('click', closeModal);
        if (btnCancel) btnCancel.addEventListener('click', closeModal);
        if (btnSave)   btnSave.addEventListener('click', addSupplier);
        if (overlay)   overlay.addEventListener('click', function (e) { if (e.target === this) closeModal(); });

        if (comboInp) {
            comboInp.addEventListener('input', function () {
                _selectedSupplier = null;
                _cvSupCloseRegPanel();
                _cvSupSetHint('Search to find a supplier');
                _cvSupCheckSaveState();
                _cvSupRenderResults(this.value);
            });
            comboInp.addEventListener('focus', function () {
                _cvSupRenderResults(this.value);
            });
        }

        // Inline registration panel wiring
        var regClose  = document.getElementById('cv-sup-reg-close');
        var regNameInp = document.getElementById('sup-reg-name');
        if (regClose) regClose.addEventListener('click', function () {
            _cvSupCloseRegPanel();
            _cvSupSetHint(_selectedSupplier ? '"' + _selectedSupplier.name + '" selected' : 'Search to find a supplier');
        });
        if (regNameInp) regNameInp.addEventListener('input', _cvSupCheckSaveState);

        renderSection();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
