-- Departments table for Account Management (organizational units).
CREATE TABLE IF NOT EXISTS `departments` (
  `department_id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_name` VARCHAR(150) NOT NULL,
  `department_abbreviation` VARCHAR(20) NOT NULL UNIQUE,
  `department_type` ENUM('Academic', 'Administrative', 'Executive') NOT NULL,
  `department_username` VARCHAR(50) NOT NULL UNIQUE,
  `department_password_hash` VARCHAR(255) NOT NULL,
  `department_photo_url` VARCHAR(255) DEFAULT NULL,
  `department_status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  `department_created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `department_updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
