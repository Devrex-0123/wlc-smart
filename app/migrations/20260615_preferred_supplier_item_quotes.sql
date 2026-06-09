-- Preferred-supplier × canvass-item quote junction (many-to-many via price rows).
-- Replaces JSON-only quoted_prices / quote_photos on requisition_preferred_suppliers.
--
-- phpMyAdmin / XAMPP (MariaDB 10.4+):
--   1. Click database "cwirms" in the left sidebar.
--   2. Open SQL tab, paste this whole file, click Go.

USE `cwirms`;

CREATE TABLE IF NOT EXISTS `requisition_preferred_supplier_item` (
  `preferred_supplier_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `price` decimal(12,2) DEFAULT NULL,
  `quote_photo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`preferred_supplier_item_id`),
  UNIQUE KEY `uq_rpsi_request_supplier_sort` (`request_id`,`supplier_id`,`sort_order`),
  KEY `idx_rpsi_supplier` (`supplier_id`),
  CONSTRAINT `rpsi_fk_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `rpsi_fk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
