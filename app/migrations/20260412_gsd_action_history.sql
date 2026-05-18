-- Audit log of GSD verifier decisions (verify / reject / undo) per requisition line.
CREATE TABLE IF NOT EXISTS `gsd_action_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('accept','reject','pending') NOT NULL,
  `acted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gah_request` (`request_id`),
  KEY `idx_gah_user` (`user_id`),
  CONSTRAINT `gsd_action_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON UPDATE CASCADE,
  CONSTRAINT `gsd_action_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
