-- Normalize requisitions: one `requisition_item` row = one request (header).
-- Requested catalog/custom lines live in `requisition_line` (FK request_id).
-- Supplier quotes per line (matrix cell price) live in `requisition_line_supplier` (FK requisition_line_id).
--
-- Run once on an existing database. After this, application code should:
-- - INSERT one header into requisition_item per submission
-- - INSERT one row per requested item into requisition_line
-- - INSERT one row per (line, supplier) into requisition_line_supplier with optional price
--
-- Existing row-per-(item×supplier) data is copied 1:1 (each old request_id keeps one line + optional supplier row).

CREATE TABLE IF NOT EXISTS `requisition_line` (
  `requisition_line_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(100) NOT NULL,
  `item_brand` varchar(100) DEFAULT NULL,
  `item_category` varchar(100) DEFAULT NULL,
  `photo_url` varchar(100) NOT NULL DEFAULT '',
  `quantity` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`requisition_line_id`),
  KEY `idx_rl_request` (`request_id`),
  KEY `idx_rl_item` (`item_id`),
  CONSTRAINT `requisition_line_ibfk_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `requisition_line_ibfk_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `requisition_line_supplier` (
  `requisition_line_supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `requisition_line_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`requisition_line_supplier_id`),
  UNIQUE KEY `uq_rls_line_supplier` (`requisition_line_id`,`supplier_id`),
  KEY `idx_rls_supplier` (`supplier_id`),
  CONSTRAINT `requisition_line_supplier_ibfk_line` FOREIGN KEY (`requisition_line_id`) REFERENCES `requisition_line` (`requisition_line_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `requisition_line_supplier_ibfk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Backfill from legacy flat columns (skip if this database was already migrated).
INSERT INTO `requisition_line` (`request_id`, `sort_order`, `item_id`, `item_name`, `item_brand`, `item_category`, `photo_url`, `quantity`)
SELECT
  ri.`request_id`,
  0,
  ri.`item_id`,
  ri.`item_name`,
  NULLIF(TRIM(ri.`item_brand`), ''),
  NULLIF(TRIM(ri.`item_category`), ''),
  COALESCE(NULLIF(TRIM(ri.`photo_url`), ''), ''),
  ri.`quantity`
FROM `requisition_item` ri
WHERE NOT EXISTS (
  SELECT 1 FROM `requisition_line` rl WHERE rl.`request_id` = ri.`request_id`
)
AND EXISTS (
  SELECT 1 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'requisition_item'
    AND COLUMN_NAME = 'item_name'
);

INSERT INTO `requisition_line_supplier` (`requisition_line_id`, `supplier_id`, `price`)
SELECT rl.`requisition_line_id`, ri.`supplier_id`, ri.`price`
FROM `requisition_item` ri
INNER JOIN `requisition_line` rl ON rl.`request_id` = ri.`request_id`
WHERE ri.`supplier_id` IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `requisition_line_supplier` x
    WHERE x.`requisition_line_id` = rl.`requisition_line_id`
      AND x.`supplier_id` = ri.`supplier_id`
  )
  AND EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'requisition_item'
      AND COLUMN_NAME = 'supplier_id'
  );

-- Drop legacy supplier/item columns on the header (names from cwirms.sql).
ALTER TABLE `requisition_item` DROP FOREIGN KEY `requisition_item_ibfk_4`;
ALTER TABLE `requisition_item` DROP FOREIGN KEY `requisition_item_ibfk_5`;

ALTER TABLE `requisition_item`
  DROP COLUMN `supplier_id`,
  DROP COLUMN `item_id`,
  DROP COLUMN `item_name`,
  DROP COLUMN `item_brand`,
  DROP COLUMN `item_category`,
  DROP COLUMN `photo_url`,
  DROP COLUMN `quantity`,
  DROP COLUMN `price`;
