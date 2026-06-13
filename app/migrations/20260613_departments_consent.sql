-- Department consent tracking (Privacy Policy / Terms acceptance).
ALTER TABLE `departments`
ADD COLUMN `had_consented` TINYINT(1) NOT NULL DEFAULT 0 AFTER `department_status`,
ADD COLUMN `consented_at` TIMESTAMP NULL DEFAULT NULL AFTER `had_consented`,
ADD COLUMN `consent_version` VARCHAR(20) DEFAULT 'v1.0' AFTER `consented_at`;
