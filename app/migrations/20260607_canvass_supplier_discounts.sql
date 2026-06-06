-- Multiple compounded discounts per canvassed supplier (replaces single discount_percent column).

CREATE TABLE IF NOT EXISTS `canvass_supplier_discounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `canvass_supplier_id` int(11) NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `discount_percent` decimal(5,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_csd_canvass_supplier` (`canvass_supplier_id`),
  CONSTRAINT `fk_csd_canvass_supplier` FOREIGN KEY (`canvass_supplier_id`)
    REFERENCES `requisition_canvass_detail_supplier` (`canvass_detail_supplier_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migrate legacy single discount_percent values (one row per supplier, anchored on earliest matrix row).
INSERT INTO `canvass_supplier_discounts` (`canvass_supplier_id`, `label`, `discount_percent`)
SELECT agg.anchor_id, NULL, agg.discount_percent
FROM (
  SELECT MIN(cds.canvass_detail_supplier_id) AS anchor_id, MAX(cds.discount_percent) AS discount_percent
  FROM `requisition_canvass_detail_supplier` cds
  WHERE cds.discount_percent IS NOT NULL AND cds.discount_percent > 0
  GROUP BY cds.supplier_id
) AS agg;

ALTER TABLE `requisition_canvass_detail_supplier` DROP COLUMN `discount_percent`;
