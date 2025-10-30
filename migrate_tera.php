<?php
require_once 'config/config.php';

try {
    echo "Starting migration to add jumlah_tera column...\n";
    
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM setoran LIKE 'jumlah_tera'");
    if ($stmt->rowCount() > 0) {
        echo "Column 'jumlah_tera' already exists. Skipping...\n";
        exit(0);
    }
    
    // Add column
    $pdo->exec("ALTER TABLE `setoran` ADD COLUMN `jumlah_tera` DECIMAL(10,2) DEFAULT 0 AFTER `nomor_akhir`");
    echo "✓ Column 'jumlah_tera' added successfully.\n";
    
    // Update existing records
    $pdo->exec("UPDATE `setoran` SET `jumlah_tera` = 0 WHERE `jumlah_tera` IS NULL");
    echo "✓ Existing records updated.\n";
    
    echo "\nMigration completed successfully!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
