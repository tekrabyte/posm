<?php
error_reporting(0);
ini_set('display_errors', 0);

echo "========================================\n";
echo "   EMAIL SYSTEM DIAGNOSTIC TEST\n";
echo "========================================\n\n";

// Test 1: Check PHP Dependencies
echo "1. Testing PHP Dependencies...\n";
require_once 'config/config.php';
require_once 'config/email_handler.php';
echo "   ✅ All dependencies loaded successfully\n\n";

// Test 2: Check Database Tables
echo "2. Testing Database Tables...\n";
$tables = ['email_config', 'email_notifications', 'data_changes'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    $exists = $stmt->fetch();
    echo "   " . ($exists ? "✅" : "❌") . " Table '$table': " . ($exists ? "EXISTS" : "NOT FOUND") . "\n";
}
echo "\n";

// Test 3: Check Email Config
echo "3. Testing Email Configuration...\n";
$emailHandler = new EmailHandler($pdo);
$config = $emailHandler->getConfig();
echo "   SMTP Host: " . $config['smtp_host'] . "\n";
echo "   SMTP Port: " . $config['smtp_port'] . "\n";
echo "   Email From: " . $config['smtp_username'] . "\n";
echo "   Email To: " . $config['recipient_email'] . "\n";
echo "   Status: " . ($config['is_enabled'] ? "✅ ENABLED" : "❌ DISABLED") . "\n\n";

// Test 4: Test SMTP Connection
echo "4. Testing SMTP Connection...\n";
$result = $emailHandler->testConnection();
echo "   " . ($result['success'] ? "✅" : "❌") . " " . $result['message'] . "\n\n";

// Test 5: Send Test Email
echo "5. Sending Test Email...\n";
$subject = '🧪 System Diagnostic Test Email';
$message = '<h3>System Test Email</h3><p>This is an automated test email sent at ' . date('Y-m-d H:i:s') . '</p>';
$result = $emailHandler->sendNotification($subject, $message, 'test');
echo "   " . ($result['success'] ? "✅" : "❌") . " " . $result['message'] . "\n\n";

// Test 6: Check Recent Notifications
echo "6. Checking Recent Notifications...\n";
$stmt = $pdo->query("SELECT COUNT(*) as total, SUM(status='sent') as sent, SUM(status='failed') as failed FROM email_notifications");
$stats = $stmt->fetch();
echo "   Total: " . $stats['total'] . " emails\n";
echo "   ✅ Sent: " . $stats['sent'] . "\n";
echo "   ❌ Failed: " . $stats['failed'] . "\n\n";

echo "========================================\n";
echo "   DIAGNOSTIC COMPLETE\n";
echo "========================================\n";
