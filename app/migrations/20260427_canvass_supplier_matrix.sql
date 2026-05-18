-- Supplier × canvass line pricing (same idea as requisition_line_supplier).

CREATE TABLE IF NOT EXISTS `requisition_canvass_detail_supplier` (
  `canvass_detail_supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `canvass_detail_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `price` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`canvass_detail_supplier_id`),
  UNIQUE KEY `uq_canvass_detail_supplier` (`canvass_detail_id`,`supplier_id`),
  KEY `idx_cds_supplier` (`supplier_id`),
  CONSTRAINT `cds_fk_detail` FOREIGN KEY (`canvass_detail_id`) REFERENCES `requisition_canvass_detail` (`canvass_detail_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `cds_fk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Line-level price replaced by per-supplier quotes (skip if you already dropped `price`).
ALTER TABLE `requisition_canvass_detail` DROP COLUMN `price`;
