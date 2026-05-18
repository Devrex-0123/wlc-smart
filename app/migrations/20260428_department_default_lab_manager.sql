-- Default lab manager per office (set by dean). New inventory rows in that dept
-- get this assignee when the inventory manager leaves personnel unset.
ALTER TABLE `office`
  ADD COLUMN `default_lab_manager_user_id` int(11) DEFAULT NULL AFTER `photo_url`,
  ADD KEY `idx_dept_default_lab_manager` (`default_lab_manager_user_id`),
  ADD CONSTRAINT `office_ibfk_default_lab_manager`
    FOREIGN KEY (`default_lab_manager_user_id`) REFERENCES `user` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE;
