-- BIR-accurate fields for purchase_order_taxes

ALTER TABLE purchase_order_taxes
    ADD COLUMN transaction_type VARCHAR(100) NULL AFTER tax_type;

ALTER TABLE purchase_order_taxes
    ADD COLUMN supplier_vat_registered TINYINT(1) NULL AFTER label;

ALTER TABLE purchase_order_taxes
    ADD COLUMN transaction_vat_exempt TINYINT(1) NULL AFTER supplier_vat_registered;

ALTER TABLE purchase_order_taxes
    ADD COLUMN rate_override TINYINT(1) NOT NULL DEFAULT 0 AFTER rate;
