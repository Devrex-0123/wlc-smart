CREATE TABLE IF NOT EXISTS `purchase_requisition_audit` (
  `purchase_audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `generated_by_user_id` int(11) NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `requester_name` varchar(120) DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `grand_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`purchase_audit_id`),
  KEY `idx_pra_request` (`request_id`),
  KEY `idx_pra_generated_by` (`generated_by_user_id`),
  CONSTRAINT `fk_pra_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pra_generated_by` FOREIGN KEY (`generated_by_user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `purchase_requisition_audit_item` (
  `purchase_audit_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_audit_id` int(11) NOT NULL,
  `line_no` int(11) NOT NULL,
  `description_name` varchar(180) NOT NULL,
  `description_brand` varchar(120) DEFAULT NULL,
  `description_model` varchar(120) DEFAULT NULL,
  `description_specification` varchar(500) DEFAULT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `supplier_name` varchar(180) NOT NULL,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`purchase_audit_item_id`),
  KEY `idx_prai_audit` (`purchase_audit_id`),
  CONSTRAINT `fk_prai_audit` FOREIGN KEY (`purchase_audit_id`) REFERENCES `purchase_requisition_audit` (`purchase_audit_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

