<?php
// Simulate real POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

session_start();
$_SESSION['user_id'] = 1; // Simulate logged in admin

$_POST['action'] = 'send_daily_report';
$_POST['force'] = 'true';

ob_start();
include 'config/api.php';
$output = ob_get_clean();

echo "=== Test Daily Report Endpoint ===\n";
echo $output . "\n";

// Parse JSON to verify
$result = json_decode($output, true);
if ($result) {
    echo "\nParsed Result:\n";
    echo "Success: " . ($result['success'] ? "YES" : "NO") . "\n";
    echo "Message: " . $result['message'] . "\n";
} else {
    echo "\nERROR: Invalid JSON response\n";
}
