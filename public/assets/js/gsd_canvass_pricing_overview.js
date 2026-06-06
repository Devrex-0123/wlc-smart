/**
 * Real-time pricing overview for G.S.D. and comptroller canvass review.
 * Depends on window.CWIRMSCanvassForm.buildPricingSnapshot() from dean_canvass_form.js.
 */
(function () {
    const section = document.getElementById('cvPricingOverviewSection');
    if (!section) {
        return;
    }

    const tableEl = document.getElementById('cvPricingOverviewTable');
    const bodyEl = document.getElementById('cvPricingOverviewBody');
    const grandTotalEl = document.getElementById('cvPricingOverviewGrandTotal');
    const footTotalEl = document.getElementById('cvPricingOverviewFootTotal');
    const footLabelEl = document.querySelector('.cv-pricing-overview-foot-label');
    const progressEl = document.getElementById('cvPricingOverviewProgress');
    const hintEl = document.getElementById('cvPricingOverviewHint');

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = value == null ? '' : String(value);
        return node.innerHTML;
    }

    function formatMoney(amount, currency) {
        const num = Number(amount);
        const safe = Number.isFinite(num) ? num : 0;
        return `${currency || 'PHP'} ${safe.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;
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

    function syncPricingOverviewDiscountColumn(showDiscount) {
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
            const unitTh = theadRow.children[5];
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
            footLabelEl.colSpan = showDiscount ? 7 : 6;
        }
    }

    function renderSnapshot(snapshot) {
        if (!snapshot || !Array.isArray(snapshot.lines)) {
            section.hidden = true;
            return;
        }

        if (snapshot.item_count === 0) {
            section.hidden = true;
            return;
        }

        section.hidden = false;

        const showDiscount = Boolean(snapshot.show_discount_column);
        syncPricingOverviewDiscountColumn(showDiscount);

        const currency = snapshot.currency || 'PHP';
        const grandTotal = formatMoney(snapshot.grand_total, currency);
        if (grandTotalEl) {
            grandTotalEl.textContent = grandTotal;
        }
        if (footTotalEl) {
            footTotalEl.textContent = grandTotal;
        }
        if (progressEl) {
            progressEl.textContent = `${snapshot.selected_count} of ${snapshot.item_count} items selected`;
        }
        if (hintEl) {
            hintEl.textContent =
                snapshot.selected_count >= snapshot.item_count
                    ? 'All items have a suggested supplier. Review the line totals below before verifying.'
                    : 'Select a suggested supplier for each item above. Line totals and the grand total update automatically.';
        }

        if (!bodyEl) {
            return;
        }

        const colSpan = showDiscount ? 8 : 7;

        if (snapshot.lines.length === 0) {
            bodyEl.innerHTML = `<tr class="cv-pricing-overview-empty"><td colspan="${colSpan}">No canvass items yet.</td></tr>`;
            return;
        }

        bodyEl.innerHTML = snapshot.lines
            .map((line, index) => {
                const pending = !line.supplier_id;
                const qtyFormatter = window.CWIRMSCanvassForm && window.CWIRMSCanvassForm.formatPricingOverviewQtyLabel;
                const qtyLabel = qtyFormatter
                    ? qtyFormatter(line)
                    : `${line.quantity} ${line.unit_type || 'unit'}`;
                const unitPrice = line.unit_price != null ? formatMoney(line.unit_price, currency) : '—';
                const lineTotal = line.line_total != null ? formatMoney(line.line_total, currency) : '—';
                const supplier = line.supplier_name
                    ? escapeHtml(line.supplier_name)
                    : '<span class="cv-pricing-overview-pending">Not selected</span>';
                const discountCell = showDiscount
                    ? `<td class="cv-pricing-overview-discount-cell">${
                          line.discount_label ? escapeHtml(line.discount_label) : '—'
                      }</td>`
                    : '';

                return `<tr class="${pending ? 'cv-pricing-overview-row-pending' : ''}">
                    <td>${index + 1}</td>
                    <td>${escapeHtml(line.item_name || `Item ${index + 1}`)}</td>
                    <td>${escapeHtml(qtyLabel)}</td>
                    <td>${supplier}</td>
                    <td>${sourceLabel(line.selection_source)}</td>
                    <td class="cv-pricing-overview-unit-price-cell">${unitPrice}</td>
                    ${discountCell}
                    <td class="cv-pricing-overview-line-total-cell">${lineTotal}</td>
                </tr>`;
            })
            .join('');
    }

    function refreshPricingOverview() {
        const api = window.CWIRMSCanvassForm;
        if (!api || typeof api.buildPricingSnapshot !== 'function') {
            return;
        }
        renderSnapshot(api.buildPricingSnapshot());
    }

    window.addEventListener('cwirms-canvass-pricing-update', refreshPricingOverview);
    window.addEventListener('load', () => {
        window.setTimeout(refreshPricingOverview, 0);
    });

    window.CWIRMSCanvassPricingOverview = {
        refresh: refreshPricingOverview,
    };
})();
