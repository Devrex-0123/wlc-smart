-- Purchase order: shipping fees, additional charges, discounts, payment terms.
-- taxable_amount stores (gross_total - discount) and is the base for tax computation.
-- Run once. MariaDB 10.4+ IF NOT EXISTS on ADD COLUMN is supported.

USE `cwirms`;

ALTER TABLE `purchase_orders`
  ADD COLUMN IF NOT EXISTS `shipping_fee`              DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER `total_amount`,
  ADD COLUMN IF NOT EXISTS `shipping_method`           VARCHAR(100)  NULL DEFAULT NULL       AFTER `shipping_fee`,
  ADD COLUMN IF NOT EXISTS `shipping_address`          TEXT          NULL DEFAULT NULL       AFTER `shipping_method`,
  ADD COLUMN IF NOT EXISTS `handling_fee`              DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER `shipping_address`,
  ADD COLUMN IF NOT EXISTS `insurance_fee`             DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER `handling_fee`,
  ADD COLUMN IF NOT EXISTS `installation_fee`          DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER `insurance_fee`,
  ADD COLUMN IF NOT EXISTS `other_charges`             DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER `installation_fee`,
  ADD COLUMN IF NOT EXISTS `other_charges_description` VARCHAR(255)  NULL DEFAULT NULL       AFTER `other_charges`,
  ADD COLUMN IF NOT EXISTS `discount_amount`           DECIMAL(12,2) NOT NULL DEFAULT 0.00  AFTER `other_charges_description`,
  ADD COLUMN IF NOT EXISTS `discount_percentage`       DECIMAL(5,2)  NOT NULL DEFAULT 0.00  AFTER `discount_amount`,
  ADD COLUMN IF NOT EXISTS `discount_reason`           VARCHAR(255)  NULL DEFAULT NULL       AFTER `discount_percentage`,
  ADD COLUMN IF NOT EXISTS `taxable_amount`            DECIMAL(12,2) NULL DEFAULT NULL       AFTER `discount_reason`,
  ADD COLUMN IF NOT EXISTS `payment_terms`             VARCHAR(100)  NULL DEFAULT NULL       AFTER `taxable_amount`,
  ADD COLUMN IF NOT EXISTS `payment_due_date`          DATE          NULL DEFAULT NULL       AFTER `payment_terms`;
