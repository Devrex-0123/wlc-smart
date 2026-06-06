/**
 * Comptroller quantity review on pricing overview (separate from GSD view).
 */
(function () {
    const cfg = window.CWIRMS_COMPTROLLER_PRICING;
    const section = document.getElementById('cvComptrollerPricingSection');
    const bodyEl = document.getElementById('cvComptrollerPricingBody');
    const grandTotalEl = document.getElementById('cvComptrollerPricingGrandTotal');
    const footTotalEl = document.getElementById('cvComptrollerPricingFootTotal');
    const progressEl = document.getElementById('cvComptrollerPricingProgress');
    const hintEl = document.getElementById('cvComptrollerPricingHint');
    const bannersEl = document.getElementById('cvComptrollerDeferredBanners');
    const validationBannerEl = document.getElementById('cvComptrollerValidationBanner');
    const form = document.getElementById('comptrollerPricingForm');

    if (!cfg || !section || !bodyEl) {
        return;
    }

    const readonly = Boolean(cfg.readonly);
    const isInteractive = Boolean(cfg.interactive) && String(cfg.viewerRole || 'comptroller') === 'comptroller';
    const viewerRole = String(cfg.viewerRole || 'comptroller');
    const comptrollerCompStatus = String(cfg.comptrollerCompStatus || 'pending').trim().toLowerCase();
    const currency = cfg.currency || 'PHP';
    const tableEl = document.getElementById('cvComptrollerPricingTable');
    const footLabelEl = tableEl ? tableEl.querySelector('.cv-pricing-overview-foot-label') : null;
    const pendingNoticeEl = document.getElementById('cvComptrollerPricingPendingNotice');
    const POPOVER_WIDTH = 320;
    const DEFERRED_REASON_SUGGESTIONS = [
        'Budget constraints',
        'Insufficient funds',
        'Stock availability issue',
        'Policy limit reached',
        'Pending further evaluation',
    ];
    let lines = Array.isArray(cfg.lines) ? cfg.lines.map(normalizeLine) : [];
    let popoverEl = null;
    let activePopoverIndex = null;

    function hasComptrollerSavedApproval(line) {
        return Boolean(String(line.comptroller_approved_at || '').trim());
    }

    function resolveInitialAcceptedQty(line, requestedQty) {
        const requested = Math.max(0, Number(requestedQty) || 0);
        if (hasComptrollerSavedApproval(line)) {
            const saved = Number(line.accepted_qty);
            if (Number.isFinite(saved)) {
                return Math.min(requested, Math.max(0, saved));
            }
        }
        return requested;
    }

    function normalizeLine(line) {
        const requestedQty = Math.max(0, Number(line.requested_qty ?? line.quantity ?? 0));
        const acceptedQty = resolveInitialAcceptedQty(line, requestedQty);
        return {
            ...line,
            requested_qty: requestedQty,
            accepted_qty: acceptedQty,
            deferred_qty: Math.max(0, requestedQty - acceptedQty),
            qty_per_set: Math.max(1, Number(line.qty_per_set ?? 1)),
            requisition_qty: Math.max(1, Number(line.requisition_qty ?? line.requested_qty ?? requestedQty)),
            unit_price: line.unit_price != null ? Number(line.unit_price) : null,
            deferred_message: String(line.deferred_message || '').trim(),
        };
    }

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = value == null ? '' : String(value);
        return node.innerHTML;
    }

    function formatMoney(amount) {
        const num = Number(amount);
        const safe = Number.isFinite(num) ? num : 0;
        return `${currency} ${safe.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;
    }

    function deferredContextMessage(metrics) {
        if (metrics.acceptedQty >= metrics.requestedQty || metrics.requestedQty <= 0) {
            return '';
        }
        if (metrics.acceptedQty === 0) {
            return 'No units approved — full quantity deferred to next procurement cycle.';
        }
        const n = metrics.deferredQty;
        const unit = n === 1 ? 'unit' : 'units';
        return `${n} ${unit} deferred — will be requested next procurement cycle.`;
    }

    function popoverBadgeFor(metrics) {
        if (metrics.acceptedQty === 0 && metrics.requestedQty > 0) {
            return { text: 'None approved', variant: 'none' };
        }
        const n = metrics.deferredQty;
        const unit = n === 1 ? 'unit' : 'units';
        return { text: `${n} ${unit} deferred`, variant: 'partial' };
    }

    function deferredBannerMessage(metrics, itemName) {
        const name = String(itemName || 'item').trim() || 'item';
        if (metrics.acceptedQty === 0 && metrics.requestedQty > 0) {
            return `No units of ${name} approved — full quantity deferred to the next procurement cycle.`;
        }
        const n = metrics.deferredQty;
        return `${n} unit(s) of ${name} will be deferred to the next procurement cycle due to partial approval.`;
    }

    function isZeroAcceptedDeferral(metrics) {
        return metrics.acceptedQty === 0 && metrics.requestedQty > 0;
    }

    function formatRequestedQtyLabel(line, metrics) {
        const reqQty = metrics.requestedQty;
        const perSet = Math.max(1, Number(line.qty_per_set ?? 1));
        const unitType = String(line.unit_type || 'unit');
        const unitLabel = pluralizeUnitForQty(unitType, reqQty);
        if (reqQty > 1 && perSet === 1) {
            const setWord = unitType === 'set' ? 'sets' : unitLabel;
            return `${reqQty} ${unitLabel} (1 per set × ${reqQty} ${setWord})`;
        }
        return `${reqQty} ${unitLabel}`;
    }

    function pluralizeUnitForQty(unitType, qty) {
        const unit = String(unitType || 'unit');
        if (qty === 1) {
            return unit;
        }
        if (unit === 'set') {
            return 'sets';
        }
        return unit.endsWith('s') ? unit : `${unit}s`;
    }

    function renderComptrollerNote(line, metrics) {
        if (metrics.deferredQty <= 0) {
            return '';
        }

        const note = String(line.deferred_message || '').trim();
        const approver = String(line.comptroller_approved_by_label || 'Comptroller').trim() || 'Comptroller';
        const noteVariant = metrics.acceptedQty === 0 && metrics.requestedQty > 0 ? 'none' : 'partial';

        if (!note) {
            return `<div class="cv-comptroller-row-note cv-comptroller-row-note--awaiting cv-comptroller-row-note--${noteVariant}">
                <span class="cv-comptroller-row-note-label">Comptroller's note:</span>
                <span class="cv-comptroller-row-note-placeholder">Awaiting comptroller review.</span>
            </div>`;
        }

        return `<div class="cv-comptroller-row-note cv-comptroller-row-note--${noteVariant}">
            <span class="cv-comptroller-row-note-label">Comptroller's note:</span>
            <blockquote class="cv-comptroller-row-note-quote">
                <i class="fas fa-quote-left cv-comptroller-row-note-icon" aria-hidden="true"></i>
                <span class="cv-comptroller-row-note-text">"${escapeHtml(note)}"</span>
                <cite class="cv-comptroller-row-note-by">— ${escapeHtml(approver)}</cite>
            </blockquote>
        </div>`;
    }

    function renderAcceptedQtyCell(line, metrics, index, readonlyMode) {
        const detailId = Number(line.canvass_detail_id || 0);
        const unitType = String(line.unit_type || 'unit');
        const noteHtml = !isInteractive ? renderComptrollerNote(line, metrics) : '';

        if (readonlyMode) {
            const acceptedLabel = `${metrics.acceptedQty} ${unitType}`;
            return `<td class="cv-comptroller-qty-cell cv-comptroller-qty-cell--readonly">
                <div class="cv-comptroller-accepted-wrap">
                    <span class="cv-comptroller-accepted-readonly">${escapeHtml(acceptedLabel)}</span>
                    <span class="cv-comptroller-qty-ceiling">/ ${metrics.requestedQty}</span>
                    ${noteHtml}
                </div>
            </td>`;
        }

        const inputAttrs = `min="0" max="${metrics.requestedQty}" step="1"`;

        return `<td class="cv-comptroller-qty-cell">
            <div class="cv-comptroller-accepted-wrap">
                <div class="cv-comptroller-accepted-input-row">
                    <input type="number"
                        class="cv-comptroller-accepted-input accepted-qty-input"
                        name="accepted_qty[${detailId}]"
                        value="${metrics.acceptedQty}"
                        data-line-index="${index}"
                        aria-label="Accepted quantity for ${escapeHtml(line.item_name || `item ${index + 1}`)}"
                        ${inputAttrs}>
                    <span class="cv-comptroller-qty-ceiling">/ ${metrics.requestedQty}</span>
                    <span class="cv-comptroller-row-indicators">${renderRowIndicators(metrics, line, readonlyMode, index)}</span>
                </div>
                <input type="hidden"
                    class="cv-comptroller-deferred-hidden"
                    name="deferred_message[${detailId}]"
                    data-line-index="${index}"
                    value="">
            </div>
        </td>`;
    }

    function sourceLabel(source) {
        if (source === 'preferred') {
            return '<span class="cv-pricing-overview-source cv-pricing-overview-source-preferred">Preferred</span>';
        }
        if (source === 'canvassed') {
            return '<span class="cv-pricing-overview-source cv-pricing-overview-source-canvassed">Canvassed</span>';
        }
        return '—';
    }

    function showDiscountColumn() {
        if (Boolean(cfg.show_discount_column)) {
            return true;
        }
        return lines.some((line) => String(line.discount_label || '').trim() !== '');
    }

    function syncDiscountColumn(showDiscount) {
        const theadRow = tableEl ? tableEl.querySelector('thead tr') : null;
        if (!theadRow) {
            return;
        }

        let discountTh = theadRow.querySelector('.cv-pricing-overview-discount-col');
        if (showDiscount && !discountTh) {
            discountTh = document.createElement('th');
            discountTh.scope = 'col';
            discountTh.className = 'cv-pricing-overview-discount-col';
            discountTh.textContent = 'Discount';
            const unitTh = theadRow.querySelector('th:nth-child(8)');
            if (unitTh) {
                unitTh.insertAdjacentElement('afterend', discountTh);
            } else {
                theadRow.appendChild(discountTh);
            }
        } else if (!showDiscount && discountTh) {
            discountTh.remove();
        }

        if (tableEl) {
            tableEl.classList.toggle('cv-pricing-overview-has-discount', Boolean(showDiscount));
        }
        if (footLabelEl) {
            footLabelEl.colSpan = showDiscount ? 9 : 8;
        }
    }

    function clampAcceptedQty(line, raw) {
        const requested = Math.max(0, Number(line.requested_qty) || 0);
        if (raw === null || raw === undefined || String(raw).trim() === '') {
            return requested;
        }
        let val = parseInt(String(raw), 10);
        if (Number.isNaN(val)) {
            val = requested;
        }
        return Math.min(requested, Math.max(0, val));
    }

    function parseAcceptedQtyOnBlur(line, raw) {
        const requested = Math.max(0, Number(line.requested_qty) || 0);
        const trimmed = String(raw ?? '').trim();
        if (trimmed === '') {
            return 0;
        }
        let val = parseInt(trimmed, 10);
        if (Number.isNaN(val)) {
            return 0;
        }
        return Math.min(requested, Math.max(0, val));
    }

    function lineMetrics(line) {
        const requestedQty = Math.max(0, Number(line.requested_qty) || 0);
        const acceptedQty = clampAcceptedQty(line, line.accepted_qty);
        const deferredQty = Math.max(0, requestedQty - acceptedQty);
        const unitPrice = line.unit_price != null && Number.isFinite(Number(line.unit_price))
            ? Number(line.unit_price)
            : null;
        const approvedLineTotal = unitPrice != null ? Math.round(unitPrice * acceptedQty * 100) / 100 : null;
        const deferredAmount = unitPrice != null ? Math.round(unitPrice * deferredQty * 100) / 100 : null;
        return {
            requestedQty,
            acceptedQty,
            deferredQty,
            unitPrice,
            approvedLineTotal,
            deferredAmount,
        };
    }

    function hasDeferredReason(line) {
        return String(line.deferred_message || '').trim() !== '';
    }

    function renderRowIndicators(metrics, line, readonlyMode, index) {
        const note = String(line.deferred_message || '').trim();
        if (metrics.deferredQty <= 0) {
            return '';
        }

        if (!readonlyMode) {
            const hasReason = hasDeferredReason(line);
            const title = hasReason ? `Edit reason: ${note}` : isZeroAcceptedDeferral(metrics)
                ? 'Add reason for non-approval'
                : 'Add reason for deferred quantity';
            const iconClass = hasReason ? 'fa-check-circle' : 'fa-clock';
            let btnClass = hasReason
                ? 'cv-comptroller-row-reason-done cv-comptroller-edit-reason-trigger'
                : 'cv-comptroller-row-deferred-icon cv-comptroller-edit-reason-trigger';
            if (!hasReason && isZeroAcceptedDeferral(metrics)) {
                btnClass = 'cv-comptroller-row-none-approved cv-comptroller-edit-reason-trigger';
            }
            return `<button type="button" class="${btnClass}" data-line-index="${index}" title="${escapeHtml(title)}" aria-label="${escapeHtml(hasReason ? 'Edit deferred reason' : 'Add deferred reason')}"><i class="fas ${iconClass}" aria-hidden="true"></i></button>`;
        }

        return '';
    }

    function ensurePopover() {
        if (popoverEl) {
            return popoverEl;
        }

        popoverEl = document.createElement('div');
        popoverEl.id = 'cvComptrollerDeferredPopover';
        popoverEl.className = 'cv-comptroller-deferred-popover';
        popoverEl.hidden = true;
        popoverEl.setAttribute('role', 'dialog');
        popoverEl.setAttribute('aria-modal', 'true');
        popoverEl.innerHTML = `
            <div class="cv-comptroller-popover-header">
                <span class="cv-comptroller-popover-item"></span>
                <span class="cv-comptroller-popover-badge"></span>
                <button type="button" class="cv-comptroller-popover-close" aria-label="Close reason panel">&times;</button>
            </div>
            <p class="cv-comptroller-popover-context" aria-live="polite"></p>
            <p class="cv-comptroller-popover-label">Reason for partial approval</p>
            <div class="cv-comptroller-popover-chips" role="group" aria-label="Suggested reasons"></div>
            <textarea class="cv-comptroller-popover-textarea" rows="3" placeholder="Write your reason here..."></textarea>
            <p class="cv-comptroller-popover-error" hidden>Please enter a reason.</p>
            <div class="cv-comptroller-popover-actions">
                <button type="button" class="btn-submit cv-comptroller-popover-done">Done</button>
            </div>
        `;
        document.body.appendChild(popoverEl);

        const chipsWrap = popoverEl.querySelector('.cv-comptroller-popover-chips');
        chipsWrap.innerHTML = DEFERRED_REASON_SUGGESTIONS.map(
            (text, chipIdx) =>
                `<button type="button" class="cv-comptroller-reason-chip" data-suggestion-index="${chipIdx}">${escapeHtml(text)}</button>`
        ).join('');

        popoverEl.querySelector('.cv-comptroller-popover-close').addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            cancelPopover();
        });

        popoverEl.querySelector('.cv-comptroller-popover-done').addEventListener('click', () => {
            onPopoverDone();
        });

        popoverEl.querySelector('.cv-comptroller-popover-textarea').addEventListener('input', (e) => {
            autoExpandTextarea(e.target);
            hidePopoverError();
        });

        chipsWrap.querySelectorAll('.cv-comptroller-reason-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                const chipIdx = parseInt(chip.getAttribute('data-suggestion-index') || '-1', 10);
                const suggestion = DEFERRED_REASON_SUGGESTIONS[chipIdx] || '';
                const textarea = popoverEl.querySelector('.cv-comptroller-popover-textarea');
                if (textarea) {
                    textarea.value = suggestion;
                    autoExpandTextarea(textarea);
                    hidePopoverError();
                }
            });
        });

        document.addEventListener('mousedown', onDocumentMouseDown);
        document.addEventListener('keydown', onDocumentKeyDown);

        return popoverEl;
    }

    function autoExpandTextarea(textarea) {
        if (!textarea) {
            return;
        }
        textarea.style.height = 'auto';
        textarea.style.height = `${Math.max(72, textarea.scrollHeight)}px`;
    }

    function hidePopoverError() {
        if (!popoverEl) {
            return;
        }
        const err = popoverEl.querySelector('.cv-comptroller-popover-error');
        const textarea = popoverEl.querySelector('.cv-comptroller-popover-textarea');
        if (err) {
            err.hidden = true;
        }
        if (textarea) {
            textarea.classList.remove('cv-comptroller-popover-textarea-invalid');
        }
    }

    function showPopoverError() {
        if (!popoverEl) {
            return;
        }
        const err = popoverEl.querySelector('.cv-comptroller-popover-error');
        const textarea = popoverEl.querySelector('.cv-comptroller-popover-textarea');
        if (err) {
            err.hidden = false;
        }
        if (textarea) {
            textarea.classList.add('cv-comptroller-popover-textarea-invalid');
            textarea.focus();
        }
    }

    function getPopoverTextareaValue() {
        if (!popoverEl) {
            return '';
        }
        const textarea = popoverEl.querySelector('.cv-comptroller-popover-textarea');
        return textarea ? String(textarea.value || '').trim() : '';
    }

    function positionPopover(anchorEl) {
        const pop = ensurePopover();
        const rect = anchorEl.getBoundingClientRect();
        pop.style.position = 'fixed';
        pop.style.width = `${POPOVER_WIDTH}px`;
        pop.hidden = false;
        pop.style.visibility = 'hidden';
        pop.style.display = 'block';

        const popHeight = pop.offsetHeight;
        let top = rect.bottom + 8;
        let left = Math.min(
            Math.max(8, rect.left),
            window.innerWidth - POPOVER_WIDTH - 8
        );

        if (top + popHeight + 8 > window.innerHeight) {
            top = rect.top - popHeight - 8;
        }
        if (top < 8) {
            top = 8;
        }

        pop.style.top = `${top}px`;
        pop.style.left = `${left}px`;
        pop.style.visibility = 'visible';
    }

    function updateHiddenInput(index) {
        const line = lines[index];
        if (!line) {
            return;
        }
        const detailId = Number(line.canvass_detail_id || 0);
        const hidden = bodyEl.querySelector(
            `.cv-comptroller-deferred-hidden[data-line-index="${index}"]`
        );
        if (hidden) {
            hidden.value = String(line.deferred_message || '');
        }
    }

    function updateRowIndicatorElements(index) {
        const line = lines[index];
        if (!line) {
            return;
        }
        const row = bodyEl.querySelector(`tr[data-line-index="${index}"]`);
        if (!row) {
            return;
        }
        const metrics = lineMetrics(line);
        const indicatorWrap = row.querySelector('.cv-comptroller-row-indicators');
        if (indicatorWrap) {
            indicatorWrap.innerHTML = renderRowIndicators(metrics, line, readonly, index);
        }
    }

    function bindEditReasonTriggers() {
        if (bodyEl.dataset.cvReasonDelegationBound === '1') {
            return;
        }
        bodyEl.dataset.cvReasonDelegationBound = '1';
        bodyEl.addEventListener('click', (e) => {
            const btn = e.target.closest('.cv-comptroller-edit-reason-trigger');
            if (!btn || !isInteractive) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            const index = parseInt(btn.getAttribute('data-line-index') || '-1', 10);
            if (Number.isNaN(index) || !lines[index]) {
                return;
            }
            const anchor = bodyEl.querySelector(
                `.cv-comptroller-accepted-input[data-line-index="${index}"]`
            );
            if (anchor) {
                openPopover(index, anchor);
            }
        });
    }

    function persistPopoverDraftToLine(index) {
        if (index === null || index < 0 || !lines[index]) {
            return;
        }
        const text = getPopoverTextareaValue();
        lines[index].deferred_message = text;
        updateHiddenInput(index);
        updateRowIndicatorElements(index);
    }

    function openPopover(index, anchorInput) {
        if (!isInteractive || !lines[index] || !anchorInput) {
            return;
        }

        const metrics = lineMetrics(lines[index]);
        if (metrics.deferredQty <= 0) {
            closePopover(true);
            return;
        }

        if (activePopoverIndex !== null && activePopoverIndex !== index) {
            persistPopoverDraftToLine(activePopoverIndex);
            closePopover(true);
        }

        activePopoverIndex = index;
        const pop = ensurePopover();
        const line = lines[index];
        const badge = popoverBadgeFor(metrics);

        pop.querySelector('.cv-comptroller-popover-item').textContent =
            line.item_name || `Item ${index + 1}`;

        const badgeEl = pop.querySelector('.cv-comptroller-popover-badge');
        badgeEl.textContent = badge.text;
        badgeEl.classList.remove('cv-comptroller-popover-badge--partial', 'cv-comptroller-popover-badge--none');
        badgeEl.classList.add(
            badge.variant === 'none'
                ? 'cv-comptroller-popover-badge--none'
                : 'cv-comptroller-popover-badge--partial'
        );

        const contextEl = pop.querySelector('.cv-comptroller-popover-context');
        const labelEl = pop.querySelector('.cv-comptroller-popover-label');
        if (contextEl) {
            contextEl.textContent = deferredContextMessage(metrics);
            contextEl.classList.remove(
                'cv-comptroller-popover-context--partial',
                'cv-comptroller-popover-context--none'
            );
            contextEl.classList.add(
                isZeroAcceptedDeferral(metrics)
                    ? 'cv-comptroller-popover-context--none'
                    : 'cv-comptroller-popover-context--partial'
            );
        }
        if (labelEl) {
            labelEl.textContent = isZeroAcceptedDeferral(metrics)
                ? 'Reason for non-approval'
                : 'Reason for partial approval';
        }

        const textarea = pop.querySelector('.cv-comptroller-popover-textarea');
        textarea.value = String(line.deferred_message || '');
        autoExpandTextarea(textarea);
        hidePopoverError();

        positionPopover(anchorInput);
        window.setTimeout(() => textarea.focus(), 0);
    }

    function closePopover(force) {
        if (!popoverEl || activePopoverIndex === null) {
            return;
        }
        if (!force) {
            return;
        }
        popoverEl.hidden = true;
        activePopoverIndex = null;
        hidePopoverError();
    }

    function cancelPopover() {
        closePopover(true);
    }

    function dismissPopover(saveDraft) {
        if (!popoverEl || activePopoverIndex === null) {
            return;
        }
        if (saveDraft && getPopoverTextareaValue() !== '') {
            persistPopoverDraftToLine(activePopoverIndex);
            hideValidationBanner();
        }
        closePopover(true);
    }

    function attemptClosePopover(saveIfFilled) {
        dismissPopover(Boolean(saveIfFilled));
    }

    function onPopoverDone() {
        if (activePopoverIndex === null) {
            return;
        }
        const text = getPopoverTextareaValue();
        if (text === '') {
            showPopoverError();
            return;
        }
        persistPopoverDraftToLine(activePopoverIndex);
        closePopover(true);
        hideValidationBanner();
    }

    function onDocumentMouseDown(e) {
        if (!popoverEl || popoverEl.hidden || activePopoverIndex === null) {
            return;
        }
        if (popoverEl.contains(e.target)) {
            return;
        }
        if (e.target.closest('.cv-comptroller-edit-reason-trigger')) {
            return;
        }
        const anchor = bodyEl.querySelector(
            `.cv-comptroller-accepted-input[data-line-index="${activePopoverIndex}"]`
        );
        if (anchor && anchor.contains(e.target)) {
            return;
        }
        attemptClosePopover(true);
    }

    function onDocumentKeyDown(e) {
        if (e.key !== 'Escape' || !popoverEl || popoverEl.hidden) {
            return;
        }
        e.preventDefault();
        cancelPopover();
    }

    function hideValidationBanner() {
        if (validationBannerEl) {
            validationBannerEl.hidden = true;
        }
    }

    function showValidationBanner() {
        if (validationBannerEl) {
            validationBannerEl.hidden = false;
            validationBannerEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function renderTable() {
        if (!lines.length) {
            section.hidden = true;
            return;
        }

        const reopenIndex = activePopoverIndex;
        closePopover(true);
        section.hidden = false;

        const showDiscount = showDiscountColumn();
        syncDiscountColumn(showDiscount);

        bodyEl.innerHTML = lines
            .map((line, index) => {
                const metrics = lineMetrics(line);
                const unitType = String(line.unit_type || 'unit');
                const requestedLabel = formatRequestedQtyLabel(line, metrics);
                const deferredLabel = `${metrics.deferredQty} ${pluralizeUnitForQty(unitType, metrics.deferredQty)}`;
                const unitPriceLabel = metrics.unitPrice != null ? formatMoney(metrics.unitPrice) : '—';
                const lineTotalLabel = metrics.approvedLineTotal != null ? formatMoney(metrics.approvedLineTotal) : '—';
                const deferredAmountLabel =
                    metrics.deferredAmount != null ? formatMoney(metrics.deferredAmount) : '';
                const supplier = line.supplier_name
                    ? escapeHtml(line.supplier_name)
                    : '<span class="cv-pricing-overview-pending">Not selected</span>';
                const detailId = Number(line.canvass_detail_id || 0);
                const discountCell = showDiscount
                    ? `<td class="cv-pricing-overview-discount-cell">${
                          line.discount_label ? escapeHtml(line.discount_label) : '—'
                      }</td>`
                    : '';

                return `<tr data-line-index="${index}" data-canvass-detail-id="${detailId}">
                    <td>${index + 1}</td>
                    <td>${escapeHtml(line.item_name || `Item ${index + 1}`)}</td>
                    <td>${escapeHtml(requestedLabel)}</td>
                    ${renderAcceptedQtyCell(line, metrics, index, !isInteractive)}
                    <td class="cv-comptroller-deferred-qty-cell">${escapeHtml(deferredLabel)}</td>
                    <td>${supplier}</td>
                    <td>${sourceLabel(line.selection_source)}</td>
                    <td class="cv-pricing-overview-unit-price-cell">${unitPriceLabel}</td>
                    ${discountCell}
                    <td class="cv-pricing-overview-line-total-cell">
                        <div class="cv-comptroller-line-total-wrap">
                            <span class="cv-comptroller-approved-total">${lineTotalLabel}</span>
                            <span class="cv-comptroller-deferred-amount"${metrics.deferredQty > 0 ? '' : ' hidden'}>${escapeHtml(deferredAmountLabel)} deferred</span>
                        </div>
                    </td>
                </tr>`;
            })
            .join('');

        if (isInteractive) {
            bodyEl.querySelectorAll('.cv-comptroller-accepted-input').forEach((input) => {
                input.addEventListener('blur', onAcceptedQtyBlur);
                input.addEventListener('keydown', onAcceptedQtyKeydown);
            });
        }

        lines.forEach((line, index) => {
            updateHiddenInput(index);
        });

        refreshSummary();

        return reopenIndex;
    }

    function updateQtyRowInPlace(index) {
        const line = lines[index];
        if (!line) {
            return;
        }
        const row = bodyEl.querySelector(`tr[data-line-index="${index}"]`);
        if (!row) {
            renderTable();
            return;
        }

        const metrics = lineMetrics(line);
        line.accepted_qty = metrics.acceptedQty;
        line.deferred_qty = metrics.deferredQty;

        const unitType = String(line.unit_type || 'unit');
        const deferredCell = row.querySelector('.cv-comptroller-deferred-qty-cell');
        if (deferredCell) {
            deferredCell.textContent = `${metrics.deferredQty} ${pluralizeUnitForQty(unitType, metrics.deferredQty)}`;
        }

        const lineTotalWrap = row.querySelector('.cv-comptroller-line-total-wrap');
        if (lineTotalWrap) {
            const approvedEl = lineTotalWrap.querySelector('.cv-comptroller-approved-total');
            const deferredEl = lineTotalWrap.querySelector('.cv-comptroller-deferred-amount');
            if (approvedEl) {
                approvedEl.textContent = metrics.approvedLineTotal != null
                    ? formatMoney(metrics.approvedLineTotal)
                    : '—';
            }
            if (deferredEl) {
                if (metrics.deferredQty > 0) {
                    deferredEl.hidden = false;
                    deferredEl.textContent = metrics.deferredAmount != null
                        ? `${formatMoney(metrics.deferredAmount)} deferred`
                        : '';
                } else {
                    deferredEl.hidden = true;
                    deferredEl.textContent = '';
                }
            }
        }

        const input = row.querySelector('.cv-comptroller-accepted-input');
        if (input && document.activeElement !== input) {
            input.value = String(metrics.acceptedQty);
        }

        updateHiddenInput(index);
        updateRowIndicatorElements(index);
        refreshSummary();
    }

    function commitAcceptedQtyInput(input, { suppressPopover = false } = {}) {
        if (!input) {
            return;
        }
        const index = parseInt(input.getAttribute('data-line-index') || '-1', 10);
        if (Number.isNaN(index) || !lines[index]) {
            return;
        }

        const prevMetrics = lineMetrics(lines[index]);
        const committed = parseAcceptedQtyOnBlur(lines[index], input.value);
        lines[index].accepted_qty = committed;
        input.value = String(committed);
        const nextMetrics = lineMetrics(lines[index]);

        if (nextMetrics.deferredQty === 0) {
            lines[index].deferred_message = '';
            closePopover(true);
        } else if (prevMetrics.deferredQty === 0) {
            lines[index].deferred_message = '';
        }

        updateQtyRowInPlace(index);

        if (!suppressPopover && isInteractive && nextMetrics.deferredQty > 0) {
            const anchor = bodyEl.querySelector(
                `.cv-comptroller-accepted-input[data-line-index="${index}"]`
            );
            if (anchor) {
                openPopover(index, anchor);
            }
        }
    }

    function onAcceptedQtyBlur(e) {
        if (e && e.isTrusted === false) {
            return;
        }
        const input = e.target.closest('.cv-comptroller-accepted-input');
        if (!input) {
            return;
        }
        commitAcceptedQtyInput(input);
    }

    function onAcceptedQtyKeydown(e) {
        if (e.key !== 'Enter') {
            return;
        }
        const input = e.target.closest('.cv-comptroller-accepted-input');
        if (!input) {
            return;
        }
        e.preventDefault();
        input.blur();
    }

    function commitActiveAcceptedQtyInput() {
        const active = document.activeElement;
        if (!active || !active.classList.contains('cv-comptroller-accepted-input')) {
            return;
        }
        commitAcceptedQtyInput(active, { suppressPopover: true });
    }

    function refreshSummary() {
        let approvedGrandTotal = 0;
        const deferredNames = [];

        lines.forEach((line) => {
            const metrics = lineMetrics(line);
            line.accepted_qty = metrics.acceptedQty;
            line.deferred_qty = metrics.deferredQty;
            if (line.supplier_id && metrics.deferredQty > 0) {
                deferredNames.push(String(line.item_name || 'Item').trim() || 'Item');
            }
            if (metrics.approvedLineTotal != null) {
                approvedGrandTotal += metrics.approvedLineTotal;
            }
        });

        const itemCount = lines.length;
        const grandLabel = formatMoney(approvedGrandTotal);
        if (grandTotalEl) {
            grandTotalEl.textContent = grandLabel;
        }
        if (footTotalEl) {
            footTotalEl.textContent = grandLabel;
        }

        if (progressEl) {
            if (deferredNames.length === 0 && itemCount > 0) {
                progressEl.textContent = `${itemCount} of ${itemCount} items fully approved`;
            } else if (deferredNames.length > 0) {
                progressEl.textContent = `Partial approval — ${deferredNames.join(', ')} deferred`;
            } else {
                progressEl.textContent = `0 of ${itemCount} items fully approved`;
            }
        }

        if (hintEl) {
            if (isInteractive) {
                hintEl.textContent = deferredNames.length > 0
                    ? 'Enter a reason for each deferred quantity before approving.'
                    : 'All items are fully approved at the requested quantities.';
            } else if (viewerRole === 'requester' || viewerRole === 'president') {
                hintEl.textContent = comptrollerCompStatus === 'pending'
                    ? 'Suggested suppliers and quantities are shown below. The comptroller has not finalized approval yet.'
                    : 'Suggested suppliers and comptroller-approved quantities for this request.';
            } else if (viewerRole === 'inventory_manager' || viewerRole === 'gsd_officer') {
                hintEl.textContent = comptrollerCompStatus === 'pending'
                    ? 'Suggested suppliers are shown below. Awaiting comptroller quantity review.'
                    : 'Suggested suppliers and comptroller-approved quantities for this request.';
            }
        }

        if (pendingNoticeEl) {
            const showPending = comptrollerCompStatus === 'pending'
                && (viewerRole === 'requester' || viewerRole === 'president');
            pendingNoticeEl.hidden = !showPending;
        }

        renderBanners(lines);
    }

    function renderBanners(activeLines) {
        if (!bannersEl || !isInteractive) {
            return;
        }
        const deferredLines = activeLines.filter((line) => lineMetrics(line).deferredQty > 0);
        if (deferredLines.length === 0) {
            bannersEl.hidden = true;
            bannersEl.innerHTML = '';
            return;
        }

        bannersEl.hidden = false;
        bannersEl.innerHTML = deferredLines
            .map((line) => {
                const metrics = lineMetrics(line);
                const bannerClass = isZeroAcceptedDeferral(metrics)
                    ? 'cv-comptroller-deferred-banner cv-comptroller-deferred-banner--none'
                    : 'cv-comptroller-deferred-banner';
                return `<div class="${bannerClass}" role="alert">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    <span>${escapeHtml(deferredBannerMessage(metrics, line.item_name))}</span>
                </div>`;
            })
            .join('');
    }

    function findFirstIncompleteDeferredIndex() {
        for (let index = 0; index < lines.length; index += 1) {
            const metrics = lineMetrics(lines[index]);
            if (metrics.deferredQty > 0 && !hasDeferredReason(lines[index])) {
                return index;
            }
        }
        return -1;
    }

    function highlightDeferredReasonErrors() {
        const firstIncomplete = findFirstIncompleteDeferredIndex();
        if (firstIncomplete < 0) {
            hideValidationBanner();
            return true;
        }

        showValidationBanner();
        const anchor = bodyEl.querySelector(
            `.cv-comptroller-accepted-input[data-line-index="${firstIncomplete}"]`
        );
        if (anchor) {
            anchor.scrollIntoView({ behavior: 'smooth', block: 'center' });
            openPopover(firstIncomplete, anchor);
            showPopoverError();
        }
        return false;
    }

    function validateBeforeSubmit() {
        if (!isInteractive || !form) {
            return false;
        }
        commitActiveAcceptedQtyInput();
        for (const line of lines) {
            const metrics = lineMetrics(line);
            if (metrics.acceptedQty > metrics.requestedQty) {
                return false;
            }
        }
        return highlightDeferredReasonErrors();
    }

    window.CWIRMSComptrollerPricing = {
        validateBeforeSubmit,
        submitApprovalForm() {
            if (!form || !isInteractive) {
                return false;
            }
            if (!validateBeforeSubmit()) {
                return false;
            }
            form.submit();
            return true;
        },
        refresh: renderTable,
    };

    function syncAcceptedQtyInputsFromLines() {
        lines.forEach((line, index) => {
            const requestedQty = Math.max(0, Number(line.requested_qty) || 0);
            line.accepted_qty = resolveInitialAcceptedQty(line, requestedQty);
            const input = bodyEl.querySelector(
                `.cv-comptroller-accepted-input[data-line-index="${index}"]`
            );
            if (input) {
                const metrics = lineMetrics(line);
                input.value = String(metrics.acceptedQty);
            }
        });
        refreshSummary();
    }

    renderTable();
    syncAcceptedQtyInputsFromLines();
    bindEditReasonTriggers();
})();
