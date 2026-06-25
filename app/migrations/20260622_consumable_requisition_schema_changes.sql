-- Equipment vs consumable requisition / canvass support
-- Run once against the cwirms database before deploying PHP changes.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- requisition_line
-- ---------------------------------------------------------------------------
ALTER TABLE `requisition_line`
  ADD COLUMN IF NOT EXISTS `item_type` ENUM('equipment','consumable') NOT NULL DEFAULT 'equipment' AFTER `unit_type`;

ALTER TABLE `requisition_line`
  MODIFY COLUMN `quantity` INT NULL DEFAULT NULL;

-- MariaDB 10.4+ supports IF NOT EXISTS on ADD COLUMN; drop/recreate check safely
ALTER TABLE `requisition_line` DROP CONSTRAINT IF EXISTS `chk_rl_qty_by_type`;

ALTER TABLE `requisition_line`
  ADD CONSTRAINT `chk_rl_qty_by_type` CHECK (
    (`item_type` = 'consumable')
    OR (`item_type` = 'equipment' AND `quantity` IS NOT NULL AND `quantity` > 0)
  );

-- ---------------------------------------------------------------------------
-- items catalog convenience
-- ---------------------------------------------------------------------------
ALTER TABLE `items`
  ADD COLUMN IF NOT EXISTS `default_item_type` ENUM('equipment','consumable') NOT NULL DEFAULT 'equipment' AFTER `status`;

-- ---------------------------------------------------------------------------
-- requisition_canvass_detail
-- ---------------------------------------------------------------------------
ALTER TABLE `requisition_canvass_detail`
  ADD COLUMN IF NOT EXISTS `quantity` INT NULL DEFAULT NULL AFTER `specification`,
  ADD COLUMN IF NOT EXISTS `per_unit_qty` INT NOT NULL DEFAULT 1 AFTER `quantity`,
  ADD COLUMN IF NOT EXISTS `unit_id` INT NULL DEFAULT NULL AFTER `per_unit_qty`;

ALTER TABLE `requisition_canvass_detail`
  DROP FOREIGN KEY IF EXISTS `rcd_fk_unit`;

ALTER TABLE `requisition_canvass_detail`
  ADD CONSTRAINT `rcd_fk_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Seed common units if table is empty (maps to legacy unit_type values)
INSERT INTO `units` (`unit_id`, `unit_name`, `unit_abbreviation`, `unit_description`)
SELECT * FROM (
  SELECT 1 AS unit_id, 'Piece' AS unit_name, 'pc' AS unit_abbreviation, 'piece' AS unit_description UNION ALL
  SELECT 2, 'Unit', 'unit', 'unit' UNION ALL
  SELECT 3, 'Set', 'set', 'set'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `units` LIMIT 1);

-- Backfill equipment quantities: per_unit_qty = 1, quantity = parent line qty
UPDATE `requisition_canvass_detail` rcd
INNER JOIN `requisition_line` rl
  ON rl.requisition_line_id = rcd.requisition_line_id
 AND rl.request_id = rcd.request_id
SET rcd.per_unit_qty = COALESCE(NULLIF(rcd.per_unit_qty, 0), 1),
    rcd.quantity = COALESCE(NULLIF(rcd.per_unit_qty, 0), 1) * rl.quantity
WHERE COALESCE(rl.item_type, 'equipment') = 'equipment'
  AND rl.quantity IS NOT NULL
  AND rl.quantity > 0
  AND (rcd.quantity IS NULL OR rcd.quantity = 0);

-- Single-line requests: attach orphan canvass rows to the only requisition line
UPDATE `requisition_canvass_detail` rcd
INNER JOIN (
  SELECT rl.request_id, MIN(rl.requisition_line_id) AS only_line_id, COUNT(*) AS line_cnt
  FROM `requisition_line` rl
  GROUP BY rl.request_id
  HAVING line_cnt = 1
) single ON single.request_id = rcd.request_id
SET rcd.requisition_line_id = single.only_line_id
WHERE rcd.requisition_line_id IS NULL;

-- Re-run quantity backfill after line-id repair
UPDATE `requisition_canvass_detail` rcd
INNER JOIN `requisition_line` rl
  ON rl.requisition_line_id = rcd.requisition_line_id
 AND rl.request_id = rcd.request_id
SET rcd.per_unit_qty = COALESCE(NULLIF(rcd.per_unit_qty, 0), 1),
    rcd.quantity = COALESCE(NULLIF(rcd.per_unit_qty, 0), 1) * rl.quantity
WHERE COALESCE(rl.item_type, 'equipment') = 'equipment'
  AND rl.quantity IS NOT NULL
  AND rl.quantity > 0
  AND (rcd.quantity IS NULL OR rcd.quantity = 0);

-- Map unit_id from parent line unit_type where possible
UPDATE `requisition_canvass_detail` rcd
INNER JOIN `requisition_line` rl ON rl.requisition_line_id = rcd.requisition_line_id
LEFT JOIN `units` u ON LOWER(u.unit_description) = LOWER(rl.unit_type)
   OR LOWER(u.unit_abbreviation) = LOWER(rl.unit_type)
SET rcd.unit_id = u.unit_id
WHERE rcd.unit_id IS NULL AND u.unit_id IS NOT NULL;

-- ---------------------------------------------------------------------------
-- requisition_preferred_supplier_item
-- ---------------------------------------------------------------------------
ALTER TABLE `requisition_preferred_supplier_item`
  ADD COLUMN IF NOT EXISTS `canvass_detail_id` INT NULL DEFAULT NULL AFTER `sort_order`;

ALTER TABLE `requisition_preferred_supplier_item`
  DROP FOREIGN KEY IF EXISTS `rpsi_fk_canvass_detail`;

ALTER TABLE `requisition_preferred_supplier_item`
  ADD CONSTRAINT `rpsi_fk_canvass_detail` FOREIGN KEY (`canvass_detail_id`)
    REFERENCES `requisition_canvass_detail` (`canvass_detail_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Backfill canvass_detail_id from positional sort_order (legacy)
UPDATE `requisition_preferred_supplier_item` rpsi
INNER JOIN (
  SELECT canvass_detail_id, request_id, sort_order,
         ROW_NUMBER() OVER (PARTITION BY request_id ORDER BY sort_order ASC, canvass_detail_id ASC) - 1 AS idx
  FROM `requisition_canvass_detail`
) cd ON cd.request_id = rpsi.request_id AND cd.idx = rpsi.sort_order
SET rpsi.canvass_detail_id = cd.canvass_detail_id
WHERE rpsi.canvass_detail_id IS NULL;

-- Fallback: match by sort_order = canvass sort_order directly
UPDATE `requisition_preferred_supplier_item` rpsi
INNER JOIN `requisition_canvass_detail` cd
  ON cd.request_id = rpsi.request_id AND cd.sort_order = rpsi.sort_order
SET rpsi.canvass_detail_id = cd.canvass_detail_id
WHERE rpsi.canvass_detail_id IS NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- Diagnostic: requests where canvass rows still lack line_id or quantity (manual reconcile)
SELECT rcd.request_id,
       COUNT(*) AS orphan_canvass_rows,
       GROUP_CONCAT(rcd.canvass_detail_id ORDER BY rcd.sort_order) AS detail_ids
FROM `requisition_canvass_detail` rcd
WHERE rcd.requisition_line_id IS NULL
   OR rcd.quantity IS NULL
GROUP BY rcd.request_id
ORDER BY rcd.request_id;
