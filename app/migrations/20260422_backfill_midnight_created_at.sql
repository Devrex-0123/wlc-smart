/*
Backfill helper for legacy requisition rows where created_at time is 00:00:00.
This is intentionally approximate so timestamps become visibly non-midnight in UI.
*/

UPDATE `requisition_item`
SET `created_at` = DATE_ADD(
    DATE(`created_at`),
    INTERVAL (480 + ((`request_id` * 17) % 540)) MINUTE
)
WHERE TIME(`created_at`) = '00:00:00';

