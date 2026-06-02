-- Comptroller partial quantity approval per GSD suggested supplier line.
-- Data lives on request_approval_suggested_supplier_item (one row per canvass line).

USE cwirms;

ALTER TABLE `request_approval_suggested_supplier_item`
  ADD COLUMN IF NOT EXISTS `accepted_qty` INT UNSIGNED NULL AFTER `selection_source`,
  ADD COLUMN IF NOT EXISTS `deferred_qty` INT UNSIGNED NULL DEFAULT 0 AFTER `accepted_qty`,
  ADD COLUMN IF NOT EXISTS `deferred_message` TEXT NULL AFTER `deferred_qty`,
  ADD COLUMN IF NOT EXISTS `comptroller_qty_status` ENUM('fully_approved','deferred') NULL AFTER `deferred_message`,
  ADD COLUMN IF NOT EXISTS `comptroller_approved_by_user_id` INT NULL AFTER `comptroller_qty_status`,
  ADD COLUMN IF NOT EXISTS `comptroller_approved_at` DATETIME NULL AFTER `comptroller_approved_by_user_id`;
