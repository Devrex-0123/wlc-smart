-- Detailed canvass lines: breakdown of each requested item (e.g. "Computer set" → monitor, keyboard) with specs and price.
-- Linked to requisition_item (request_id) and optionally requisition_line; tracks last editor (user_id).

CREATE TABLE IF NOT EXISTS `requisition_canvass_detail` (
  `canvass_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `requisition_line_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `component_label` varchar(150) NOT NULL,
  `specification` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`canvass_detail_id`),
  KEY `idx_rcd_request` (`request_id`),
  KEY `idx_rcd_line` (`requisition_line_id`),
  KEY `idx_rcd_user` (`user_id`),
  CONSTRAINT `rcd_fk_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `rcd_fk_line` FOREIGN KEY (`requisition_line_id`) REFERENCES `requisition_line` (`requisition_line_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `rcd_fk_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
