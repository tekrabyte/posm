<?php
/**
 * Fix Script untuk Error 500 di realtime_api.php
 * 
 * Script ini akan:
 * 1. Cek apakah tabel data_changes ada
 * 2. Cek apakah tabel email_config ada
 * 3. Cek apakah tabel email_notifications ada
 * 4. Membuat tabel jika belum ada
 * 5. Update kolom last_daily_report_sent jika belum ada
 */

require_once __DIR__ . '/config/config.php';

echo "========================================\n";
echo "Fix Script - Realtime API Error 500\n";
echo "========================================\n\n";

try {
    // Check & Create data_changes table
    echo "1. Checking data_changes table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'data_changes'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "   → Table not found. Creating...\n";
        $pdo->exec("
            CREATE TABLE `data_changes` (
              `id` INT PRIMARY KEY AUTO_INCREMENT,
              `table_name` VARCHAR(50) NOT NULL,
              `record_id` INT NOT NULL,
              `change_type` ENUM('insert', 'update', 'delete') NOT NULL,
              `change_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `notified` BOOLEAN DEFAULT 0,
              INDEX `idx_timestamp` (`change_timestamp`),
              INDEX `idx_notified` (`notified`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "   ✅ Table created!\n\n";
    } else {
        echo "   ✅ Table exists!\n\n";
    }
    
    // Check & Create email_config table
    echo "2. Checking email_config table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_config'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "   → Table not found. Creating...\n";
        $pdo->exec("
            CREATE TABLE `email_config` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Insert default config
        $pdo->exec("
            INSERT INTO `email_config` 
              (`smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `recipient_email`, `is_enabled`) 
            VALUES 
              ('smtp.gmail.com', 587, '', '', '', 0)
        ");
        
        echo "   ✅ Table created with default config!\n\n";
    } else {
        echo "   ✅ Table exists!\n";
        
        // Check if last_daily_report_sent column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM email_config LIKE 'last_daily_report_sent'");
        $columnExists = $stmt->fetch();
        
        if (!$columnExists) {
            echo "   → Adding last_daily_report_sent column...\n";
            $pdo->exec("ALTER TABLE email_config ADD COLUMN last_daily_report_sent DATETIME NULL DEFAULT NULL AFTER last_sent");
            echo "   ✅ Column added!\n\n";
        } else {
            echo "   ✅ Column last_daily_report_sent exists!\n\n";
        }
    }
    
    // Check & Create email_notifications table
    echo "3. Checking email_notifications table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_notifications'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "   → Table not found. Creating...\n";
        $pdo->exec("
            CREATE TABLE `email_notifications` (
              `id` INT PRIMARY KEY AUTO_INCREMENT,
              `notification_type` VARCHAR(50) NOT NULL COMMENT 'setoran, pengeluaran, pemasukan, mixed, daily_report',
              `related_id` INT NULL COMMENT 'ID dari setoran/cashflow',
              `subject` VARCHAR(255) NOT NULL,
              `message` TEXT NOT NULL,
              `recipient_email` VARCHAR(255) NOT NULL,
              `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
              `sent_at` DATETIME NULL,
              `error_message` TEXT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "   ✅ Table created!\n\n";
    } else {
        echo "   ✅ Table exists!\n\n";
    }
    
    // Test query to make sure everything works
    echo "4. Testing query...\n";
    $stmt = $pdo->query("
        SELECT 
            dc.id,
            dc.table_name,
            dc.record_id,
            dc.change_type,
            dc.change_timestamp
        FROM data_changes dc
        WHERE dc.change_timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY)
        LIMIT 5
    ");
    $results = $stmt->fetchAll();
    echo "   ✅ Query successful! Found " . count($results) . " recent changes.\n\n";
    
    // Check email config
    echo "5. Checking email config...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM email_config");
    $count = $stmt->fetch();
    
    if ($count['total'] == 0) {
        echo "   → No config found. Creating default...\n";
        $pdo->exec("
            INSERT INTO `email_config` 
              (`smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `recipient_email`, `is_enabled`) 
            VALUES 
              ('smtp.gmail.com', 587, '', '', '', 0)
        ");
        echo "   ✅ Default config created!\n\n";
    } else {
        $stmt = $pdo->query("SELECT * FROM email_config WHERE id = 1");
        $config = $stmt->fetch();
        echo "   ✅ Config found!\n";
        echo "   - Is Enabled: " . ($config['is_enabled'] ? 'Yes' : 'No') . "\n";
        echo "   - SMTP Username: " . ($config['smtp_username'] ? '***set***' : 'Not set') . "\n\n";
    }
    
    echo "========================================\n";
    echo "✅ All fixes completed successfully!\n";
    echo "========================================\n\n";
    
    echo "The realtime_api.php error should now be fixed.\n";
    echo "If you still see errors, please check:\n";
    echo "1. Web server error logs\n";
    echo "2. PHP error logs\n";
    echo "3. Database connection\n\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
