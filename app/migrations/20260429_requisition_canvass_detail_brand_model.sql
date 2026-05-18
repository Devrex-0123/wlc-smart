-- Optional brand/model on each canvass line (before free-text specification).
ALTER TABLE `requisition_canvass_detail`
    ADD COLUMN `brand` VARCHAR(100) NULL DEFAULT NULL AFTER `component_label`,
    ADD COLUMN `model` VARCHAR(100) NULL DEFAULT NULL AFTER `brand`;
