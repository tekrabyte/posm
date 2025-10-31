<?php
/**
 * Setup Script untuk Laporan Harian Feature
 * 
 * Script ini akan:
 * 1. Update database dengan kolom last_daily_report_sent
 * 2. Verify instalasi
 * 3. Show status
 */

require_once __DIR__ . '/config/config.php';

echo "========================================\n";
echo "Setup Laporan Harian Email - POSM\n";
echo "========================================\n\n";

try {
    // Check if column already exists
    echo "1. Checking database structure...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM email_config LIKE 'last_daily_report_sent'");
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        echo "   → Column 'last_daily_report_sent' not found. Adding...\n";
        $pdo->exec("ALTER TABLE email_config ADD COLUMN last_daily_report_sent DATETIME NULL DEFAULT NULL AFTER last_sent");
        echo "   ✅ Column added successfully!\n\n";
    } else {
        echo "   ✅ Column already exists. Skipping...\n\n";
    }
    
    // Verify email_config table
    echo "2. Verifying email_config table...\n";
    $stmt = $pdo->query("SELECT * FROM email_config WHERE id = 1 LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        echo "   ✅ Email config found!\n";
        echo "   - SMTP Host: " . ($config['smtp_host'] ?? 'Not set') . "\n";
        echo "   - SMTP Port: " . ($config['smtp_port'] ?? 'Not set') . "\n";
        echo "   - SMTP Username: " . ($config['smtp_username'] ? '***set***' : 'Not set') . "\n";
        echo "   - Recipient Email: " . ($config['recipient_email'] ?? 'Not set') . "\n";
        echo "   - Is Enabled: " . ($config['is_enabled'] ? 'Yes' : 'No') . "\n";
        echo "   - Last Daily Report Sent: " . ($config['last_daily_report_sent'] ?? 'Never') . "\n\n";
        
        if (empty($config['smtp_username']) || empty($config['smtp_password']) || empty($config['recipient_email'])) {
            echo "   ⚠️  WARNING: Email configuration incomplete!\n";
            echo "   Please configure email settings in Admin Panel → Email Settings\n\n";
        }
    } else {
        echo "   ⚠️  Email config not found. Please configure via Admin Panel.\n\n";
    }
    
    // Check if EmailHandler class exists and has sendDailyReport method
    echo "3. Verifying EmailHandler class...\n";
    require_once __DIR__ . '/config/email_handler.php';
    
    if (class_exists('EmailHandler')) {
        echo "   ✅ EmailHandler class found!\n";
        
        if (method_exists('EmailHandler', 'sendDailyReport')) {
            echo "   ✅ sendDailyReport() method found!\n\n";
        } else {
            echo "   ❌ sendDailyReport() method NOT found!\n";
            echo "   Please check /config/email_handler.php file\n\n";
        }
    } else {
        echo "   ❌ EmailHandler class NOT found!\n\n";
    }
    
    // Check cron file
    echo "4. Checking cron job file...\n";
    $cronFile = __DIR__ . '/cron_daily_report.php';
    if (file_exists($cronFile)) {
        echo "   ✅ Cron file found: " . $cronFile . "\n";
        echo "   - Readable: " . (is_readable($cronFile) ? 'Yes' : 'No') . "\n";
        echo "   - Executable: " . (is_executable($cronFile) ? 'Yes' : 'No') . "\n\n";
        
        if (!is_executable($cronFile)) {
            echo "   ℹ️  To make it executable, run:\n";
            echo "   chmod +x " . $cronFile . "\n\n";
        }
    } else {
        echo "   ❌ Cron file NOT found!\n";
        echo "   Expected location: " . $cronFile . "\n\n";
    }
    
    // Check stores data
    echo "5. Checking stores data...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM stores");
    $storesCount = $stmt->fetch();
    echo "   ✅ Total stores: " . $storesCount['total'] . "\n\n";
    
    // Check setoran data for current month
    echo "6. Checking data for current month...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as total, 
               SUM(total_pemasukan) as total_pemasukan,
               SUM(total_pengeluaran) as total_pengeluaran,
               SUM(total_liter) as total_liter
        FROM setoran 
        WHERE MONTH(tanggal) = MONTH(CURRENT_DATE())
          AND YEAR(tanggal) = YEAR(CURRENT_DATE())
    ");
    $monthData = $stmt->fetch();
    
    if ($monthData['total'] > 0) {
        echo "   ✅ Data found for this month:\n";
        echo "   - Total records: " . $monthData['total'] . "\n";
        echo "   - Total Pemasukan: Rp " . number_format($monthData['total_pemasukan'], 0, ',', '.') . "\n";
        echo "   - Total Pengeluaran: Rp " . number_format($monthData['total_pengeluaran'], 0, ',', '.') . "\n";
        echo "   - Total Liter: " . number_format($monthData['total_liter'], 2, ',', '.') . " L\n\n";
    } else {
        echo "   ⚠️  No data found for current month\n";
        echo "   Laporan akan kosong jika dikirim sekarang\n\n";
    }
    
    // Check data for today
    echo "7. Checking data for today...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as total, 
               SUM(total_pemasukan) as total_pemasukan,
               SUM(total_pengeluaran) as total_pengeluaran,
               SUM(total_liter) as total_liter
        FROM setoran 
        WHERE DATE(tanggal) = CURRENT_DATE()
    ");
    $todayData = $stmt->fetch();
    
    if ($todayData['total'] > 0) {
        echo "   ✅ Data found for today:\n";
        echo "   - Total records: " . $todayData['total'] . "\n";
        echo "   - Total Pemasukan: Rp " . number_format($todayData['total_pemasukan'], 0, ',', '.') . "\n";
        echo "   - Total Pengeluaran: Rp " . number_format($todayData['total_pengeluaran'], 0, ',', '.') . "\n";
        echo "   - Total Liter: " . number_format($todayData['total_liter'], 2, ',', '.') . " L\n\n";
    } else {
        echo "   ℹ️  No data found for today yet\n\n";
    }
    
    // Final summary
    echo "========================================\n";
    echo "Setup Summary\n";
    echo "========================================\n\n";
    
    $allGood = true;
    
    if (!$columnExists && $columnExists !== false) {
        echo "✅ Database updated successfully\n";
    } else {
        echo "✅ Database already up-to-date\n";
    }
    
    if (class_exists('EmailHandler') && method_exists('EmailHandler', 'sendDailyReport')) {
        echo "✅ EmailHandler ready\n";
    } else {
        echo "❌ EmailHandler NOT ready\n";
        $allGood = false;
    }
    
    if (file_exists($cronFile)) {
        echo "✅ Cron file ready\n";
    } else {
        echo "❌ Cron file missing\n";
        $allGood = false;
    }
    
    if ($config && !empty($config['smtp_username']) && !empty($config['smtp_password']) && !empty($config['recipient_email'])) {
        echo "✅ Email configured\n";
    } else {
        echo "⚠️  Email NOT configured (configure via Admin Panel)\n";
    }
    
    echo "\n";
    
    if ($allGood) {
        echo "🎉 Setup completed successfully!\n\n";
        echo "Next steps:\n";
        echo "1. Configure email settings in Admin Panel (if not yet)\n";
        echo "2. Test send report via Admin Panel → Email Settings → 'Kirim Laporan Harian Sekarang'\n";
        echo "3. Setup cron job to run at 12:00 WIB daily:\n";
        echo "   0 12 * * * /usr/bin/php " . $cronFile . " >> /path/to/logs/daily_report.log 2>&1\n\n";
    } else {
        echo "⚠️  Setup incomplete. Please check errors above.\n\n";
    }
    
    echo "For more info, read: SETUP_LAPORAN_HARIAN.md\n\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "========================================\n";
echo "Setup script completed.\n";
echo "========================================\n";
?>
