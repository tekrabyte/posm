#!/usr/bin/env php
<?php
/**
 * Cron Job untuk mengirim laporan harian otomatis
 * Jalankan setiap hari jam 12:00 WIB
 * 
 * Cara setup cron:
 * 0 12 * * * /usr/bin/php /app/cron_daily_report.php >> /var/log/daily_report.log 2>&1
 */

// Set timezone ke Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

// Log start
echo "[" . date('Y-m-d H:i:s') . "] Starting daily report cron job...\n";

// Load dependencies
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/email_handler.php';

try {
    // Inisialisasi EmailHandler
    $emailHandler = new EmailHandler($pdo);
    
    // Kirim laporan harian (force=false, jadi ada proteksi anti-duplicate)
    $result = $emailHandler->sendDailyReport(false);
    
    if ($result['success']) {
        echo "[" . date('Y-m-d H:i:s') . "] SUCCESS: " . $result['message'] . "\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] INFO: " . $result['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Cron job completed.\n";
exit(0);
