-- Optional benefits note and percentage discount per canvassed supplier (stored on matrix price rows).

ALTER TABLE `requisition_canvass_detail_supplier`
  ADD COLUMN `benefits` TEXT NULL DEFAULT NULL,
  ADD COLUMN `discount_percent` DECIMAL(5,2) NULL DEFAULT NULL;
