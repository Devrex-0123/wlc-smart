-- Migrate department login accounts into user table (Phase A).
-- Pre-flight: no Email/username collisions; all abbreviations map to offices.

START TRANSACTION;

ALTER TABLE `user`
  MODIFY COLUMN `role` ENUM(
    'Inventory Manager',
    'Dean',
    'Laboratory Manager',
    'Comptroller',
    'President',
    'Employee',
    'User',
    'GSD officer',
    'Department'
  ) NOT NULL DEFAULT 'User';

ALTER TABLE `user`
  ADD COLUMN `department_id` INT NULL DEFAULT NULL AFTER `office_id`;

ALTER TABLE `user`
  ADD UNIQUE KEY `ux_user_department_id` (`department_id`);

ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_department`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

INSERT INTO `user` (
    Email,
    password,
    role,
    office_id,
    department_id,
    photo_url,
    full_name,
    has_consented,
    consent_version,
    consent_date,
    account_status,
    created_at,
    updated_at
)
SELECT
    d.department_username,
    d.department_password_hash,
    'Department',
    o.office_id,
    d.department_id,
    d.department_photo_url,
    d.department_name,
    COALESCE(d.had_consented, 0),
    d.consent_version,
    d.consented_at,
    CASE
        WHEN LOWER(TRIM(d.department_status)) = 'active' THEN 'active'
        ELSE 'disabled'
    END,
    COALESCE(d.department_created_at, NOW()),
    COALESCE(d.department_updated_at, NOW())
FROM departments d
LEFT JOIN offices o
  ON LOWER(TRIM(o.office_name)) = LOWER(TRIM(d.department_abbreviation))
WHERE TRIM(COALESCE(d.department_username, '')) <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM user u
      WHERE u.department_id = d.department_id
         OR LOWER(TRIM(u.Email)) = LOWER(TRIM(d.department_username))
  );

COMMIT;
