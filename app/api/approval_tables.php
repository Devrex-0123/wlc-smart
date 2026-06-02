<?php

declare(strict_types=1);

/**
 * Split approval storage: requisition form vs canvass/GSD/comptroller/president chain vs purchase requisition.
 */

function cwirmsApprovalTableExists(PDO $db, string $table): bool
{
    $st = $db->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute([$table]);

    return (bool) $st->fetchColumn();
}

function ensureRequisitionFormApprovalRow(PDO $db, int $requestId): void
{
    $st = $db->prepare('INSERT IGNORE INTO requisition_form_approval (request_id) VALUES (?)');
    $st->execute([$requestId]);
}

function ensureCanvassVerificationApprovalRow(PDO $db, int $requestId): void
{
    $st = $db->prepare('INSERT IGNORE INTO canvass_verification_approval (request_id) VALUES (?)');
    $st->execute([$requestId]);
}

function ensurePurchaseRequisitionApprovalRow(PDO $db, int $requestId): void
{
    $st = $db->prepare('INSERT IGNORE INTO purchase_requisition_approval (request_id) VALUES (?)');
    $st->execute([$requestId]);
}

function ensureRequisitionCanvassSubmissionColumn(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $colCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'requisition_canvass_detail'
           AND COLUMN_NAME = 'canvass_submission_status'"
    );
    $colCheck->execute();
    $exists = ((int) $colCheck->fetchColumn()) > 0;
    if (!$exists) {
        $db->exec(
            "ALTER TABLE requisition_canvass_detail
             ADD COLUMN canvass_submission_status ENUM('draft', 'submitted') DEFAULT 'draft'
             AFTER sort_order"
        );
        $db->exec(
            "CREATE INDEX idx_canvass_detail_submission_status
             ON requisition_canvass_detail (canvass_submission_status)"
        );
    }

    // One-time backfill from legacy storage on canvass_verification_approval (if legacy column exists).
    $legacyColCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'canvass_verification_approval'
           AND COLUMN_NAME = 'canvas_submission_status'"
    );
    $legacyColCheck->execute();
    $legacyColumnExists = ((int) $legacyColCheck->fetchColumn()) > 0;
    if ($legacyColumnExists) {
        $db->exec(
            "UPDATE requisition_canvass_detail rcd
             INNER JOIN canvass_verification_approval cva ON cva.request_id = rcd.request_id
             SET rcd.canvass_submission_status = CASE
                 WHEN LOWER(TRIM(COALESCE(cva.canvas_submission_status, 'draft'))) = 'submitted' THEN 'submitted'
                 ELSE COALESCE(rcd.canvass_submission_status, 'draft')
             END"
        );
    }
}
