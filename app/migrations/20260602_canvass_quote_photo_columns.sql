-- Preferred-supplier quote photos and prices live on requisition_preferred_suppliers.
-- Remove misplaced photo columns from requisition_canvass_detail / detail_supplier.
--
-- phpMyAdmin / XAMPP (MariaDB 10.4+):
--   1. Click database "cwirms" in the left sidebar (required).
--   2. Open SQL tab, paste this whole file, click Go.
--   Safe to re-run (IF NOT EXISTS / IF EXISTS).

USE `cwirms`;

ALTER TABLE `requisition_preferred_suppliers`
  ADD COLUMN IF NOT EXISTS `quoted_prices` TEXT NULL AFTER `supplier_id`;

ALTER TABLE `requisition_preferred_suppliers`
  ADD COLUMN IF NOT EXISTS `quote_photos` TEXT NULL AFTER `quoted_prices`;

ALTER TABLE `requisition_canvass_detail`
  DROP COLUMN IF EXISTS `photo_uploaded_at`;

ALTER TABLE `requisition_canvass_detail`
  DROP COLUMN IF EXISTS `photo_url`;

ALTER TABLE `requisition_canvass_detail_supplier`
  DROP COLUMN IF EXISTS `photo_url`;

ALTER TABLE `requisition_canvass_detail_supplier`
  DROP COLUMN IF EXISTS `photo_uploaded_at`;
