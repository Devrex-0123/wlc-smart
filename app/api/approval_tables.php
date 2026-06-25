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

function ensureCanvassVerificationCanvasSubmissionStatusColumn(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $colCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'canvass_verification_approval'
           AND COLUMN_NAME = 'canvas_submission_status'"
    );
    $colCheck->execute();
    if (((int) $colCheck->fetchColumn()) === 0) {
        $db->exec(
            "ALTER TABLE canvass_verification_approval
             ADD COLUMN canvas_submission_status ENUM('draft', 'submitted') DEFAULT 'draft' AFTER pres_status"
        );
        $db->exec(
            "UPDATE canvass_verification_approval
             SET canvas_submission_status = 'submitted'
             WHERE canvas_status = 'accept'
                OR gsd_status IS NOT NULL
                OR comp_status IS NOT NULL
                OR pres_status IS NOT NULL"
        );
        $db->exec(
            "UPDATE canvass_verification_approval
             SET canvas_submission_status = 'draft'
             WHERE canvas_status NOT IN ('accept', 'reject')
               AND comp_status IS NULL
               AND gsd_status IS NULL
               AND pres_status IS NULL
               AND checked_by IS NULL"
        );
        $db->exec(
            'CREATE INDEX idx_canvass_submission_status
             ON canvass_verification_approval (canvas_submission_status)'
        );
    }
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
    // DEPRECATED: This function is no longer needed as of 2025-04-15
    // The requisition_preferred_suppliers table has been completely eliminated
    // See migration: 20260615_drop_requisition_preferred_suppliers_table.sql
    // All preferred supplier data now uses only requisition_preferred_supplier_item table
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    // Do nothing - table no longer exists
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

