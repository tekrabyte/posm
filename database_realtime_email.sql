-- Database schema untuk Real-time & Email Features
-- Dibuat: August 2025

-- 1. Tabel untuk Email Configuration
CREATE TABLE IF NOT EXISTS `email_config` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `smtp_host` VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
  `smtp_port` INT NOT NULL DEFAULT 587,
  `smtp_username` VARCHAR(255) NOT NULL,
  `smtp_password` VARCHAR(255) NOT NULL,
  `recipient_email` VARCHAR(255) NOT NULL,
  `is_enabled` BOOLEAN DEFAULT 1,
  `last_sent` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel untuk Email Notification Log
CREATE TABLE IF NOT EXISTS `email_notifications` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `notification_type` VARCHAR(50) NOT NULL COMMENT 'setoran, pengeluaran, pemasukan, mixed',
  `related_id` INT NULL COMMENT 'ID dari setoran/cashflow',
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `recipient_email` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
  `sent_at` DATETIME NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel untuk Data Change Tracking (untuk polling)
CREATE TABLE IF NOT EXISTS `data_changes` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `table_name` VARCHAR(50) NOT NULL,
  `record_id` INT NOT NULL,
  `change_type` ENUM('insert', 'update', 'delete') NOT NULL,
  `change_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notified` BOOLEAN DEFAULT 0,
  INDEX `idx_timestamp` (`change_timestamp`),
  INDEX `idx_notified` (`notified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Insert default email config (akan diupdate user via UI)
INSERT INTO `email_config` 
  (`smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `recipient_email`, `is_enabled`) 
VALUES 
  ('smtp.gmail.com', 587, 'tekrabyte@gmail.com', 'wqcl niki hdhy hejz', 'tekrabyte@gmail.com', 1)
ON DUPLICATE KEY UPDATE 
  `smtp_username` = VALUES(`smtp_username`);

-- 5. Index untuk performa query
ALTER TABLE `setoran` ADD INDEX `idx_created_at` (`created_at`);
ALTER TABLE `cashflow` ADD INDEX `idx_created_at` (`created_at`);
