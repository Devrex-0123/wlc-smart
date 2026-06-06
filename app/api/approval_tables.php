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

function ensureRequisitionPreferredQuoteColumns(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $db->exec(
        'CREATE TABLE IF NOT EXISTS requisition_preferred_suppliers (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            supplier_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $quotedPricesCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'requisition_preferred_suppliers'
           AND COLUMN_NAME = 'quoted_prices'"
    );
    $quotedPricesCheck->execute();
    if (((int) $quotedPricesCheck->fetchColumn()) === 0) {
        $db->exec('ALTER TABLE requisition_preferred_suppliers ADD COLUMN quoted_prices TEXT NULL AFTER supplier_id');
    }

    $quotePhotosCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'requisition_preferred_suppliers'
           AND COLUMN_NAME = 'quote_photos'"
    );
    $quotePhotosCheck->execute();
    if (((int) $quotePhotosCheck->fetchColumn()) === 0) {
        $db->exec('ALTER TABLE requisition_preferred_suppliers ADD COLUMN quote_photos TEXT NULL AFTER quoted_prices');
    }
}

function dropRequisitionCanvassDetailPhotoColumns(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $photoAtCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'requisition_canvass_detail'
           AND COLUMN_NAME = 'photo_uploaded_at'"
    );
    $photoAtCheck->execute();
    if (((int) $photoAtCheck->fetchColumn()) > 0) {
        $db->exec('ALTER TABLE requisition_canvass_detail DROP COLUMN photo_uploaded_at');
    }

    $photoUrlCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'requisition_canvass_detail'
           AND COLUMN_NAME = 'photo_url'"
    );
    $photoUrlCheck->execute();
    if (((int) $photoUrlCheck->fetchColumn()) > 0) {
        $db->exec('ALTER TABLE requisition_canvass_detail DROP COLUMN photo_url');
    }
}

function ensureCanvassSupplierQuoteSourceColumn(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $colCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'requisition_canvass_detail_supplier'
           AND COLUMN_NAME = 'quote_source'"
    );
    $colCheck->execute();
    if (((int) $colCheck->fetchColumn()) === 0) {
        $db->exec(
            "ALTER TABLE requisition_canvass_detail_supplier
             ADD COLUMN quote_source ENUM('preferred','canvasser') NOT NULL DEFAULT 'canvasser' AFTER price"
        );
    }
}

function ensureCanvassSupplierNotesColumns(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    ensureCanvassSupplierQuoteSourceColumn($db);

    $columns = [
        'benefits' => 'TEXT NULL DEFAULT NULL',
    ];

    foreach ($columns as $name => $definition) {
        $colCheck = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'requisition_canvass_detail_supplier'
               AND COLUMN_NAME = ?"
        );
        $colCheck->execute([$name]);
        if (((int) $colCheck->fetchColumn()) === 0) {
            $db->exec(
                "ALTER TABLE requisition_canvass_detail_supplier ADD COLUMN {$name} {$definition}"
            );
        }
    }
}

function ensureCanvassSupplierDiscountsTable(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $tableCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'canvass_supplier_discounts'"
    );
    $tableCheck->execute();
    if (((int) $tableCheck->fetchColumn()) === 0) {
        $db->exec(
            'CREATE TABLE canvass_supplier_discounts (
                id INT(11) NOT NULL AUTO_INCREMENT,
                canvass_supplier_id INT(11) NOT NULL,
                label VARCHAR(100) NULL DEFAULT NULL,
                discount_percent DECIMAL(5,2) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_csd_canvass_supplier (canvass_supplier_id),
                CONSTRAINT fk_csd_canvass_supplier FOREIGN KEY (canvass_supplier_id)
                    REFERENCES requisition_canvass_detail_supplier (canvass_detail_supplier_id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    }

    $legacyCol = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'requisition_canvass_detail_supplier'
           AND COLUMN_NAME = 'discount_percent'"
    );
    $legacyCol->execute();
    if (((int) $legacyCol->fetchColumn()) > 0) {
        $db->exec(
            'INSERT INTO canvass_supplier_discounts (canvass_supplier_id, label, discount_percent)
             SELECT agg.anchor_id, NULL, agg.discount_percent
             FROM (
                 SELECT MIN(cds.canvass_detail_supplier_id) AS anchor_id, MAX(cds.discount_percent) AS discount_percent
                 FROM requisition_canvass_detail_supplier cds
                 WHERE cds.discount_percent IS NOT NULL AND cds.discount_percent > 0
                 GROUP BY cds.supplier_id
             ) AS agg'
        );
        $db->exec('ALTER TABLE requisition_canvass_detail_supplier DROP COLUMN discount_percent');
    }
}

function ensureSuggestedSupplierSelectionSourceColumn(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $colCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'request_approval_suggested_supplier_item'
           AND COLUMN_NAME = 'selection_source'"
    );
    $colCheck->execute();
    if (((int) $colCheck->fetchColumn()) === 0) {
        $db->exec(
            "ALTER TABLE request_approval_suggested_supplier_item
             ADD COLUMN selection_source ENUM('preferred','canvassed') NULL AFTER supplier_id"
        );
    }
}

function ensureComptrollerPartialQtyColumns(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $columns = [
        'accepted_qty' => 'INT UNSIGNED NULL',
        'deferred_qty' => 'INT UNSIGNED NULL DEFAULT 0',
        'deferred_message' => 'TEXT NULL',
        'comptroller_qty_status' => "ENUM('fully_approved','deferred') NULL",
        'comptroller_approved_by_user_id' => 'INT NULL',
        'comptroller_approved_at' => 'DATETIME NULL',
    ];

    foreach ($columns as $name => $definition) {
        $colCheck = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'request_approval_suggested_supplier_item'
               AND COLUMN_NAME = ?"
        );
        $colCheck->execute([$name]);
        if (((int) $colCheck->fetchColumn()) === 0) {
            $db->exec(
                "ALTER TABLE request_approval_suggested_supplier_item ADD COLUMN {$name} {$definition}"
            );
        }
    }
}
