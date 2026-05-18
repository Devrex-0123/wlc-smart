-- item_components: every catalog part for an inventory is stored here (including the first).
-- inventory.item_id is kept in sync with the first part for list/detail header joins.
-- The application rejects duplicate (parent_item_id, component_item_id) rows.
--
-- If you previously ran an older version of this file that DELETEs mirrored rows, re-add any
-- missing first-part rows from backups or by editing the inventory in the app.

ALTER TABLE item_components
    COMMENT = 'Catalog parts per inventory. No duplicate catalog item per parent (app-enforced). inventory.item_id matches the first part.';
