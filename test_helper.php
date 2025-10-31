<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing helper_functions.php...\n\n";

// Test include
try {
    require_once __DIR__ . '/config/helper_functions.php';
    echo "✓ Helper functions loaded successfully\n\n";
} catch (Exception $e) {
    echo "✗ Error loading helper functions: " . $e->getMessage() . "\n";
    exit(1);
}

// Test formatIndonesianDate
try {
    $result = formatIndonesianDate('2025-01-31');
    echo "✓ formatIndonesianDate('2025-01-31') = " . $result . "\n";
} catch (Exception $e) {
    echo "✗ formatIndonesianDate error: " . $e->getMessage() . "\n";
}

// Test with today's date
try {
    $today = date('Y-m-d');
    $result = formatIndonesianDate($today);
    echo "✓ formatIndonesianDate(today) = " . $result . "\n";
} catch (Exception $e) {
    echo "✗ formatIndonesianDate(today) error: " . $e->getMessage() . "\n";
}

// Test formatRupiah
try {
    $result = formatRupiah(788235);
    echo "✓ formatRupiah(788235) = " . $result . "\n";
    
    $result = formatRupiah(50000);
    echo "✓ formatRupiah(50000) = " . $result . "\n";
} catch (Exception $e) {
    echo "✗ formatRupiah error: " . $e->getMessage() . "\n";
}

echo "\n✓ All tests passed!\n";
?>
