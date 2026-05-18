<?php
declare(strict_types=1);

/** @var int $rfRequestId */
if (!isset($rfRequestId) || (int) $rfRequestId <= 0) {
    return;
}
$rfStepLine = isset($rfStepLine) ? (string) $rfStepLine : '';
$rfHint = isset($rfHint) ? (string) $rfHint : '';
$rfHint = trim($rfHint);
$rfLinkUrl = isset($rfLinkUrl) ? (string) $rfLinkUrl : '';
$rfLinkUrl = trim($rfLinkUrl);
$rfLinkText = isset($rfLinkText) ? (string) $rfLinkText : '';
$rfLinkText = trim($rfLinkText);
?>
<div class="req-flow-context" role="navigation" aria-label="Request context">
    <div class="req-flow-context-top">
        <div class="req-flow-context-main">
            <span class="req-flow-id">Request #<?php echo (int) $rfRequestId; ?></span>
            <span class="req-flow-step"><?php echo htmlspecialchars($rfStepLine); ?></span>
        </div>
        <?php if ($rfLinkUrl !== '' && $rfLinkText !== ''): ?>
            <a class="req-flow-context-link" href="<?php echo htmlspecialchars($rfLinkUrl); ?>"><?php echo htmlspecialchars($rfLinkText); ?></a>
        <?php endif; ?>
    </div>
    <?php if ($rfHint !== ''): ?>
        <p class="req-flow-hint"><?php echo htmlspecialchars($rfHint); ?></p>
    <?php endif; ?>
</div>
