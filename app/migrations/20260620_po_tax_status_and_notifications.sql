-- Part 1: draft vs finalized tax workflow on purchase_orders header.
-- Part 2: requester notification inbox (separate from sidebar badge counts).

START TRANSACTION;

ALTER TABLE `purchase_orders`
  MODIFY COLUMN `status` ENUM(
    'pending',
    'approved',
    'rejected',
    'ready_for_release'
  ) NOT NULL DEFAULT 'pending';

ALTER TABLE `purchase_orders`
  ADD COLUMN `tax_status` ENUM('draft', 'finalized') NOT NULL DEFAULT 'draft' AFTER `tax_computed`;

ALTER TABLE `purchase_orders`
  ADD COLUMN `tax_finalized_at` DATETIME NULL DEFAULT NULL AFTER `tax_status`;

UPDATE `purchase_orders`
SET `tax_status` = 'draft'
WHERE `tax_computed` = 1
  AND (`tax_status` IS NULL OR `tax_status` = '');

CREATE TABLE IF NOT EXISTS `user_notifications` (
  `notification_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `purchase_order_id` INT UNSIGNED NULL DEFAULT NULL,
  `requisition_id` INT NULL DEFAULT NULL,
  `type` VARCHAR(50) NOT NULL,
  `meta_json` JSON NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_user_notifications_user_read` (`user_id`, `is_read`, `created_at`),
  KEY `idx_user_notifications_po` (`purchase_order_id`),
  CONSTRAINT `fk_user_notifications_user`
    FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_notifications_po`
    FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
