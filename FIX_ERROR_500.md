# 🔧 Fix Error 500 - realtime_api.php

## 🐛 Error yang Terjadi

```
[500]: GET /config/realtime_api.php?action=get_latest_changes
```

---

## 🔍 Root Cause Analysis

Error 500 disebabkan oleh:

1. **Tabel `data_changes` belum dibuat**
   - File `database_realtime_email.sql` belum dijalankan
   - Tabel diperlukan untuk real-time polling feature

2. **Query menggunakan nama tabel yang salah**
   - Query menggunakan `cashflow` 
   - Tabel sebenarnya bernama `cash_flow_management`
   - Sudah diperbaiki ✅

3. **Missing column `last_daily_report_sent`**
   - Kolom ini untuk fitur laporan harian
   - Belum ditambahkan ke tabel `email_config`

---

## ✅ Solusi

### Quick Fix (Otomatis):

Jalankan script fix otomatis:

```bash
php /path/to/app/fix_realtime_error.php
```

Script ini akan:
- ✅ Cek & buat tabel `data_changes`
- ✅ Cek & buat tabel `email_config`
- ✅ Cek & buat tabel `email_notifications`
- ✅ Tambah kolom `last_daily_report_sent`
- ✅ Test query untuk memastikan semua OK

---

### Manual Fix:

Jika prefer manual, jalankan query ini di database:

```sql
-- 1. Buat tabel data_changes
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

-- 2. Buat tabel email_config (jika belum ada)
CREATE TABLE IF NOT EXISTS `email_config` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `smtp_host` VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
  `smtp_port` INT NOT NULL DEFAULT 587,
  `smtp_username` VARCHAR(255) NOT NULL,
  `smtp_password` VARCHAR(255) NOT NULL,
  `recipient_email` VARCHAR(255) NOT NULL,
  `is_enabled` BOOLEAN DEFAULT 1,
  `last_sent` DATETIME NULL,
  `last_daily_report_sent` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Insert default config (jika tabel kosong)
INSERT INTO `email_config` 
  (`smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `recipient_email`, `is_enabled`) 
VALUES 
  ('smtp.gmail.com', 587, '', '', '', 0)
ON DUPLICATE KEY UPDATE id=id;

-- 4. Buat tabel email_notifications
CREATE TABLE IF NOT EXISTS `email_notifications` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `notification_type` VARCHAR(50) NOT NULL,
  `related_id` INT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `recipient_email` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
  `sent_at` DATETIME NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tambah kolom last_daily_report_sent (jika belum ada)
ALTER TABLE `email_config` 
ADD COLUMN `last_daily_report_sent` DATETIME NULL DEFAULT NULL AFTER `last_sent`;
```

---

## 🔍 Verifikasi Fix

Setelah menjalankan fix, verifikasi dengan:

```bash
# 1. Cek apakah tabel sudah ada
mysql -u your_user -p your_database -e "SHOW TABLES LIKE 'data_changes'"

# 2. Test API endpoint
curl "http://yoursite.com/config/realtime_api.php?action=get_latest_changes"

# Expected response:
# {"success":true,"has_changes":false,"changes":[],"current_time":"2025-10-31 12:00:00"}
```

---

## 📁 File yang Diperbaiki

### 1. `/config/realtime_api.php`
**Perubahan:**
- Fixed query: `cashflow` → `cash_flow_management`
- Tambah check tabel existence sebelum query
- Improved error handling dengan detail error

### 2. `/fix_realtime_error.php` (NEW)
**Fungsi:**
- Script otomatis untuk fix semua error
- Create missing tables
- Add missing columns
- Verify installation

---

## 📊 Tabel-tabel yang Diperlukan

### 1. `data_changes`
**Fungsi:** Track perubahan data untuk real-time notification
**Kolom:**
- `id` - Primary key
- `table_name` - Nama tabel yang berubah
- `record_id` - ID record yang berubah
- `change_type` - insert/update/delete
- `change_timestamp` - Waktu perubahan
- `notified` - Status sudah notifikasi atau belum

### 2. `email_config`
**Fungsi:** Konfigurasi SMTP & email settings
**Kolom:**
- SMTP credentials
- Recipient email
- Enable/disable flag
- Tracking last sent

### 3. `email_notifications`
**Fungsi:** Log history pengiriman email
**Kolom:**
- Notification details
- Status (pending/sent/failed)
- Error messages
- Timestamps

---

## 🎯 Testing

### Test Real-time API:

```bash
# Test get_latest_changes
curl "http://yoursite.com/config/realtime_api.php?action=get_latest_changes"

# Expected: JSON response tanpa error 500
```

### Test dari Browser:

1. Buka Admin Panel
2. Tab mana saja
3. Cek Console di Developer Tools
4. Seharusnya tidak ada error 500 lagi

---

## 🔄 Related Features

Fix ini akan memperbaiki:
- ✅ Real-time polling di Admin Panel
- ✅ Email notification system
- ✅ Daily report feature
- ✅ Email settings configuration

---

## ⚠️ Prevention

Untuk menghindari error serupa di masa depan:

1. **Selalu jalankan migration files:**
   - `database_realtime_email.sql`
   - `database_daily_report.sql`

2. **Run setup scripts:**
   - `setup_daily_report.php`
   - `fix_realtime_error.php`

3. **Check logs regularly:**
   - PHP error logs
   - Web server error logs
   - Application logs

---

## 📞 Support

Jika masih ada error setelah fix:

1. Cek PHP error log: `/var/log/php_errors.log`
2. Cek web server log: `/var/log/apache2/error.log` atau `/var/log/nginx/error.log`
3. Run setup script ulang
4. Verify database tables exist

---

## ✅ Checklist

- [ ] Jalankan `fix_realtime_error.php`
- [ ] Verify tabel `data_changes` ada
- [ ] Verify tabel `email_config` ada
- [ ] Verify tabel `email_notifications` ada
- [ ] Test realtime_api.php endpoint
- [ ] Cek Admin Panel - no error 500
- [ ] Configure email settings
- [ ] Test email notification

---

**Status:** ✅ Fixed  
**Date:** October 2025  
**Files Modified:** 
- `/config/realtime_api.php`
- `/fix_realtime_error.php` (NEW)
