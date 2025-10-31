<?php
error_reporting(0);
ini_set('display_errors', 0);

echo "========================================\n";
echo "   DAILY REPORT EMAIL TEST\n";
echo "========================================\n\n";

// Simulate authenticated session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

$_POST['action'] = 'send_daily_report';
$_POST['force'] = 'true';

echo "Testing endpoint: /config/api.php?action=send_daily_report\n";
echo "User: admin (authenticated)\n";
echo "Force override: true\n\n";

ob_start();
include 'config/api.php';
$output = ob_get_clean();

$result = json_decode($output, true);

if ($result && isset($result['success'])) {
    if ($result['success']) {
        echo "✅ SUCCESS: " . $result['message'] . "\n";
    } else {
        echo "❌ FAILED: " . $result['message'] . "\n";
    }
} else {
    echo "❌ ERROR: Invalid response\n";
    echo "Raw output: " . $output . "\n";
}

echo "\n========================================\n";
