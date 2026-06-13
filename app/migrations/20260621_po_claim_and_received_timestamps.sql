-- Requester self-reported claim/release and items-received milestones on purchase orders.

START TRANSACTION;

ALTER TABLE `purchase_orders`
  MODIFY COLUMN `status` ENUM(
    'pending',
    'approved',
    'rejected',
    'ready_for_release',
    'completed'
  ) NOT NULL DEFAULT 'pending';

ALTER TABLE `purchase_orders`
  ADD COLUMN `payment_released_at` DATETIME NULL DEFAULT NULL AFTER `tax_finalized_at`;

ALTER TABLE `purchase_orders`
  ADD COLUMN `items_received_at` DATETIME NULL DEFAULT NULL AFTER `payment_released_at`;

COMMIT;
