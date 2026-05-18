-- Per-item suggested supplier selections by GSD.
-- One selected supplier per canvass detail row (item line) of a request.

CREATE TABLE IF NOT EXISTS `request_approval_suggested_supplier_item` (
  `suggested_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `canvass_detail_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `selected_by_user_id` int(11) NOT NULL,
  `selected_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`suggested_item_id`),
  UNIQUE KEY `uq_req_canvass_detail` (`request_id`, `canvass_detail_id`),
  KEY `idx_rassi_request` (`request_id`),
  KEY `idx_rassi_supplier` (`supplier_id`),
  CONSTRAINT `fk_rassi_request` FOREIGN KEY (`request_id`) REFERENCES `requisition_item` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rassi_canvass_detail` FOREIGN KEY (`canvass_detail_id`) REFERENCES `requisition_canvass_detail` (`canvass_detail_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rassi_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_rassi_user` FOREIGN KEY (`selected_by_user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
