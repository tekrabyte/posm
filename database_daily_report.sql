-- Update email_config table untuk menambah kolom last_daily_report_sent
-- Untuk tracking kapan terakhir kirim laporan harian

ALTER TABLE `email_config` 
ADD COLUMN `last_daily_report_sent` DATETIME NULL DEFAULT NULL AFTER `last_sent`;
