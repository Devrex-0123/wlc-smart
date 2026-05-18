-- Audit log of president verifier decisions per requisition line.
CREATE TABLE IF NOT EXISTS `president_action_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('accept','reject','pending') NOT NULL,
  `acted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pah_request` (`request_id`),
  KEY `idx_pah_user` (`user_id`),
  CONSTRAINT `president_action_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON UPDATE CASCADE,
  CONSTRAINT `president_action_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
