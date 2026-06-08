-- Purchase Order header and line items (standalone PO module)

CREATE TABLE IF NOT EXISTS purchase_orders (
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
    KEY idx_purchase_orders_deleted (deleted_at),
    CONSTRAINT fk_po_requisition
        FOREIGN KEY (requisition_id) REFERENCES requisition_item (request_id) ON DELETE SET NULL,
    CONSTRAINT fk_po_requested_by
        FOREIGN KEY (requested_by_user_id) REFERENCES user (user_id) ON DELETE SET NULL,
    CONSTRAINT fk_po_facility
        FOREIGN KEY (facility_id) REFERENCES facilities (facility_id) ON DELETE SET NULL,
    CONSTRAINT fk_po_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers (supplier_id) ON DELETE SET NULL,
    CONSTRAINT fk_po_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES user (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_lines (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
