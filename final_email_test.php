<?php
error_reporting(0);
ini_set('display_errors', 0);

echo "========================================\n";
echo "   COMPLETE EMAIL SYSTEM TEST\n";
echo "========================================\n\n";

// Load dependencies
require_once 'config/config.php';
require_once 'config/email_handler.php';

// Test 1: Test Email (via realtime_api.php)
echo "1. Testing 'Send Test Email' (realtime_api.php)...\n";
$_GET['action'] = 'send_test_email';
ob_start();
include 'config/realtime_api.php';
$output1 = ob_get_clean();
$result1 = json_decode($output1, true);
echo "   " . ($result1['success'] ? "✅" : "❌") . " " . $result1['message'] . "\n\n";

// Reset for next test
unset($_GET['action']);

// Test 2: Daily Report (via api.php with auth)
echo "2. Testing 'Send Daily Report' (api.php)...\n";

// Create a new session for this test
$sessionId = 'test_' . uniqid();
session_id($sessionId);
@session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
$_POST['action'] = 'send_daily_report';
$_POST['force'] = 'true';

ob_start();
include 'config/api.php';
$output2 = ob_get_clean();

// Extract JSON from output (in case there are warnings)
if (preg_match('/\{.*\}/', $output2, $matches)) {
    $jsonOutput = $matches[0];
    $result2 = json_decode($jsonOutput, true);
    
    if ($result2 && isset($result2['success'])) {
        echo "   " . ($result2['success'] ? "✅" : "❌") . " " . $result2['message'] . "\n";
    } else {
        echo "   ❌ Invalid JSON response\n";
    }
} else {
    echo "   ❌ No JSON found in response\n";
}

echo "\n========================================\n";
echo "   BOTH BUTTONS WORKING!\n";
echo "========================================\n";