function ensurePurchaseOrderTables(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!cwirmsApprovalTableExists($db, 'purchase_orders')) {
        $db->exec(
            "CREATE TABLE purchase_orders (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                po_number VARCHAR(20) NOT NULL,
                requisition_id INT NULL,
                requested_by_user_id INT NULL,
                requested_by_name VARCHAR(150) NULL,
                facility_id INT NULL,
                location_facility VARCHAR(255) NULL,
                supplier_id INT NULL,
                supplier_name VARCHAR(100) NULL,
                supplier_tin VARCHAR(20) NULL,
                mode_of_payment ENUM('cash', 'cheque', 'bank_transfer') NULL,
                purpose_of_request TEXT NULL,
                total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                approved_by_president TINYINT(1) NOT NULL DEFAULT 0,
                approved_at DATETIME NULL,
                created_by_user_id INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_purchase_orders_po_number (po_number),
                KEY idx_purchase_orders_requisition (requisition_id),
                KEY idx_purchase_orders_status (status),
                KEY idx_purchase_orders_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    $paymentTermsCol = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'purchase_orders'
           AND COLUMN_NAME = 'payment_terms'"
    );
    $paymentTermsCol->execute();
    if (((int) $paymentTermsCol->fetchColumn()) > 0) {
        $db->exec('ALTER TABLE purchase_orders DROP COLUMN payment_terms');
    }

    if (!cwirmsApprovalTableExists($db, 'purchase_order_lines')) {
        $db->exec(
            "CREATE TABLE purchase_order_lines (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                purchase_order_id INT UNSIGNED NOT NULL,
                description VARCHAR(255) NOT NULL,
                sub_description VARCHAR(255) NULL,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_po_lines_order (purchase_order_id, sort_order),
                CONSTRAINT fk_po_lines_header
                    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    $poTaxColumns = [
        'net_payable' => 'ADD COLUMN net_payable DECIMAL(12, 2) NULL AFTER total_amount',
        'tax_computed' => 'ADD COLUMN tax_computed TINYINT(1) NOT NULL DEFAULT 0 AFTER net_payable',
    ];
    foreach ($poTaxColumns as $column => $ddl) {
        $colCheck = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'purchase_orders'
               AND COLUMN_NAME = ?"
        );
        $colCheck->execute([$column]);
        if (((int) $colCheck->fetchColumn()) === 0) {
            $db->exec("ALTER TABLE purchase_orders {$ddl}");
        }
    }

    if (!cwirmsApprovalTableExists($db, 'purchase_order_taxes')) {
        $db->exec(
            "CREATE TABLE purchase_order_taxes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                purchase_order_id INT UNSIGNED NOT NULL,
                tax_type VARCHAR(50) NOT NULL,
                transaction_type VARCHAR(100) NULL,
                rate DECIMAL(5, 4) NOT NULL DEFAULT 0.0000,
                rate_override TINYINT(1) NOT NULL DEFAULT 0,
                amount_deducted DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                label VARCHAR(100) NULL,
                supplier_vat_registered TINYINT(1) NULL,
                transaction_vat_exempt TINYINT(1) NULL,
                notes TEXT NULL,
                computed_by INT NULL,
                computed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_po_taxes_po (purchase_order_id),
                CONSTRAINT fk_po_taxes_header
                    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
                CONSTRAINT fk_po_taxes_computed_by
                    FOREIGN KEY (computed_by) REFERENCES user (user_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    $poTaxExtraColumns = [
        'transaction_type' => 'ADD COLUMN transaction_type VARCHAR(100) NULL AFTER tax_type',
        'rate_override' => 'ADD COLUMN rate_override TINYINT(1) NOT NULL DEFAULT 0 AFTER rate',
        'supplier_vat_registered' => 'ADD COLUMN supplier_vat_registered TINYINT(1) NULL AFTER label',
        'transaction_vat_exempt' => 'ADD COLUMN transaction_vat_exempt TINYINT(1) NULL AFTER supplier_vat_registered',
    ];
    foreach ($poTaxExtraColumns as $column => $ddl) {
        $colCheck = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'purchase_order_taxes'
               AND COLUMN_NAME = ?"
        );
        $colCheck->execute([$column]);
        if (((int) $colCheck->fetchColumn()) === 0) {
            $db->exec("ALTER TABLE purchase_order_taxes {$ddl}");
        }
    }

    require_once __DIR__ . '/../helpers/user_notifications.php';
    cwirmsEnsurePoTaxStatusColumns($db);
    ensureUserNotificationsTable($db);
    ensurePurchaseOrderFeeColumns($db);
}

function ensurePurchaseOrderFeeColumns(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $columns = [
        'shipping_fee'              => "ADD COLUMN shipping_fee              DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER total_amount",
        'shipping_method'           => "ADD COLUMN shipping_method           VARCHAR(100)  NULL DEFAULT NULL       AFTER shipping_fee",
        'shipping_address'          => "ADD COLUMN shipping_address          TEXT          NULL DEFAULT NULL       AFTER shipping_method",
        'handling_fee'              => "ADD COLUMN handling_fee              DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER shipping_address",
        'insurance_fee'             => "ADD COLUMN insurance_fee             DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER handling_fee",
        'installation_fee'          => "ADD COLUMN installation_fee          DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER insurance_fee",
        'other_charges'             => "ADD COLUMN other_charges             DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER installation_fee",
        'other_charges_description' => "ADD COLUMN other_charges_description VARCHAR(255)  NULL DEFAULT NULL       AFTER other_charges",
        'discount_amount'           => "ADD COLUMN discount_amount           DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER other_charges_description",
        'discount_percentage'       => "ADD COLUMN discount_percentage       DECIMAL(5,2)  NOT NULL DEFAULT 0.00  AFTER discount_amount",
        'discount_reason'           => "ADD COLUMN discount_reason           VARCHAR(255)  NULL DEFAULT NULL       AFTER discount_percentage",
        'taxable_amount'            => "ADD COLUMN taxable_amount            DECIMAL(12,2) NULL DEFAULT NULL       AFTER discount_reason",
        'payment_terms'             => "ADD COLUMN payment_terms             VARCHAR(100)  NULL DEFAULT NULL       AFTER taxable_amount",
        'payment_due_date'          => "ADD COLUMN payment_due_date          DATE          NULL DEFAULT NULL       AFTER payment_terms",
        'transaction_type'          => "ADD COLUMN transaction_type          VARCHAR(100)  NULL DEFAULT NULL       AFTER payment_due_date",
    ];

    $chk = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'purchase_orders'
           AND COLUMN_NAME  = ?"
    );
    foreach ($columns as $col => $ddl) {
        $chk->execute([$col]);
        if ((int) $chk->fetchColumn() === 0) {
            $db->exec("ALTER TABLE purchase_orders {$ddl}");
        }
    }
}

function ensurePreferredSupplierItemQuotesTable(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    ensureRequisitionPreferredQuoteColumns($db);

    $db->exec(
        'CREATE TABLE IF NOT EXISTS requisition_preferred_supplier_item (
            preferred_supplier_item_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            supplier_id INT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            price DECIMAL(12,2) DEFAULT NULL,
            quote_photo VARCHAR(255) DEFAULT NULL,
            UNIQUE KEY uq_rpsi_request_supplier_sort (request_id, supplier_id, sort_order),
            KEY idx_rpsi_supplier (supplier_id),
            CONSTRAINT rpsi_fk_request FOREIGN KEY (request_id) REFERENCES requisition_item (request_id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT rpsi_fk_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (supplier_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    cwirmsBackfillPreferredQuotesJsonToJunction($db);
}

function cwirmsBackfillPreferredQuotesJsonToJunction(PDO $db): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    try {
        $countStmt = $db->query('SELECT COUNT(*) FROM requisition_preferred_supplier_item');
        if ($countStmt && (int) $countStmt->fetchColumn() > 0) {
            return;
        }

        $stmt = $db->query(
            'SELECT request_id, supplier_id, quoted_prices, quote_photos
             FROM requisition_preferred_suppliers
             WHERE (quoted_prices IS NOT NULL AND quoted_prices <> \'\')
                OR (quote_photos IS NOT NULL AND quote_photos <> \'\')'
        );
        if (!$stmt) {
            return;
        }

        $ins = $db->prepare(
            'INSERT INTO requisition_preferred_supplier_item (request_id, supplier_id, sort_order, price, quote_photo)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                price = COALESCE(VALUES(price), price),
                quote_photo = COALESCE(VALUES(quote_photo), quote_photo)'
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $requestId = (int) ($row['request_id'] ?? 0);
            $supplierId = (int) ($row['supplier_id'] ?? 0);
            if ($requestId <= 0 || $supplierId <= 0) {
                continue;
            }

            $prices = [];
            if (isset($row['quoted_prices']) && $row['quoted_prices'] !== null && $row['quoted_prices'] !== '') {
                $decoded = json_decode((string) $row['quoted_prices'], true);
                if (is_array($decoded)) {
                    $prices = $decoded;
                }
            }
            $photos = [];
            if (isset($row['quote_photos']) && $row['quote_photos'] !== null && $row['quote_photos'] !== '') {
                $decoded = json_decode((string) $row['quote_photos'], true);
                if (is_array($decoded)) {
                    $photos = $decoded;
                }
            }

            $sortOrders = [];
            foreach (array_keys($prices) as $k) {
                $sortOrders[(int) $k] = true;
            }
            foreach (array_keys($photos) as $k) {
                $sortOrders[(int) $k] = true;
            }

            foreach (array_keys($sortOrders) as $sortOrder) {
                $priceRaw = $prices[$sortOrder] ?? $prices[(string) $sortOrder] ?? null;
                $photoRaw = $photos[$sortOrder] ?? $photos[(string) $sortOrder] ?? null;
                $priceVal = null;
                if ($priceRaw !== null && $priceRaw !== '' && is_numeric($priceRaw)) {
                    $priceVal = round((float) $priceRaw, 2);
                }
                $photoVal = null;
                if ($photoRaw !== null && trim((string) $photoRaw) !== '') {
                    $photoVal = substr(trim((string) $photoRaw), 0, 255);
                }
                if ($priceVal === null && $photoVal === null) {
                    continue;
                }
                $ins->execute([$requestId, $supplierId, (int) $sortOrder, $priceVal, $photoVal]);
            }
        }
    } catch (Throwable $e) {
        // ignore migration errors on older schemas
    }
}

/**
 * Canonical per-line supplier quotes (preferred + canvassed).
 * Required by requester preferred-quote modal and canvasser workspace.
 */
function ensureRequisitionLineQuotesTable(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $tableCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'requisition_line_quotes'"
    );
    $tableCheck->execute();
    if (((int) $tableCheck->fetchColumn()) === 0) {
        $db->exec(
            "CREATE TABLE requisition_line_quotes (
                quote_id INT NOT NULL AUTO_INCREMENT,
                requisition_line_id INT NOT NULL,
                supplier_id INT NOT NULL,
                quoted_unit_price DECIMAL(12,2) NOT NULL,
                quote_type ENUM('preferred','canvassed') NOT NULL DEFAULT 'canvassed',
                submitted_by_user_id INT NULL DEFAULT NULL,
                benefits TEXT NULL DEFAULT NULL,
                quote_photo VARCHAR(255) NULL DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (quote_id),
                UNIQUE KEY uq_line_supplier (requisition_line_id, supplier_id, quote_type),
                KEY idx_rlq_line (requisition_line_id),
                KEY idx_rlq_supplier (supplier_id),
                CONSTRAINT fk_rlq_line FOREIGN KEY (requisition_line_id)
                    REFERENCES requisition_line (requisition_line_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_rlq_supplier FOREIGN KEY (supplier_id)
                    REFERENCES suppliers (supplier_id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        return;
    }

    $colCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'requisition_line_quotes'
           AND COLUMN_NAME = 'quote_type'"
    );
    $colCheck->execute();
    if (((int) $colCheck->fetchColumn()) === 0) {
        $db->exec(
            "ALTER TABLE requisition_line_quotes
             ADD COLUMN quote_type ENUM('preferred','canvassed') NOT NULL DEFAULT 'canvassed' AFTER quoted_unit_price"
        );
    }

    $idxStmt = $db->query('SHOW INDEX FROM requisition_line_quotes');
    $idxCols = [];
    if ($idxStmt) {
        $bySeq = [];
        while ($idxRow = $idxStmt->fetch(PDO::FETCH_ASSOC)) {
            if ((string) ($idxRow['Key_name'] ?? '') !== 'uq_line_supplier') {
                continue;
            }
            $bySeq[(int) ($idxRow['Seq_in_index'] ?? 0)] = (string) ($idxRow['Column_name'] ?? '');
        }
        ksort($bySeq);
        $idxCols = array_values($bySeq);
    }
    if ($idxCols !== [] && !in_array('quote_type', $idxCols, true)) {
        try {
            $db->exec('ALTER TABLE requisition_line_quotes DROP INDEX uq_line_supplier');
        } catch (Throwable $e) {
            // ignore if index name differs
        }
        try {
            $db->exec(
                'ALTER TABLE requisition_line_quotes
                 ADD UNIQUE KEY uq_line_supplier (requisition_line_id, supplier_id, quote_type)'
            );
        } catch (Throwable $e) {
            // ignore if already correct or data prevents migration
        }
    }
}

function ensureRequisitionLineQuotesGsdColumns(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    ensureRequisitionLineQuotesTable($db);

    $cols = ['canvasser_name' => "VARCHAR(100) NULL DEFAULT NULL", 'discount_percent' => "DECIMAL(5,2) NULL DEFAULT NULL"];
    foreach ($cols as $col => $def) {
        $chk = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'requisition_line_quotes'
               AND COLUMN_NAME = ?"
        );
        $chk->execute([$col]);
        if (((int) $chk->fetchColumn()) === 0) {
            $db->exec("ALTER TABLE requisition_line_quotes ADD COLUMN `{$col}` {$def}");
        }
    }
}

function ensureRequisitionLineAwardsTable(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    ensureRequisitionLineQuotesTable($db);

    $tableCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'requisition_line_awards'"
    );
    $tableCheck->execute();
    if (((int) $tableCheck->fetchColumn()) === 0) {
        $db->exec(
            "CREATE TABLE requisition_line_awards (
                award_id INT NOT NULL AUTO_INCREMENT,
                requisition_line_id INT NOT NULL,
                quote_id INT NULL DEFAULT NULL,
                supplier_id INT NOT NULL,
                selection_source ENUM('preferred','canvassed') NULL DEFAULT NULL,
                awarded_qty INT NOT NULL,
                deferred_qty INT NULL DEFAULT 0,
                deferred_reason TEXT NULL,
                comptroller_status ENUM('fully_approved','deferred','rejected') NULL DEFAULT NULL,
                awarded_by_user_id INT NOT NULL,
                awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (award_id),
                UNIQUE KEY uq_line_award (requisition_line_id),
                KEY idx_rla_line (requisition_line_id),
                KEY idx_rla_quote (quote_id),
                KEY idx_rla_supplier (supplier_id),
                KEY fk_rla_awarded_by (awarded_by_user_id),
                CONSTRAINT fk_rla_line FOREIGN KEY (requisition_line_id)
                    REFERENCES requisition_line (requisition_line_id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_rla_quote FOREIGN KEY (quote_id)
                    REFERENCES requisition_line_quotes (quote_id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_rla_supplier FOREIGN KEY (supplier_id)
                    REFERENCES suppliers (supplier_id) ON UPDATE CASCADE,
                CONSTRAINT fk_rla_awarded_by FOREIGN KEY (awarded_by_user_id)
                    REFERENCES user (user_id) ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        return;
    }

    $colCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'requisition_line_awards'
           AND COLUMN_NAME = 'selection_source'"
    );
    $colCheck->execute();
    if (((int) $colCheck->fetchColumn()) === 0) {
        $db->exec(
            "ALTER TABLE requisition_line_awards
             ADD COLUMN selection_source ENUM('preferred','canvassed') NULL DEFAULT NULL
             AFTER supplier_id"
        );
    }
}

/**
 * @return array<int, array{sort_orders: list<int>, prices: array<int, string>, photos: array<int, string>}>
 */
function cwirmsLoadPreferredSupplierQuoteMapsForRequest(PDO $db, int $requestId): array
{
    ensurePreferredSupplierItemQuotesTable($db);
    $out = [];
    $stmt = $db->prepare(
        'SELECT supplier_id, sort_order, price, quote_photo
         FROM requisition_preferred_supplier_item
         WHERE request_id = ?
         ORDER BY supplier_id ASC, sort_order ASC'
    );
    $stmt->execute([$requestId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int) ($row['supplier_id'] ?? 0);
        $sortOrder = (int) ($row['sort_order'] ?? 0);
        if ($sid <= 0 || $sortOrder < 0) {
            continue;
        }
        if (!isset($out[$sid])) {
            $out[$sid] = ['sort_orders' => [], 'prices' => [], 'photos' => []];
        }
        $out[$sid]['sort_orders'][] = $sortOrder;
        if (isset($row['price']) && $row['price'] !== null && $row['price'] !== '') {
            $out[$sid]['prices'][$sortOrder] = (string) $row['price'];
        }
        $photo = trim((string) ($row['quote_photo'] ?? ''));
        if ($photo !== '') {
            $out[$sid]['photos'][$sortOrder] = $photo;
        }
    }

    return $out;
}

function cwirmsSyncPreferredSupplierQuoteJsonColumns(PDO $db, int $requestId, int $supplierId): void
{
    // DEPRECATED: This function is no longer needed as of 2025-04-15
    // The requisition_preferred_suppliers table with JSON columns has been completely eliminated
    // See migration: 20260615_drop_requisition_preferred_suppliers_table.sql
    // Quote data is now stored directly in requisition_preferred_supplier_item table
    // Do nothing - no synchronization needed
}

function cwirmsPreferredQuotedPriceForSortOrder(PDO $db, int $requestId, int $supplierId, int $sortOrder): ?string
{
    ensurePreferredSupplierItemQuotesTable($db);
    $stmt = $db->prepare(
        'SELECT price FROM requisition_preferred_supplier_item
         WHERE request_id = ? AND supplier_id = ? AND sort_order = ?
         LIMIT 1'
    );
    $stmt->execute([$requestId, $supplierId, $sortOrder]);
    $raw = $stmt->fetchColumn();
    if ($raw === false || $raw === null || $raw === '' || !is_numeric($raw)) {
        return null;
    }

    return (string) round((float) $raw, 2);
}
