-- Slim `inventory` to set-level fields only; per-part qty/condition/status live on `item_components`.
-- Run once on your database (phpMyAdmin / mysql client).

-- 1) Extend item_components
ALTER TABLE item_components
    ADD COLUMN condition_status VARCHAR(50) NULL AFTER quantity,
    ADD COLUMN status VARCHAR(50) NULL DEFAULT 'Available' AFTER condition_status;

-- 2) Backfill from inventory row onto every component line (then drop inventory columns)
UPDATE item_components ic
INNER JOIN inventory inv ON inv.inventory_id = ic.parent_item_id
SET ic.condition_status = inv.condition_status,
    ic.status = IFNULL(NULLIF(TRIM(inv.status), ''), 'Available');

-- 3) Move set-level photo onto first component row if that row has no photo
UPDATE item_components ic
INNER JOIN inventory inv ON inv.inventory_id = ic.parent_item_id
INNER JOIN (
    SELECT parent_item_id, MIN(component_id) AS cid
    FROM item_components
    GROUP BY parent_item_id
) z ON z.parent_item_id = ic.parent_item_id AND z.cid = ic.component_id
SET ic.photo_url = CASE
    WHEN (ic.photo_url IS NULL OR ic.photo_url = '') AND inv.photo_url IS NOT NULL AND inv.photo_url != ''
    THEN inv.photo_url
    ELSE ic.photo_url
END;

-- 4) Drop redundant inventory columns (run separately if one fails; drop FK on item_id first if present)
ALTER TABLE inventory DROP COLUMN item_id;
ALTER TABLE inventory DROP COLUMN quantity;
ALTER TABLE inventory DROP COLUMN condition_status;
ALTER TABLE inventory DROP COLUMN status;
ALTER TABLE inventory DROP COLUMN photo_url;
