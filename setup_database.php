<?php
/**
 * Database Setup Script
 * Run this file once to setup RBAC and Activity Logging tables
 * Access via: http://your-domain/setup_database.php
 */

require_once 'config/config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Database Setup</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".success{color:green;}.error{color:red;}.info{color:blue;}</style></head><body>";
echo "<h1>🔧 Database Setup for RBAC & Activity Logging</h1>";

try {
    // Read and execute SQL file
    $sql = file_get_contents(__DIR__ . '/database_updates.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $success_count++;
            echo "<p class='success'>✓ Executed successfully</p>";
        } catch (PDOException $e) {
            $error_count++;
            echo "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    echo "<hr>";
    echo "<h2>Summary</h2>";
    echo "<p class='info'>✓ Successful: $success_count</p>";
    echo "<p class='error'>✗ Errors: $error_count</p>";
    
    // Verify tables
    echo "<hr><h2>Verification</h2>";
    
    $tables = ['roles', 'permissions', 'role_permissions', 'activity_log', 'rate_limit_log', 'user_sessions'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch();
            echo "<p class='success'>✓ Table '$table' exists with {$result['count']} records</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>✗ Table '$table' not found or error</p>";
        }
    }
    
    echo "<hr><p class='info'><strong>✅ Setup Complete!</strong></p>";
    echo "<p>You can now delete this file for security: <code>setup_database.php</code></p>";
    
} catch (Exception $e) {
    echo "<p class='error'><strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
