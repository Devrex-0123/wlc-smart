-- Remove unused payment_terms column from purchase_orders

ALTER TABLE purchase_orders DROP COLUMN IF EXISTS payment_terms;
