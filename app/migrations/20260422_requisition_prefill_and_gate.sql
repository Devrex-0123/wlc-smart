ALTER TABLE `requisition_item`
    ADD COLUMN `purpose` VARCHAR(255) NULL AFTER `message`,
    ADD COLUMN `urgent_note` VARCHAR(255) NULL AFTER `purpose`;

ALTER TABLE `requisition_line`
    ADD COLUMN `unit_type` ENUM('set','unit','piece') NOT NULL DEFAULT 'unit' AFTER `quantity`;

ALTER TABLE `request_approval`
    ADD COLUMN `requisition_status` ENUM('accept','reject','pending','') DEFAULT 'pending' AFTER `request_id`,
    ADD COLUMN `requisition_note` VARCHAR(255) NULL AFTER `requisition_status`,
    ADD COLUMN `requisition_reviewed_by` VARCHAR(100) NULL AFTER `requisition_note`,
    ADD COLUMN `requisition_reviewed_at` DATETIME NULL AFTER `requisition_reviewed_by`;
