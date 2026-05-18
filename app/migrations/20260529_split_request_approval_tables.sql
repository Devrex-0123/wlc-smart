-- Split monolithic request_approval into three tables by workflow.
-- Run once on databases that still have request_approval.
-- Fresh installs: use cwirms.sql (already split).

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Create new tables (skip if already applied)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `requisition_form_approval` (
  `request_id` int(11) NOT NULL,
  `requisition_status` enum('accept','reject','pending','') DEFAULT 'pending',
  `requisition_note` varchar(255) DEFAULT NULL,
  `requisition_reviewed_by` varchar(100) DEFAULT NULL,
  `requisition_reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  CONSTRAINT `fk_rfa_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `canvass_verification_approval` (
  `request_id` int(11) NOT NULL,
  `canvas_status` enum('accept','reject','pending','') DEFAULT 'pending',
  `canvassed_by` varchar(100) DEFAULT NULL,
  `canvassed_at` datetime DEFAULT NULL,
  `canvas_assignee_user_id` int(11) DEFAULT NULL,
  `suggested_supplier_id` int(11) DEFAULT NULL,
  `suggested_supplier_name` varchar(120) DEFAULT NULL,
  `checked_by` varchar(100) DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `comp_status` enum('accept','reject','pending','') DEFAULT 'pending',
  `verified_by` varchar(100) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `gsd_status` enum('accept','reject','pending','') DEFAULT 'pending',
  `approved_by` varchar(100) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `pres_status` enum('accept','reject','pending','') DEFAULT 'pending',
  PRIMARY KEY (`request_id`),
  KEY `idx_cva_canvas_assignee` (`canvas_assignee_user_id`),
  KEY `idx_cva_suggested_supplier` (`suggested_supplier_id`),
  CONSTRAINT `fk_cva_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cva_suggested_supplier` FOREIGN KEY (`suggested_supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- FK name must not be `fk_pra_request` (already used by `purchase_requisition_audit`); duplicate names → errno 121.
CREATE TABLE IF NOT EXISTS `purchase_requisition_approval` (
  `request_id` int(11) NOT NULL,
  `pr_inv_status` enum('accept','reject','pending') NOT NULL DEFAULT 'pending',
  `pr_inv_note` varchar(500) DEFAULT NULL,
  `pr_inv_at` datetime DEFAULT NULL,
  `pr_pres_status` enum('accept','reject','pending') NOT NULL DEFAULT 'pending',
  `pr_pres_note` varchar(500) DEFAULT NULL,
  `pr_pres_at` datetime DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  CONSTRAINT `fk_pra_pr_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- Migrate data from legacy request_approval (one canonical row per request_id)
-- ---------------------------------------------------------------------------

INSERT INTO `requisition_form_approval` (
  `request_id`, `requisition_status`, `requisition_note`, `requisition_reviewed_by`, `requisition_reviewed_at`
)
SELECT ra.`request_id`, ra.`requisition_status`, ra.`requisition_note`, ra.`requisition_reviewed_by`, ra.`requisition_reviewed_at`
FROM `request_approval` ra
INNER JOIN (
  SELECT `request_id`, MAX(`request_approval_id`) AS `pick_id` FROM `request_approval` GROUP BY `request_id`
) t ON ra.`request_approval_id` = t.`pick_id`
ON DUPLICATE KEY UPDATE
  `requisition_status` = VALUES(`requisition_status`),
  `requisition_note` = VALUES(`requisition_note`),
  `requisition_reviewed_by` = VALUES(`requisition_reviewed_by`),
  `requisition_reviewed_at` = VALUES(`requisition_reviewed_at`);

INSERT INTO `canvass_verification_approval` (
  `request_id`, `canvas_status`, `canvassed_by`, `canvassed_at`, `canvas_assignee_user_id`,
  `suggested_supplier_id`, `suggested_supplier_name`, `checked_by`, `checked_at`, `comp_status`,
  `verified_by`, `verified_at`, `gsd_status`, `approved_by`, `approved_at`, `pres_status`
)
SELECT ra.`request_id`, ra.`canvas_status`, ra.`canvassed_by`, ra.`canvassed_at`, ra.`canvas_assignee_user_id`,
  ra.`suggested_supplier_id`, ra.`suggested_supplier_name`, ra.`checked_by`, ra.`checked_at`, ra.`comp_status`,
  ra.`verified_by`, ra.`verified_at`, ra.`gsd_status`, ra.`approved_by`, ra.`approved_at`, ra.`pres_status`
FROM `request_approval` ra
INNER JOIN (
  SELECT `request_id`, MAX(`request_approval_id`) AS `pick_id` FROM `request_approval` GROUP BY `request_id`
) t ON ra.`request_approval_id` = t.`pick_id`
ON DUPLICATE KEY UPDATE
  `canvas_status` = VALUES(`canvas_status`),
  `canvassed_by` = VALUES(`canvassed_by`),
  `canvassed_at` = VALUES(`canvassed_at`),
  `canvas_assignee_user_id` = VALUES(`canvas_assignee_user_id`),
  `suggested_supplier_id` = VALUES(`suggested_supplier_id`),
  `suggested_supplier_name` = VALUES(`suggested_supplier_name`),
  `checked_by` = VALUES(`checked_by`),
  `checked_at` = VALUES(`checked_at`),
  `comp_status` = VALUES(`comp_status`),
  `verified_by` = VALUES(`verified_by`),
  `verified_at` = VALUES(`verified_at`),
  `gsd_status` = VALUES(`gsd_status`),
  `approved_by` = VALUES(`approved_by`),
  `approved_at` = VALUES(`approved_at`),
  `pres_status` = VALUES(`pres_status`);

INSERT INTO `purchase_requisition_approval` (
  `request_id`, `pr_inv_status`, `pr_inv_note`, `pr_inv_at`, `pr_pres_status`, `pr_pres_note`, `pr_pres_at`
)
SELECT ra.`request_id`,
  COALESCE(NULLIF(TRIM(ra.`pr_inv_status`), ''), 'pending'),
  ra.`pr_inv_note`, ra.`pr_inv_at`,
  COALESCE(NULLIF(TRIM(ra.`pr_pres_status`), ''), 'pending'),
  ra.`pr_pres_note`, ra.`pr_pres_at`
FROM `request_approval` ra
INNER JOIN (
  SELECT `request_id`, MAX(`request_approval_id`) AS `pick_id` FROM `request_approval` GROUP BY `request_id`
) t ON ra.`request_approval_id` = t.`pick_id`
ON DUPLICATE KEY UPDATE
  `pr_inv_status` = VALUES(`pr_inv_status`),
  `pr_inv_note` = VALUES(`pr_inv_note`),
  `pr_inv_at` = VALUES(`pr_inv_at`),
  `pr_pres_status` = VALUES(`pr_pres_status`),
  `pr_pres_note` = VALUES(`pr_pres_note`),
  `pr_pres_at` = VALUES(`pr_pres_at`);

-- Ensure every request with a canvass chain row has a purchase requisition approval row
INSERT IGNORE INTO `purchase_requisition_approval` (`request_id`)
SELECT `request_id` FROM `canvass_verification_approval`;

-- ---------------------------------------------------------------------------
-- Deduplicate purchase requisition audit snapshots (keep latest per request)
-- ---------------------------------------------------------------------------

DELETE pai FROM `purchase_requisition_audit_item` pai
WHERE pai.`purchase_audit_id` NOT IN (
  SELECT `id` FROM (
    SELECT MAX(`purchase_audit_id`) AS `id` FROM `purchase_requisition_audit` GROUP BY `request_id`
  ) x
);

DELETE pa FROM `purchase_requisition_audit` pa
WHERE pa.`purchase_audit_id` NOT IN (
  SELECT `id` FROM (
    SELECT MAX(`purchase_audit_id`) AS `id` FROM `purchase_requisition_audit` GROUP BY `request_id`
  ) x
);

-- ---------------------------------------------------------------------------
-- Drop legacy table
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS `request_approval`;

SET FOREIGN_KEY_CHECKS = 1;
