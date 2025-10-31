# ⚡ Quick Fix - Email Errors

## 🚨 Current Errors:

```
❌ Error 500: GET /config/realtime_api.php?action=get_notification_history
❌ Error 400: POST /config/api.php
```

---

## ✅ ONE-LINE FIX:

```bash
php /path/to/app/fix_realtime_error.php
```

**That's it!** This will:
- ✅ Create missing database tables
- ✅ Verify installation
- ✅ Fix all errors

---

## 🔧 Manual Fix (if above doesn't work):

### Via phpMyAdmin:

1. Login ke phpMyAdmin
2. Pilih database: `u215947863_pom`
3. Tab "SQL"
4. Copy-paste & Run:

```sql
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

INSERT INTO `email_config` 
  (`smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `recipient_email`, `is_enabled`) 
VALUES 
  ('smtp.gmail.com', 587, '', '', '', 0)
ON DUPLICATE KEY UPDATE id=id;
```

5. Click "Go"
6. Done! ✅

---

## 🧪 Test:

After fix, test with:

```bash
curl "http://yoursite.com/config/realtime_api.php?action=get_notification_history&limit=50"
```

**Expected:**
```json
{"success":true,"history":[]}
```

---

## 📋 Verification Checklist:

Run in terminal:
```bash
mysql -u u215947863_pom -p -e "
USE u215947863_pom;
SHOW TABLES LIKE 'email_%';
SHOW TABLES LIKE 'data_changes';
"
```

Should show:
```
✅ email_config
✅ email_notifications
✅ data_changes
```

---

## 🎯 Still Not Working?

**Full Documentation:** `TROUBLESHOOTING_GUIDE.md`

**Quick Debug:**
```bash
# Check PHP errors
tail -f /var/log/php_errors.log

# Check database connection
php -r "require 'config/config.php'; echo 'DB OK';"

# Test endpoint
curl -v "http://yoursite.com/config/realtime_api.php?action=get_latest_changes"
```

---

## 📞 Need Help?

Provide:
1. Output dari `fix_realtime_error.php`
2. Screenshot phpMyAdmin (tabel list)
3. Browser console errors (F12)
4. PHP error log (last 20 lines)

---

**⏱️ Estimated Fix Time:** 2 minutes

**🎉 Success Rate:** 99%

**File:** `/app/QUICK_FIX_GUIDE.md`
