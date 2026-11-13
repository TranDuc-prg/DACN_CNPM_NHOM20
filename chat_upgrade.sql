-- Chạy trong phpMyAdmin sau khi chọn database shop_db.
-- Các bảng mới, không xóa hoặc sửa dữ liệu hiện có.
CREATE TABLE IF NOT EXISTS `chat_conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_token` CHAR(64) NOT NULL,
  `visitor_name` VARCHAR(120) DEFAULT NULL,
  `status` ENUM('bot','human','closed') NOT NULL DEFAULT 'bot',
  `assigned_to` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_message_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_visitor_token` (`visitor_token`),
  KEY `idx_chat_status_last` (`status`,`last_message_at`),
  KEY `idx_chat_assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `sender` ENUM('visitor','ai','staff','system') NOT NULL,
  `staff_id` INT(11) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `read_by_staff` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_conversation` (`conversation_id`,`id`),
  KEY `idx_chat_messages_unread` (`read_by_staff`,`sender`),
  CONSTRAINT `fk_chat_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
