ALTER TABLE `departments`
ADD COLUMN `department_photo_url` VARCHAR(255) DEFAULT NULL AFTER `department_password_hash`;
