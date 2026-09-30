-- Run once on existing cPanel databases (phpMyAdmin → SQL)
ALTER TABLE `messages`
  MODIFY `message_type` ENUM('text','image','file','voice','call') NOT NULL DEFAULT 'text';
