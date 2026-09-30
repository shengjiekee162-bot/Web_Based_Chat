-- WebConnect Database Schema
-- Charset: utf8mb4 / utf8mb4_unicode_ci
-- Import into a fresh MySQL/MariaDB database

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `webconnect`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `webconnect`;

-- --------------------------------------------------------
-- Admins (separate from users)
-- --------------------------------------------------------
CREATE TABLE `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL DEFAULT '',
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Users
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) NULL DEFAULT NULL,
  `status_message` VARCHAR(255) NOT NULL DEFAULT '',
  `presence` ENUM('online','away','offline','busy') NOT NULL DEFAULT 'offline',
  `last_seen_at` DATETIME NULL DEFAULT NULL,
  `last_activity_at` DATETIME NULL DEFAULT NULL,
  `is_disabled` TINYINT(1) NOT NULL DEFAULT 0,
  `notify_desktop` TINYINT(1) NOT NULL DEFAULT 1,
  `prefer_call_type` ENUM('voice','video') NOT NULL DEFAULT 'voice',
  `show_last_seen` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_presence` (`presence`),
  KEY `idx_users_last_activity` (`last_activity_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Friend requests
-- --------------------------------------------------------
CREATE TABLE `friend_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_friend_req_pair` (`sender_id`,`receiver_id`),
  KEY `idx_friend_req_receiver_status` (`receiver_id`,`status`),
  CONSTRAINT `fk_fr_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fr_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Friendships (normalized pair: user_low_id < user_high_id)
-- --------------------------------------------------------
CREATE TABLE `friendships` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_low_id` INT UNSIGNED NOT NULL,
  `user_high_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_friendship_pair` (`user_low_id`,`user_high_id`),
  KEY `idx_friendships_high` (`user_high_id`),
  CONSTRAINT `fk_fs_low` FOREIGN KEY (`user_low_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fs_high` FOREIGN KEY (`user_high_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Blocks
-- --------------------------------------------------------
CREATE TABLE `user_blocks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blocker_id` INT UNSIGNED NOT NULL,
  `blocked_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_block_pair` (`blocker_id`,`blocked_id`),
  KEY `idx_blocks_blocked` (`blocked_id`),
  CONSTRAINT `fk_block_blocker` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_block_blocked` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Private messages
-- --------------------------------------------------------
CREATE TABLE `messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `message_type` ENUM('text','image','file','voice','call') NOT NULL DEFAULT 'text',
  `body` TEXT NULL,
  `file_path` VARCHAR(255) NULL DEFAULT NULL,
  `file_name` VARCHAR(255) NULL DEFAULT NULL,
  `mime_type` VARCHAR(120) NULL DEFAULT NULL,
  `file_size` INT UNSIGNED NULL DEFAULT NULL,
  `voice_duration` DECIMAL(8,2) NULL DEFAULT NULL,
  `reply_to_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `delivery_status` ENUM('sent','delivered','read') NOT NULL DEFAULT 'sent',
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_by_sender` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_by_receiver` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `edited_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_msg_pair_created` (`sender_id`,`receiver_id`,`created_at`),
  KEY `idx_msg_receiver_created` (`receiver_id`,`created_at`),
  KEY `idx_msg_reply` (`reply_to_id`),
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_reply` FOREIGN KEY (`reply_to_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_reactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reaction` VARCHAR(16) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_msg_reaction` (`message_id`,`user_id`,`reaction`),
  KEY `idx_msg_react_user` (`user_id`),
  CONSTRAINT `fk_mr_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_read_receipts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_msg_read` (`message_id`,`user_id`),
  KEY `idx_msg_read_user` (`user_id`),
  CONSTRAINT `fk_mrr_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mrr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `typing_indicators` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `conversation_type` ENUM('private','group') NOT NULL,
  `target_id` INT UNSIGNED NOT NULL,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_typing` (`user_id`,`conversation_type`,`target_id`),
  KEY `idx_typing_target_exp` (`conversation_type`,`target_id`,`expires_at`),
  CONSTRAINT `fk_typing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Groups
-- --------------------------------------------------------
CREATE TABLE `group_chats` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `avatar` VARCHAR(255) NULL DEFAULT NULL,
  `owner_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_groups_owner` (`owner_id`),
  CONSTRAINT `fk_groups_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('owner','admin','member') NOT NULL DEFAULT 'member',
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_group_member` (`group_id`,`user_id`),
  KEY `idx_gm_user` (`user_id`),
  CONSTRAINT `fk_gm_group` FOREIGN KEY (`group_id`) REFERENCES `group_chats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `message_type` ENUM('text','image','file','voice') NOT NULL DEFAULT 'text',
  `body` TEXT NULL,
  `file_path` VARCHAR(255) NULL DEFAULT NULL,
  `file_name` VARCHAR(255) NULL DEFAULT NULL,
  `mime_type` VARCHAR(120) NULL DEFAULT NULL,
  `file_size` INT UNSIGNED NULL DEFAULT NULL,
  `voice_duration` DECIMAL(8,2) NULL DEFAULT NULL,
  `reply_to_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `edited_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gmsg_group_created` (`group_id`,`created_at`),
  KEY `idx_gmsg_sender` (`sender_id`),
  KEY `idx_gmsg_reply` (`reply_to_id`),
  CONSTRAINT `fk_gmsg_group` FOREIGN KEY (`group_id`) REFERENCES `group_chats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gmsg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gmsg_reply` FOREIGN KEY (`reply_to_id`) REFERENCES `group_messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_message_reactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reaction` VARCHAR(16) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gmsg_reaction` (`message_id`,`user_id`,`reaction`),
  KEY `idx_gmr_user` (`user_id`),
  CONSTRAINT `fk_gmr_message` FOREIGN KEY (`message_id`) REFERENCES `group_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gmr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_message_reads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gmsg_read` (`message_id`,`user_id`),
  KEY `idx_gmr_read_user` (`user_id`),
  CONSTRAINT `fk_gmread_message` FOREIGN KEY (`message_id`) REFERENCES `group_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gmread_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Voice / Video call sessions & signalling
-- --------------------------------------------------------
CREATE TABLE `voice_call_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `caller_id` INT UNSIGNED NOT NULL,
  `callee_id` INT UNSIGNED NOT NULL,
  `call_type` ENUM('voice','video') NOT NULL DEFAULT 'voice',
  `status` ENUM('ringing','accepted','rejected','ended','missed','cancelled') NOT NULL DEFAULT 'ringing',
  `offer_sdp` MEDIUMTEXT NULL,
  `answer_sdp` MEDIUMTEXT NULL,
  `started_at` DATETIME NULL DEFAULT NULL,
  `ended_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_call_callee_status` (`callee_id`,`status`),
  KEY `idx_call_caller_status` (`caller_id`,`status`),
  CONSTRAINT `fk_call_caller` FOREIGN KEY (`caller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_call_callee` FOREIGN KEY (`callee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `voice_call_signals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `call_id` INT UNSIGNED NOT NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `signal_type` ENUM('ice','renegotiate','hangup') NOT NULL DEFAULT 'ice',
  `payload` MEDIUMTEXT NOT NULL,
  `consumed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_signal_call_consumed` (`call_id`,`consumed`,`id`),
  CONSTRAINT `fk_signal_call` FOREIGN KEY (`call_id`) REFERENCES `voice_call_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_signal_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Notifications
-- --------------------------------------------------------
CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `body` VARCHAR(500) NOT NULL DEFAULT '',
  `link` VARCHAR(255) NULL DEFAULT NULL,
  `ref_type` VARCHAR(50) NULL DEFAULT NULL,
  `ref_id` INT UNSIGNED NULL DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read` (`user_id`,`is_read`,`created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Forums
-- --------------------------------------------------------
CREATE TABLE `forums` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_forums_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `forum_posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `forum_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `content` MEDIUMTEXT NOT NULL,
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `is_hidden` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_posts_forum` (`forum_id`,`created_at`),
  KEY `idx_posts_user` (`user_id`),
  CONSTRAINT `fk_posts_forum` FOREIGN KEY (`forum_id`) REFERENCES `forums` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `post_comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  `is_hidden` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comments_post` (`post_id`,`created_at`),
  CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `post_likes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_like` (`post_id`,`user_id`),
  CONSTRAINT `fk_likes_post` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `post_shares` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shares_post` (`post_id`),
  CONSTRAINT `fk_shares_post` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shares_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Reports
-- --------------------------------------------------------
CREATE TABLE `reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_id` INT UNSIGNED NOT NULL,
  `target_type` ENUM('user','private_message','group_message','forum_post') NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL,
  `reason` VARCHAR(500) NOT NULL,
  `status` ENUM('open','reviewed','resolved') NOT NULL DEFAULT 'open',
  `admin_notes` TEXT NULL,
  `reviewed_by` INT UNSIGNED NULL DEFAULT NULL,
  `reviewed_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reports_status` (`status`,`created_at`),
  KEY `idx_reports_reporter` (`reporter_id`),
  CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Rate limiting
-- --------------------------------------------------------
CREATE TABLE `rate_limits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rate_key` VARCHAR(191) NOT NULL,
  `hits` INT UNSIGNED NOT NULL DEFAULT 1,
  `window_start` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_key` (`rate_key`),
  KEY `idx_rate_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reset_token` (`token`),
  KEY `idx_reset_user` (`user_id`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Seed data (DEMO ONLY — change passwords before production use)
-- Admin: admin / Admin@ChangeMe1
-- Users: alice, bob, carol / Demo@12345
-- --------------------------------------------------------
INSERT INTO `admins` (`username`, `email`, `password_hash`, `display_name`, `must_change_password`) VALUES
('admin', 'admin@webconnect.local', '$2y$12$y8q2IrKLY44iquwtVIRbR.sUAdfAZqEktxSosoSivgaynJ2no5e5C', 'System Administrator', 1);

INSERT INTO `users` (`username`, `email`, `password_hash`, `status_message`, `presence`) VALUES
('alice', 'alice@example.com', '$2y$12$7fdhNVOe/qbdoBiwVNcO4.gq9nRy3MwCWWP2q38qMbQG.dEVovDua', 'Hello, I am Alice.', 'offline'),
('bob', 'bob@example.com', '$2y$12$7fdhNVOe/qbdoBiwVNcO4.gq9nRy3MwCWWP2q38qMbQG.dEVovDua', 'Building cool chats.', 'offline'),
('carol', 'carol@example.com', '$2y$12$7fdhNVOe/qbdoBiwVNcO4.gq9nRy3MwCWWP2q38qMbQG.dEVovDua', 'Always learning.', 'offline');

INSERT INTO `friendships` (`user_low_id`, `user_high_id`) VALUES (1, 2), (1, 3), (2, 3);

INSERT INTO `forums` (`name`, `slug`, `description`, `sort_order`) VALUES
('General Discussion', 'general-discussion', 'Talk about anything related to the community.', 1),
('Technology', 'technology', 'Discuss software, hardware, and web development.', 2),
('Student Discussion', 'student-discussion', 'A space for students to share and collaborate.', 3),
('Announcements', 'announcements', 'Official platform announcements.', 4);
