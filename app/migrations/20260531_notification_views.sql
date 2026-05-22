-- Create notification tracking records to support read/unread badge counts.
CREATE TABLE IF NOT EXISTS `notification_views` (
  `notification_view_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `notification_key` varchar(64) NOT NULL,
  `viewed_at` datetime NOT NULL,
  PRIMARY KEY (`notification_view_id`),
  UNIQUE KEY `idx_user_request_key` (`user_id`,`request_id`,`notification_key`),
  KEY `idx_user_key` (`user_id`,`notification_key`),
  KEY `idx_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
