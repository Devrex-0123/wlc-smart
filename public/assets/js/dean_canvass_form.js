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
    const cvOpenAddPreferredBtn = document.getElementById('cvOpenAddPreferredBtn');
    const cvSupplierDropdown = document.getElementById('cvSupplierDropdown');
    const cvSupplierDropdownBtn = document.getElementById('cvSupplierDropdownBtn');
    const cvSupplierDropdownList = document.getElementById('cvSupplierDropdownList');
    const cvSupplierSelectedText = document.getElementById('cvSupplierSelectedText');
    const cvSupplierDropdownPreview = document.getElementById('cvSupplierDropdownPreview');
    const cvAddSupplierBtn = document.getElementById('cvAddSupplierBtn');
    const cvRegisterSupplierBtn = document.getElementById('cvRegisterSupplierBtn');
    const cvSupplierTable = document.getElementById('cvSupplierTable');
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

    function getPreferredSupplierPrice(supplierId, itemIndex) {
        const selected = state.selectedSuppliers.find((s) => Number(s.supplier_id) === Number(supplierId));
        if (!selected || !selected.prices || typeof selected.prices !== 'object') {
            return '—';
        }
        const value = selected.prices[itemIndex];
        if (value === null || value === undefined || value === '') {
            return '—';
        }
        return escapeHtml(String(value));
    }

    function renderPreferredTable() {
        if (!cvPreferredTable) return;
        const editable = Boolean(window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.editable);
        const thead = cvPreferredTable.querySelector('thead');
        const tbody = cvPreferredTable.querySelector('tbody');
        const rows = Array.isArray(state.preferredSuppliers) ? state.preferredSuppliers : [];
        const itemCount = Math.max(1, Array.isArray(state.items) ? state.items.length : 0);

        const headerCells = [`<th>SUPPLIER</th>`].concat(
            Array.from({ length: itemCount }, (_, index) => `<th>${formatItemHeader(state.items[index] || {}, index)}</th>`)
        );
        if (thead) {
            thead.innerHTML = `<tr>${headerCells.join('')}</tr>`;
        }

        const colCount = headerCells.length;
        if (rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${colCount}" class="empty-state">${editable ? 'No preferred suppliers yet.' : 'No preferred suppliers added by the requester.'}</td></tr>`;
            const hint = document.getElementById('cvPreferredHint');
            if (hint) hint.textContent = editable
                ? 'Preferred suppliers are listed here for item pricing only. Remove entries with the x icon if needed.'
                : 'Preferred suppliers indicated by the requester. Prices appear here if the supplier is already in the matrix.';
            return;
        }

        tbody.innerHTML = rows.map((s) => {
            const avatar = escapeHtml(getSupplierImageUrl(s.supplier_image));
            const supplierNameHtml = `
                <div class="supplier-table-supplier">
                    <img src="${avatar}" alt="" class="supplier-table-avatar" width="32" height="32" decoding="async" onerror="${supplierAvatarOnError}">
                    <span class="supplier-table-name">${escapeHtml(s.supplier_name || '')}</span>
                </div>`;
            const removeHtml = editable
                ? `<button type="button" class="cv-pref-remove-btn" data-pref-id="${escapeHtml(String(s.supplier_id || ''))}" title="Remove preferred supplier">×</button>`
                : '';
            const supplierCell = `<div class="supplier-table-name-wrap">${supplierNameHtml}${removeHtml}</div>`;
            const priceCells = Array.from({ length: itemCount }, (_, index) => `
                <td>${getPreferredSupplierPrice(s.supplier_id, index)}</td>`);
            return `<tr>
                <td class="supplier-table-name-cell">${supplierCell}</td>
                ${priceCells.join('')}
            </tr>`;
        }).join('');

        const hint = document.getElementById('cvPreferredHint');
        if (hint) hint.textContent = editable
            ? 'Preferred suppliers are listed here for item pricing only. Remove entries with the x icon if needed.'
            : 'Preferred suppliers indicated by the requester. Prices appear here if the supplier is already in the matrix.';
    }

    function updatePreferredSearchUi() {
        const btn = document.getElementById('cvPrefAddSelectedBtn');
        const preview = document.getElementById('cvPrefSelectedSupplierPreview');
        const selected = state.preferredSearchSelection;
        if (btn) {
            btn.disabled = !selected;
        }
        if (preview) {
            if (selected) {
                preview.style.display = 'block';
                preview.textContent = `Selected supplier: ${selected.supplier_name || '—'}${selected.contact_person ? ` · ${selected.contact_person}` : ''}`;
            } else {
                preview.style.display = 'none';
                preview.textContent = '';
            }
        }
    }

    function renderPreferredPicker() {
        const input = document.getElementById('cvPrefSupplierSearch');
        const list = document.getElementById('cvPrefSupplierSearchList');
        if (!input || !list) return;
        const q = String(input.value || '').trim().toLowerCase();
        const focused = Boolean(state.preferredSearchFocused);
        const suppliers = Array.isArray(state.availableSuppliers) ? state.availableSuppliers : [];
        const suggested = unionSuggestedSupplierIds();

        if (!focused && q.length < 2) {
            list.innerHTML = '';
            return;
        }

        const candidateSuppliers = q.length >= 2
            ? suppliers.filter((s) => {
                  const name = String(s.supplier_name || '').toLowerCase();
                  const contact = String(s.contact_person || '').toLowerCase();
                  return name.includes(q) || contact.includes(q);
              })
            : suppliers;

        if (candidateSuppliers.length === 0) {
            list.innerHTML = `<div class="supplier-option-empty">${q.length === 0 ? 'No suggested suppliers available.' : 'No suppliers found. Try another keyword.'}</div>`;
            return;
        }

        const ordered = [...candidateSuppliers].sort((a, b) => {
            const sa = suggested.has(Number(a.supplier_id)) ? 0 : 1;
            const sb = suggested.has(Number(b.supplier_id)) ? 0 : 1;
            if (sa !== sb) {
                return sa - sb;
            }
            return String(a.supplier_name || '').localeCompare(String(b.supplier_name || ''), undefined, {
                sensitivity: 'base',
            });
        });

        list.innerHTML = ordered
            .slice(0, 50)
            .map((s) => {
                const sid = Number(s.supplier_id);
                const isSugg = suggested.has(sid);
                return `<button type="button" class="pref-search-option${isSugg ? ' supplier-option-suggested' : ''}" data-supplier-id="${escapeHtml(String(s.supplier_id))}">
                    <img src="${escapeHtml(getSupplierImageUrl(s.supplier_image))}" alt="" class="supplier-option-avatar" onerror="${supplierAvatarOnError}">
                    <span class="supplier-option-name">${escapeHtml(s.supplier_name)}${isSugg ? '<span class="supplier-suggest-badge">Suggested</span>' : ''}</span>
                </button>`;
            })
            .join('');
    }

    function setPreferredSearchSelection(supplier) {
        state.preferredSearchSelection = supplier || null;
        const input = document.getElementById('cvPrefSupplierSearch');
        if (input && supplier) {
            input.value = supplier.supplier_name || '';
        }
        updatePreferredSearchUi();
    }

    async function confirmLinkPreferredSupplier() {
        const selected = state.preferredSearchSelection;
        if (!selected || !selected.supplier_id) {
            showToast('Select a supplier first.', 'error');
            return;
        }
        await linkPreferredSupplier(selected.supplier_id);
        state.preferredSearchSelection = null;
        const input = document.getElementById('cvPrefSupplierSearch');
        if (input) input.value = '';
        renderPreferredPicker();
        updatePreferredSearchUi();
    }

    async function linkPreferredSupplier(supplierId) {
        try {
            const prefApi = (window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.api) || api;
            const body = new URLSearchParams();
            body.set('action', 'link_preferred');
            body.set('request_id', String(requestId));
            body.set('supplier_id', String(supplierId));
            const res = await fetch(prefApi, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(), credentials: 'include' });
            const data = await res.json();
            if (!data.success) { showToast(data.message || 'Failed to add preferred.', 'error'); return; }
            showToast(data.message || 'Preferred supplier added.');
            const prefSearch = document.getElementById('cvPrefSupplierSearch');
            if (prefSearch) {
                prefSearch.value = '';
            }
            await loadPreferredSuppliers();
        } catch {
            showToast('Network error.', 'error');
        }
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
        state.selectedSuppliers.push({ supplier_id: full.supplier_id, supplier_name: full.supplier_name, supplier_image: full.supplier_image, prices });
        renderSupplierTable();
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
                    supplier_image: s.supplier_image || '',
                }));
            } else {
                state.preferredSuppliers = [];
            }
        } catch {
            showToast('Unable to load preferred suppliers.', 'error');
            state.preferredSuppliers = [];
        }
        renderPreferredTable();
        renderPreferredPicker();
    }

    function openPrefSupModal(mode, existing) {
        const modal = document.getElementById('cvPrefSupModal');
        if (!modal) return;
        document.getElementById('cvPrefSupModalTitle').textContent = mode === 'edit' ? 'Edit preferred supplier' : 'Add preferred supplier';
        document.getElementById('cvPrefSupModalSupplierId').value = existing ? String(existing.supplier_id || '') : '';
        document.getElementById('cvPrefSupName').value = existing ? (existing.supplier_name || '') : '';
        document.getElementById('cvPrefSupContact').value = existing ? (existing.contact_person || '') : '';
        document.getElementById('cvPrefSupPhone').value = existing ? (existing.phone_number || '') : '';
        document.getElementById('cvPrefSupEmail').value = existing ? (existing.email || '') : '';
        document.getElementById('cvPrefSupUrl').value = existing ? (existing.shop_url || '') : '';
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
        body.set('phone_number', (document.getElementById('cvPrefSupPhone').value || '').trim());
        body.set('email', (document.getElementById('cvPrefSupEmail').value || '').trim());
        body.set('shop_url', (document.getElementById('cvPrefSupUrl').value || '').trim());
        try {
            const prefApi = (window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.api) || api;
            const res = await fetch(prefApi, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(), credentials: 'include' });
            const data = await res.json();
            if (!data.success) { showToast(data.message || 'Failed to save.', 'error'); return; }
            closePrefSupModal();
            showToast(data.message || 'Saved.');
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
        suggestedByItem: {},
    };
    const gsdSuggestedHiddenInput = document.getElementById('gsdSuggestedSupplierId');
    const canSelectSuggestedSupplierInTable = !!gsdSuggestedHiddenInput;

    let cachedCanvassApproval = null;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function getSupplierImageUrl(supplierImage) {
        if (!supplierImage) {
            return 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" rx="20" fill="%23dcf5e0"/><text x="50%" y="55%" text-anchor="middle" font-size="16" fill="%231f4f28" font-family="Arial">S</text></svg>';
        }
        if (/^https?:\/\//i.test(supplierImage)) {
            return supplierImage;
        }
        return `../${supplierImage.replace(/^\/+/, '')}`;
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

    function renderSupplierTable() {
        if (!cvSupplierTable) return;
        const thead = cvSupplierTable.querySelector('thead');
        const tbody = cvSupplierTable.querySelector('tbody');

        const itemTitles = canvassItemColumnTitles();
        const canvasStatus = String((cachedCanvassApproval && cachedCanvassApproval.canvas_status) || '').trim().toLowerCase();
        const canvasserLocked = canvasserRegister && (canvasStatus === 'accept' || canvasStatus === 'reject');
        const isRequesterView = Boolean(window.CWIRMS_PREF_SUP && window.CWIRMS_PREF_SUP.isRequester);
        const hideStructureActions = gsdReadonly || canvasserLocked || isRequesterView;
        const colCount = 1 + itemTitles.length + (hideStructureActions ? 0 : 1);
        const headRow = `<tr><th>SUPPLIER</th>${itemTitles
            .map((t) => `<th>${escapeHtml(t)}</th>`)
            .join('')}${hideStructureActions ? '' : '<th>ACTION</th>'}</tr>`;

        if (state.selectedSuppliers.length === 0) {
            thead.innerHTML = headRow;
            tbody.innerHTML = `<tr><td colspan="${colCount}" class="empty-state">Add items and suppliers to build the matrix.</td></tr>`;
            return;
        }

        if (state.items.length === 0) {
            thead.innerHTML = headRow;
            tbody.innerHTML = `<tr><td colspan="${colCount}" class="empty-state">${
                canvasserRegister
                    ? 'Waiting for the requester to add canvass lines before you can quote prices.'
                    : 'Add canvass items to generate columns.'
            }</td></tr>`;
            return;
        }

        thead.innerHTML = headRow;

        tbody.innerHTML = state.selectedSuppliers
            .map((supplier, supplierIndex) => {
                const roAttr = (gsdReadonly || isRequesterView) ? ' readonly' : '';
                const sidNum = Number(supplier.supplier_id);
                const gsdDecision = String((cachedCanvassApproval && cachedCanvassApproval.gsd_status) || '').trim().toLowerCase();
                const canChangeSuggested = canSelectSuggestedSupplierInTable && gsdDecision !== 'accept' && gsdDecision !== 'reject';
                const itemCells = state.items.map((it, itemIndex) => {
                    const value = supplier.prices[itemIndex] ?? '';
                    const isSelectedForItem = Number(state.suggestedByItem[itemIndex] || 0) === sidNum;
                    const pricePresent = value !== null && String(value).trim() !== '';
                    const radioName = `cvSuggestedSupplierItem${itemIndex}`;
                    const canPickThisCell = canChangeSuggested && pricePresent;
                    const radioHtml = gsdReadonly
                        ? `<label class="cv-suggested-radio-wrap" title="Suggested supplier for this item">
                            <input type="radio"
                                class="cv-suggested-item-radio"
                                name="${radioName}"
                                value="${sidNum}"
                                data-item-index="${itemIndex}"
                                data-canvass-detail-id="${Number(it.canvass_detail_id || 0)}"
                                ${isSelectedForItem ? 'checked' : ''}
                                ${canPickThisCell ? '' : 'disabled'}>
                           </label>`
                        : '';
                    const selectedBadge = gsdReadonly && isSelectedForItem
                        ? '<span class="cv-gsd-suggested-badge">Suggested</span>'
                        : '';
                    return `<td class="${isSelectedForItem ? 'cv-gsd-suggested-cell' : ''}"><div class="cv-price-select-inline">${radioHtml}<input type="number" min="0" step="0.01" class="supplier-price-input" data-cv-supplier-index="${supplierIndex}" data-cv-item-index="${itemIndex}" value="${escapeHtml(String(value))}" placeholder="Quoted price"${roAttr}>${selectedBadge}</div></td>`;
                }).join('');

                const avatarSrc = escapeHtml(getSupplierImageUrl(supplier.supplier_image));
                const nameInner = canvasserRegister
                    ? `<button type="button" class="supplier-table-contact-name-btn" data-cv-supplier-id="${escapeHtml(
                          String(supplier.supplier_id)
                      )}" aria-label="${escapeHtml(`View contact for ${supplier.supplier_name || 'supplier'}`)}"><span class="supplier-table-name">${escapeHtml(
                          supplier.supplier_name
                      )}</span><span class="supplier-table-view-contact-hint" aria-hidden="true">View contact</span></button>`
                    : `<span class="supplier-table-name">${escapeHtml(supplier.supplier_name)}</span>`;
                const removeSup = hideStructureActions
                    ? ''
                    : `<td>
                    <button type="button" class="remove-supplier-btn" data-cv-supplier-index="${supplierIndex}" title="Remove supplier">
                        <i class="fas fa-times"></i>
                    </button>
                </td>`;
                return `<tr>
                <td class="supplier-table-name-cell">
                    <div class="supplier-table-supplier">
                        <img src="${avatarSrc}" alt="" class="supplier-table-avatar" width="32" height="32" decoding="async" onerror="${supplierAvatarOnError}">
                        <span class="supplier-table-name-wrap">${nameInner}</span>
                    </div>
                </td>
                ${itemCells}
                ${removeSup}
            </tr>`;
            })
            .join('');
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
            }));

            state.suggestedByItem = {};
            state.items.forEach((it, idx) => {
                const sid = Number(it.selected_supplier_id || 0);
                if (sid > 0) {
                    state.suggestedByItem[idx] = sid;
                }
            });

            state.selectedSuppliers = (Array.isArray(data.suppliers) ? data.suppliers : []).map((s) => ({
                supplier_id: s.supplier_id,
                supplier_name: s.supplier_name,
                supplier_image: s.supplier_image,
                prices: normalizePrices(s.prices),
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
            applyCanvassApproval(data.approval);
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
        }
    }

    async function persistCanvassToServer() {
        if (state.items.length === 0) {
            return {
                ok: false,
                message: canvasserRegister
                    ? 'The requester has not added canvass lines yet. Nothing to quote.'
                    : 'Add at least one canvass item.',
            };
        }

        const itemsPayload = state.items.map((it) => ({
            item_name: it.name,
            brand: it.brand || '',
            model: it.model || '',
            specification: it.specification,
            requisition_line_id: it.requisition_line_id,
        }));

        const suppliersPayload = state.selectedSuppliers.map((s) => ({
            supplier_id: s.supplier_id,
            prices: s.prices,
        }));

        const body = new URLSearchParams();
        body.set('action', 'save');
        body.set('request_id', String(requestId));
        body.set('items', JSON.stringify(itemsPayload));
        body.set('suppliers', JSON.stringify(suppliersPayload));
        body.set('preferred_suppliers', JSON.stringify(state.preferredSuppliers || []));

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
            const result = await persistCanvassToServer();
            if (!result.ok) {
                showToast(result.message || 'Save failed.', 'error');
                return;
            }
            showToast('Draft saved. Complete canvassing when you are ready to approve.');
            await loadForm();
        } catch {
            showToast('Network error.', 'error');
        } finally {
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
            let persistResult = await persistCanvassToServer();
            if (!persistResult.ok) {
                if (persistResult.code === 'canvas_finalized') {
                    persistResult = { ok: true };
                } else {
                    showToast(persistResult.message || 'Save failed.', 'error');
                    return;
                }
            }
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
        const ok = await showConfirmModal('Save canvass items and supplier prices?');
        if (!ok) return;

        if (cvSaveBtn) cvSaveBtn.disabled = true;
        try {
            const result = await persistCanvassToServer();
            if (!result.ok) {
                showToast(result.message || 'Save failed.', 'error');
                return;
            }
            showToast(result.message || 'Saved.');
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
            });
            renderSupplierTable();
        });
    }

    if (cvSupplierTable) {
        cvSupplierTable.addEventListener('click', (e) => {
            const contactBtn = e.target.closest('.supplier-table-contact-name-btn');
            if (contactBtn) {
                const sid = contactBtn.getAttribute('data-cv-supplier-id');
                if (sid) {
                    setSupplierPickerHighlight(sid);
                }
                return;
            }
            const btn = e.target.closest('.remove-supplier-btn');
            if (!btn) return;
            if (gsdReadonly) {
                return;
            }
            const idx = parseInt(btn.getAttribute('data-cv-supplier-index'), 10);
            if (Number.isNaN(idx)) return;
            removeSupplierAt(idx);
            renderSupplierTable();
        });

        cvSupplierTable.addEventListener('input', (e) => {
            const inp = e.target.closest('.supplier-price-input');
            if (!inp) return;
            const si = parseInt(inp.getAttribute('data-cv-supplier-index'), 10);
            const ii = parseInt(inp.getAttribute('data-cv-item-index'), 10);
            if (Number.isNaN(si) || Number.isNaN(ii)) return;
            const sup = state.selectedSuppliers[si];
            if (!sup) return;
            sup.prices[ii] = inp.value;
        });

        cvSupplierTable.addEventListener('change', (e) => {
            const radio = e.target.closest('.cv-suggested-item-radio');
            if (!radio) return;
            const itemIndex = parseInt(radio.getAttribute('data-item-index') || '-1', 10);
            const supplierId = parseInt(radio.value || '0', 10);
            if (Number.isNaN(itemIndex) || itemIndex < 0) return;
            if (Number.isNaN(supplierId) || supplierId <= 0) return;
            state.suggestedByItem[itemIndex] = supplierId;
            renderSupplierTable();
        });
    }

    if (cvSaveBtn) {
        cvSaveBtn.addEventListener('click', () => void saveForm());
    }
    if (cvSaveDraftBtn) {
        cvSaveDraftBtn.addEventListener('click', () => void saveDraft());
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
        if (!editable) return;
        if (cvOpenAddPreferredBtn) {
            cvOpenAddPreferredBtn.addEventListener('click', () => openPrefSupModal('add', null));
        }
        const saveBtn = document.getElementById('cvPrefSupModalSave');
        const cancelBtn = document.getElementById('cvPrefSupModalCancel');
        const backdrop = document.getElementById('cvPrefSupModalBackdrop');
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
            });

            const prefSearch = document.getElementById('cvPrefSupplierSearch');
            const prefList = document.getElementById('cvPrefSupplierSearchList');
            if (prefSearch) {
                prefSearch.addEventListener('input', () => {
                    state.preferredSearchSelection = null;
                    renderPreferredPicker();
                    updatePreferredSearchUi();
                });
                prefSearch.addEventListener('focus', () => {
                    state.preferredSearchFocused = true;
                    renderPreferredPicker();
                });
                prefSearch.addEventListener('blur', () => {
                    setTimeout(() => {
                        state.preferredSearchFocused = false;
                        renderPreferredPicker();
                    }, 120);
                });
            }
            if (prefList) {
                prefList.addEventListener('click', (e) => {
                    const opt = e.target.closest('.pref-search-option');
                    if (!opt) return;
                    const sid = opt.getAttribute('data-supplier-id');
                    if (!sid) return;
                    const supplier = state.availableSuppliers.find((s) => String(s.supplier_id) === String(sid));
                    if (!supplier) return;
                    setPreferredSearchSelection(supplier);
                });
            }
            const addSelectedBtn = document.getElementById('cvPrefAddSelectedBtn');
            if (addSelectedBtn) {
                addSelectedBtn.addEventListener('click', () => void confirmLinkPreferredSupplier());
            }
        }
    })();

    applyGsdReadonlyUi();
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
    };
})();
