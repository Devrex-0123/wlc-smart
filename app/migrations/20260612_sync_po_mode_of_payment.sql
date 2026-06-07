-- Recompute mode_of_payment from total_amount (≤1500 = cash, >1500 = cheque)

UPDATE purchase_orders
SET mode_of_payment = CASE
    WHEN total_amount <= 1500 THEN 'cash'
    ELSE 'cheque'
END
WHERE deleted_at IS NULL;
