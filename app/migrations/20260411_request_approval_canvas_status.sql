-- CANVASSED BY step: drive UI from request_approval.canvas_status (accept = green).
-- Run once on existing databases.

ALTER TABLE `request_approval`
  ADD COLUMN `canvas_status` ENUM('accept','reject','pending','') DEFAULT 'pending' AFTER `request_id`,
  ADD COLUMN `canvassed_by` VARCHAR(100) DEFAULT NULL AFTER `canvas_status`,
  ADD COLUMN `canvassed_at` DATETIME DEFAULT NULL AFTER `canvassed_by`;

CREATE TABLE IF NOT EXISTS `canvasser_action_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('accept','reject','pending') NOT NULL,
  `acted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cavh_request` (`request_id`),
  KEY `idx_cavh_user` (`user_id`),
  CONSTRAINT `canvasser_action_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON UPDATE CASCADE,
  CONSTRAINT `canvasser_action_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
