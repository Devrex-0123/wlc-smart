-- Many suppliers per catalog item (canvass matrix suggestions).
CREATE TABLE IF NOT EXISTS `item_supplier` (
  `item_supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`item_supplier_id`),
  UNIQUE KEY `uq_item_supplier` (`item_id`,`supplier_id`),
  KEY `idx_item_supplier_item` (`item_id`),
  KEY `idx_item_supplier_supplier` (`supplier_id`),
  CONSTRAINT `item_supplier_ibfk_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `item_supplier_ibfk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed junction from legacy single FK (one row per item that had supplier_id).
INSERT IGNORE INTO `item_supplier` (`item_id`, `supplier_id`, `sort_order`)
SELECT `item_id`, `supplier_id`, 0 FROM `items` WHERE `supplier_id` IS NOT NULL;
