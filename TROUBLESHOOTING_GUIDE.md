# 🔧 Troubleshooting Guide - Email Notification Errors

## 📋 Error Summary

Anda mengalami 2 error utama:

### Error 1: 500 Internal Server Error
```
GET /config/realtime_api.php?action=get_notification_history&limit=50
Status: 500 (Internal Server Error)
```

### Error 2: 400 Bad Request
```
POST /config/api.php
Status: 400 (Bad Request)
```

---

## 🔍 Root Cause Analysis

### Error 500 - get_notification_history

**Penyebab:**
1. ❌ Tabel `email_notifications` belum ada di database
2. ❌ Tabel `email_config` belum ada di database
3. ❌ Tabel `data_changes` belum ada di database

**Kenapa Terjadi:**
- File SQL migration belum dijalankan
- Database schema belum complete

**Dampak:**
- Email notification history tidak bisa ditampilkan
- Admin panel Email Settings tab error
- Real-time polling gagal

---

### Error 400 - send_daily_report

**Penyebab:**
1. ❌ Request format tidak sesuai yang diharapkan API
2. ❌ Parameter action tidak terbaca dengan benar
3. ❌ Missing required parameters

**Kenapa Terjadi:**
- FormData format vs JSON format mismatch
- CSRF token handling issue
- API endpoint tidak recognize request

**Dampak:**
- Tombol "Kirim Laporan Harian Sekarang" tidak berfungsi
- Laporan tidak bisa dikirim manual

---

## ✅ Solution - Step by Step

### Step 1: Fix Database (CRITICAL - MUST DO FIRST)

**Option A: Automatic Fix (Recommended)**

```bash
# Via SSH/Terminal
cd /path/to/your/app
php fix_realtime_error.php
```

**Option B: Manual Fix via phpMyAdmin**

1. Login ke phpMyAdmin
2. Pilih database `u215947863_pom`
3. Klik tab "SQL"
4. Copy-paste dan run query berikut:

```sql
-- 1. Create email_config table
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

-- 2. Create email_notifications table
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

-- 3. Create data_changes table
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

-- 4. Insert default email config
INSERT INTO `email_config` 
  (`smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `recipient_email`, `is_enabled`) 
VALUES 
  ('smtp.gmail.com', 587, '', '', '', 0)
