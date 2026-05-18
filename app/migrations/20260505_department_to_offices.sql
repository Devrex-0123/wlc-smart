-- Rename department domain to office domain.
-- Run during maintenance window.

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- Drop foreign keys that still point to `department`.
ALTER TABLE `facilities` DROP FOREIGN KEY `facilities_ibfk_1`;
ALTER TABLE `requisition_item` DROP FOREIGN KEY `requisition_item_ibfk_1`;

-- Rename FK columns in child tables.
ALTER TABLE `facilities`
  CHANGE COLUMN `department_id` `office_id` INT(11) NOT NULL;

ALTER TABLE `requisition_item`
  CHANGE COLUMN `department_id` `office_id` INT(11) NOT NULL;

ALTER TABLE `user`
  CHANGE COLUMN `department_id` `office_id` INT(11) DEFAULT NULL;

-- Rename parent table and columns.
ALTER TABLE `department` RENAME TO `offices`;

ALTER TABLE `offices`
  CHANGE COLUMN `department_id` `office_id` INT(11) NOT NULL AUTO_INCREMENT,
  CHANGE COLUMN `department name` `office_name` VARCHAR(100) NOT NULL;

-- Add office type classification.
ALTER TABLE `offices`
  ADD COLUMN `type` ENUM('academic', 'administrative', 'executive')
  NOT NULL DEFAULT 'administrative'
  AFTER `office_name`;

-- Seed office type values from current names.
UPDATE `offices`
SET `type` = CASE
  WHEN UPPER(TRIM(`office_name`)) IN ('CICTE', 'CONAHS') THEN 'academic'
  WHEN UPPER(TRIM(`office_name`)) = 'INVENTORY OFFICE' THEN 'administrative'
  WHEN UPPER(TRIM(`office_name`)) = 'PRESIDENT''S OFFICE' THEN 'executive'
  WHEN UPPER(`office_name`) LIKE '%PRESIDENT%' THEN 'executive'
  WHEN UPPER(`office_name`) LIKE '%OFFICE%' THEN 'administrative'
  ELSE 'academic'
END;

-- Re-create foreign keys against offices.
ALTER TABLE `facilities`
  ADD CONSTRAINT `facilities_ibfk_office`
  FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`);

ALTER TABLE `requisition_item`
  ADD CONSTRAINT `requisition_item_ibfk_office`
  FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`);

-- Optional but recommended: enforce office FK in user table too.
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_office`
  FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
