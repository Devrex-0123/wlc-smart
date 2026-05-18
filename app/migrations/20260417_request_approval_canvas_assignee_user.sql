-- Persist GSD-designated canvassing assignee (user_id) alongside canvassed_by label.
-- Run once on existing databases.

ALTER TABLE `request_approval`
  ADD COLUMN `canvas_assignee_user_id` int(11) DEFAULT NULL AFTER `canvassed_at`,
  ADD KEY `idx_ra_canvas_assignee_user` (`canvas_assignee_user_id`);