ON DUPLICATE KEY UPDATE id=id;
```

5. Klik "Go" untuk execute

---

### Step 2: Verify Database

**Via Terminal:**
```bash
php -r "
require_once 'config/config.php';
\$tables = ['email_config', 'email_notifications', 'data_changes'];
foreach (\$tables as \$table) {
    \$stmt = \$pdo->query(\"SHOW TABLES LIKE '\$table'\");
    echo \$table . ': ' . (\$stmt->fetch() ? '✅ EXISTS' : '❌ MISSING') . PHP_EOL;
}
"
```

**Via phpMyAdmin:**
1. Login ke phpMyAdmin
2. Pilih database `u215947863_pom`
3. Cek apakah tabel berikut ada:
   - ✅ `email_config`
   - ✅ `email_notifications`
   - ✅ `data_changes`

---

### Step 3: Test Endpoints

**Test 1: Check get_notification_history**
```bash
curl "http://yoursite.com/config/realtime_api.php?action=get_notification_history&limit=50"
```

**Expected Response (Success):**
```json
{
  "success": true,
  "history": []
}
```

**Expected Response (If table missing - but graceful):**
```json
{
  "success": true,
  "history": [],
  "info": "email_notifications table not found - please run migration"
}
```

**Test 2: Check send_daily_report**
```bash
curl -X POST "http://yoursite.com/config/api.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=send_daily_report&force=true"
```

**Expected Response (if not authenticated):**
```json
{
  "success": false,
  "message": "Sesi Anda telah berakhir. Silakan login kembali."
}
```

---

## 🎯 Quick Fix Commands

### Complete Fix in One Command

```bash
# Run this single command to fix everything
cd /path/to/your/app && php fix_realtime_error.php
```

### Manual Database Fix

```bash
# Import SQL file directly
mysql -u u215947863_pom -p u215947863_pom < database_realtime_email.sql
mysql -u u215947863_pom -p u215947863_pom < database_daily_report.sql
```

---

## 📊 Error Codes Explained

| Error Code | Meaning | Solution |
|------------|---------|----------|
| **500** | Internal Server Error | Database table missing - run migration |
| **403** | Forbidden | CSRF token issue - already fixed in code |
| **400** | Bad Request | Invalid request format - check parameters |
| **401** | Unauthorized | Session expired - login again |

---

## 🔍 Debugging Steps

### Step 1: Check PHP Error Logs

```bash
# Via terminal
tail -f /var/log/php_errors.log

# Or check web server logs
tail -f /var/log/apache2/error.log
# OR
tail -f /var/log/nginx/error.log
```

### Step 2: Enable Debug Mode

Add to `/config/api.php` at the top:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Step 3: Check Database Connection

```bash
php -r "
require_once 'config/config.php';
try {
    \$pdo->query('SELECT 1');
    echo '✅ Database connection OK' . PHP_EOL;
} catch (Exception \$e) {
    echo '❌ Database error: ' . \$e->getMessage() . PHP_EOL;
}
"
```

### Step 4: Test Each Endpoint

**Test realtime_api.php:**
```bash
curl -v "http://yoursite.com/config/realtime_api.php?action=get_latest_changes"
```

**Test api.php:**
```bash
curl -v -X POST "http://yoursite.com/config/api.php" -d "action=get_csrf_token"
```

---

## 🚨 Common Mistakes

### ❌ Mistake 1: Forgot to Run Migration
**Problem:** Directly accessing features without setting up database

**Solution:**
```bash
php fix_realtime_error.php
```

### ❌ Mistake 2: Wrong Database Credentials
**Problem:** Can't connect to database

**Check:** `/config/config.php`
```php
$host = 'srv1412.hstgr.io';
$dbname = 'u215947863_pom';
$username = 'u215947863_pom';
$password = 'marketLokal2611@';
```

### ❌ Mistake 3: File Permissions
**Problem:** PHP can't read/write files

**Solution:**
```bash
chmod 644 /path/to/app/config/*.php
chmod 755 /path/to/app
```

---

## 📞 Still Not Working?

### Checklist Before Asking for Help:

- [ ] ✅ Ran `fix_realtime_error.php`
- [ ] ✅ Verified all 3 tables exist in database
- [ ] ✅ Checked PHP error logs
- [ ] ✅ Tested endpoints with curl
- [ ] ✅ Database connection works
- [ ] ✅ File permissions correct (644 for PHP files)
- [ ] ✅ Cleared browser cache
- [ ] ✅ Tried different browser

### Provide This Info:

1. **Error logs** (from PHP error log)
2. **Database verification** (screenshot of tables)
3. **Curl test results** (copy-paste output)
4. **Browser console errors** (F12 → Console tab)
5. **Steps taken** (what you already tried)

---

## 📝 Files Reference

### Created Files:
- `/fix_realtime_error.php` - Auto-fix script
- `/database_realtime_email.sql` - Email tables schema
- `/database_daily_report.sql` - Daily report column
- `/cron_daily_report.php` - Cron job script
- `/FIX_ERROR_500.md` - Error 500 documentation
- `/TROUBLESHOOTING_GUIDE.md` - This file

### Modified Files:
- `/config/realtime_api.php` - Added error handling
- `/config/api.php` - Added CSRF exception
- `/config/email_handler.php` - Added daily report method
- `/assets/js/realtime-polling.js` - Fixed fetch calls

---

## 🎉 Success Criteria

After fixing, you should see:

1. ✅ Admin Panel → Email Settings tab loads without errors
2. ✅ Browser console has no 500 or 403 errors
3. ✅ Can click "Kirim Laporan Harian Sekarang" button
4. ✅ Email notification history shows (even if empty)
5. ✅ No errors in PHP error log

---

## 🔄 Quick Recovery

If everything breaks, run this:

```bash
# 1. Backup current state
cp -r /path/to/app /path/to/app_backup

# 2. Run fix script
php fix_realtime_error.php

# 3. Restart web server
sudo service apache2 restart
# OR
sudo service nginx restart

# 4. Clear cache
rm -rf /tmp/php_sessions/*

# 5. Test
curl "http://yoursite.com/config/realtime_api.php?action=get_notification_history&limit=50"
```

---

**Last Updated:** October 2025  
**Version:** 2.1  
**Status:** Complete Troubleshooting Guide
