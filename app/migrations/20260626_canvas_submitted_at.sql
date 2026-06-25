-- Add canvas_submitted_at timestamp to canvass_verification_approval.
-- Tracks the exact moment the requester first submitted the canvass form,
-- used to enforce a 1-hour edit window before the form becomes read-only.

ALTER TABLE `canvass_verification_approval`
ADD COLUMN `canvas_submitted_at` DATETIME DEFAULT NULL AFTER `canvas_submission_status`;

-- Backfill approximate submission time for existing submitted rows from known timestamps.
UPDATE `canvass_verification_approval`
SET `canvas_submitted_at` = COALESCE(`canvassed_at`, `verified_at`, `approved_at`)
WHERE `canvas_submission_status` = 'submitted'
  AND `canvas_submitted_at` IS NULL;
