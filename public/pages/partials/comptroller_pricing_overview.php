<?php
$poViewerRole = $pricingOverviewViewerRole ?? 'comptroller';
$poInteractive = !empty($pricingOverviewInteractive);
$poRoleClass = preg_replace('/[^a-z0-9_-]+/', '-', strtolower($poViewerRole)) ?: 'comptroller';
?>
<section
    class="cv-pricing-overview-section cv-comptroller-pricing-section cv-comptroller-pricing-section--<?php echo htmlspecialchars($poRoleClass); ?>"
    id="cvComptrollerPricingSection"
    aria-label="Pricing overview with quantity review"
    hidden
>
    <?php if ($poInteractive): ?>
    <form
        id="comptrollerPricingForm"
        class="cv-comptroller-pricing-form"
        method="post"
        action="../../app/handlers/comptroller_canvass_approval.php"
        novalidate
    >
        <input type="hidden" name="request_id" value="<?php echo (int) $requestId; ?>">
        <input type="hidden" name="comp_status" value="accept">
    <?php else: ?>
    <div id="comptrollerPricingForm" class="cv-comptroller-pricing-form cv-comptroller-pricing-readonly">
    <?php endif; ?>

        <div id="cvComptrollerValidationBanner" class="cv-comptroller-validation-banner" hidden role="alert">
            Please enter a reason for all deferred items before approving.
        </div>

        <div class="cv-pricing-overview-head">
            <div>
                <h2 class="cv-pricing-overview-title">Pricing overview<?php echo $poInteractive ? ' · quantity review' : ''; ?></h2>
                <p class="cv-pricing-overview-hint" id="cvComptrollerPricingHint">
                    <?php if ($poInteractive): ?>
                    Review suggested suppliers, adjust accepted quantities, and enter a reason for any deferred units before approving.
                    <?php elseif ($poViewerRole === 'requester' || $poViewerRole === 'president'): ?>
                    Review suggested suppliers, accepted quantities, and any comptroller notes on deferred units.
                    <?php else: ?>
                    Review suggested suppliers, accepted quantities, and comptroller notes on deferred units.
                    <?php endif; ?>
                </p>
                <p
                    id="cvComptrollerPricingPendingNotice"
                    class="cv-comptroller-pending-notice"
                    <?php echo ($comptrollerCompStatus ?? 'pending') === 'pending' && in_array($poViewerRole, ['requester', 'president'], true) ? '' : 'hidden'; ?>
                >
                    Awaiting comptroller approval
                </p>
            </div>
            <div class="cv-pricing-overview-summary" aria-live="polite">
                <span class="cv-pricing-overview-summary-label">Approved total</span>
                <strong class="cv-pricing-overview-grand-total" id="cvComptrollerPricingGrandTotal">PHP 0.00</strong>
                <span class="cv-pricing-overview-progress" id="cvComptrollerPricingProgress">0 of 0 items fully approved</span>
            </div>
        </div>

        <div class="cv-pricing-overview-table-wrap">
            <table class="cv-pricing-overview-table cv-comptroller-pricing-table" id="cvComptrollerPricingTable">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Item</th>
                        <th scope="col">Requested qty</th>
                        <th scope="col">Accepted qty</th>
                        <th scope="col">Deferred qty</th>
                        <th scope="col">Suggested supplier</th>
                        <th scope="col">Source</th>
                        <th scope="col">Unit price</th>
                        <th scope="col">Line total</th>
                    </tr>
                </thead>
                <tbody id="cvComptrollerPricingBody">
                    <tr class="cv-pricing-overview-empty">
                        <td colspan="9">Loading pricing lines…</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="8" class="cv-pricing-overview-foot-label">Grand total</td>
                        <td class="cv-pricing-overview-foot-total" id="cvComptrollerPricingFootTotal">PHP 0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>

    <?php if ($poInteractive): ?>
    </form>
    <?php else: ?>
    </div>
    <?php endif; ?>
</section>
