-- Patch for databases imported from cwirms (2).sql before canvas_submission_status was added.
-- Safe to run once; skip if column already exists (MySQL will error — check DESCRIBE first).

ALTER TABLE `canvass_verification_approval`
ADD COLUMN `canvas_submission_status` ENUM('draft', 'submitted') DEFAULT 'draft' AFTER `pres_status`;

UPDATE `canvass_verification_approval`
SET `canvas_submission_status` = 'submitted'
WHERE `canvas_status` = 'accept'
   OR `gsd_status` IS NOT NULL
   OR `comp_status` IS NOT NULL
   OR `pres_status` IS NOT NULL;

-- Rows with no workflow activity stay draft (e.g. request 9)
UPDATE `canvass_verification_approval`
SET `canvas_submission_status` = 'draft'
WHERE `canvas_status` NOT IN ('accept', 'reject')
  AND `comp_status` IS NULL
  AND `gsd_status` IS NULL
  AND `pres_status` IS NULL
  AND `checked_by` IS NULL;

CREATE INDEX `idx_canvass_submission_status` ON `canvass_verification_approval` (`canvas_submission_status`);
