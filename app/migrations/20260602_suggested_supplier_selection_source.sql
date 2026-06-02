-- Track whether GSD picked a requester-preferred quote vs a canvassed-matrix quote.
USE `cwirms`;

ALTER TABLE `request_approval_suggested_supplier_item`
  ADD COLUMN IF NOT EXISTS `selection_source` ENUM('preferred','canvassed') NULL AFTER `supplier_id`;
