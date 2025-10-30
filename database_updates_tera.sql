-- Tambah kolom jumlah_tera ke tabel setoran
ALTER TABLE `setoran` 
ADD COLUMN `jumlah_tera` DECIMAL(10,2) DEFAULT 0 AFTER `nomor_akhir`;

-- Update existing records to have jumlah_tera = 0
UPDATE `setoran` SET `jumlah_tera` = 0 WHERE `jumlah_tera` IS NULL;
