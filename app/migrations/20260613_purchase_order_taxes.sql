-- Comptroller tax & deduction audit records for purchase orders

ALTER TABLE purchase_orders
    ADD COLUMN net_payable DECIMAL(12, 2) NULL AFTER total_amount;

ALTER TABLE purchase_orders
    ADD COLUMN tax_computed TINYINT(1) NOT NULL DEFAULT 0 AFTER net_payable;

CREATE TABLE IF NOT EXISTS purchase_order_taxes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_order_id INT UNSIGNED NOT NULL,
    tax_type VARCHAR(50) NOT NULL,
    rate DECIMAL(5, 4) NOT NULL DEFAULT 0.0000,
    amount_deducted DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    label VARCHAR(100) NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
