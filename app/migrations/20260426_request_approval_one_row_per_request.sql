-- Enforce one request_approval row per requisition (prevents ambiguous LIMIT 1 / duplicate list rows).
-- Safe to run more than once (skips ADD UNIQUE if the index already exists).
--
-- Semantics:
--   requisition_* columns = inventory manager validation of the initial requisition only.
--   Other columns (canvas_*, gsd_status, comp_status, pres_status, etc.) = later workflow;
--   they must not be used to decide inventory approval — use requisition_status only.

-- Drop duplicates, keeping the oldest row per request_id.
DELETE ra_dup FROM request_approval ra_dup
INNER JOIN request_approval ra_keep
  ON ra_dup.request_id = ra_keep.request_id
  AND ra_dup.request_approval_id > ra_keep.request_approval_id;

SET @idx_exists := (
  SELECT COUNT(1) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'request_approval'
    AND index_name = 'uq_request_approval_request_id'
);

SET @sql := IF(
  @idx_exists > 0,
  'SELECT ''uq_request_approval_request_id already exists; skipping.'' AS migration_note',
  'ALTER TABLE request_approval ADD UNIQUE KEY uq_request_approval_request_id (request_id)'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
